<?php
class Billmate_Cardpay_Model_Gateway extends Varien_Object
{
    public $isMatched = true;

    public function makePayment()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $_customer  = Mage::getSingleton('customer/session')->isLoggedIn() ? Mage::getSingleton('customer/session')->getCustomer()->getData() : null;
        $Customer = (object)$_customer;

        if(empty($_POST)) $_POST = $_GET;
        $country_to_currency = array(
            'NO' => 'NOK',
            'SE' => 'SEK',
            'FI' => 'EUR',
            'DK' => 'DKK',
            'DE' => 'EUR',
            'NL' => 'EUR',
        );
        $k = Mage::helper('billmatecardpay')->getBillmate(true, false);

        $customerId = (!Mage::getSingleton('customer/session')->getCustomer()->getId()) ? $quote->getCustomerId() : Mage::getSingleton('customer/session')->getCustomer()->getId();


        //  $quote = Mage::getSingleton('checkout/session')->getQuote();
        $Billing= $quote->getBillingAddress();
        $Shipping= $quote->getShippingAddress();

        $billingiso3 = Mage::getModel('directory/country')->load($Billing->getCountryId())->getIso3Code();
        $billing_country = Mage::getModel('directory/country')->load($Billing->getCountryId())->getName() ;

        $shippingiso3 = Mage::getModel('directory/country')->load($Shipping->getCountryId())->getIso3Code();
        $shipping_country = Mage::getModel('directory/country')->load($Shipping->getCountryId())->getName() ;
        $shipping_country = $shipping_country == 'SWE' ? 209 : $shipping_country;

        // Get Store Country
        $countryCode = Mage::getStoreConfig('general/country/default',Mage::app()->getStore());
        $storeCountryIso2 = Mage::getModel('directory/country')->loadByCode($countryCode)->getIso2Code();

        // Get Store language
        $storeLanguage = Mage::app()->getLocale()->getLocaleCode();

        $language = 138;
        $encoding = 2;
        $currency = 0;

        switch ($billingiso3) {
            // Sweden
            case 'SWE':
                $country = 209;
                $language = 138;
                $encoding = 2;
                $currency = 0;
                $billing_country = 209;
                break;
        }
        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        $ship_address = $bill_address = array();
        $shipp = $Shipping->getStreet();
        $bill = $Billing->getStreet();
        $quote->reserveOrderId();
        $orderValues['PaymentData'] = array(
            'method' => 8,
            'currency' => $currentCurrencyCode,
            'country' => $storeCountryIso2,
            'orderid' => ($quote->getReservedOrderId()) ? $quote->getReservedOrderId() : (string)time(),
            'autoactivate' => Mage::getStoreConfig('payment/billmatecardpay/payment_action') == 'sale' ? 1 : 0,
            'language' => BillmateCountry::fromLocale($storeLanguage),
            'logo' => (strlen(Mage::getStoreConfig('billmate/settings/logo')) > 0) ? Mage::getStoreConfig('billmate/settings/logo') : ''
        );
        $orderValues['PaymentInfo'] = array(
            'paymentdate' => (string)date('Y-m-d'),
            'yourreference' => $Billing->getFirstname(). ' ' . $Billing->getLastname(),
            'delivery' => $Shipping->getShippingDescription(),

        );

        $orderValues['Card'] = array(
            'accepturl' => Mage::getUrl('cardpay/cardpay/accept',array('billmate_quote_id' => $quote->getId(),'_secure' => true)),
            'cancelurl' => Mage::getUrl('cardpay/cardpay/cancel',array('_secure' => true)),
            'callbackurl' => Mage::getUrl('cardpay/cardpay/callback',array('billmate_quote_id' => $quote->getId(),'_secure' => true)),
            'returnmethod' => (Mage::app()->getStore()->isCurrentlySecure()) ? 'POST' : 'GET'
        );

        $orderValues['Customer'] = array(
            'nr' => $customerId,
        );
        $orderValues['Customer']['Billing'] = array(
            'firstname' => $Billing->getFirstname(),
            'lastname' => $Billing->getLastname(),
            'company' => $Billing->getCompany(),
            'street' => $bill[0],
            'street2' => isset($bill[1]) ? $bill[1] : '',
            'zip' => $Billing->getPostcode(),
            'city' => $Billing->getCity(),
            'country' => $Billing->getCountryId(),
            'phone' => $Billing->getTelephone(),
            'email' => $Billing->email
        );

