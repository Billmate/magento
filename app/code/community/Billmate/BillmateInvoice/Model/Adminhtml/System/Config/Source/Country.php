<?php

class Billmate_BillmateInvoice_Model_Adminhtml_System_Config_Source_Country extends Mage_Adminhtml_Model_System_Config_Source_Country
{
    public function toOptionArray($isMultiselect=false)
    {
        $countries = array('SE','NO','DK','FI','DE','NL');
        
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('directory/country_collection')->loadData()->toOptionArray(false);
        }
        
        $options = $this->_options;
        if(!$isMultiselect){
            array_unshift($options, array('value'=>'', 'label'=> Mage::helper('adminhtml')->__('--Please Select--')));
        }
        foreach($options as $key => $col ){
            if( !in_array( $col['value'], $countries ) ) {
                unset( $options[$key] );
            }
        }
        return $options;
    }
}
