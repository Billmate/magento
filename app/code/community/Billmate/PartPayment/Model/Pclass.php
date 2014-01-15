<?php

class Billmate_PartPayment_Model_Pclass extends Mage_Core_Model_Abstract
{
    public function _construct(){
        parent::_construct();
        $this->_init('partpayment/pclass');
    }
}

?>
