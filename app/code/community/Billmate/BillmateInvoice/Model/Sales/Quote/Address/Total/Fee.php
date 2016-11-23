<?php
class Billmate_BillmateInvoice_Model_Sales_Quote_Address_Total_Fee extends Mage_Sales_Model_Quote_Address_Total_Abstract{
    protected $_code = 'billmateinvoice_fee';
	protected $_calculator = null;
	
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        parent::collect($address);

	
        $this->_setAmount(0);
        $this->_setBaseAmount(0);
		
		if(count($address->getAllItems()) == 0)
        {
            return $this;
        }

        $quote = $address->getQuote();
        $payment = $quote->getPayment();
		
        try{
            $method = $payment->getMethodInstance();
        }catch(Mage_Core_Exception $e){
            return $this;
        }

        if ($method->getCode() != 'billmateinvoice') {
            return $this;
        }
        $items = $this->_getAddressItems($address);
        if (!count($items)) {
            return $this; //this makes only address type shipping to come through
        }
 
        $invoiceFee = $baseInvoiceFee = Mage::helper('billmateinvoice')
			->replaceSeparator( 
				Mage::getStoreConfig('payment/billmateinvoice/billmate_fee') 
			);
		

        $exist_amount = $quote->getFeeAmount();

		
		if(Mage::getStoreConfig('payment/billmateinvoice/tax_class')){
			$data = Mage::helper('billmateinvoice')->getInvoiceFeeArray($invoiceFee, $address, $quote->getCustomerTaxClassId());
			$invoiceFee = $data['base_incl'];
		}
		
		$this->_calculator  = Mage::getSingleton('tax/calculation');
		$calc               = $this->_calculator;
		$store              = $address->getQuote()->getStore();
		$addressTaxRequest  = $calc->getRateRequest(
			$address->getQuote()->getShippingAddress(),
			$address->getQuote()->getBillingAddress(),
			$address->getQuote()->getCustomerTaxClassId(),
			$store
		);

		$paymentTaxClass = Mage::getStoreConfig('payment/billmateinvoice/tax_class');
		$addressTaxRequest->setProductClassId($paymentTaxClass);

		$rate          = $calc->getRate($addressTaxRequest);
		$taxAmount     = $calc->calcTaxAmount($baseInvoiceFee, $rate, false, true);
		$baseTaxAmount = $calc->calcTaxAmount($baseInvoiceFee, $rate, false, true);
		$address->setPaymentTaxAmount(Mage::helper('directory')->currencyConvert($baseTaxAmount,MAge::app()->getStore()->getBaseCurrencyCode(),Mage::app()->getStore()->getCurrentCurrencyCode()));
		$address->setBasePaymentTaxAmount($baseTaxAmount);
//
		$address->setTaxAmount($address->getTaxAmount() + $taxAmount);
		$address->setBaseTaxAmount($address->getBaseTaxAmount() + $baseTaxAmount);
		/* clime: tax calculation end */
		
		
		$address->setFeeAmount(Mage::helper('directory')->currencyConvert($baseInvoiceFee,MAge::app()->getStore()->getBaseCurrencyCode(),Mage::app()->getStore()->getCurrentCurrencyCode()));
		$address->setBaseFeeAmount($baseInvoiceFee);
	    $address->setFeeTaxAmount(Mage::helper('directory')->currencyConvert($baseTaxAmount,MAge::app()->getStore()->getBaseCurrencyCode(),Mage::app()->getStore()->getCurrentCurrencyCode()));
	    $address->setBaseFeeTaxAmount($baseTaxAmount);

	    $totInv = $baseInvoiceFee+$taxAmount;



        $quote->setFeeAmount(Mage::helper('directory')->currencyConvert($baseInvoiceFee,MAge::app()->getStore()->getBaseCurrencyCode(),Mage::app()->getStore()->getCurrentCurrencyCode()));
        $quote->setBaseFeeAmount($baseInvoiceFee);
        $quote->setFeeTaxAmount(Mage::helper('directory')->currencyConvert($baseTaxAmount,MAge::app()->getStore()->getBaseCurrencyCode(),Mage::app()->getStore()->getCurrentCurrencyCode()));
        $quote->setBaseFeeTaxAmount($baseTaxAmount);

        $address->setGrandTotal($address->getGrandTotal() + $address->getFeeAmount() );
        $address->setBaseGrandTotal($address->getBaseGrandTotal() + $address->getBaseFeeAmount() );
    }
 
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $quote = $address->getQuote();
        $payment = $quote->getPayment();
        try{
            $method = $payment->getMethodInstance();
        }catch(Mage_Core_Exception $e){
            return $this;
        }

        if ($method->getCode() != 'billmateinvoice') {
            return $this;
        }

        $amt = $address->getFeeAmount();

		$extra = '';
		if(Mage::getStoreConfig('payment/billmateinvoice/tax_class') && $address->getFeeTaxAmount() > 0 && Mage::getStoreConfig('payment/billmateinvoice/include_tax')){
			$extra = ' (Incl. Vat)';
			$amt += $address->getFeeTaxAmount();
		}else{
			$extra = ' (Excl. Vat)';
		}
			
        $address->addTotal(array(
                'code'=>$this->getCode(),
                'title'=>Mage::helper('billmateinvoice')->__('Billmate Invoice Fee'.$extra),
                'value'=> $amt
        ));
        return $this;
    }
}
