<?php

class Billmate_BillmateInvoice_Block_Adminhtml_Sales_Order_Totals extends Mage_Adminhtml_Block_Sales_Order_Totals
{
    protected function _initTotals()
    {
        parent::_initTotals();

        $order = $this->getOrder();
        $payment = $order->getPayment();
        if ($payment->getMethod() != "billmateinvoice") {
            return $this;
        }
        $info = $order->getPayment()->getMethodInstance()->getInfoInstance();
        if (!$info->getAdditionalInformation("billmateinvoice_fee")) {
            return $this;
        }
        return Mage::helper('billmateinvoice/total')->addToBlock($this);
    }
    // @codingStandardsIgnoreEnd
}
