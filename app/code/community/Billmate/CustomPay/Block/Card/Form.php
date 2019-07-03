<?php
class Billmate_CustomPay_Block_Card_Form extends Billmate_CustomPay_Block_Form
{
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('billmatecustompay/method/card.phtml');
    }
}