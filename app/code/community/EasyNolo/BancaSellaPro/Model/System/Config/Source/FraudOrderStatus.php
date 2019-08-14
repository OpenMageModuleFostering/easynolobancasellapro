<?php
/**
 * @category EasyNolo
 * @package  EasyNolo_BancaSellaPro
 * @author   Easy Nolo <ecommerce@sella.it>
 */
class EasyNolo_BancaSellaPro_Model_System_Config_Source_FraudOrderStatus extends Mage_Adminhtml_Model_System_Config_Source_Order_Status {
    // set null to return all order statuses
    protected $_stateStatuses = array(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
}