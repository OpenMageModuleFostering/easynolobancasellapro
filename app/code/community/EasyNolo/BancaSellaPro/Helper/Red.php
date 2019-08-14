<?php

class EasyNolo_BancaSellaPro_Helper_Red extends Mage_Core_Helper_Abstract
{
    private function _sanitize($str, $type, $length=null){
        if(!$length) $length = strlen($str);
        switch ($type){
            case 'Alphanumeric':
                return substr(preg_replace("/[^A-Za-z0-9 ]/", '', $str), 0, $length);
            case 'Numeric';
                return (int)substr($str, 0, $length);
            case 'String':
                return substr($str, 0, $length);
            case 'Email';
                if($f = filter_var(substr($str, 0, $length), FILTER_VALIDATE_EMAIL))
                    return $f;
                return '';
            case 'DoB':
                if(preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $str))
                    return $str;
                return '';
            case 'IP';
                if(preg_match("/^[0-9]{3}\.[0-9]{3}\.[0-9]{3}\.[0-9]{3}$/", $str))
                    return $str;
                return '';
            default:
                return $str;
        }
        return '';
    }

    const TIME_TO_DEPARTURE_MAPPING = array(
        'Low Cost' => 'C',
        'Designated by customer' => 'D',
        'International' => 'I',
        'Military' => 'M',
        'Next Day/Overnight' => 'N',
        'Other' => 'O',
        'Store Pickup' => 'P',
        '2 day Service' => 'T',
        '3 day Service' => 'W'
    );

    public function addRedParams(&$params, $order_id=null){
        if(!$order_id) return;
        $order = Mage::getModel('sales/order')->load($order_id);
        if(!$order->getId()) return;

        $gestpay = $order->getPayment()->getMethodInstance();

        $params['redFraudPrevention'] = 1;
        // Red_CustomerInfo
        if($gestpay->getRedConfigData('customer_info')) {
            $params['Red_CustomerInfo'] = array(
                'Customer_Name' => $this->_sanitize($order->getCustomerFirstname(), 'String', 30),
                'Customer_Surname' => $this->_sanitize($order->getCustomerLastname(), 'String', 30),
                'Customer_Email' => $this->_sanitize($order->getCustomerEmail(), 'Email', 45),
                //'Customer_Address' => $this->_sanitize('', 'String', 30),
                //'Customer_Address2' => $this->_sanitize('', 'String', 30),
                //'Customer_City' => $this->_sanitize('', 'String', 20),
                //'Customer_StateCode' => $this->_sanitize('', 'String', 2),
                //'Customer_Country' => $this->_sanitize('', 'String', 3),
                //'Customer_PostalCode' => $this->_sanitize('', 'Alphanumeric', 9),
                //'Customer_Phone' => $this->_sanitize('', 'Numeric', 19)
            );
            if($order->getCustomerPrefix())
                $params['Red_CustomerInfo']['Customer_Title'] = $this->_sanitize($order->getCustomerPrefix(), 'String', 5);
        }
        // Red_ShippingInfo
        if($gestpay->getRedConfigData('shipping_info')) {
            $shipping_address = $order->getShippingAddress();
            $params['Red_ShippingInfo'] = array(
                'Shipping_Name' => $this->_sanitize($shipping_address->getFirstname(), 'String', 30),
                'Shipping_Surname' => $this->_sanitize($shipping_address->getLastname(), 'String', 30),
                'Shipping_Email' => $this->_sanitize($shipping_address->getEmail(), 'Email', 45),
                'Shipping_Address' => $this->_sanitize($shipping_address->getStreet(-1), 'String', 30),
                //'Shipping_Address2' => $this->_sanitize('', 'String', 30),
                'Shipping_City' => $this->_sanitize($shipping_address->getCity(), 'String', 20),
                'Shipping_Country' => $this->_sanitize($shipping_address->getCountryModel()->getIso3Code(), 'String', 3),
                'Shipping_PostalCode' => $this->_sanitize($shipping_address->getPostcode(), 'Alphanumeric', 9),
                'Shipping_HomePhone' => $this->_sanitize($shipping_address->getTelephone(), 'Numeric', 19),
                //'Shipping_MobilePhone' => $this->_sanitize('', 'Numeric', 12),
                //'Shipping_FaxPhone' => $this->_sanitize('', 'Numeric', 19),
                //'Shipping_TimeToDeparture' => $this->_sanitize('', 'String', 1)
            );
            if($shipping_address->getRegionId())
                $params['Red_ShippingInfo']['Shipping_StateCode'] = $this->_sanitize($shipping_address->getRegionModel()->getCode(), 'String', 2);
        }
        // Red_BillingInfo
        if($gestpay->getRedConfigData('billing_info')) {
            $billing_address = $order->getBillingAddress();
            $params['Red_BillingInfo'] = array(
                'Billing_Id' => $this->_sanitize($billing_address->getEntityId(), 'Alphanumeric', 16),
                'Billing_Name' => $this->_sanitize($billing_address->getFirstname(), 'String', 30),
                'Billing_Surname' => $this->_sanitize($billing_address->getLastname(), 'String', 30),
                'Billing_DateOfBirth' => $this->_sanitize(date('Y-m-d', $order->getCustomerDob()), 'DoB'),
                //'Billing_Email' => $this->_sanitize($billing_address->getEmail(), 'Email', 45),
                'Billing_Email' => 'challenge@email.com',
                'Billing_Address' => $this->_sanitize($billing_address->getStreet(-1), 'String', 30),
                //'Billing_Address2' => $this->_sanitize('', 'String', 30),
                'Billing_City' => $this->_sanitize($billing_address->getCity(), 'String', 20),
                'Billing_Country' => $this->_sanitize($billing_address->getCountryModel()->getIso3Code(), 'String', 3),
                'Billing_PostalCode' => $this->_sanitize($billing_address->getPostcode(), 'Alphanumeric', 9),
                'Billing_HomePhone' => $this->_sanitize($billing_address->getTelephone(), 'Numeric', 19),
                //'Billing_MobilePhone' => $this->_sanitize('', 'Numeric', 12),
                //'Billing_WorkPhone' => $this->_sanitize('', 'Numeric', 19)
            );
            if($billing_address->getRegionId())
                $params['Red_BillingInfo']['Billing_StateCode'] = $this->_sanitize($billing_address->getRegionModel()->getCode(), 'String', 2);
        }
        // Red_CustomerData
        if($gestpay->getRedConfigData('customer_data')){
            $params['Red_CustomerData'] = array(
                'MerchantWebSite' => $this->_sanitize(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, $order->getStoreId()), 'String', 60),
                //'PreviousCustomer' => 'N'
            );
            if($order->getRemoteIp())
                $params['Red_CustomerData']['Customer_IpAddress'] = $this->_sanitize($order->getRemoteIp(), 'IP');
            if($gestpay->getRedConfigData('red_merchant_id'))
                $params['Red_CustomerData']['Red_Merchant_ID'] = $gestpay->getRedConfigData('red_merchant_id');
            if($gestpay->getRedConfigData('red_service_type'))
                $params['Red_CustomerData']['Red_ServiceType'] = $gestpay->getRedConfigData('red_service_type');
            $finger_print = Mage::getModel('easynolo_bancasellapro/sales_order_fingerPrint')->getCollection()->addFieldToFilter('order_id', $order_id)->getFirstItem();
            if($finger_print->getId()){
                $params['Red_CustomerData']['PC_FingerPrint'] = $finger_print->getFingerPrint();
            }
            else{
                $params['Red_CustomerData']['Red_ServiceType'] = 'N';
            }
        }
        // Red_Items
        if($gestpay->getRedConfigData('order_items')) {
            $params['Red_Items'] = array(
                'NumberOfItems' => count($order->getAllItems()),
                'Red_Item' => array()
            );
            foreach($order->getAllItems() as $order_item) {
                $params['Red_Items']['Red_Item'][] = array(
                    'Item_ProductCode' => $this->_sanitize($order_item->getSku(), 'String', 12),
                    'Item_Description' => $this->_sanitize($order_item->getName(), 'String', 26),
                    'Item_Quantity' => (int)$order_item->getQtyOrdered(),
                    'Item_InitCost' => (int)($order_item->getPrice() * 10000),
                    'Item_TotalCost' => (int)($order_item->getRowTotal() * 10000)
                );
            }
        }
    }
}