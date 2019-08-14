<?php

class EasyNolo_BancaSellaPro_Helper_AlternativePayments_Ideal extends Mage_Core_Helper_Abstract implements EasyNolo_BancaSellaPro_Helper_AlternativePayments_Interface {

    public function getEncryptParams(Mage_Sales_Model_Order $order){
        $storeId = $order->getStoreId();

        $params['paymentTypeDetail'] = array(
            'IdealBankCode' => Mage::getStoreConfigFlag('payment/gestpaypro_alternative_payments/ideal_bankcode', $storeId)
        );

        return $params;
    }
    
}