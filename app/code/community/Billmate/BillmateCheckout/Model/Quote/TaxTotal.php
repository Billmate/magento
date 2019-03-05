<?php

class Billmate_BillmateCheckout_Model_Quote_TaxTotal extends Mage_Sales_Model_Quote_Address_Total_Tax
{
    /**
     * @param Mage_Sales_Model_Quote_Address $address
     *
     * @return $this
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $collection = $address->getQuote()->getPaymentsCollection();
        if ($collection->count() <= 0 || $address->getQuote()->getPayment()->getMethod() == null) {
            return $this;
        }

        $paymentMethod = $address->getQuote()->getPayment()->getMethodInstance();

        if ($paymentMethod->getCode() != 'billmatecheckout') {
            return $this;
        }

        $store = $address->getQuote()->getStore();        

        $items = $address->getAllItems();
        if (!count($items)) {
            return $this;
        }

        $custTaxClassId = $address->getQuote()->getCustomerTaxClassId();

        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        /* @var $taxCalculationModel Mage_Tax_Model_Calculation */
        $request = $taxCalculationModel->getRateRequest(
            $address,
            $address->getQuote()->getBillingAddress(),
            $custTaxClassId,
            $store
        );
        $InvoiceTaxClass = Mage::helper('billmatecheckout')->getInvoiceTaxClass($store);

        $InvoiceTax      = 0;
        $InvoiceBaseTax  = 0;

        if ($InvoiceTaxClass) {
            if ($rate = $taxCalculationModel->getRate($request->setProductClassId($InvoiceTaxClass))) {

                if (!Mage::helper('billmatecheckout')->InvoicePriceIncludesTax()) {
                    $InvoiceTax    = $address->getFeeAmount() * $rate/100;
                    $InvoiceBaseTax= $address->getBaseFeeAmount() * $rate/100;
                } else {
                    $InvoiceTax    = $address->getPaymentTaxAmount();
                    $InvoiceBaseTax= $address->getBasePaymentTaxAmount();
                }

                $InvoiceTax    = $store->roundPrice($InvoiceTax);
                $InvoiceBaseTax= $store->roundPrice($InvoiceBaseTax);

                $address->setTaxAmount($address->getTaxAmount() + $InvoiceTax);
                $address->setBaseTaxAmount($address->getBaseTaxAmount() + $InvoiceBaseTax);

                $this->_saveAppliedTaxes(
                    $address,
                    $taxCalculationModel->getAppliedRates($request),
                    $InvoiceTax,
                    $InvoiceBaseTax,
                    $rate
                );
            }
        }

        if (!Mage::helper('billmatecheckout')->InvoicePriceIncludesTax()) {
            $address->setInvoiceTaxAmount($InvoiceTax);
            $address->setBaseInvoiceTaxAmount($InvoiceBaseTax);
        }

        $address->setGrandTotal($address->getGrandTotal() + $address->getPaymentTaxAmount());
        $address->setBaseGrandTotal($address->getBaseGrandTotal() + $address->getBasePaymentTaxAmount());

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $address
     *
     * @return $this
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {        
        $store = $address->getQuote()->getStore();
        /**
         * Modify subtotal
         */

         if (Mage::getSingleton('tax/config')->displayCartSubtotalBoth($store) ||
            Mage::getSingleton('tax/config')->displayCartSubtotalInclTax($store)) {

            if ($address->getSubtotalInclTax() > 0) {
                $subtotalInclTax = $address->getSubtotalInclTax();
            } else {
                $subtotalInclTax = $address->getSubtotal()+ $address->getTaxAmount() -
                    $address->getShippingTaxAmount() - $address->getPaymentTaxAmount();
            }            

            $address->addTotal(
                array(
                    'code'      => 'subtotal',
                    'title'     => Mage::helper('sales')->__('Subtotal'),
                    'value'     => $subtotalInclTax,
                    'value_incl_tax' => $subtotalInclTax,
                    'value_excl_tax' => $address->getSubtotal()
                )
            );
        }
        return $this;
    }
}
