<?php

class Billmate_CustomPay_Model_Pclass extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('billmatecustompay/pclass');
    }
}
