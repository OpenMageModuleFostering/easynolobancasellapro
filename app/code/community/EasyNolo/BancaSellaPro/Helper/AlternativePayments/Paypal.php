<?php

class EasyNolo_BancaSellaPro_Helper_AlternativePayments_Paypal extends Mage_Core_Helper_Abstract implements EasyNolo_BancaSellaPro_Helper_AlternativePayments_Interface {

    public function getEncryptParams(Mage_Sales_Model_Order $order){

        $storeId = $order->getStoreId();
        $showProductInfo = Mage::getStoreConfigFlag('payment/gestpaypro_alternative_payments/paypal_show_product_info', $storeId);
        $sellerProtection = Mage::getStoreConfigFlag('payment/gestpaypro_alternative_payments/paypal_seller_protection', $storeId);

        if($showProductInfo){
            $params['OrderDetails'] = array();
            $params['OrderDetails']['ProductDetails'] = array();
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
        }

        if($sellerProtection){
            $params['ppSellerProtection'] = 1;
            $shipping_address = $order->getShippingAddress();
            $params['shippingDetails'] = array();
            $params['shippingDetails']['shipToName'] = $shipping_address->getFirstname().' '.$shipping_address->getLastname();
            $params['shippingDetails']['shipToStreet'] = $shipping_address->getStreet(1);
            $params['shippingDetails']['shipToCity'] = $shipping_address->getCity();
            $params['shippingDetails']['shipToCountryCode'] = $shipping_address->getCountryModel()->getIso2Code();
            $params['shippingDetails']['shipToZip'] = $shipping_address->getPostcode();
            $params['shippingDetails']['shipToStreet2'] = $shipping_address->getStreet(2);
        }

        return $params;
    }

}
