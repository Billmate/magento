<?php

class Billmate_BillmateInvoice_Model_Quote_Total extends Mage_Sales_Model_Quote_Address_Total_Abstract
{

    protected $address;

    protected $paymentMethod;


    public function collect(Mage_Sales_Model_Quote_Address $address)
    {

		parent::collect($address);
        if ($address->getAddressType() != "shipping") {
            return $this;
        }

        $this->address = $address;
        $this->_resetValues();

        if ($this->address->getQuote()->getId() == null) {
            return $this;
        }

        $items = $this->address->getAllItems();
        if (!count($items)) {
            return $this;
        }

        $payment = $this->address->getQuote()->getPayment();

        try {
            $this->paymentMethod = $payment->getMethodInstance();
        } catch (Mage_Core_Exception $e) {
            return $this;
        }

        if (!$this->paymentMethod instanceof Mage_Payment_Model_Method_Abstract) {
            return $this;
        }

        if ($this->paymentMethod->getCode() === 'billmateinvoice') {
            $this->_initInvoiceFee();
        }

      return $this;

    }

    private function _resetValues()
    {
        $this->address->setInvoiceFee(0);
        $this->address->setBaseInvoiceFee(0);
    }


    private function _initInvoiceFee()
    {
        $fee = Mage::getStoreConfig('payment/billmateinvoice/billmate_fee', Mage::app()->getStore()->getId());

		$fee = Mage::helper('billmateinvoice')->replaceSeparator($fee);

        $this->address->setBaseInvoiceFee($fee);
        $this->address->setInvoiceFee($fee);

        // Add our invoice fee to the address totals
        $this->address->setBaseGrandTotal(
            $this->address->getBaseGrandTotal() + $fee
        );
        $this->address->setGrandTotal(
            $this->address->getGrandTotal() + $fee
        );
    }


    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getAddressType() != "shipping") {
            return $this;
        }

        $incl = $address->getInvoiceFee();

        $data = Mage::helper("billmateinvoice");

        if ($incl == 0) {
            return $this;
        }

        $address->addTotal(
            array(
                'code' => $this->getCode(),
                'title' => Mage::helper('payment')->__('Billmate Invoice Fee'),
                'value' => $incl
            )
        );
        return $this;
    }

}
