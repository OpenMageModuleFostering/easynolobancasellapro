<?php
/**
 * Created by PhpStorm.
 * User: Massimo Maino
 * Date: 28/10/16
 * Time: 16:01
 */ 
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$connection = $installer->getConnection();
$installer->startSetup();

$tableName = $installer->getTable('easynolo_bancasellapro/pc_finger_print');

if (!$connection->isTableExists($tableName)) {
    $table = $connection->newTable($tableName)
        ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ), 'ID')
        ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
            'default' => '0',
        ), 'Order Id')
        ->addColumn('finger_print', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', array(
            'nullable' => false,
        ), 'Customer PC Finger Print')
        ->addForeignKey(
            $installer->getFkName(
                'easynolo_bancasellapro/pc_finger_print',
                'order_id',
                'sales/order',
                'entity_id'
            ),
            'order_id', $installer->getTable('sales/order'), 'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE);

    $connection->createTable($table);
}

$installer->endSetup();