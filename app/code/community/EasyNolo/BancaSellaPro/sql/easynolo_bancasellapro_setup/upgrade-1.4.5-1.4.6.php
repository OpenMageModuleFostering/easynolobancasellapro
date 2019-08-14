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
        array('status' => 'riskified_declined', 'label' => 'Riskified - Declined'),
        array('status' => 'riskified_submitted', 'label' => 'Riskified - Submitted')
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
            'status' => 'riskified_declined',
            'state' => Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            'is_default' => 0
        ),
        array(
            'status' => 'riskified_submitted',
            'state' => Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            'is_default' => 0
        )
    )
);