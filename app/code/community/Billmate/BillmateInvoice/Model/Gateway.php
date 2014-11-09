<?php
class Billmate_BillmateInvoice_Model_Gateway extends Varien_Object{
    public $isMatched = true;
	
    function makePayment(){
         
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
	    $ship_address = array(
		    'email'           => $Shipping->email,
		    'telno'           => $Shipping->getTelephone(),
		    'cellno'          => '',
		    'fname'           => $Shipping->getFirstname(),
		    'lname'           => $Shipping->getLastname(),
		    'company'         => $Shipping->getCompany(),
		    'careof'          => '',
		    'street'          => $shipp[0],
		    'house_number'    => isset($house_no)? $house_no: '',
		    'house_extension' => isset($house_ext)?$house_ext:'',
		    'zip'             => $Shipping->getPostcode(),
		    'city'            => $Shipping->getCity(),
		    'country'         => $country,
	    );
	    $bill = $Billing->getStreet();
	    $bill_address = array(
		    'email'           => $Billing->email,
		    'telno'           => $Billing->getTelephone(),
		    'cellno'          => '',
		    'fname'           => $Billing->getFirstname(),
		    'lname'           => $Billing->getLastname(),
		    'company'         => $Billing->getCompany(),
		    'careof'          => '',
		    'street'          => $bill[0],
		    'house_number'    => '',
		    'house_extension' => '',
		    'zip'             => $Billing->getPostcode(),
		    'city'            => $Billing->getCity(),
		    'country'         => $country,
	    );
	    foreach($bill_address as $key => $col ){
	        $bill_address[$key] = utf8_decode($col);
	    }
	    foreach($ship_address as $key => $col ){
	        $ship_address[$key] = utf8_decode($col);
	    }
	    $goods_list = array();
        
	    $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $_directory = Mage::helper('directory');
		

		$_taxHelper  = Mage::helper('tax');
        $_weeeHelper = Mage::helper('weee');
		$store = Mage::app()->getStore();
		$_simplePricesTax = ($_taxHelper->displayPriceIncludingTax() || $_taxHelper->displayBothPrices());

		// Create Array to save ParentId when bundle is fixed prised
		$bundleArr = array();
		
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
					
				// Get bundle options
				$options = $_item->getChildrenItems();
					
					
				$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
				$goods_list[] = array(
						'qty'   => (int)$_item->getQty(),
						'goods' => array(
								'artno'    => $_item->getSKU(),
								'title'    => $_item->getName(),
								// Dynamic pricing set price to zero
								'price'    => (int)0,
								'vat'      => (float)$percent,
								'discount' => 0.0,
								'flags'    => 0,
						)
				);
		
		
				// Else the item is not bundle and dynamic priced
			} else {
				$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
					
		
				$_product = $_item->getProduct();
		
				$_price = $_taxHelper->getPrice($_product, $_product->getPrice()) ;
				$_regularPrice = $_taxHelper->getPrice($_product, $_product->getPrice(), $_simplePricesTax);
				$_finalPrice = $_taxHelper->getPrice($_product, $_product->getFinalPrice(), false) ;
				$_finalPriceInclTax = $_taxHelper->getPrice($_product, $_product->getFinalPrice(), true) ;
				$_weeeDisplayType = $_weeeHelper->getPriceDisplayType();
		
				// For tierPrices to work, we need to get calculation price not the price on the product.
				// If a customer buys many of a kind and get a discounted price, the price will bee on the quote item.
				$price = $_directory->currencyConvert($_item->getCalculationPrice(),$baseCurrencyCode,$currentCurrencyCode);
					
				//Mage::throwException( 'error '.$_regularPrice.'1-'. $_finalPrice .'2-'.$_finalPriceInclTax.'3-'.$_price);
		
				$goods_list[] = array(
						'qty'   => (int)$_item->getQty(),
						'goods' => array(
								'artno'    => $_item->getSKU(),
								'title'    => $_item->getName(),
								'price'    => (int)round($price*100,0),
								'vat'      => (float)$percent,
								'discount' => 0.0,
								'flags'    => 0,
						)
				);
			}
		}
		
		$totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();

		if(isset($totals['discount'])) {
			$goods_list[] = array(
				'qty'   => (int)1,
				'goods' => array(
					'artno'    => 'discount',
					'title'    => Mage::helper('payment')->__('Discount'),
					'price'    => round($totals['discount']->getValue()*0.8)*100,
					'vat'      => (float)$percent,
					'discount' => 0.0,
					'flags'    => 0,
				)
			);
		}

		$tax = $Shipping->getAppliedTaxes();
		foreach($tax as $key => $col ){
			$shippingTaxRate = $col['percent'];
		}
		
       $rates = $quote->getShippingAddress()->getShippingRatesCollection();
       if(!empty($rates)){
			if( $Shipping->getBaseShippingTaxAmount() > 0 )
				$rate = round( $Shipping->getBaseShippingTaxAmount() / $Shipping->getBaseShippingAmount() * 100);
			else
				$rate = 0;
		    $goods_list[] = array(
			    'qty'   => 1,
			    'goods' => array(
				    'artno'    => '',
				    'title'    => Mage::helper('payment')->__('Shipping'),
				    'price'    => $Shipping->getShippingAmount()*100,
				    'vat'      => (int)$rate, 
				    'discount' => 0.0,
				    'flags'    => 8,
			    )
		    );
		}

		if( $methodname == 'billmateinvoice' ){
			$invoiceFee = Mage::getStoreConfig('payment/billmateinvoice/billmate_fee');
			$invoiceFee = Mage::helper('billmateinvoice')->replaceSeparator($invoiceFee);

			//if(Mage::getStoreConfig('payment/billmateinvoice/tax_class')){
				$feeinfo = Mage::helper('billmateinvoice')->getInvoiceFeeArray($invoiceFee, $Shipping, $quote->getCustomerTaxClassId());
			//}
			if( !empty( $invoiceFee) && $invoiceFee> 0){
               // $invoiceFee = $_directory->currencyConvert($invoiceFee,$baseCurrencyCode,$currentCurrencyCode);
			    $goods_list[] = array(
				    'qty'   => 1,
				    'goods' => array(
					    'artno'    => '',
					    'title'    => Mage::helper('payment')->__('Invoice Fee'),
					    'price'    => $Shipping->getBaseFeeAmount()*100,
					    'vat'      => $feeinfo['rate'],
					    'discount' => 0.0,
					    'flags'    => 16,
				    )
			    );
			}
		}
		
	    $pno = $payment[$methodname.'_pno'];
	    $pclass = empty($_POST['pclass'])? -1:(int)$_POST['pclass'] ;
        
	    $transaction = array(
		    "order1"=>(string)time(),
		    "order2"=>'',			
		    "comment"=>(string)'',
		    "flags"=>0,
		    "reference"=>"",
		    "reference_code"=>"",
		    "currency"=>$currency,
		    "country"=>$country,
		    "language"=>$language,
		    "pclass"=>$pclass,
		    'gender'=>'1',
		    "shipInfo"=>array("delay_adjust"=>"1"),
		    "travelInfo"=>array(),
		    "incomeInfo"=>array(),
		    "bankInfo"=>array(),
		    "sid"=>array("time"=>microtime(true)),
		    "extraInfo"=>array(array("cust_no"=>(string)$customerId))
	    );

	    $result1 = $k->AddInvoice($pno,$ship_address,$bill_address,$goods_list,$transaction);
		    
		if( !is_array($result1)){
            Mage::throwException( utf8_encode( $result1 ));
        }else{
			$session = Mage::getSingleton("core/session",  array("name"=>"frontend"));
			// set data
			$session->setData("billmateinvoice_id", $result1[0]);
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
            $addr = $k->GetAddress($payment[$methodname.'_pno']);
            
			if(!is_array($addr)){
		        Mage::throwException( Mage::helper('payment')->__(utf8_encode($addr)));
            }

			if( empty( $addr[0] ) ){
		        Mage::throwException( Mage::helper('payment')->__('Invalid personal number'));
			}
			foreach( $addr[0] as $key => $col ){
				$addr[0][$key] = utf8_encode(($col));
			}
			if( empty( $addr[0][0] ) ){
				$this->firstname = $Billing->getFirstname();
				$this->lastname = $Billing->getLastname();
				$this->company  = $addr[0][1];
			} else {
				$this->firstname = $addr[0][0];
				$this->lastname = $addr[0][1];
				$this->company  = '';
			}
            $this->street = $addr[0][2];
            $this->postcode = $addr[0][3];
            $this->city = $addr[0][4];
            $this->country = BillmateCountry::getCode( $addr[0][5] );
			$this->country_name = Mage::getModel('directory/country')->loadByCode($this->country)->getName();

        }catch( Exception $ex ){
            Mage::logException( $ex );
            die('alert("'.strip_tags( str_replace("<br> ",'\n\n', $ex->getMessage()) ).'");');
        }
        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();

        $fullname = $Billing->getFirstname().' '.$Billing->getLastname().' '.$Billing->getCompany();
		if( empty($addr[0][0]) ){
			$apiName = $Billing->getFirstname().' '.$Billing->getLastname().' '.$Billing->getCompany();
		} else {
			$apiName  = $addr[0][0].' '.$addr[0][1];
		}
        $billingStreet = $Billing->getStreet();
        
		$addressNotMatched = !isEqual($addr[0][2], $billingStreet[0] ) ||
		    !isEqual($addr[0][3], $Billing->getPostcode()) || 
		    !isEqual($addr[0][4], $Billing->getCity()) || 
		    !isEqual($addr[0][5], BillmateCountry::fromCode($Billing->getCountryId()));

        
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