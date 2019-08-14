<?php
/**
 * Created by PhpStorm.
 * User: maintux
 * Date: 28/10/16
 * Time: 16:47
 */ 
class EasyNolo_BancaSellaPro_Model_Resource_Sales_Order_FingerPrint_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{

    protected function _construct()
    {
        $this->_init('easynolo_bancasellapro/sales_order_fingerPrint');
    }

}