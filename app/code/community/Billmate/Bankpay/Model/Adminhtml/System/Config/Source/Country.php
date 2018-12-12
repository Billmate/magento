<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 15-05-10
 * Time: 11:02
 */

class Billmate_Bankpay_Model_Adminhtml_System_Config_Source_Country extends Mage_Adminhtml_Model_System_Config_Source_Country
{
    /**
     * @param bool $isMultiselect
     *
     * @return array
     */
	public function toOptionArray($isMultiselect=false)
	{
		$countries = array('SE');

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