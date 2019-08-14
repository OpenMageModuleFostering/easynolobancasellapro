<?php

class EasyNolo_BancaSellaPro_Helper_AlternativePayments extends Mage_Core_Helper_Abstract
{

    const SELECT_HTMLID = 'gestpay_alternative_payment';

    protected $_methods = null;

    protected function _init()
    {
        if (is_null($this->_methods)) {
            $this->_methods = array();
            foreach ((array)Mage::getConfig()->getNode('global/gestpaypro/alternative_payments') as $code => $methodsXml) {
                if (Mage::getStoreConfig('payment/gestpaypro_alternative_payments/enable_'.$code)) {
                    $this->_methods[$code] = (array)$methodsXml;
                }
            }
        }

        return $this->_methods;
    }

    public function isEnabled()
    {
        $methods = $this->_init();

        return count($methods) > 0;
    }

    public function getSelectBox($defValue = null)
    {
        $html = '';
        $methods = $this->_init();

        if ($methods) {
            $id = self::SELECT_HTMLID;
            $name = 'payment[alternative_payment]';
            $additionalBlocks = array(
                'js' => 'easynolo/bancasellapro/gestpay/alternative_payments/js.phtml'
            );

            if (is_null($defValue)) {
                $defValue = '';
            }
            $options = array(
                '' => Mage::helper('easynolo_bancasellapro')->__('Credit Card')
            );
            foreach ($methods as $code => $method) {
                $options[$code] = $method['title'];

                if (empty($method['template'])) {
                    $method['template'] = 'default.phtml';
                }

                $additionalBlocks[] =
                    Mage::app()->getLayout()
                        ->createBlock('easynolo_bancasellapro/alternativePayments_info')
                        ->setCode($code)
                        ->setMethodConfig(new Varien_Object($method))
                        ->setTemplate('easynolo/bancasellapro/gestpay/alternative_payments/'.$method['template']);
            }
            $selectHtml = Mage::app()->getLayout()->createBlock('core/html_select')
                ->setName($name)
                ->setId($id)
                ->setTitle(Mage::helper('easynolo_bancasellapro')->__('Payment Method'))
                ->setValue($defValue)
                ->setOptions($options)
                ->setExtraParams('onchange="alternativePaymentMethodChange(this);"')
                ->getHtml();

            $block = Mage::app()->getLayout()->createBlock('easynolo_bancasellapro/alternativePayments_info')
                ->setTemplate('easynolo/bancasellapro/gestpay/alternative_payments.phtml')
                ->setSelectHtml($selectHtml)
            ;

            $this->_appendAdditional($block, $additionalBlocks);

            $html = $block->toHtml();
        }

        return $html;
    }

    public function getNoJs()
    {
        $block = Mage::app()->getLayout()->createBlock('easynolo_bancasellapro/alternativePayments_info')
            ->setTemplate('easynolo/bancasellapro/gestpay/alternative_payments/nojs.phtml')
        ;
        return $block->toHtml();
    }

    protected function _appendAdditional(Mage_Core_Block_Abstract $infoBlock, $additional)
    {
        foreach ($additional as $block)
        {
            if (is_string($block)) {
                $block = Mage::app()->getLayout()->createBlock('core/template')->setTemplate($block);
            }

            if (is_object($block) && $block instanceof Mage_Core_Block_Abstract) {
                $infoBlock->append($block);
            }
        }
    }

    public function getMethodsJson()
    {
        $json = array();
        $methods = $this->_init();

        foreach ($methods as $code => $method) {
            $json[$code] = array(
                'title' => $method['title'],
                'type' => $method['type'],
            );
        }

        return json_encode($json);
    }

    public function getMethod($code)
    {
        $methods = $this->_init();

        if (isset($methods[$code])) {
            return $methods[$code];
        }

        return false;
    }
}