<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-03-20
 * Time: 15:50
 */
class Billmate_Common_Block_Adminhtml_System_Config_Form_Fieldset_About extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        $html .= Mage::helper('billmatecommon')->__('Need Installation help?  <br/><a href="https://billmate.se/plugins/manual/Installationsmanual_Magento_Billmate.pdf">Swedish</a><br/><a href="https://billmate.se/plugins/manual/Installation_Manual_Magento_Billmate.pdf">English</a>');
        $html .= $this->_getFooterHtml($element);
        return $html;
    }
}