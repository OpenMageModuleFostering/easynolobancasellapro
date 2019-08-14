<?php
/**
 * Created by PhpStorm.
 * User: maintux
 * Date: 27/12/16
 * Time: 16:15
 */

class EasyNolo_BancaSellaPro_Model_System_Config_Source_TokenizationContext
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'always',
                'label' => Mage::helper('easynolo_bancasellapro')->__('Enable tokenization for all payments')
            ),
            array(
                'value' => 'recurring_profile',
                'label' => Mage::helper('easynolo_bancasellapro')->__('Enable tokenization for recurring profile')
            )
        );
    }
}