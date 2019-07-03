<?php

class Billmate_CustomPay_Model_Mysql4_Pclass extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('billmatecustompay/billmate_custompay_pclasses', 'id');
    }
}
