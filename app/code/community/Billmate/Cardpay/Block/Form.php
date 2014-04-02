<?php
class Billmate_Cardpay_Block_Form extends Mage_Payment_Block_Form{
    
    public function _construct(){
        parent::_construct();
        $this->setTemplate('billmate/cardpay.phtml');
    }
}
?>