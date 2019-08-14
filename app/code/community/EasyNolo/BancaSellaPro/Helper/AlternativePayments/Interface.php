<?php

interface EasyNolo_BancaSellaPro_Helper_AlternativePayments_Interface
{
    public function getEncryptParams(Mage_Sales_Model_Order $order);
}