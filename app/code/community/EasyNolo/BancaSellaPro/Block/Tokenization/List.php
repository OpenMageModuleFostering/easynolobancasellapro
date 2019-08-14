<?php
/**
 * Created by PhpStorm.
 * User: maintux
 * Date: 18/12/16
 * Time: 11:00
 */

class EasyNolo_BancaSellaPro_Block_Tokenization_List extends Mage_Core_Block_Template
{
    public function getAllTokens(){
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $tokens = Mage::getModel('easynolo_bancasellapro/token')
            ->getCollection()
            ->addFieldToFilter('customer_id', $customer->getId());
        return $tokens;
    }

    protected function _beforeToHtml()
    {
        $this->setBackUrl($this->getUrl('customer/account/'));
        return parent::_beforeToHtml();
    }
}