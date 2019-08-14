<?php
/**
 * Created by PhpStorm.
 * User: maintux
 * Date: 18/12/16
 * Time: 10:20
 */
$installer = $this;

// Required tables
$statusTable = $installer->getTable('sales/order_status');
$statusStateTable = $installer->getTable('sales/order_status_state');

// Insert statuses
$installer->getConnection()->insertArray(
    $statusTable,
    array(
        'status',
        'label'
    ),
    array(
        array('status' => 'red_deny', 'label' => 'RED - Deny'),
        array('status' => 'red_challenge', 'label' => 'RED - Challenge')
    )
);

// Insert states and mapping of statuses to states
$installer->getConnection()->insertArray(
    $statusStateTable,
    array(
        'status',
        'state',
        'is_default'
    ),
    array(
        array(
            'status' => 'red_deny',
            'state' => Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            'is_default' => 0
        ),
        array(
            'status' => 'red_challenge',
            'state' => Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            'is_default' => 0
        )
    )
);