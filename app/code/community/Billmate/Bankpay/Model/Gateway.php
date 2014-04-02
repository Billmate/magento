<?php
class Billmate_Bankpay_Model_Gateway extends Varien_Object{
    public $isMatched = true;
    function makePayment($quote, $addorder = false){
        
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
		$methodname = 'billmatebankpay';
        $k = Mage::helper($methodname)->getBillmate(true, false);

        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
        

      //  $quote = Mage::getSingleton('checkout/session')->getQuote();        
        $Billing= $quote->getBillingAddress();
        $Shipping= $quote->getShippingAddress();
		
        $billingiso3 = Mage::getModel('directory/country')->load($Billing->getCountryId())->getIso3Code();
		$billing_country = Mage::getModel('directory/country')->load($Billing->getCountryId())->getName() ;

        $shippingiso3 = Mage::getModel('directory/country')->load($Shipping->getCountryId())->getIso3Code();
		$shipping_country = Mage::getModel('directory/country')->load($Shipping->getCountryId())->getName() ;
		$shipping_country = $shipping_country == 'SWE' ? 209 : $shipping_country;

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
		    'country'         => $shipping_country,
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
		    'country'         => $billing_country,
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
		$percent = 0;
        $store = Mage::app()->getStore();
		$discountAmount = 0;
		$_simplePricesTax = ($_taxHelper->displayPriceIncludingTax() || $_taxHelper->displayBothPrices());
	    foreach( $quote->getAllItems() as $_item){ 
            
            if( $_item->getParentItemId() ){
				continue;
			}

			$request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
			
			$productId = $_item->getProductId();
			$_product = Mage::getModel('catalog/product')->load($productId);
			
            $taxclassid = $_product->getData('tax_class_id');
			
			if( $_item->getProductType() == 'bundle'){
				$options = $_item->getChildrenItems();
				$percent=0;
				foreach($options as $option){
					$taxclassid = $option->getProduct()->getData('tax_class_id');
					$percent += Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
				}
				$percent = $percent/sizeof($options);
			} else {
				$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
			}
			            
			$priceinc = $_item->getOriginalPrice();
			
			if( Mage::getStoreConfig("tax/calculation/price_includes_tax", $store->getId())){
				$price = $_item->getOriginalPrice() / (1+$percent/100);
			}else{
				$price = $_item->getOriginalPrice();
			}
			
			if( $baseCurrencyCode != $currentCurrencyCode ){
				$price = $_directory->currencyConvert($_finalPrice,$baseCurrencyCode,$currentCurrencyCode);
			}

			$goods_list[] = array(
				'qty'   => (int)$_item->getQtyOrdered(),
				'goods' => array(
					'artno'    => $_product->getSKU(),
					'title'    => $_item->getName(),
					'price'    => (int)round($price*100,0),
					'vat'      => (float)$percent,
					'discount' => 0.0,
					'flags'    => 0,
				)
			);
			$discountAmount+= abs( $_item->getDiscountAmount() );
	    }

		$totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();
		
		//print_r($quote1['subtotal']->getData());
		
		if(isset($totals['discount']) || $discountAmount > 0) {
			if( $discountAmount == 0 ){
				$discountAmount = abs($totals['discount']->getValue());
			}
			$applyTax = (boolean)Mage::getStoreConfig('tax/calculation/discount_tax');
			$goods_list[] = array(
				'qty'   => (int)1,
				'goods' => array(
					'artno'    => '',
					'title'    => Mage::helper('payment')->__('Rabatt'),
					'price'    => -round(($discountAmount*0.8)*100),
					'vat'      => ($applyTax) ?$percent:0,
					'discount' => 0.0,
					'flags'    => 0,
				)
			);
		}
        $shippingamount = $quote->getShippingAmount(); 
		
		$request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
		
		$shippingTaxClass = Mage::getStoreConfig('tax/classes/shipping_tax_class', Mage::app()->getStore() );
		$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($shippingTaxClass));
		
        if(!empty($shippingamount) && $shippingamount>0){
            
		    $goods_list[] = array(
			    'qty'   => 1,
			    'goods' => array(
				    'artno'    => '',
				    'title'    => Mage::helper('payment')->__('Shipping'),
				    'price'    => round($shippingamount*100,0),
				    'vat'      => $percent,
				    'discount' => 0.0,
				    'flags'    => 8,
			    )
		    );
		}
		
	    $pclass = -1 ;
        $last_order_increment_id = Mage::getModel("sales/order")->getCollection()->getLastItem()->getIncrementId();
	    $transaction = array(
		    "order1"=>(string)$last_order_increment_id,
			'order2'=>'',
		    "comment"=>(string)'',
		    "flags"=>0,
		    "reference"=>"",
		    "reference_code"=>"",
		    "currency"=>$currency,
		    "country"=>209,
		    "language"=>$language,
		    "pclass"=>$pclass,
		    'gender'=>'1',
		    "shipInfo"=>array("delay_adjust"=>"1"),
		    "travelInfo"=>array(),
		    "incomeInfo"=>array(),
		    "bankInfo"=>array(),
		    "sid"=>array("time"=>microtime(true)),
		    "extraInfo"=>array(array("cust_no"=>(string)$customerId,"creditcard_data"=>$_POST))
	    );
		$transaction["extraInfo"][0]["status"] = 'Paid';
		$qt = Mage::getSingleton('checkout/session')->getQuote();
		
		if( $addorder ) {
			$k->addOrder('',$bill_address,$ship_address,$goods_list,$transaction);
			return;
		}
		$session = Mage::getSingleton("core/session",  array("name"=>"frontend"));
		if( $session->getData('bank_api_called') == 1) return;

	    $result1 = $k->AddInvoice('',$bill_address,$ship_address,$goods_list,$transaction);
        if( !is_array($result1)){
            Mage::throwException( utf8_encode( $result1));
        }else{
			$session->setData("bank_api_called", 1);
		}
    }
}
