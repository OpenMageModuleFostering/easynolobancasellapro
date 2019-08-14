<?php
/**
 * Created by PhpStorm.
 * User: maintux
 * Date: 28/10/16
 * Time: 16:41
 */ 
class EasyNolo_BancaSellaPro_Model_Resource_Sales_Order_FingerPrint extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct()
    {
        $this->_init('easynolo_bancasellapro/pc_finger_print', 'entity_id');
    }

}