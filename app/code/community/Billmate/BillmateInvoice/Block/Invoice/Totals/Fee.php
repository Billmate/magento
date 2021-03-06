<?php

class Billmate_BillmateInvoice_Block_Invoice_Totals_Fee extends Mage_Sales_Block_Order_Invoice_Totals
{
    /**
     * @return $this
     */
    public function _initTotals()
    {
        parent::_initTotals();
        $payment = $this->getOrder()->getPayment();
        if ($payment->getMethod() != "billmateinvoice" && $payment->getMethod() != "billmatecheckout") {
            return $this;
        }
        $info = $payment->getMethodInstance()->getInfoInstance();

        if (!$info->getAdditionalInformation("invoice_fee")) {
            return $this;
        }
        return Mage::helper('billmateinvoice/total')->addToBlock($this);
    }
}
