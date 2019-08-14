<?php
/**
 * @category EasyNolo
 * @package  EasyNolo_BancaSellaPro
 * @author   Easy Nolo <ecommerce@sella.it>
 */
class EasyNolo_BancaSellaPro_GestpayController extends Mage_Core_Controller_Front_Action {

    private $_order, $_profile;

    private function getOrder()
    {
        if ($this->_order == null) {
            $session = Mage::getSingleton('checkout/session');
            $this->_order = Mage::getModel('sales/order');
            $this->_order->loadByIncrementId($session->getLastRealOrderId());
        }
        return $this->_order;
    }

    // This action is used to retrieve the EncryptedString via Ajax call when iFrame is enabled
    public function getEncryptedStringAction(){
        $crypt = Mage::helper('easynolo_bancasellapro/crypt');
        $this->getResponse()->setHeader('Content-type','application/json', true);
        $result = array('b'=>$crypt->getEncryptStringByOrder($this->getOrder()));
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function redirectAction(){
        $order = $this->getOrder();

        if (!$order->getId()) {
            $this->norouteAction();
            return;
        }

        $order->addStatusHistoryComment($this->__('User correctly redirected to Banca Sella for completion of payment.'));
        $order->save();

        /** @var EasyNolo_BancaSellaPro_Helper_Data $_helper */
        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Reindirizzamento utente sul sito di bancasella dopo aver effettuato l\'ordine con id='.$order->getId());


        Mage::register('current_order', $order);

        try{

            $this->loadLayout();
            $this->renderLayout();

        }catch (Exception $e){
            $_helper->log($e->getMessage());
            $checkoutSession = Mage::getSingleton('checkout/session');
            $checkoutSession->addError($this->__('Payment has been declined. Please try again.'));
            // set order quote to active
            if ($lastQuoteId = $checkoutSession->getLastQuoteId()){
            	$quote = Mage::getModel('sales/quote')->load($lastQuoteId);
            	if ($quoteId = $quote->getId()) {
            		$quote->setIsActive(true);
            		$quote->setReservedOrderId(null);
            		$quote->save();
            		$checkoutSession->setQuoteId($quoteId);
            	}
            }
            
            $this->_redirect('checkout/cart');
            return;
        }
    }

    // This action is used by GestPay as return URL after payment on Banca Sella page
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

        $checkoutSession = Mage::getSingleton('checkout/session');
        $paymentCheckResult = $helper->isPaymentOk( $a , $b );
        if($paymentCheckResult === true){
            $_helper->log('L\'utente ha completato correttamente l\'inserimento dei dati su bancasella');
            // reset quote on checkout session
            if ($lastQuoteId = $checkoutSession->getLastQuoteId()){
                $quote = Mage::getModel('sales/quote')->load($lastQuoteId);
                if ($quoteId = $quote->getId()) {
                    $quote->setIsActive(false)->save();
                    $checkoutSession->setQuoteId(null);
                }
            }

            $redirect ='checkout/onepage/success';
        }
        else{
            $_helper->log('L\'utente ha annullato il pagamento, oppure qualche dato non corrisponde');
            // set order quote to active
            if ($lastQuoteId = $checkoutSession->getLastQuoteId()){
                $quote = Mage::getModel('sales/quote')->load($lastQuoteId);
                if ($quoteId = $quote->getId()) {
                    $quote->setIsActive(true);
                    $quote->setReservedOrderId(null);
                    $quote->save();
                    $checkoutSession->setQuoteId($quoteId);
                }
            }

            $checkoutSession->addError($paymentCheckResult);
            $redirect = 'checkout/cart';
        }

        //se è impostato lo store allora reindirizzo l'utente allo store corretto
        $store= Mage::registry('easynolo_bancasellapro_store_maked_order');
        if($store && $store->getId()){
            $this->redirectInCorrectStore( $store, $redirect );
        }else{
            $this->_redirect($redirect);
        }

