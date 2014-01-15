<?php

class Billmate_PartPayment_Model_Mysql4_Pclass extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct(){
        $this->_init('partpayment/billmate_partpayment_pclasses', 'id');
    }
}

?>
