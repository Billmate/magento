<?php
class Billmate_BillmateInvoice_Block_Adminhtml_Sales_Creditmemo_Totals extends Mage_Sales_Block_Order_Creditmemo_Totals
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
}
