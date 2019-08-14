<?php

require_once Mage::getModuleDir('controllers', 'Mage_Checkout') . DS . 'OnepageController.php';

if (Mage::helper('easynolo_bancasellapro')->isIWDOpcEnable()) {
    require_once Mage::getModuleDir('controllers', 'IWD_Opc') . DS . 'Checkout' . DS . 'OnepageController.php';
    abstract class EasyNolo_BancaSellaPro_Checkout_OnepageController_Abstract extends IWD_Opc_Checkout_OnepageController {}
} else {
    abstract class EasyNolo_BancaSellaPro_Checkout_OnepageController_Abstract extends Mage_Checkout_OnepageController {}
}

class EasyNolo_BancaSellaPro_Checkout_OnepageController extends EasyNolo_BancaSellaPro_Checkout_OnepageController_Abstract
{
    /**
     * Create order action
     */
    public function saveOrderAction()
    {
        if(version_compare(Mage::getVersion(), '1.8.1') >= 0) {
            if (!$this->_validateFormKey()) {
                $this->_redirect('*/*');
                return;
            }
        }

        $result = array();
        try {

            if ($this->getRequest()->isPost()) {
                $data = $this->getRequest()->getPost('billing', array());
                if (!empty($data)) {
                    $customerAddressId = $this->getRequest()->getPost('billing_address_id', false);

                    if (isset($data['email'])) {
                        $data['email'] = trim($data['email']);
                    }
                    $result = $this->getOnepage()->saveBilling($data, $customerAddressId);

                    if (!empty($result['error'])) {
                        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    }
                }

                if (empty($data['use_for_shipping'])) {
                    $data = $this->getRequest()->getPost('shipping', array());
                    if (!empty($data)) {
                        $customerAddressId = $this->getRequest()->getPost('shipping_address_id', false);
                        $result = $this->getOnepage()->saveShipping($data, $customerAddressId);

                        if (!empty($result['error'])) {
                            return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                        }
                    }
                }
            }

            $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
            if ($requiredAgreements) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                $diff = array_diff($requiredAgreements, $postedAgreements);
                if ($diff) {
                    $result['success'] = false;
                    $result['error'] = true;
                    $result['error_messages'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    return;
                }
            }

            $data = $this->getRequest()->getPost('payment', array());
            if ($data) {
                if(version_compare(Mage::getVersion(), '1.9') >= 0) {
                    $data['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
                        | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                        | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                        | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                        | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                }
                $this->getOnepage()->getQuote()->getPayment()->importData($data);
            }

            $this->getOnepage()->saveOrder();

            $redirectUrl = $this->getOnepage()->getCheckout()->getRedirectUrl();
            $result['success'] = true;
            $result['error']   = false;
        } catch (Mage_Payment_Model_Info_Exception $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $result['error_messages'] = $message;
            }
            $result['goto_section'] = 'payment';
            $result['update_section'] = array(
                'name' => 'payment-method',
                'html' => $this->_getPaymentMethodsHtml()
            );
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success'] = false;
            $result['error'] = true;
            $result['error_messages'] = $e->getMessage();

            $gotoSection = $this->getOnepage()->getCheckout()->getGotoSection();
            if ($gotoSection) {
                $result['goto_section'] = $gotoSection;
                $this->getOnepage()->getCheckout()->setGotoSection(null);
            }
            $updateSection = $this->getOnepage()->getCheckout()->getUpdateSection();
            if ($updateSection) {
                if (isset($this->_sectionUpdateFunctions[$updateSection])) {
                    $updateSectionFunction = $this->_sectionUpdateFunctions[$updateSection];
                    $result['update_section'] = array(
                        'name' => $updateSection,
                        'html' => $this->$updateSectionFunction()
                    );
                }
                $this->getOnepage()->getCheckout()->setUpdateSection(null);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success']  = false;
            $result['error']    = true;
            $result['error_messages'] = $this->__('There was an error processing your order. Please contact us or try again later.');
        }
        $this->getOnepage()->getQuote()->save();
        /**
         * when there is redirect to third party, we don't want to save order yet.
         * we will save the order in return action.
         */
        if (isset($redirectUrl)) {
            $result['redirect'] = $redirectUrl;
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}