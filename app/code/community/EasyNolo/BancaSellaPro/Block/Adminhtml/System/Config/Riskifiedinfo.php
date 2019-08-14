<?php
/**
 * Created by PhpStorm.
 * User: Massimo Maino
 * Date: 28/10/16
 * Time: 19:31
 */

class EasyNolo_BancaSellaPro_Block_Adminhtml_System_Config_Riskifiedinfo
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
            <img src="'.$this->getSkinUrl('images/bancasellapro/riskified.png').'" style="width: 200px;" />
            <br/>
            This option gives you a tool of fraud prevention linked to each
            transaction, provided by Riskified.
            <br>
            This extension works only if your active plan with Banca Sella is "Shop Protection" because we don\'t have sufficent information to decide wich transactions must be reviewed, 
            so with "Shop Protection" plan all transactions are submitted for review by default.
            <br>
            Based on the collection and processing of order information, Riskified returns a risk
            score (ACCEPTED, DECLINED, UnderReview), which is used to change the order state/status.
            <br><br>
        ';
        return $html;
    }

}