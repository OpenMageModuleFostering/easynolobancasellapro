<?php

class EasyNolo_BancaSellaPro_Helper_AlternativePayments_Qiwi extends Mage_Core_Helper_Abstract implements EasyNolo_BancaSellaPro_Helper_AlternativePayments_Interface {

    public function getEncryptParams(Mage_Sales_Model_Order $order){

        $params = array('OrderDetails' => array('BillingAddress' => array(), 'CustomerDetail' => array()));

        $params['OrderDetails']['BillingAddress']['CountryCode'] = $order->getBillingAddress()->getCountryId();
        $params['OrderDetails']['CustomerDetail']['PrimaryPhone'] = $order->getBillingAddress()->getTelephone();

        return $params;
    }

}
