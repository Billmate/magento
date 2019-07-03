<?php
class Billmate_CustomPay_Block_Bankpay_Form extends Billmate_CustomPay_Block_Form
{
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('billmatecustompay/method/bankpay.phtml');
    }
}