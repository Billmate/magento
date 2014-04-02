<?php

class Billmate_BillmateInvoice_Block_Order_Totals_Fee extends Mage_Sales_Block_Order_Totals
{

    public function _initTotals()
    {
        
        parent::_initTotals();
		
        $payment = $this->getOrder()->getPayment();

        if ($payment->getMethod() != "billmateinvoice") {
            return $this;
        }
        $info = $payment->getMethodInstance()->getInfoInstance();

        if (!$info->getAdditionalInformation("billmateinvoice_fee")) {
            return $this;
        }
        return Mage::helper('billmateinvoice/total')->addToBlock($this);
    }
}
