<?php

class Billmate_PartPayment_Block_Form extends Mage_Payment_Block_Form{
    protected $method = 'partpayment';
	public function termsx(){
	
		$total = Mage::getSingleTon('checkout/session')
				 ->getQuote()
				 ->getShippingAddress()
				 ->getGrandTotal();
				 
		return Mage::helper('partpayment')->getPlclass($total);
	}
    protected function _construct(){
        parent::_construct();
        $this->setTemplate('billmate/partpayment/invoice.phtml');
    }
}
