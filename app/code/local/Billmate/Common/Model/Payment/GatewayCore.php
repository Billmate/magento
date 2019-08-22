<?php
class Billmate_Common_Model_Payment_GatewayCore extends Varien_Object
{
    const METHOD_CODE = 1;

    const DEFAULT_COUNTRY = 'SE';

    const DEFAULT_LANGUAGE = 'sv';

    /**
     * @var Billmate_Common_Helper_Data
     */
    protected $helper;

    public function __construct()
    {
        $this->helper = Mage::helper('billmatecommon');
    }

    /**
     * @return array
     */
    protected function getBillingData()
    {
        $billingAddress = $this->getBillingAddress();
        $streets =  $billingAddress->getStreet();
        return [
            'firstname' => $billingAddress->getFirstname(),
            'lastname'  => $billingAddress->getLastname(),
            'company'   => $billingAddress->getCompany(),
            'street'    => $streets[0],
            'street2'   => isset( $streets[1] ) ? $streets[1] : '',
            'zip'       => $billingAddress->getPostcode(),
            'city'      => $billingAddress->getCity(),
            'country'   => $billingAddress->getCountryId(),
            'phone'     => $billingAddress->getTelephone(),
            'email'     => $billingAddress->email
        ];
    }

    /**
     * @return array
     */
    protected function getShippingData()
    {
        $shippingAddress = $this->getShippingAddress();
        $streets =  $shippingAddress->getStreet();
        return [
            'firstname' => $shippingAddress->getFirstname(),
            'lastname'  => $shippingAddress->getLastname(),
            'company'   => $shippingAddress->getCompany(),
            'street'    => $streets[0],
            'street2'   => isset( $streets[1] ) ? $streets[1] : '',
            'zip'       => $shippingAddress->getPostcode(),
            'city'      => $shippingAddress->getCity(),
            'country'   => $shippingAddress->getCountryId(),
            'phone'     => $shippingAddress->getTelephone()
        ];
    }

    /**
     * @return array
     */
    protected function getPaymentInfo()
    {
        $billingAddress = $this->getBillingAddress();
        $shippingAddress = $this->getShippingAddress();
        return [
            'paymentdate'   => (string) date( 'Y-m-d' ),
            'yourreference' => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
            'delivery'      => $shippingAddress->getShippingDescription(),
        ];
    }

    /**
     * @return array
     */
    protected function getPaymentData()
    {
        $countryCode      = Mage::getStoreConfig( 'general/country/default', Mage::app()->getStore() );
        $storeCountryIso2 = Mage::getModel( 'directory/country' )->loadByCode( $countryCode )->getIso2Code();
        $storeLanguage    = Mage::app()->getLocale()->getLocaleCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $quote = $this->getQuote();

        return [
            'method'       => static::METHOD_CODE,
            'paymentplanid' => $quote->getPayment()->getData('pclass'),
            'currency'     => $currentCurrencyCode,
            'country'      => self::DEFAULT_COUNTRY,
            'orderid' => ($quote->getReservedOrderId()) ? $quote->getReservedOrderId() : (string)time(),
            'autoactivate' => 0,
            'language'     => self::DEFAULT_LANGUAGE,
            'logo' => (strlen(Mage::getStoreConfig('billmate/settings/logo')) > 0) ? Mage::getStoreConfig('billmate/settings/logo') : ''
        ];
    }

    /**
     * @return int
     */
    public function getCustomerId()
    {
        $sessionCustomerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
        $customerId =
            ($sessionCustomerId) ? $sessionCustomerId : $this->getQuote()->getCustomerId();
        return $customerId;
    }

    /**
     * @return array
     */
    protected function getShippingCostData()
    {
        $shippingCostData = [];
        $shippingAddress = $this->getShippingAddress();
        $rates = $shippingAddress->getShippingRatesCollection();
        if (!empty($rates)) {
            if ( $shippingAddress->getBaseShippingTaxAmount() > 0 ) {
                $shippingExclTax = $shippingAddress->getShippingAmount();
                $shippingIncTax = $shippingAddress->getShippingInclTax();
                $rate = $shippingExclTax > 0 ? (($shippingIncTax / $shippingExclTax) - 1) * 100 : 0;
            } else {
                $rate = 0;
            }

            if ($shippingAddress->getShippingAmount() > 0) {
                $shippingCostData = [
                    'withouttax' => $shippingAddress->getShippingAmount() * 100,
                    'taxrate' => (int)$rate
                ];
            }
        }
        return $shippingCostData;
    }

    /**
     * @return array
     */
    protected function getShippingHandData()
    {
        file_put_contents("var/log/bmdev.log", "getshippinghanddata 1\n", FILE_APPEND);
        $shippingCostData = [];
        $invoiceFee = Mage::getStoreConfig( 'payment/billmateinvoice/billmate_fee' );
        $invoiceFee = Mage::helper( 'billmateinvoice' )->replaceSeparator( $invoiceFee );
        $shippingAddress = $this->getShippingAddress();

        $feeinfo = Mage::helper( 'billmateinvoice' )
            ->getInvoiceFeeArray( $invoiceFee, $shippingAddress, $this->getQuote()->getCustomerTaxClassId() );
        if ((!empty( $invoiceFee ) && $invoiceFee > 0)) {
            $shippingCostData = array(
                'withouttax' => round($invoiceFee * 100),
                'taxrate'    => $feeinfo['rate']
            );
        }
        return $shippingCostData;
    }

    /**
     * @return array
     */
    protected function calculateArticlesToQuote()
    {
        $quote = $this->getQuote();
        return $this->helper->prepareArticles($quote);
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if (is_null($this->quote)) {
            $this->quote = Mage::getSingleton( 'checkout/session' )->getQuote();
        }
        return $this->quote;
    }

    /**
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getShippingAddress()
    {
        return $this->getQuote()->getShippingAddress();
    }

    /**
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getBillingAddress()
    {
        return $this->getQuote()->getBillingAddress();
    }

    /**
     * @return BillMate
     */
    public function getBMConnection()
    {
        return $this->helper->getBillmate();
    }
}