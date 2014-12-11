<?php
class Billmate_BillmateInvoice_Model_Gateway extends Varien_Object{
    public $isMatched = true;
	
    function makePayment(){
        // Init $orderValues Array
        $orderValues = array();
        $quote = Mage::getSingleton('checkout/session')->getQuote();        
        $Billing= $quote->getBillingAddress();
        $Shipping= $quote->getShippingAddress();
		
        $payment = Mage::app()->getRequest()->getPost('payment');
        $_customer  = Mage::getSingleton('customer/session')->isLoggedIn() ? Mage::getSingleton('customer/session')->getCustomer()->getData() : null;
        
		$Customer = (object)$_customer;
        
		$country_to_currency = array(
			'NO' => 'NOK',
			'SE' => 'SEK',
			'FI' => 'EUR',
			'DK' => 'DKK',
			'DE' => 'EUR',
			'NL' => 'EUR',
		);
		$methodname = $payment['method'] == 'billmateinvoice'? 'billmateinvoice': 'partpayment';
        $k = Mage::helper($methodname)->getBillmate(true, false);

        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
        $iso3 = Mage::getModel('directory/country')->load($Billing->getCountryId())->getIso3Code();
        $countryCode = Mage::getStoreConfig('general/country/default',Mage::app()->getStore());
        $storeCountryIso2 = Mage::getModel('directory/country')->loadByCode($countryCode)->getIso2Code();
        $storeLanguage = Mage::app()->getLocale()->getLocaleCode();
		
		if( $payment['method'] == 'billmatecardpay' ){
			$country = Mage::getModel('directory/country')->load($Billing->getCountryId())->getName() ;
		} else {
			$country = 209;
		}
		$language = 138;
		$encoding = 2;
		$currency = 0;

		switch ($iso3) {
			// Sweden
			case 'SWE':
				$country = 209;
				$language = 138;
				$encoding = 2;
				$currency = 0;
				break;
		}
		$ship_address = $bill_address = array();
	    $shipp = $Shipping->getStreet();

	    $bill = $Billing->getStreet();

	    foreach($bill_address as $key => $col ){
	        $bill_address[$key] = utf8_decode($col);
	    }
	    foreach($ship_address as $key => $col ){
	        $ship_address[$key] = utf8_decode($col);
	    }

        
	    $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $_directory = Mage::helper('directory');
		


		$store = Mage::app()->getStore();

        $orderValues['paymentData'] = array(
            'Method' => 1,
            'Currency' => $currentCurrencyCode,
            'Country' => $storeCountryIso2,
            'OrderId' => (string)time(),
            'AutoActivate' => 0,
            'Language' => Billmate_Country::fromLocale($storeLanguage)

        );
        $orderValues['PaymentInfo'] = array(
            'PaymentDate' => date('Y-m-d',now()),
            'Paymentterms' => 14,
            'Yourreference' => $Billing->getFirstname(). ' ' . $Billing->getLastname(),
            'Delivery' => $Shipping->getShippingMethod(),

        );

        $orderValues['Customer'] = array(
            'nr' => $customerId,
            'pno' => $payment[$methodname.'_pno']
        );
        $orderValues['Customer']['Billing'] = array(
            'firstname' => $Billing->getFirstname(),
            'lastname' => $Billing->getLastname(),
            'company' => $Billing->getCompany(),
            'street' => $bill[0],
            'street2' => isset($bill[1]) ? $bill[1] : '',
            'zip' => $Billing->getPostCode(),
            'city' => $Billing->getCity(),
            'country' => Billmate_Country::fromCode($Billing->getCountry()),
            'phone' => $Billing->getTelephone(),
            'email' => $Billing->email
        );

        $orderValues['Customer']['Shipping'] = array(
            'firstname' => $Shipping->getFirstname(),
            'lastname' => $Shipping->getLastname(),
            'company' => $Shipping->getCompany(),
            'street' => $shipp[0],
            'street2' => isset($shipp[1]) ? $shipp[1] : '',
            'zip' => $Shipping->getPostCode(),
            'city' => $Shipping->getCity(),
            'country' => Billmate_Country::fromCode($Shipping->getCountry()),
            'phone' => $Shipping->getTelephone()
        );

		// Create Array to save ParentId when bundle is fixed prised
		$bundleArr = array();
		$totalValue = 0;
        $totalTax = 0;
		foreach( $quote->getAllItems() as $_item){
			// Continue if bundleArr contains item parent id, no need for get price then.
			if( in_array($_item->getParentItemId(),$bundleArr)){
				continue;
			}
			$request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
			$taxclassid = $_item->getProduct()->getData('tax_class_id');
			// If Product type == bunde and if bundle price type == fixed
			if($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 1){
				// Set bundle id to $bundleArr
				$bundleArr[] = $_item->getId();
		
			}
				
			// If Product type == bunde and if bundle price type == dynamic
			if($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 0){

				$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
				$orderValues['Articles'][] = array(
						'quantity'   => (int)$_item->getQty(),
						'artnr'    => $_item->getSKU(),
                        'title'    => $_item->getName(),
                        // Dynamic pricing set price to zero
                        'aprice'    => (int)0,
                        'taxrate'      => (float)$percent,
                        'discount' => 0.0,
                        'flags'    => 0,
                        'withouttax' => (int)0

				);
		
		
				// Else the item is not bundle and dynamic priced
			} else {
                $temp = 0;
				$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));

		
				// For tierPrices to work, we need to get calculation price not the price on the product.
				// If a customer buys many of a kind and get a discounted price, the price will bee on the quote item.
				$price = $_directory->currencyConvert($_item->getCalculationPrice(),$baseCurrencyCode,$currentCurrencyCode);
					
				//Mage::throwException( 'error '.$_regularPrice.'1-'. $_finalPrice .'2-'.$_finalPriceInclTax.'3-'.$_price);
		
				$orderValues['Articles'][] = array(
						'quantity'   => (int)$_item->getQty(),
						'artnr'    => $_item->getSKU(),
                        'title'    => $_item->getName(),
                        'aprice'    => (int)round($price*100,0),
                        'vat'      => (float)$percent,
                        'discount' => 0.0,
                        'withouttax' => $_item->getQty() * (int)round($price*100,0)

				);
                $temp = $_item->getQty() * (int) round($price*100,0);
                $totalValue += $temp;
                $totalTax += $temp * ($percent/100);
			}
		}
		
		$totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();

		if(isset($totals['discount'])) {
			$orderValues['Articles'][] = array(
				'qty'   => (int)1,
                'artno'    => 'discount',
                'title'    => Mage::helper('payment')->__('Discount'),
                'price'    => round($totals['discount']->getValue()*0.8)*100,
                'vat'      => (float)$percent,
                'discount' => 0.0,
                'flags'    => 0,

			);
            $totalValue += (-1 * round($totals['discount']->getValue()*0.8))*100;
            $totalTax += ((-1 * round($totals['discount']->getValue()*0.8))*100) * ($percent/100);
		}


       $rates = $quote->getShippingAddress()->getShippingRatesCollection();
       if(!empty($rates)){
			if( $Shipping->getBaseShippingTaxAmount() > 0 )
				$rate = round( $Shipping->getBaseShippingTaxAmount() / $Shipping->getBaseShippingAmount() * 100);
			else
				$rate = 0;

           $orderValues['Cart']['Shipping'] = array(
               'withouttax' => $Shipping->getShippingAmount()*100,
               'taxrate' => (int)$rate
           );
           $totalValue += $Shipping->getShippingAmount()*100;
           $totalTax += ($Shipping->getShippingAmount()*100) * ($rate/100);
		}


		if( $methodname == 'billmateinvoice' ){
			$invoiceFee = Mage::getStoreConfig('payment/billmateinvoice/billmate_fee');
			$invoiceFee = Mage::helper('billmateinvoice')->replaceSeparator($invoiceFee);

			//if(Mage::getStoreConfig('payment/billmateinvoice/tax_class')){
				$feeinfo = Mage::helper('billmateinvoice')->getInvoiceFeeArray($invoiceFee, $Shipping, $quote->getCustomerTaxClassId());
			//}
			if( !empty( $invoiceFee) && $invoiceFee> 0){
               // $invoiceFee = $_directory->currencyConvert($invoiceFee,$baseCurrencyCode,$currentCurrencyCode);

                $orderValues['Cart']['Handling'] = array(
                    'withouttax' => $Shipping->getBaseFeeAmount()*100,
                    'taxrate' => $feeinfo['rate']
                );
                $totalValue += $Shipping->getBaseFeeAmount()*100;
                $totalTax += ($Shipping->getBaseFeeAmount()*100) * ($feeinfo['rate']/100);
			}
		}

        $orderValues['Cart']['Total'] = array(
            'withouttax' => $totalValue,
            'tax' => $totalTax,
            'withtax' => $totalValue + $totalTax
        );
		$result  = json_decode($k->addPayment($orderValues),true);

        if(isset($result['data']['code'])){

            Mage::throwException(utf8_encode($result['data']['code']. ': '.$result['data']['message']));
        } else {
            $session = Mage::getSingleton('core/session', array('name' => 'frontend'));
            $session->setData('billmateinvoice_id', $result['data']['number']);
            $session->setData('billmateorder_id', $result['data']['orderid']);
        }
    }
    
    function init($update = false){
     
        $payment = Mage::app()->getRequest()->getPost('payment');
        $_customer  = Mage::getSingleton('customer/session')->isLoggedIn() ? Mage::getSingleton('customer/session')->getCustomer()->getData() : null;
        $Customer = (object)$_customer;
        
		$country_to_currency = array(
			'NO' => 'NOK',
			'SE' => 'SEK',
			'FI' => 'EUR',
			'DK' => 'DKK',
			'DE' => 'EUR',
			'NL' => 'EUR',
		);

    	$methodname = $payment['method'];
        $k = Mage::helper($methodname)->getBillmate(true, false);
        $quote = Mage::getSingleton('checkout/session')->getQuote();        
        $Billing= $quote->getBillingAddress();
        $Shipping= $quote->getShippingAddress();
  
        try{
            $addr = json_decode($k->getAddress(array('pno' =>$payment[$methodname.'_pno'])),true);
            
			if(!is_array($addr)){
		        Mage::throwException( Mage::helper('payment')->__(utf8_encode($addr)));
            }

			if( isset($addr['data']['code']) ){
                switch($addr['data']['code']){
                    case '1001':
                        Mage::throwException( Mage::helper('payment')->__('Credit denied for Personal/Organisationnumber'));
                        break;
                    case '8010':
                        Mage::throwException(Mage::helper('payment')->__('Personal/Organisation number can not be found in the system'));
                        break;
                    case '9010':
                    case '9011':
                        if(Mage::getStoreConfig('payment/billmateinvoice/test_mode')) {
                            Mage::throwException(Mage::helper('payment')->__(utf8_encode($addr['data']['message'])));
                        }
                        break;
                    case '9014':
                        Mage::throwException(Mage::helper('payment')->__('Personal/Organisation number is required'));
                        break;
                    case '9015':
                        Mage::throwException(Mage::helper('payment')->__('Personal/Organisation number is shorter than expected'));
                        break;
                    case '9016':
                        Mage::throwException(Mage::helper('payment')->__('Personal/Organisation number is not valid'));
                        break;
                }
			}
			foreach( $addr['data'] as $key => $col ){
				$addr['data'][$key] = utf8_encode(($col));
			}
			if( empty( $addr['data']['firstname'] ) ){
				$this->firstname = $Billing->getFirstname();
				$this->lastname = $Billing->getLastname();
				$this->company  = $addr['data']['lastname'];
			} else {
				$this->firstname = $addr['data']['firstname'];
				$this->lastname = $addr['data']['lastname'];
				$this->company  = '';
			}
            $this->street = $addr['data']['address'];
            $this->postcode = $addr['data']['zipcode'];
            $this->city = $addr['data']['city'];
            $this->country = BillmateCountry::getCode( $addr['data']['country'] );
			$this->country_name = Mage::getModel('directory/country')->loadByCode($this->country)->getName();

        }catch( Exception $ex ){
            Mage::logException( $ex );
            die('alert("'.strip_tags( str_replace("<br> ",'\n\n', $ex->getMessage()) ).'");');
        }
        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();

        $fullname = $Billing->getFirstname().' '.$Billing->getLastname().' '.$Billing->getCompany();
		if( empty($addr['data']['firstname']) ){
			$apiName = $Billing->getFirstname().' '.$Billing->getLastname().' '.$Billing->getCompany();
		} else {
			$apiName  = $addr['data']['firstname'].' '.$addr['data']['lastname'];
		}
        $billingStreet = $Billing->getStreet();
        
		$addressNotMatched = !isEqual($addr['data']['address'], $billingStreet[0] ) ||
		    !isEqual($addr['data']['zipcode'], $Billing->getPostcode()) ||
		    !isEqual($addr['data']['city'], $Billing->getCity()) ||
		    !isEqual(strtolower($addr['data']['country']), strtolower(BillmateCountry::fromCode($Billing->getCountryId())));

        
        $shippingStreet = $Shipping->getStreet();

        $shippingAndBilling =  !match_usernamevp( $fullname , $apiName) ||
		    !isEqual($shippingStreet[0], $billingStreet[0] ) ||
		    !isEqual($Shipping->getPostcode(), $Billing->getPostcode()) || 
		    !isEqual($Shipping->getCity(), $Billing->getCity()) || 
		    !isEqual($Shipping->getCountryId, $Billing->getCountryId) ;
        if( $addressNotMatched || $shippingAndBilling ){
            $this->isMatched = false;
        }
        if( $update) {
            $this->isMatched = true;
			$data = array(
			    'firstname' => $this->firstname,
			    'lastname'  => $this->lastname,
			    'street'    => $this->street,
				'company'   => $this->company,
			    'postcode'  => $this->postcode,
			    'city'      => $this->city,
			    'country_id'   => strtoupper($this->country),
			);
			
			$customerAddress = Mage::getModel('customer/address');
			$customer = Mage::getSingleton('customer/session')->getCustomer();
            $Billing->addData( $data )->save();
            $Shipping->addData($data)->save();
        //    Mage::getSingleton('checkout/session')->clear();
            Mage::getModel('checkout/session')->loadCustomerQuote();
        }
    }
}