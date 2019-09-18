<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-03-20
 * Time: 15:50
 */
class Billmate_Common_Block_Adminhtml_System_Config_Form_Fieldset_Version extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    /**
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        $html .= Mage::helper('billmatecommon')->__("Billmate Version:") . " " . explode(':',BILLMATE_CLIENT)[1];
        $html .= $this->_getFooterHtml($element);
        return $html;
    }
}