        return $this;
    }

    // This function is used by GestPay to notify payment callbacks
    public function s2sAction(){
        $a = $this->getRequest()->getParam('a',false);
        $b = $this->getRequest()->getParam('b',false);
        /** @var EasyNolo_BancaSellaPro_Helper_Data $_helper */

        $_helper= Mage::helper('easynolo_bancasellapro');

        if(!$a || !$b){
            $_helper->log('Richiesta S2S, mancano i parametri di input');
            $this->norouteAction();
            return;
        }

        Mage::register('bancasella_param_a', $a);
        Mage::register('bancasella_param_b', $b);

        /** @var EasyNolo_BancaSellaPro_Helper_Crypt $helper */
        $helper= Mage::helper('easynolo_bancasellapro/crypt');

        $webservice = $helper->getInitWebservice();

        $webservice->setDecryptParam($a , $b);
        $helper->decryptPaymentRequest ($webservice);

        $orderId = $_helper->getIncrementIdByShopTransactionId($webservice->getShopTransactionID());
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

        if($order->getId()){
            $_helper->log('Imposto lo stato dell\'ordine in base al decrypt');
            $helper->setStatusOrderByS2SRequest($order, $webservice);
            Mage::helper('easynolo_bancasellapro/recurringprofile')->checkAndSaveToken($order, $webservice);
        }else{
            $_helper->log('La richiesta effettuata non ha un corrispettivo ordine. Id ordine= '.$webservice->getShopTransactionID());
        }

        //restiutisco una pagina vuota per notifica a GestPay
        $this->getResponse()->setBody('<html></html>');
        return;
    }

    // This function is used to check 3D payment using iframe
    public function confirm3dAction(){
        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Richiamata azione conferma 3dsecure');
        $_helper->log($_REQUEST);
        
        $order = $this->getOrder();
        if($order->getId()){
        	$order->addStatusHistoryComment($this->__('User is redirecting to issuing bank for 3d authentification.'));
        	$order->save();
        }
        
        $this->loadLayout();
        $this->renderLayout();
    }

    // This function is used to check 3D payment using S2S method (eg. when tokenization is enabled)
    public function confirm3dS2SAction(){
        $order = $this->getOrder();
        $gestpay = $order->getPayment()->getMethodInstance();
        $checkoutSession = Mage::getSingleton('checkout/session');
        if($order->getId()){
            $order->addStatusHistoryComment($this->__('User is redirecting to issuing bank for 3d authentification.'));
            $transactionKey = Mage::getSingleton('checkout/session')->getGestpayTransactionKey();
            $paRes = $this->getRequest()->get('PaRes');

            $webservice = Mage::helper('easynolo_bancasellapro/s2s')->getInitWebservice();
            $result = Mage::helper('easynolo_bancasellapro/s2s')->execute3DPaymentS2S($webservice, $order, $transactionKey, $paRes);
            if(!$result->getTransactionResult() || $result->getTransactionResult() == 'KO') {
                $checkoutSession->addError($result->getErrorDescription());
                $redirect = 'checkout/cart';
            } else {
                $helperDecrypt = Mage::helper('easynolo_bancasellapro/crypt');
                $helperDecrypt->setStatusOrderByS2SRequest($order, $webservice);
                if ($order->getStatus() == $gestpay->getOrderStatusOkGestPay()) {
                    $order->sendNewOrderEmail();
                }
                $order->save();
                // reset quote on checkout session
                if ($lastQuoteId = $checkoutSession->getLastQuoteId()){
                    $quote = Mage::getModel('sales/quote')->load($lastQuoteId);
                    if ($quoteId = $quote->getId()) {
                        $quote->setIsActive(false)->save();
                        $checkoutSession->setQuoteId(null);
                    }
                }
                $redirect ='checkout/onepage/success';
            }

            $store = Mage::registry('easynolo_bancasellapro_store_maked_order');
            if($store && $store->getId()){
                $this->redirectInCorrectStore($store, $redirect);
            }else{
                $this->_redirect($redirect);
            }

            return $this;
        }
    }

    protected function redirectInCorrectStore($store, $path, $arguments = array())
    {
        $params = array_merge(
            $arguments,
            array(
                '_use_rewrite' => false,
                '_store' => $store,
                '_store_to_url' => true,
                '_secure' => $store->isCurrentlySecure()
            ) );
        $url = Mage::getUrl($path,$params);

        $this->getResponse()->setRedirect($url);
        return;
    }


}