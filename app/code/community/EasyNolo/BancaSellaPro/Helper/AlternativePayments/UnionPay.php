<?php

class EasyNolo_BancaSellaPro_Helper_AlternativePayments_UnionPay extends Mage_Core_Helper_Abstract implements EasyNolo_BancaSellaPro_Helper_AlternativePayments_Interface {

    public function getEncryptParams(Mage_Sales_Model_Order $order){

        $params = array('OrderDetails' => array('CustomerDetail' => array()));

        $params['OrderDetails']['CustomerDetail']['PrimaryEmail'] = $order->getCustomerEmail();
        $params['OrderDetails']['CustomerDetail']['PrimaryPhone'] = $order->getBillingAddress()->getTelephone();

        return $params;
    }

}
