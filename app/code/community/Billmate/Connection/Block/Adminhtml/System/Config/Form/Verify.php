<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-27
 * Time: 15:52
 */

class Billmate_Connection_Block_Adminhtml_System_Config_Form_Verify extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bmconnection/system/config/verify.phtml');
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
        return Mage::helper('adminhtml')
            ->getUrl('adminhtml/adminhtml_bmconnection/verify');
    }

    /**
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id' => 'bmconnection_verify',
                'label' => $this->helper('adminhtml')->__('Verify'),
                'onclick' => 'javascript:verify(); return false'
            ));
        return $button->toHtml();
    }

    /**
     * @return int|mixed
     */
    public function getStoreId()
    {
        $storeId = 0;
        if (strlen($code = Mage::app()->getRequest()->getParam('store'))) { // store level
            $storeId = Mage::getModel('core/store')->load($code)->getId();
        } elseif (strlen($code = $code = Mage::app()->getRequest()->getParam('website'))) { // website level
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $storeId = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        }

        return $storeId;
    }
}
