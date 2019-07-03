<?php

class Billmate_CustomPay_Model_Adminhtml_Comment
{
    /**
     * @var Billmate_CustomPay_Helper_Data
     */
    protected $helper;

    public function __construct()
    {
        $this->helper = Mage::helper('billmatecustompay');
    }

    public function getCommentText($element, $currentValue)
    {
        $methodCode = $element->getParent()->getParent()->getName();
        $methodLogoUrl = $this->helper->getMethodLogo($methodCode);
        return '<img src="'. $methodLogoUrl .'"/>';
    }
}