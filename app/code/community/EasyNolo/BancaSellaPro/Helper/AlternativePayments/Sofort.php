<?php
/**
 * Created by PhpStorm.
 * User: maintux
 * Date: 18/01/17
 * Time: 17:48
 */ 
class EasyNolo_BancaSellaPro_Helper_AlternativePayments_Sofort extends Mage_Core_Helper_Abstract {

    function addCustomParams(&$params, $order_id=null){
        if(!$order_id) return;
        $order = Mage::getModel('sales/order')->load($order_id);
        if(!isset($params['OrderDetails'])) $params['OrderDetails'] = array();
        $params['OrderDetails']['CustomerDetail'] = array(
            'FirstName' => $order->getCustomerFirstname(),
            'Lastname' => $order->getCustomerLastname(),
            'PrimaryEmail' => $order->getCustomerEmail()
        );
        $billing_address = $order->getBillingAddress();
        $params['OrderDetails']['BillingAddress'] = array(
            'CountryCode' => $billing_address->getCountryModel()->getIso2Code()
        );
    }

}