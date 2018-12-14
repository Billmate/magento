<?php
class Billmate_BillmateInvoice_Helper_Data extends Mage_Core_Helper_Abstract
{

	protected $_InvoicePriceIncludesTax;

    /**
     * @param        $amount
     * @param string $thousand
     * @param string $decimal
     *
     * @return mixed|string
     */
	public function replaceSeparator($amount, $thousand = '.', $decimal = ',')
    {
		return $this->convert2Decimal($amount);
	}

    /**
     * @param $amount
     *
     * @return mixed|string
     */
	public function convert2Decimal($amount)
    {
		if( empty( $amount)) {
			return '';
		}
		$dotPosition = strpos($amount, '.');
		$CommaPosition = strpos($amount, ',');
		if( $dotPosition > $CommaPosition ){
			return str_replace(',', '', $amount);
		}else{
			$data = explode(',', $amount);
			$data[1] = empty($data[1])?'':$data[1];
			$data[0] = empty($data[0])?'':$data[0];
			$p = str_replace( '.' ,'', $data[0]);
			return $p.'.'.$data[1];
		}
	}

    /**
     * @param bool $ssl
     * @param bool $debug
     *
     * @return Billmate
     */
    public function getBillmate($ssl = true, $debug = false )
    {
        return Mage::helper('bmpaymentcore')->getBillmate($ssl, $debug);
    }

    /**
     * @return bool
     */
    public function isOneStepCheckout()
    {
        return (bool) Mage::getStoreConfig(
            'onestepcheckout/general/rewrite_checkout_links'
        );
    }

    /**
     * @param $base
     * @param $address
     * @param $taxClassId
     *
     * @return array
     */
    public function getInvoiceFeeArray($base, $address, $taxClassId)
    {
        //Get the correct rate to use
        $store = Mage::app()->getStore();
        $calc = Mage::getSingleton('tax/calculation');
        $rateRequest = $calc->getRateRequest(
            $address, $address, $taxClassId, $store
        );
        $taxClass = (int) Mage::getStoreConfig('payment/billmateinvoice/tax_class');;
        $rateRequest->setProductClassId($taxClass);
        $rate = $calc->getRate($rateRequest);
        //Get the vat display options for products from Magento tax settings
        $VatOptions = Mage::getStoreConfig(
            "tax/calculation/price_includes_tax", $store->getId()
        );

        if ($VatOptions == 1) {
            //Catalog prices are set to include taxes
            $value = $calc->calcTaxAmount($base, $rate, false, false);
            $excl = $base;
            return array(
                'excl' => $excl,
                'base_excl' => $this->calcBaseValue($excl),
                'incl' => $base + $value,
                'base_incl' => $this->calcBaseValue($base + $value),
                'taxamount' => $value,
                'base_taxamount' => $this->calcBaseValue($value),
                'rate' => $rate
            );
        }
        //Catalog prices are set to exclude taxes
        $value = $calc->calcTaxAmount($base, $rate, false, false);
        $incl = ($base + $value);

        return array(
            'excl' => $base,
            'base_excl' => $this->calcBaseValue($base),
            'incl' => $incl,
            'base_incl' => $this->calcBaseValue($incl),
            'taxamount' => $value,
            'base_taxamount' => $this->calcBaseValue($value),
            'rate' => $rate
        );
    }
    /**
     * Try to calculate the value of the invoice fee with the base currency
     * of the store if the purchase was done with a different currency.
     *
     * @param float $value value to calculate on
     *
     * @return float
     */
    protected function calcBaseValue($value)
    {
        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
		$value = Mage::helper('directory')->currencyConvert($value,$baseCurrencyCode,$currentCurrencyCode);
	    return $value;
    }

    /**
     * @param $store
     *
     * @return int
     */
	public function getInvoiceTaxClass($store)
    {
        return (int)Mage::getStoreConfig(
            'payment/billmateinvoice/tax_class',
            $store
        );
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function InvoicePriceIncludesTax($store = null)
    {
        $storeId = Mage::app()->getStore($store)->getId();
        $this->_InvoicePriceIncludesTax[$storeId] = true;
        return $this->_InvoicePriceIncludesTax[$storeId];
    }

    /**
     * @param      $price
     * @param null $includingTax
     * @param null $shippingAddress
     * @param null $ctc
     * @param null $store
     *
     * @return float
     */
    public function getInvoicePrice($price, $includingTax = null, $shippingAddress = null, $ctc = null, $store = null)
    {
        $billingAddress = false;
        if ($shippingAddress && $shippingAddress->getQuote() && $shippingAddress->getQuote()->getBillingAddress()) {
            $billingAddress = $shippingAddress->getQuote()->getBillingAddress();
        }
        
        $calc = Mage::getSingleton('tax/calculation');
        $taxRequest = $calc->getRateRequest(
            $shippingAddress,
            $billingAddress,
            $shippingAddress->getQuote()->getCustomerTaxClassId(),
            $store
        );
        $taxRequest->setProductClassId($this->getInvoiceTaxClass($store));
        $rate = $calc->getRate($taxRequest);
        $tax = $calc->calcTaxAmount($price, $rate, $this->InvoicePriceIncludesTax($store), true);
        
        if ($this->InvoicePriceIncludesTax($store)) {
            return $includingTax ? $price : $price - $tax;
        } else {
            return $includingTax ? $price + $tax : $price;
        }
    }

    /**
     * @param null $store
     *
     * @return int
     */
    public function getInvoiceFeeDisplayType($store = null)
    {
        $storeId = Mage::app()->getStore($store)->getId();
        return $this->_shippingPriceDisplayType[$storeId] = Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH;
    }

    /**
     * @return bool
     */
    public function displayInvoiceFeeIncludingTax()
    {
        return $this->getInvoiceFeeDisplayType() == Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * @return bool
     */
    public function displayInvoiceFeeExcludingTax()
    {
        return $this->getInvoiceFeeDisplayType() == Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * @return bool
     */
    public function displayInvoiceBothPrices()
    {
        return $this->getInvoiceFeeDisplayType() == Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH;
    }
}
