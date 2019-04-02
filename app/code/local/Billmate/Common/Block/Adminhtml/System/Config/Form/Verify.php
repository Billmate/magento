<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-27
 * Time: 15:52
 */

class Billmate_Common_Block_Adminhtml_System_Config_Form_Verify extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('billmate/system/config/verify.phtml');
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getAjaxCheckUrl()
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/adminhtml_billmatecommon/verify');
    }

    /**
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id' => 'billmatecommon_verify',
                'label' => $this->helper('adminhtml')->__('Verify'),
                'onclick' => 'javascript:verify(); return false'
            ));
        return $button->toHtml();
    }
}
