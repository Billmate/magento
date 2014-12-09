<?php
class Billmate_BillmateInvoice_Model_Order_Creditmemo_Total_Fee extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo){
        $order = $creditmemo->getOrder();

        $feeAmountLeft = $order->getFeeAmountInvoiced() - $order->getFeeAmountRefunded();
        $basefeeAmountLeft = $order->getBaseFeeAmountInvoiced() - $order->getBaseFeeAmountRefunded();

        if ($basefeeAmountLeft > 0) {

        			$creditmemo->setFeeAmount($feeAmountLeft);
        			$creditmemo->setBaseFeeAmount($basefeeAmountLeft);
        			$creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $feeAmountLeft - $basefeeAmountLeft);
        			$creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $feeAmountLeft - $basefeeAmountLeft);
        			$creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $feeAmountLeft);
                    $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $feeAmountLeft);

        }
        return $this;
    }

}