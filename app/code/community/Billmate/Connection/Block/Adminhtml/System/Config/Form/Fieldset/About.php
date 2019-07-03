<?php

class Billmate_Connection_Block_Adminhtml_System_Config_Form_Fieldset_About
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    /**
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {

        $html = $this->__(
            'Need Installation help? %s  or %s <br><br>',
            '<a href="https://billmate.se/plugins/manual/Installationsmanual_Magento_Billmate.pdf">Swedish</a>',
            '<a href="https://billmate.se/plugins/manual/Installation_Manual_Magento_Billmate.pdf">English</a>');
        return $html;
    }
}