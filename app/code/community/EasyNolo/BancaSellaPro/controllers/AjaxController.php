<?php
/**
 * Created by PhpStorm.
 * User: caccia
 * Date: 01/04/17
 * Time: 13.45
 */
class EasyNolo_BancaSellaPro_AjaxController extends Mage_Core_Controller_Front_Action{

    public function saveOrderAction(){

        $formErrors = array(
            'billing_errors' => array(),
            'shipping_errors' => array(),
        );

        $settings = Mage::helper('onestepcheckout/checkout')->loadConfig();
        $post = $this->getRequest()->getPost();
        $checkoutHelper = Mage::helper('onestepcheckout/checkout');

        $payment_data = $this->getRequest()->getPost('payment');


        $billing_data = $this->getRequest()->getPost('billing', array());
        $shipping_data = $this->getRequest()->getPost('shipping', array());

        $billing_data = $checkoutHelper->load_exclude_data($billing_data);
        $shipping_data = $checkoutHelper->load_exclude_data($shipping_data);

        if(!empty($billing_data)){
            $this->getQuote()->getBillingAddress()->addData($billing_data)->implodeStreetAddress();
        }
        if(!$this->getQuote()->isVirtual()&&$settings['enable_different_shipping']) {

            $this->getQuote()->getShippingAddress()->setCountryId($shipping_data['country_id'])->setCollectShippingRates(true);
        }

        if(!$this->_isLoggedIn()){
            $registration_mode = $settings['registration_mode'];
            if($registration_mode == 'auto_generate_account')   {
                // Modify billing data to contain password also
                $password = Mage::helper('onestepcheckout/checkout')->generatePassword();
                $billing_data['customer_password'] = $password;
                $billing_data['confirm_password'] = $password;
                $this->getQuote()->getCustomer()->setData('password', $password);
                $this->getQuote()->setData('password_hash',Mage::getModel('customer/customer')->encryptPassword($password));

            }


            if($registration_mode == 'require_registration' || $registration_mode == 'allow_guest')   {
                if(!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password']) && ($billing_data['customer_password'] == $billing_data['confirm_password'])){
                    $password = $billing_data['customer_password'];
                    $this->getQuote()->setCheckoutMethod('register');
                    $this->getQuote()->setCustomerId(0);
                    $this->getQuote()->getCustomer()->setData('password', $password);
                    $this->getQuote()->setData('password_hash',Mage::getModel('customer/customer')->encryptPassword($password));
                }
            }

        }

        if($this->_isLoggedIn() || $registration_mode == 'require_registration' || $registration_mode == 'auto_generate_account' || (!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password']))){
            //handle this as Magento handles subscriptions for registered users (no confirmation ever)
            $subscribe_newsletter = $this->getRequest()->getPost('subscribe_newsletter');
            if(!empty($subscribe_newsletter)){
                $this->getQuote()->getCustomer()->setIsSubscribed(1);
            }
        }

        $billingAddressId = $this->getRequest()->getPost('billing_address_id');
        $customerAddressId = (!empty($billingAddressId)) ? $billingAddressId : false ;

        if($this->_isLoggedIn()){
            $this->getQuote()->getBillingAddress()->setSaveInAddressBook(empty($billing_data['save_in_address_book']) ? 0 : 1);
            $this->getQuote()->getShippingAddress()->setSaveInAddressBook(empty($shipping_data['save_in_address_book']) ? 0 : 1);
        }

        $result = $this->_getOnepage()->saveBilling($billing_data, $customerAddressId);

        if(!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password']))   {
            // Trick to allow saving of
            $this->_getOnepage()->saveCheckoutMethod('register');
            $this->getQuote()->setCustomerId(0);
            $customerData = '';
            $tmpBilling = $billing_data;

            if(!empty($tmpBilling['street']) && is_array($tmpBilling['street'])){
                $tmpBilling ['street'] = '';
            }
            $tmpBData = array();
            foreach($this->getQuote()->getBillingAddress()->implodeStreetAddress()->getData() as $k=>$v){
                if(!empty($v) && !is_array($v)){
                    $tmpBData[$k]=$v;
                }
            }
            $customerData= array_intersect($tmpBilling, $tmpBData);

            if(!empty($customerData)){
                $this->getQuote()->getCustomer()->addData($customerData);
                foreach($customerData as $key => $value){
                    $this->getQuote()->setData('customer_'.$key, $value);
                }
            }
        }

        $customerSession = Mage::getSingleton('customer/session');

        if (!empty($billing_data['dob']) && !$customerSession->isLoggedIn()) {
            $dob = Mage::app()->getLocale()->date($billing_data['dob'], null, null, false)->toString('yyyy-MM-dd');
            $this->getQuote()->setCustomerDob($dob);
            $this->getQuote()->setDob($dob);
            $this->getQuote()->getBillingAddress()->setDob($dob);
        }

        if($customerSession->isLoggedIn() && !empty($billing_data['dob'])){
            $dob = Mage::app()->getLocale()->date($billing_data['dob'], null, null, false)->toString('yyyy-MM-dd');
            $customerSession->getCustomer()
                ->setId($customerSession->getId())
                ->setWebsiteId($customerSession->getCustomer()->getWebsiteId())
                ->setEmail($customerSession->getCustomer()->getEmail())
                ->setDob($dob)
                ->save()
            ;
        }

        // set customer tax/vat number for further usage
        $taxid = '';
        if(!empty($billing_data['taxvat'])){
            $taxid = $billing_data['taxvat'];
        } else if(!empty($billing_data['vat_id'])){
            $taxid = $billing_data['vat_id'];
        }
        if (!empty($taxid)) {
            $this->getQuote()->setCustomerTaxvat($taxid);
            $this->getQuote()->setTaxvat($taxid);
            $this->getQuote()->getBillingAddress()->setTaxvat($taxid);
            $this->getQuote()->getBillingAddress()->setTaxId($taxid);
            $this->getQuote()->getBillingAddress()->setVatId($taxid);
        }

        if($customerSession->isLoggedIn() && !empty($billing_data['taxvat'])){
            $customerSession->getCustomer()
                ->setTaxId($billing_data['taxvat'])
                ->setTaxvat($billing_data['taxvat'])
                ->setVatId($billing_data['taxvat'])
                ->save()
            ;
        }

        if(isset($result['error'])) {
            $formErrors['billing_error'] = true;
            $formErrors['billing_errors'] = $checkoutHelper->_getAddressError($result, $billing_data);
            //$this->log[] = 'Error saving billing details: ' . implode(', ', $formErrors['billing_errors']);
        }

        // Validate stuff that saveBilling doesn't handle
        if(!$this->_isLoggedIn())   {
            $validator = new Zend_Validate_EmailAddress();
            if(!$billing_data['email'] || $billing_data['email'] == '' || !$validator->isValid($billing_data['email'])) {

                if(is_array($formErrors['billing_errors']))   {
                    $formErrors['billing_errors'][] = 'email';
                }
                else    {
                    $formErrors['billing_errors'] = array('email');
                }

                $formErrors['billing_error'] = true;

            }
            else    {


                $allow_guest_create_account_validation = false;

                if($settings['registration_mode'] == 'allow_guest')   {
                    if(isset($post['create_account']) && $post['create_account'] == '1')  {
                        $allow_guest_create_account_validation = true;
                    }
                }


                if($settings['registration_mode'] == 'require_registration' || $settings['registration_mode'] == 'auto_generate_account' || $allow_guest_create_account_validation)  {
                    if($this->_customerEmailExists($billing_data['email'], Mage::app()->getWebsite()->getId()))   {

                        $allow_without_password = $settings['registration_order_without_password'];



                        if(!$allow_without_password)    {
                            if(is_array($formErrors['billing_errors']))   {
                                $formErrors['billing_errors'][] = 'email';
                                $formErrors['billing_errors'][] = 'email_registered';
                            }
                            else    {
                                $formErrors['billing_errors'] = array('email','email_registered');
                            }
                        }
                        else    {
                        }
                    }
                    else    {

                        $password_errors = array();

                        if(!isset($billing_data['customer_password']) || $billing_data['customer_password'] == '')    {
                            $password_errors[] = 'password';
                        }

                        if(!isset($billing_data['confirm_password']) || $billing_data['confirm_password'] == '')    {
                            $password_errors[] = 'confirm_password';
                        }
                        else    {
                            if($billing_data['confirm_password'] !== $billing_data['customer_password']) {
                                $password_errors[] = 'password';
                                $password_errors[] = 'confirm_password';
                            }
                        }

                        if(count($password_errors) > 0) {
                            if(is_array($formErrors['billing_errors']))   {
                                foreach($password_errors as $error) {
                                    $formErrors['billing_errors'][] = $error;
                                }
                            }
                            else    {
                                $formErrors['billing_errors'] = $password_errors;
                            }
                        }
                    }
                }


            }
        }

        if($settings['enable_terms']) {
            if(!isset($post['accept_terms']) || $post['accept_terms'] != '1')   {
                $formErrors['terms_error'] = true;
            }
        }


        if ($settings['enable_default_terms'] && $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds()) {
            $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
            if ($diff = array_diff($requiredAgreements, $postedAgreements)) {
                //$formErrors['terms_error'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                $formErrors['agreements_error'] = true;
            }
        }

        $shippingAddressId = $this->getRequest()->getPost('shipping_address_id', false);

        if(!$this->getQuote()->isVirtual()&&$settings['enable_different_shipping']) {
            if(!isset($billing_data['use_for_shipping']) || $billing_data['use_for_shipping'] != '1')   {
                //$shipping_result = $this->_getOnepage()->saveShipping($shipping_data, $shippingAddressId);
                $shipping_result = Mage::helper('onestepcheckout/checkout')->saveShipping($shipping_data, $shippingAddressId);

                if(isset($shipping_result['error']))    {
                    $formErrors['shipping_error'] = true;
                    $formErrors['shipping_errors'] = $checkoutHelper->_getAddressError($shipping_result, $shipping_data, 'shipping');
                }
            }
            else    {
                //$shipping_result = $this->_getOnepage()->saveShipping($billing_data, $shippingAddressId);
                $shipping_result = Mage::helper('onestepcheckout/checkout')->saveShipping($billing_data, $customerAddressId);
            }
        }


        // Save shipping method
        $shipping_method = $this->getRequest()->getPost('shipping_method', '');

        if(!$this->getQuote()->isVirtual()){
            //additional checks if the rate is indeed available for chosen shippin address
            $availableRates = $this->getAvailableRates($this->_getOnepage()->getQuote()->getShippingAddress()->getGroupedAllShippingRates());
            if(empty($shipping_method) || !in_array($shipping_method,$availableRates['codes'])){
                $formErrors['shipping_method'] = true;
            } else if (!$this->_getOnepage()->getQuote()->getShippingAddress()->getShippingDescription()) {
                if(!empty($availableRates['rates'][$shipping_method])){
                    $rate = $availableRates['rates'][$shipping_method];
                    $shippingDescription = $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle();
                    $this->_getOnepage()->getQuote()->getShippingAddress()->setShippingDescription(trim($shippingDescription, ' -'));
                }
            }

            //$result = $this->_getOnepage()->saveShippingMethod($shipping_method);
            $result = Mage::helper('onestepcheckout/checkout')->saveShippingMethod($shipping_method);
            if(isset($result['error']))    {
                $formErrors['shipping_method'] = true;
            }
            else    {
                Mage::dispatchEvent('checkout_controller_onepage_save_shipping_method', array('request'=>$this->getRequest(), 'quote'=>$this->_getOnepage()->getQuote()));
            }
        }




        // Save payment method
        $payment = $this->getRequest()->getPost('payment', array());
        $paymentRedirect = false;

        $payment = $this->filterPaymentData($payment);

        //echo '<pre>' . print_r($_POST,1) . '</pre>';
        //echo '<pre>' . print_r($payment,1) . '</pre>';

        try {
            if(!empty($payment['method']) && $payment['method'] == 'free' && $this->getQuote()->getGrandTotal() > 0){

                $instance = Mage::helper('payment')->getMethodInstance('free');
                if ($instance->isAvailable($this->getQuote())) {
                    $instance->setInfoInstance($this->getQuote()->getPayment());
                    $this->getQuote()->getPayment()->setMethodInstance($instance);
                }
            }
            //$result = $this->_getOnepage()->savePayment($payment);
            $result = Mage::helper('onestepcheckout/checkout')->savePayment($payment);
            $paymentRedirect = $this->getQuote()->getPayment()->getCheckoutRedirectUrl();



        }
        catch (Mage_Payment_Exception $e) {
            if ($e->getFields()) {
                $result['fields'] = $e->getFields();
            }
            $result['error'] = $e->getMessage();
        }
        catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        if(isset($result['error'])) {

            if($result['error'] == 'Can not retrieve payment method instance')  {
                $formErrors['payment_method'] = true;
            }
            else    {
                $formErrors['payment_method_error']  = $result['error'];
            }
        }


        if(!$this->hasFormErrors($formErrors)) {

            if($settings['enable_newsletter'] && isset($post['subscribe_newsletter'])) {
                // Handle newsletter
                $subscribe_newsletter = $post['subscribe_newsletter'];
                $registration_mode = $settings['registration_mode'];
                if(!empty($subscribe_newsletter) && ($registration_mode != 'require_registration' && $registration_mode != 'auto_generate_account') && !$this->getRequest()->getPost('create_account'))  {
                    $model = Mage::getModel('newsletter/subscriber');
                    $model->loadByEmail($billing_data['email']);
                    if(!$model->isSubscribed()){
                        $model->subscribe($billing_data['email']);
                    }
                }
            }

            if($paymentRedirect && $paymentRedirect != '')  {
                $response = array("success"=>true,"error"=>false,"redirect"=>$paymentRedirect);
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
                return;
            }
            $customer =  null;

            if(! $this->_isLoggedIn() )  {

                if( $this->_isEmailRegistered($billing_data['email']) )   {

                    $registration_mode = $settings['registration_mode'];
                    $allow_without_password = $settings['registration_order_without_password'];

                    if($registration_mode == 'require_registration' || $registration_mode == 'auto_generate_account')   {

                        if($allow_without_password) {

                            // Place order on the emails account without the password
                            $customer = $this->_getCustomer($billing_data['email']);

                            $this->_getOnepage()->saveCheckoutMethod('guest');

                        }

                    } elseif ($registration_mode == 'allow_guest') {
                        $customer = $this->_getCustomer($billing_data['email']);
                        $this->_getOnepage()->saveCheckoutMethod('guest');

                    } else {
                        $this->get_Onepage()->saveCheckoutMethod('guest');

                    }


                } else {

                    if($settings['registration_mode'] == 'require_registration')  {

                        // Save as register
                        $this->_getOnepage()->saveCheckoutMethod('register');
                        $this->getQuote()->setCustomerId(0);

                    }
                    elseif($settings['registration_mode'] == 'allow_guest')   {
                        if(isset($post['create_account']) && $post['create_account'] == '1')  {
                            $this->_getOnepage()->saveCheckoutMethod('register');
                            $this->getQuote()->setCustomerId(0);

                        }
                        else    {
                            $this->_getOnepage()->saveCheckoutMethod('guest');

                        }
                    }
                    else{


                        $registration_mode = $settings['registration_mode'];

                        if($registration_mode == 'auto_generate_account')   {
                            $this->_getOnepage()->saveCheckoutMethod('register');
                            $this->getQuote()->setCustomerId(0);
                        }
                        else    {
                            $this->_getOnepage()->saveCheckoutMethod('guest');
                        }
                    }
                }
            }
            $result = $this->_saveOrder($customer);

        }
        else{
            $result=array('success'=>false,'error'=>true,'errors'=>$formErrors);
            $this->getLayout()->getBlockSingleton('onestepcheckout/checkout')->setFormErrors($formErrors);

        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    protected function _saveOrder($customer)
    {
        // Hack to fix weird Magento payment behaviour
        $result = array();
        try {
            $payment = $this->getRequest()->getPost('payment', false);
            if($payment) {
                $payment = $this->filterPaymentData($payment);
                $this->getQuote()->getPayment()->importData($payment);

                $ccSaveAllowedMethods = array('ccsave');
                $method = $this->getQuote()->getPayment()->getMethodInstance();

                if(in_array($method->getCode(), $ccSaveAllowedMethods)){
                    $info = $method->getInfoInstance();
                    $info->setCcNumberEnc($info->encrypt($info->getCcNumber()));
                }

            }




            if(!$this->getQuote()&& !$this->getQuote()->getShippingAddress()->getShippingDescription()){
                Mage::throwException(Mage::helper('checkout')->__('Please choose a shipping method'));
            }

            if(!Mage::helper('customer')->isLoggedIn()){
                $this->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
            }
            $this->_getOnepage()->saveOrder();


            if($customer) {
                $order_id = $this->_getOnepage()->getLastOrderId();
                $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);

                $order
                    ->setCustomerId($customer->getId())
                    ->setCustomerIsGuest(false)
                    ->setCustomerGroupId($customer->getGroupId())
                    ->setCustomerEmail($customer->getEmail())
                    ->setCustomerFirstname($customer->getFirstname())
                    ->setCustomerLastname($customer->getLastname())
                    ->setCustomerMiddlename($customer->getMiddlename())
                    ->setCustomerPrefix($customer->getPrefix())
                    ->setCustomerSuffix($customer->getSuffix())
                    ->setCustomerTaxvat($customer->getTaxvat())
                    ->setCustomerGender($customer->getGender())
                    ->save();
            }

            $redirectUrl = $this->_getOnepage()->getCheckout()->getRedirectUrl();

            if($redirectUrl)    {
                $redirect = $redirectUrl;
            } else {
                $this->getQuote()->setIsActive(false);
                $this->getQuote()->save();
                $redirect = Mage::getUrl('checkout/onepage/success');
                //$this->_redirect('checkout/onepage/success', array('_secure'=>true));
            }
            $result = array('success'=>true);
            if (isset($redirect)) {
                $result['redirect'] = $redirect;
            }
        } catch(Exception $e)   {
            //need to activate
            $this->_getOnepage()->getQuote()->setIsActive(true);
            //need to recalculate
            $this->_getOnepage()->getQuote()->getShippingAddress()->setCollectShippingRates(true)->collectTotals();
            $error = $e->getMessage();
            $errors = array();
            $errors['unknown_source_error'] = $error;
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getQuote(), $error);
            $result = array('success'=>false,
                'error'=>true,
                'errors'=>$errors);
            $this->getLayout()->getBlockSingleton('onestepcheckout/checkout')->setFormErrors($errors);

        }

        return $result;
    }

    public function getQuote(){
        return $this->_getOnepage()->getQuote();
    }

    protected function _getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    protected function _getCustomer($email)
    {
        $model = Mage::getModel('customer/customer');
        $model->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($email);

        if($model->getId() == NULL) {
            return false;
        }

        return $model;
    }

    protected function _isLoggedIn()
    {
        $helper = Mage::helper('customer');
        if( $helper->isLoggedIn())  {
            return true;
        }

        return false;
    }

    protected function _customerEmailExists($email, $websiteId = null)
    {
        $customer = Mage::getModel('customer/customer');
        if ($websiteId) {
            $customer->setWebsiteId($websiteId);
        }
        $customer->loadByEmail($email);
        if ($customer->getId()) {
            return $customer;
        }
        return false;
    }

    public function getAvailableRates($rates){
        $return = array();
        if(!empty($rates)){
            foreach ($rates as $_code => $_rates){
                foreach ($_rates as  $rate){
                    $return['codes'][] = $rate->getCode();
                    $return['rates'][$rate->getCode()] = $rate;
                }
            }
        }
        return $return;
    }
    public function hasFormErrors($formErrors)
    {
        if($this->hasShippingErrors($formErrors) || $this->hasBillingErrors($formErrors) || $this->hasMethodErrors($formErrors) || $this->hasShipmentErrors($formErrors)) {
            return true;
        }

        return false;
    }

    public function hasMethodErrors($formErrors)
    {
        if(isset($formErrors['shipping_method']) && $formErrors['shipping_method']) {
            return true;
        }

        if(isset($formErrors['payment_method']) && $formErrors['payment_method'])   {
            return true;
        }

        if(isset($formErrors['payment_method_error']))    {
            return true;
        }

        if(isset($formErrors['terms_error'])) {
            return true;
        }

        if(isset($formErrors['agreements_error'])) {
            return true;
        }

        return false;
    }

    public function hasShippingErrors($formErrors)
    {
        if(isset($formErrors['shipping_errors']))  {
            if(count($formErrors['shipping_errors']) == 0) {
                return false;
            }
            return true;
        }
        else    {
            return true;
        }
    }

    public function hasBillingErrors($formErrors)
    {
        if(count($formErrors) > 0)   {
            if(isset($formErrors['billing_errors']))  {
                if(count($formErrors['billing_errors']) == 0) {

                    return false;
                }
                return true;
            }
            else    {
                return true;
            }
        }
        return false;
    }

    public function hasShipmentErrors($formErrors)
    {
        if(!empty($formErrors['shipping_method'])){
            return true;
        }
        return false;
    }

    protected function filterPaymentData($payment){
        if($payment){

            foreach($payment as $key => $value){

                if(!strstr($key, '_data') && is_array($value) && !empty($value)){
                    foreach($value as $subkey => $realValue){
                        if(!empty($realValue)){
                            $payment[$subkey]=$realValue;
                        }
                    }
                }
            }

            foreach ($payment as $key => $value){
                if(!strstr($key, '_data') && is_array($value)){
                    unset($payment[$key]);
                }
            }
        }

        return $payment;
    }

    protected function _isEmailRegistered($email)
    {
        $model = Mage::getModel('customer/customer');
        $model->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($email);

        if($model->getId() == NULL)    {
            return false;
        }

        return true;
    }
}