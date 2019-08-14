<?php
/**
 * Created by PhpStorm.
 * User: maintux
 * Date: 18/12/16
 * Time: 10:20
 */
$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$tableName = $installer->getTable('easynolo_bancasellapro/token');

if ($connection->isTableExists($tableName)) {
    $connection->modifyColumn($tableName, 'profile_id', 'INT UNSIGNED NULL DEFAULT NULL');
}