        $orderValues['Customer']['Shipping'] = array(
            'firstname' => $Shipping->getFirstname(),
            'lastname' => $Shipping->getLastname(),
            'company' => $Shipping->getCompany(),
            'street' => $shipp[0],
            'street2' => isset($shipp[1]) ? $shipp[1] : '',
            'zip' => $Shipping->getPostcode(),
            'city' => $Shipping->getCity(),
            'country' => $Shipping->getCountryId(),
            'phone' => $Shipping->getTelephone()
        );
        foreach($bill_address as $key => $col ){
            $bill_address[$key] = utf8_decode($col);
        }
        foreach($ship_address as $key => $col ){
            $ship_address[$key] = utf8_decode($col);
        }
        $goods_list = array();

        $_directory = Mage::helper('directory');
        $_taxHelper  = Mage::helper('tax');
        $_weeeHelper = Mage::helper('weee');
        $percent = 0;
        $store = Mage::app()->getStore();
        $discountAmount = 0;
        $_simplePricesTax = ($_taxHelper->displayPriceIncludingTax() || $_taxHelper->displayBothPrices());
        // Create Array to save ParentId when bundle is fixed prised
        $bundleArr = array();
        $totalValue = 0;
        $totalTax = 0;
        $discountAdded = false;
        $discountValue = 0;
        $discountTax = 0;
        $discounts = array();
        $configSku = false;
        $preparedArticle = Mage::helper('billmatecommon')->prepareArticles($quote);
        $discounts = $preparedArticle['discounts'];
        $totalTax = $preparedArticle['totalTax'];
        $totalValue = $preparedArticle['totalValue'];
        $orderValues['Articles'] = $preparedArticle['articles'];

        $totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();

        //print_r($quote1['subtotal']->getData());

	    if(isset($totals['discount']) && !$discountAdded) {
		    $totalDiscountInclTax = $totals['discount']->getValue();
		    $subtotal = $totalValue;
		    foreach($discounts as $percent => $amount) {
			    $discountPercent = $amount / $subtotal;
			    $floor    = 1 + ( $percent / 100 );
			    $marginal = 1 / $floor;
			    $discountAmount = $discountPercent * $totalDiscountInclTax;
			    $orderValues['Articles'][] = array(
				    'quantity'   => (int) 1,
				    'artnr'      => 'discount',
				    'title'      => Mage::helper( 'payment' )->__( 'Discount' ).' '. Mage::helper('billmatecardpay')->__('%s Vat',$percent),
				    'aprice'     => round( ($discountAmount * $marginal ) * 100 ),
				    'taxrate'    => (float) $percent,
				    'discount'   => 0.0,
				    'withouttax' => round( ($discountAmount * $marginal ) * 100 ),

			    );
			    $totalValue += ( 1 * round( $discountAmount * $marginal * 100 ) );
			    $totalTax += ( 1 * round( ( $discountAmount * $marginal ) * 100 ) * ( $percent / 100 ) );
		    }
	    }


        $rates = $quote->getShippingAddress()->getShippingRatesCollection();
        if(!empty($rates)){
            if( $Shipping->getBaseShippingTaxAmount() > 0 ){

                $shippingExclTax = $Shipping->getShippingAmount();
                $shippingIncTax = $Shipping->getShippingInclTax();
                $rate = $shippingExclTax > 0 ? (($shippingIncTax / $shippingExclTax) - 1) * 100 : 0;
            }
            else
                $rate = 0;
            if($Shipping->getShippingAmount() > 0) {
                $orderValues['Cart']['Shipping'] = array(
                    'withouttax' => $Shipping->getShippingAmount() * 100,
                    'taxrate' => (int)$rate
                );
                $totalValue += $Shipping->getShippingAmount() * 100;
                $totalTax += ($Shipping->getShippingAmount() * 100) * ($rate / 100);
            }
        }
        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);


        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );
        $result = $k->addPayment($orderValues);

        if( isset($result['code'])){
            Mage::throwException( utf8_encode($result['message']));
        }else{
            $session = Mage::getSingleton('core/session', array('name' => 'frontend'));
            $session->setData('billmateinvoice_id', $result['number']);
            $session->setData('billmateorder_id', $result['orderid']);
        }
        return $result;
    }
}
