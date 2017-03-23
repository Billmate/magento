<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-27
 * Time: 15:52
 */

class Billmate_PartPayment_Block_Adminhtml_System_Config_Form_Updateplans extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('billmate/system/config/updateplans.phtml');
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxCheckUrl()
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/adminhtml_billmatepartpayment/updateplans',array('_secure' => true));
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id' => 'partpayment_update',
                'label' => $this->helper('adminhtml')->__('Update'),
                'onclick' => 'javascript:updateplans(); return false'
            ));
        return $button->toHtml();
    }


}