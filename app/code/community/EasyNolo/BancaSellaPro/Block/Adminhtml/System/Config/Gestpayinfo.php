<?php
/**
 * Created by PhpStorm.
 * User: Massimo Maino
 * Date: 28/10/16
 * Time: 19:31
 */

class EasyNolo_BancaSellaPro_Block_Adminhtml_System_Config_Gestpayinfo
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{

    /**
     * Render Information element
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = '
            <img src="'.$this->getSkinUrl('images/bancasellapro/gestpay.png').'" style="width: 200px;" />
            <br/>
            <br><br>
        ';
        return $html;
    }

}