<?php
/**
 * Created by PhpStorm.
 * User: Massimo Maino
 * Date: 28/10/16
 * Time: 19:31
 */

class EasyNolo_BancaSellaPro_Block_Adminhtml_System_Config_Redinfo
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
            <img src="'.$this->getSkinUrl('images/bancasellapro/red.png').'" style="width: 200px;" />
            <br/>
            This option gives you a tool of fraud prevention linked to each
            transaction, provided by RED.
            <br>
            You must define your own configuration on RED activation. Based on this
            configuration some parameters has to be passed or not. Once configured you environment,
            you must enable or disable the following options as well.
            <br>
            Based on the collection and processing of this information, Red returns a risk
            score (Accept, Deny, Challenge), which is used to change the order state/status.
            <br><br>
        ';
        return $html;
    }

}