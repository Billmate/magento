<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2014-12-09
 * Time: 10:25
 */
class Billmate_BillmateCheckout_Model_Order_Invoice_Total_Tax extends Mage_Sales_Model_Order_Invoice_Total_Tax
{
    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return $this
     */
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();
        $store = $order->getStore();
        $invoiceTaxClass = Mage::getHelper('billmatecheckout')->getInvoiceTaxClass($store);

        $custTaxClassId = $order->getQuote()->getCustomerTaxClassId();
        $address = $order->getBillingAddress();
        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        /* @var $taxCalculationModel Mage_Tax_Model_Calculation */
        $request = $taxCalculationModel->getRateRequest(
            $invoice,
            $order->getQuote()->getBillingAddress(),
            $custTaxClassId,
            $store
        );


        if ($invoiceTaxClass) {
            if ($rate = $taxCalculationModel->getRate($request->setProductClassId($invoiceTaxClass))) {

                if (!Mage::helper('billmatecheckout')->InvoicePriceIncludesTax()) {
                    $InvoiceTax    = $invoice->getFeeAmount() * $rate/100;
                    $InvoiceBaseTax= $invoice->getBaseFeeAmount() * $rate/100;
                } else {
                    $InvoiceTax    = $invoice->getPaymentTaxAmount();
                    $InvoiceBaseTax= $invoice->getBasePaymentTaxAmount();
                }

                $InvoiceTax    = $store->roundPrice($InvoiceTax);
                $InvoiceBaseTax= $store->roundPrice($InvoiceBaseTax);

                $invoice->setTaxAmount($invoice->getTaxAmount() + $InvoiceTax);
                $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $InvoiceBaseTax);

                $this->_saveAppliedTaxes(
                    $invoice,
                    $taxCalculationModel->getAppliedRates($request),
                    $InvoiceTax,
                    $InvoiceBaseTax,
                    $rate
                );
            }
        }
        if (!Mage::helper('billmatecheckout')->InvoicePriceIncludesTax()) {
            $invoice->setInvoiceTaxAmount($InvoiceTax);
            $invoice->setBaseInvoiceTaxAmount($InvoiceBaseTax);
        }

        $invoice->setGrandTotal($invoice->getGrandTotal() + $invoice->getPaymentTaxAmount());
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $invoice->getBasePaymentTaxAmount());

        return $this;
    }
}