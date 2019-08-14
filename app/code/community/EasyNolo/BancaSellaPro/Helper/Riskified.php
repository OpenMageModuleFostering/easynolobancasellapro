<?php

class EasyNolo_BancaSellaPro_Helper_Riskified extends Mage_Core_Helper_Abstract
{

    public function addOrderDetailsParams(&$params, $order_id=null){
        $beaconSessionID = Mage::getSingleton("core/session")->getEncryptedSessionId();
        if(!$order_id) return;
        if(!$beaconSessionID) return;
        $order = Mage::getModel('sales/order')->load($order_id);
        if(!$order->getId()) return;

        $gestpay = $order->getPayment()->getMethodInstance();

        $params['OrderDetails'] = array();
        $params['OrderDetails']['FraudPrevention'] = array();
        $params['OrderDetails']['FraudPrevention']['SubmitForReview'] = 1;
        $createdAt = $order->getCreatedAt();
        $orderDate = date('Y-m-d', strtotime($createdAt));
        $params['OrderDetails']['FraudPrevention']['OrderDateTime'] = $orderDate;
        $params['OrderDetails']['FraudPrevention']['Source'] = 'website';
        $params['OrderDetails']['FraudPrevention']['SubmissionReason'] = 'rule_decision';
        $params['OrderDetails']['FraudPrevention']['BeaconSessionID'] = $beaconSessionID;

        if($gestpay->getRiskifiedConfigData('customer_data')) {
            $params['OrderDetails']['CustomerDetail'] = array();
            $params['OrderDetails']['CustomerDetail']['FirstName'] = $order->getCustomerFirstname();
            $params['OrderDetails']['CustomerDetail']['Lastname'] = $order->getCustomerLastname();
            $params['OrderDetails']['CustomerDetail']['PrimaryEmail'] = $order->getCustomerEmail();
            if ($order->getCustomerDob())
                $params['OrderDetails']['CustomerDetail']['DateOfBirth'] = $order->getCustomerDob();
        }

        if($gestpay->getRiskifiedConfigData('shipping_info')) {
            $shipping_address = $order->getShippingAddress();
            $params['OrderDetails']['ShippingAddress'] = array();
            $params['OrderDetails']['ShippingAddress']['FirstName'] = $shipping_address->getFirstname();
            $params['OrderDetails']['ShippingAddress']['Lastname'] = $shipping_address->getLastname();
            $params['OrderDetails']['ShippingAddress']['Email'] = $shipping_address->getEmail();
            $params['OrderDetails']['ShippingAddress']['StreetName'] = $shipping_address->getStreet(-1);
            $params['OrderDetails']['ShippingAddress']['City'] = $shipping_address->getCity();
            $params['OrderDetails']['ShippingAddress']['CountryCode'] = $shipping_address->getCountryModel()->getIso2Code();
            $params['OrderDetails']['ShippingAddress']['ZipCode'] = $shipping_address->getPostcode();
            $params['OrderDetails']['ShippingAddress']['PrimaryPhone'] = $shipping_address->getTelephone();
        }

        if($gestpay->getRiskifiedConfigData('billing_info')) {
            $billing_address = $order->getBillingAddress();
            $params['OrderDetails']['BillingAddress'] = array();
            $params['OrderDetails']['BillingAddress']['FirstName'] = $billing_address->getFirstname();
            $params['OrderDetails']['BillingAddress']['Lastname'] = $billing_address->getLastname();
            $params['OrderDetails']['BillingAddress']['Email'] = $billing_address->getEmail();
            $params['OrderDetails']['BillingAddress']['StreetName'] = $billing_address->getStreet(-1);
            $params['OrderDetails']['BillingAddress']['City'] = $billing_address->getCity();
            $params['OrderDetails']['BillingAddress']['CountryCode'] = $billing_address->getCountryModel()->getIso2Code();
            $params['OrderDetails']['BillingAddress']['ZipCode'] = $billing_address->getPostcode();
            $params['OrderDetails']['BillingAddress']['PrimaryPhone'] = $billing_address->getTelephone();
        }

        if($gestpay->getRiskifiedConfigData('product_details')) {
            $params['OrderDetails']['ArrayOfProductDetail'] = array('ProductDetail' => array());
            foreach($order->getAllItems() as $order_item) {
                $params['OrderDetails']['ArrayOfProductDetail']['ProductDetail'][] = array(
                    'ProductCode' => $order_item->getId(),
                    'Name' => $order_item->getName(),
                    'SKU' => $order_item->getSku(),
                    'Description' => $order_item->getDescription(),
                    'Quantity' => (int)$order_item->getQtyOrdered(),
                    'UnitPrice' => round($order_item->getPrice(), 2),
                    'Price' => round($order_item->getRowTotal(), 2),
                    'Type' => 1,
                    'Vat' => $order_item->getTaxPercent().'%',
                    'RequiresShipping' => true
                );
            }
        }
    }
}