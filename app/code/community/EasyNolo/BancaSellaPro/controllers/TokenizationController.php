<?php
/**
 * Class     Tokenization.php
 * @category EasyNolo_BancaSellaPro
 * @package  EasyNolo
 * @author   Easy Nolo <ecommerce@sella.it>
 */

class EasyNolo_BancaSellaPro_TokenizationController extends Mage_Core_Controller_Front_Action {

    /**
     *
     * @var Mage_Customer_Model_Session
     */
    protected $_session = null;
    protected $_order = null;

    private function getOrder()
    {
        if ($this->_order == null) {
            $this->_order = Mage::getModel('sales/order');
            $this->_order->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
        }
        return $this->_order;
    }

    private function selectToken(){
        $customer = $this->_session->getCustomer();
        $token = Mage::getModel('easynolo_bancasellapro/token')
            ->getCollection()
            ->addFieldToFilter('customer_id', $customer->getId())
            ->addFieldToFilter('entity_id', $this->getRequest()->getParam('token'))
            ->getFirstItem();
        return $token;
    }

    public function indexAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    public function deleteAction()
    {
        $token = $this->selectToken();
        if($token->getEntityId()){
            try {
                $token->delete();
                $this->_session->addSuccess($this->__('The credit card has been deleted.'));
            } catch (Exception $e) {
                $this->_session->addError($this->__('An error occurred while deleting this credit card.'));
            }
        }
        $this->_redirect('*/*/');
    }

    public function payUsingTokenAction(){
        $_helper = Mage::helper('easynolo_bancasellapro/recurringprofile');
        $token = $this->selectToken();
        if($token->getEntityId()) {
            $order = $this->getOrder();
            $webservice = $_helper->getInitWebservice();
            $webservice->setOrder($order);
            $webservice->setToken($token);
            $result = $_helper->executePaymentS2S($webservice);
            $method = Mage::getModel('easynolo_bancasellapro/gestpay');
            // Analyze result from S2S call
            if(strcmp($result->getErrorCode(),'8006')==0){
                Mage::getSingleton('checkout/session')->setGestpayTransactionKey($result->getTransactionKey());
                $_a = $method->getMerchantId();
                $_b = $result->getVbVRisp();
                $_c = Mage::getUrl('bancasellapro/gestpay/confirm3dS2S', array('_secure' => Mage::app()->getStore()->isCurrentlySecure()));
                $this->_redirectUrl($method->getAuthPage().'?a='.$_a.'&b='.$_b.'&c='.urlencode($_c));
                return;
            }else {
                $checkoutSession = Mage::getSingleton('checkout/session');
                if(!$result->getTransactionResult() || $result->getTransactionResult() == 'KO') {
                    $checkoutSession->addError($result->getErrorDescription());
                    $redirect = 'checkout/cart';
                } else {
                    $helperDecrypt = Mage::helper('easynolo_bancasellapro/crypt');
                    $helperDecrypt->setStatusOrderByS2SRequest($order, $webservice);
                    if ($order->getStatus() == $method->getOrderStatusOkGestPay()) {
                        $order->sendNewOrderEmail();
                    }
                    $order->save();
                    // reset quote on checkout session
                    if ($lastQuoteId = $checkoutSession->getLastQuoteId()) {
                        $quote = Mage::getModel('sales/quote')->load($lastQuoteId);
                        if ($quoteId = $quote->getId()) {
                            $quote->setIsActive(false)->save();
                            $checkoutSession->setQuoteId(null);
                        }
                    }
                    $redirect = 'checkout/onepage/success';
                }
                $store = Mage::registry('easynolo_bancasellapro_store_maked_order');
                if($store && $store->getId()){
                    $this->redirectInCorrectStore($store, $redirect);
                }else{
                    $this->_redirect($redirect);
                }
                return;
            }
        }
        $this->getResponse()->setHeader($_SERVER['SERVER_PROTOCOL'], '422 Unprocessable Entity');
        return;
    }

    /**
     * Make sure customer is logged in and put it into registry
     */
    public function preDispatch()
    {
        parent::preDispatch();
        if (!$this->getRequest()->isDispatched()) {
            return;
        }
        $this->_session = Mage::getSingleton('customer/session');
        if (!$this->_session->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        }
        Mage::register('current_customer', $this->_session->getCustomer());
    }

    protected function _initProfile()
    {
        /** @var Mage_Sales_Model_Recurring_Profile $profile */
        $profile = Mage::getModel('sales/recurring_profile')->load($this->getRequest()->getParam('profile'));
        //se non esiste il profilo, non è dell'utente corrente oppure non è il metodo gestito dal modulo allora lancio eccezione
        if (!$profile->getId() || $profile->getCustomerId()!= $this->_session->getCustomerId() || $profile->getMethodCode()!= EasyNolo_BancaSellaPro_Model_Gestpay::METHOD_CODE ) {
            Mage::throwException($this->__('Specified profile does not exist.'));
        }

        Mage::register('current_recurring_profile', $profile);
        return $profile;
    }

    public function newTokenAction(){

        $profile = null;
        try {
            $profile = $this->_initProfile();

            if(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED != $profile->getState())
            {
                //se il profilo non è sospeso effettuo il redirect alla pagina dei recurring payment senza dare altri messaggi
                $this->_redirect('sales/recurring_profile');
                return;
            }
            $this->loadLayout();
            $this->renderLayout();

        } catch (Mage_Core_Exception $e) {
            $this->_session->addError($e->getMessage());
            $this->_redirect('sales/recurring_profile');

        } catch (Exception $e) {
            $this->_session->addError($this->__('Failed to update the profile.'));
            Mage::logException($e);
            $this->_redirect('sales/recurring_profile');

        }

    }

    public function disableAction()
    {
        $profile = null;
        try {
            $profile = $this->_initProfile();
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
            $profile->save();
            $this->_redirect('sales/recurring_profile/view',
                array(
                    'profile'=> $profile->getId()
                )
            );
        }
        catch(Exception $e){
            $this->_session->addError($this->__('Failed to update the profile.'));
            Mage::logException($e);
            $this->_redirect('sales/recurring_profile');
        }

    }


    public function resultAction(){
        $a = $this->getRequest()->getParam('a',false);
        $b = $this->getRequest()->getParam('b',false);

        $_helper= Mage::helper('easynolo_bancasellapro');


        if(!$a || !$b){
            $_helper->log('Accesso alla pagina per il risultato del pagamento non consentito, mancano i parametri di input');
            $this->norouteAction();
            return;
        }

        Mage::register('bancasella_param_a', $a);
        Mage::register('bancasella_param_b', $b);

        /** @var EasyNolo_BancaSellaPro_Helper_Crypt $helper */
        $helper= Mage::helper('easynolo_bancasellapro/crypt');

        if( $helper->isPaymentOk( $a , $b )){
            $_helper->log('L\'utente ha completato correttamente l\'inserimento dei dati su bancasella');
            $this->_session->addSuccess($this->__('Richiesto aggiornamento del token effettuata con successo'));
            $redirect ='sales/recurring_profile/view';// '*/*/success';
        }
        else{
            $_helper->log('L\'utente ha annullato il pagamento, oppure qualche dato non corrisponde');
            $this->_session->addError($this->__('Richiesto aggiornamento del token non effettuata'));
            $redirect = '*/*/disable';

        }

        $profile = Mage::helper('easynolo_bancasellapro/recurringprofile')->getProfileIdByOrder(Mage::registry('easynolo_bancasellapro_order'));
        $this->_redirect($redirect,array('profile'=>$profile));

        return;

    }

} 