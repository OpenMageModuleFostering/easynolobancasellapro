<?php

class EasyNolo_BancaSellaPro_Helper_AlternativePayments_Klarna extends Mage_Core_Helper_Abstract implements EasyNolo_BancaSellaPro_Helper_AlternativePayments_Interface {

    public function getEncryptParams(Mage_Sales_Model_Order $order){

        $method = $order->getPayment()->getMethodInstance();
        $additionalData = $method->getInfoInstance()->getAdditionalData();

        $additionalData = @unserialize($additionalData);

        $params = array('OrderDetails' => array('BillingAddress' => array(), 'ProductDetails' => array()));

        if ($additionalData) {
            $params['OrderDetails']['BillingAddress']['StreetName'] = $additionalData['klarna_street'];
            $params['OrderDetails']['BillingAddress']['City'] = $additionalData['klarna_city'];
            $params['OrderDetails']['BillingAddress']['ZipCode'] = $additionalData['klarna_zip'];
            $params['OrderDetails']['BillingAddress']['CountryCode'] = $additionalData['klarna_country'];
        }

        foreach($order->getAllItems() as $order_item) {
            $params['OrderDetails']['ProductDetails']['ProductDetail'][] = array(
                'ProductCode' => $order_item->getId(),
                'Name' => $order_item->getName(),
                'SKU' => $order_item->getSku(),
                'Description' => $order_item->getDescription(),
                'Quantity' => (int)$order_item->getQtyOrdered(),
                'UnitPrice' => round($order_item->getPrice(), 2),
                'Price' => round($order_item->getRowTotal(), 2),
                'Type' => 1,
                'Vat' => $order_item->getTaxPercent().'%',
                'Discount' => round($order_item->getDiscountAmount(), 2)
            );
        }

        return $params;
    }

}
