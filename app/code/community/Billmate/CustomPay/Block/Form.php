<?php
class Billmate_CustomPay_Block_Form extends Mage_Payment_Block_Form
{
    public function getMethodLogo()
    {
        return Mage::helper('billmatecustompay')->getMethodLogo($this->getMethodCode());
    }
}