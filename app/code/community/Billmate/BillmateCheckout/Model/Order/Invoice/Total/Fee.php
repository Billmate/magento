<?php
class Billmate_BillmateCheckout_Model_Order_Invoice_Total_Fee extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return $this
     */
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();
	    $invoiceTax = $order->getPayment()->getAdditionalInformation('billmatecheckout_fee_tax');
        $feeAmountLeft = $order->getFeeAmount() - $order->getFeeAmountInvoiced();
        $baseFeeAmountLeft = $order->getBaseFeeAmount() - $order->getBaseFeeAmountInvoiced();

	    if($order->getFeeTaxAmount() > 0 && version_compare(Mage::getVersion(),'1.9.0.0','<')){
		    $feeAmountLeft += $order->getFeeTaxAmount();
		    $baseFeeAmountLeft += $order->getBaseFeeTaxAmount();
	    }

        if($baseFeeAmountLeft > 0 || abs($baseFeeAmountLeft) < $invoice->getBaseGrandTotal()){
            $invoice->setGrandTotal($invoice->getGrandTotal() + $feeAmountLeft);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseFeeAmountLeft);
        } else {
            $feeAmountLeft = $invoice->getGrandTotal() * -1;
            $baseFeeAmountLeft = $invoice->getBaseGrandTotal() * -1;

            $invoice->setGrandTotal($invoice->getGrandTotal() + $feeAmountLeft);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseFeeAmountLeft);
        }
        $invoice->setFeeAmount($feeAmountLeft);
        $invoice->setBaseFeeAmount($baseFeeAmountLeft);

        return $this;
    }

}