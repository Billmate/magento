<?php
class Billmate_Partpayment_Model_Gateway extends Varien_Object{
    public $isMatched = true;
    function makePayment(){
        
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

        $k = Mage::helper('partpayment')->getBillmate(true, false);

        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
        

        $quote = Mage::getSingleton('checkout/session')->getQuote();        
        $Billing= $quote->getBillingAddress();
        $Shipping= $quote->getShippingAddress();
        $iso3 = Mage::getModel('directory/country')->load($Billing->getCountryId())->getIso3Code();
        
		
		switch ($iso3) {
			// Sweden
			case 'SWE':
				$country = 209;
				$language = 138;
				$encoding = 2;
				$currency = 0;
				break;
			// Finland
			case 'FIN':
				$country = 73;
				$language = 37;
				$encoding = 4;
				$currency = 2;
				break;
			// Denmark
			case 'DNK':
				$country = 59;
				$language = 27;
				$encoding = 5;
				$currency = 3;
				break;
			// Norway	
			case 'NOR':
				$country = 164;
				$language = 97;
				$encoding = 3;
				$currency = 1;

				break;
			// Germany	
			case 'DEU':
				$country = 81;
				$language = 28;
				$encoding = 6;
				$currency = 2;
				break;
			// Netherlands															
			case 'NLD':
				$country = 154;
				$language = 101;
				$encoding = 7;
				$currency = 2;
				break;
		}
		$ship_address = $bill_address = array();
	    $shipp = $Shipping->getStreet();

	    $ship_address = array(
		    'email'           => $Customer->email,
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
		    'email'           => $Customer->email,
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
		$_taxHelper  = Mage::helper('tax');
        $_weeeHelper = Mage::helper('weee');
	    $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $_directory = Mage::helper('directory');
	    $_simplePricesTax = ($_taxHelper->displayPriceIncludingTax() || $_taxHelper->displayBothPrices());
        $store = Mage::app()->getStore();
	    foreach( $quote->getAllItems() as $_item){ 
            
            if( $_item->getParentItemId() ){
				continue;
			}

            $request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
            $taxclassid = $_item->getProduct()->getData('tax_class_id');
			
			if( $taxclassid == null && $_item->getProductType() == 'bundle'){
				$options = $_item->getChildren();
				$percent=0;
				foreach($options as $option){
					$taxclassid = $option->getProduct()->getData('tax_class_id');
					$percent += Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
				}
				$percent = $percent/sizeof($options);
			} else {
				$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
			}
			
            $_product = $_item->getProduct();

			$_price = $_taxHelper->getPrice($_product, $_product->getPrice()) ;
			$_regularPrice = $_taxHelper->getPrice($_product, $_product->getPrice(), $_simplePricesTax); 
			$_finalPrice = $_taxHelper->getPrice($_product, $_product->getFinalPrice(), false) ;
			$_finalPriceInclTax = $_taxHelper->getPrice($_product, $_product->getFinalPrice(), true) ;
			$_weeeDisplayType = $_weeeHelper->getPriceDisplayType(); 
		
			//$price = $_directory->currencyConvert($_finalPrice,$baseCurrencyCode,$currentCurrencyCode);
			$price = $_finalPrice;

			if( $_item->getProductType() == 'configurable' || $_item->getProductType() == 'bundle' ){
				$price = $_item->getCalculationPriceOriginal();
			}

			if( $baseCurrencyCode != $currentCurrencyCode ){
				$price = $_directory->currencyConvert($_finalPrice,$baseCurrencyCode,$currentCurrencyCode);
			}
			
			$goods_list[] = array(
				'qty'   => (int)$_item->getQty(),
				'goods' => array(
					'artno'    => $_product->getSKU(),
					'title'    => $_item->getName(),
					'price'    => (int)round($price*100,0),
					'vat'      => (float)$percent,
					'discount' => 0.0,
					'flags'    => 0,
				)
			);
	    }
		$totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();

		if(isset($totals['discount'])) {
			$goods_list[] = array(
				'qty'   => (int)1,
				'goods' => array(
					'artno'    => 'discount',
					'title'    => Mage::helper('payment')->__('Discount'),
					'price'    => round(($totals['discount']->getValue()*0.8)*100),
					'vat'      => (float)$percent,
					'discount' => 0.0,
					'flags'    => 0,
				)
			);
		}

/*        $rates = $quote->getShippingAddress()->getShippingRatesCollection();
        if(!empty($rates)){
            $shippingAmt = 0 ;
            foreach ($rates as $rate) {
                    $shippingAmt+= $rate->getPrice();
            }
            
		    $goods_list[] = array(
			    'qty'   => 1,
			    'goods' => array(
				    'artno'    => '',
				    'title'    => Mage::helper('payment')->__('Shipping'),
				    'price'    => round($shippingAmt*100,0),
				    'vat'      => $percent,
				    'discount' => 0.0,
				    'flags'    => 8,
			    )
		    );
		}*/
		$tax = $Shipping->getAppliedTaxes();
		foreach($tax as $key => $col ){
			$shippingTaxRate = $col['percent'];
		}
		
       $rates = $quote->getShippingAddress()->getShippingRatesCollection();
       if(!empty($rates)){
			$rate = round( $Shipping->getBaseShippingTaxAmount() / $Shipping->getBaseShippingAmount() * 100);
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
	
	    $pno = $payment['partpayment_pno'];
	    $pclass = $payment['pclass'];

	    $transaction = array(
		    "order1"=>(string)time(),
			'order2'=>'',
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
        if( !empty($result1['error'])){
            Mage::throwException( utf8_encode( $result1['error']));
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

        $k = Mage::helper('partpayment')->getBillmate(true, false);
        try{
            $addr = $k->GetAddress($payment['partpayment_pno']);
            
			if(isset($addr['error'])){
		        Mage::throwException( Mage::helper('payment')->__('Invalid personal number'));
            }

			if( empty( $addr[0] ) ){
		        Mage::throwException(Mage::helper('payment')->__('Invalid personal number'));
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
            die('alert("'.$ex->getMessage().'");');
        }
        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
        

        $quote = Mage::getSingleton('checkout/session')->getQuote();        
        $Billing= $quote->getBillingAddress();
        $Shipping= $quote->getShippingAddress();
  
        $fullname = $Billing->getFirstname().' '.$Billing->getLastname().' '.$Billing->getCompany();

		if( empty( $addr[0][0])){
			$apiName = $fullname;
		} else {
			$apiName  = $addr[0][0].' '.$addr[0][1];
		}

        $firstArr = explode(' ', $Billing->getFirstname());
        $lastArr  = explode(' ', $Billing->getLastname());
        
        if( empty( $addr[0][0] ) ){
            $apifirst = $firstArr;
            $apilast  = $lastArr ;
        }else {
            $apifirst = explode(' ', $addr[0][0] );
            $apilast  = explode(' ', $addr[0][1] );
        }
        $matchedFirst = array_intersect($apifirst, $firstArr );
        $matchedLast  = array_intersect($apilast, $lastArr );
        $apiMatchedName   = !empty($matchedFirst) && !empty($matchedLast);

        $billingStreet = $Billing->getStreet();
        
		$addressNotMatched = !isEqual($addr[0][2], $billingStreet[0] ) ||
		    !isEqual($addr[0][3], $Billing->getPostcode()) || 
		    !isEqual($addr[0][4], $Billing->getCity()) || 
		    !isEqual($addr[0][5], BillmateCountry::fromCode($Billing->getCountryId()));

         
        $shippingStreet = $Shipping->getStreet();

        $shippingAndBilling =  !$apiMatchedName ||
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

            if ($defaultShippingId = $customer->getDefaultShipping()){
                 $customerAddress->load($defaultShippingId); 
            } else {   
                 $customerAddress
                    ->setCustomerId($customer->getId())
                    ->setIsDefaultShipping(1)
                    ->setSaveInAddressBook(1)
                 ;   

                 $customer->addAddress($customerAddress);
            }            
            try {

                $customerAddress
                    ->addData($data)
                    ->save()
                ;           
            } catch(Exception $e){
                Mage::log('Address Save Error::' . $e->getMessage());
                Mage::logException( $e );
                die('alert("'.$e->getMessage().'");');
            }
        }
    }
}
