<?php

	class Billmate_BillmateInvoice_Model_Gateway extends Varien_Object {

		public $isMatched = true;

		function makePayment()
		{
			// Init $orderValues Array
			$orderValues = array();
			$quote       = Mage::getSingleton( 'checkout/session' )->getQuote();

			$Billing  = $quote->getBillingAddress();
			$Shipping = $quote->getShippingAddress();

			$payment = Mage::app()->getRequest()->getPost( 'payment' );

			$methodname = $payment['method'] == 'billmateinvoice' ? 'billmateinvoice' : 'billmatepartpayment';
			$k          = Mage::helper( 'billmateinvoice' )->getBillmate( true, false );

			$customerId = (!Mage::getSingleton('customer/session')->getCustomer()->getId()) ? $quote->getCustomerId() : Mage::getSingleton('customer/session')->getCustomer()->getId();
			$countryCode      = Mage::getStoreConfig( 'general/country/default', Mage::app()->getStore() );
			$storeCountryIso2 = Mage::getModel( 'directory/country' )->loadByCode( $countryCode )->getIso2Code();
			$storeLanguage    = Mage::app()->getLocale()->getLocaleCode();


			$ship_address = $bill_address = array();
			$shipp        = $Shipping->getStreet();

			$bill = $Billing->getStreet();

			foreach ( $bill_address as $key => $col )
			{
				$bill_address[ $key ] = mb_convert_encoding( $col, 'UTF-8', 'auto' );
			}
			foreach ( $ship_address as $key => $col )
			{
				$ship_address[ $key ] = mb_convert_encoding( $col, 'UTF-8', 'auto' );
			}


			$baseCurrencyCode    = Mage::app()->getStore()->getBaseCurrencyCode();
			$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
			$_directory          = Mage::helper( 'directory' );


			$store = Mage::app()->getStore();

			$orderValues['PaymentData'] = array(
				'method'       => 1,
				'currency'     => $currentCurrencyCode,
				'country'      => $storeCountryIso2,
				'orderid' => ($quote->getReservedOrderId()) ? $quote->getReservedOrderId() : (string)time(),
				'autoactivate' => 0,
				'language'     => BillmateCountry::fromLocale( $storeLanguage ),
				'logo' => (strlen(Mage::getStoreConfig('billmate/settings/logo')) > 0) ? Mage::getStoreConfig('billmate/settings/logo') : ''


			);
			$orderValues['PaymentInfo'] = array(
				'paymentdate'   => (string) date( 'Y-m-d' ),
				'yourreference' => $Billing->getFirstname() . ' ' . $Billing->getLastname(),
				'delivery'      => $Shipping->getShippingDescription(),

			);

			$orderValues['Customer']            = array(
				'nr'  => $customerId,
				'pno' => ( empty( $payment[ $methodname . '_pno' ] ) ) ? $payment['person_number'] : $payment[ $methodname . '_pno' ]
			);
			$orderValues['Customer']['Billing'] = array(
				'firstname' => $Billing->getFirstname(),
				'lastname'  => $Billing->getLastname(),
				'company'   => $Billing->getCompany(),
				'street'    => $bill[0],
				'street2'   => isset( $bill[1] ) ? $bill[1] : '',
				'zip'       => $Billing->getPostcode(),
				'city'      => $Billing->getCity(),
				'country'   => $Billing->getCountryId(),
				'phone'     => $Billing->getTelephone(),
				'email'     => $Billing->email
			);

			$orderValues['Customer']['Shipping'] = array(
				'firstname' => $Shipping->getFirstname(),
				'lastname'  => $Shipping->getLastname(),
				'company'   => $Shipping->getCompany(),
				'street'    => $shipp[0],
				'street2'   => isset( $shipp[1] ) ? $shipp[1] : '',
				'zip'       => $Shipping->getPostcode(),
				'city'      => $Shipping->getCity(),
				'country'   => $Shipping->getCountryId(),
				'phone'     => $Shipping->getTelephone()
			);

			// Create Array to save ParentId when bundle is fixed prised
			$bundleArr     = array();
			$totalValue    = 0;
			$totalTax      = 0;
			$discountAdded = false;
			$discountValue = 0;
			$configSku     = false;
			$discounts     = array();
			foreach ( $quote->getAllItems() as $_item )
			{
				// Continue if bundleArr contains item parent id, no need for get price then.
				if ( in_array( $_item->getParentItemId(), $bundleArr ) )
				{
					continue;
				}
				$request    = Mage::getSingleton( 'tax/calculation' )->getRateRequest( null, null, null, $store );
				$taxclassid = $_item->getProduct()->getData( 'tax_class_id' );
				// If Product type == bunde and if bundle price type == fixed
				if ( $_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 1 )
				{
					// Set bundle id to $bundleArr
					$bundleArr[] = $_item->getId();

				}
				if ( $_item->getProductType() == 'configurable' )
				{
					$configSku = $_item->getSku();
					$cp        = $_item->getProduct();
					$sp        = Mage::getModel( 'catalog/product' )->loadByAttribute( 'sku', $_item->getSku() );

					$price = $_item->getCalculationPrice();
					$percent        = Mage::getSingleton( 'tax/calculation' )
					                      ->getRate( $request->setProductClassId( $taxclassid ) );
					$discount       = 0.0;
					$discountAmount = 0;
					if ( $_item->getDiscountPercent() != 0 )
					{
						$discountAdded = true;
						$discount      = $_item->getDiscountPercent();
						$marginal      = ( $percent / 100 ) / ( 1 + ( $percent / 100 ) );

						$discountAmount = $_item->getDiscountAmount();
						// $discountPerArticle without VAT
						$discountAmount = $discountAmount - ( $discountAmount * $marginal );

					}
					$total                     = ( $discountAdded ) ? (int) round( ( ( $price * $_item->getQty() - $discountAmount ) * 100 ) ) : (int) round( $price * 100 ) * $_item->getQty();
					$orderValues['Articles'][] = array(
						'quantity'   => (int) $_item->getQty(),
						'artnr'      => $_item->getProduct()->getSKU(),
						'title'      => addslashes($cp->getName() . ' - ' . $sp->getName()),
						// Dynamic pricing set price to zero
						'aprice'     => (int) round( $price * 100, 0 ),
						'taxrate'    => (float) $percent,
						'discount'   => $discount,
						'withouttax' => $total

					);

					$temp = $total;
					$totalValue += $temp;
					$totalTax += $temp * ( $percent / 100 );
					if ( isset( $discounts[ $percent ] ) )
					{
						$discounts[ $percent ] += $temp;
					}
					else
					{
						$discounts[ $percent ] = $temp;
					}

				}
				if ( $_item->getSku() == $configSku )
				{


					continue;
				}

				// If Product type == bunde and if bundle price type == dynamic
				if ( $_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 0 )
				{

					$percent                   = Mage::getSingleton( 'tax/calculation' )
					                                 ->getRate( $request->setProductClassId( $taxclassid ) );
					$orderValues['Articles'][] = array(
						'quantity'   => (int) $_item->getQty(),
						'artnr'      => $_item->getProduct()->getSKU(),
						'title'      => addslashes($_item->getName()),
						// Dynamic pricing set price to zero
						'aprice'     => (int) 0,
						'taxrate'    => (float) $percent,
						'discount'   => 0.0,
						'withouttax' => (int) 0

					);


					// Else the item is not bundle and dynamic priced
				}
				else
				{
					$temp    = 0;
					$percent = Mage::getSingleton( 'tax/calculation' )
					               ->getRate( $request->setProductClassId( $taxclassid ) );


					// For tierPrices to work, we need to get calculation price not the price on the product.
					// If a customer buys many of a kind and get a discounted price, the price will bee on the quote item.

					$price = $_item->getCalculationPrice();
					//Mage::throwException( 'error '.$_regularPrice.'1-'. $_finalPrice .'2-'.$_finalPriceInclTax.'3-'.$_price);
					$discount       = 0.0;
					$discountAmount = 0;
					if ( $_item->getDiscountPercent() != 0 )
					{
						$discountAdded = true;
						$discount      = $_item->getDiscountPercent();
						$marginal      = ( $percent / 100 ) / ( 1 + ( $percent / 100 ) );

						$discountAmount = $_item->getDiscountAmount();
						// $discountPerArticle without VAT
						$discountAmount = $discountAmount - ( $discountAmount * $marginal );

					}
					$parentItem = $_item->getParentItem();
					if($parentItem)
						$qty = $parentItem->getQty();
					else
						$qty = $_item->getQty();


					$total = ($discountAdded) ? (int) round((($price * $qty - $discountAmount)* 100)) : (int)round($price*100) * $qty;


					$orderValues['Articles'][] = array(
						'quantity'   => (int)$qty,
						'artnr'      => $_item->getProduct()->getSKU(),
						'title'      => addslashes($_item->getName()),
						'aprice'     => (int) round( $price * 100, 0 ),
						'taxrate'    => (float) $percent,
						'discount'   => $discount,
						'withouttax' => $total

					);
					$temp                      = $total;
					$totalValue += $temp;
					$totalTax += $temp * ( $percent / 100 );
					if ( isset( $discounts[ $percent ] ) )
					{
						$discounts[ $percent ] += $temp;
					}
					else
					{
						$discounts[ $percent ] = $temp;
					}
				}
			}

			$totals = Mage::getSingleton( 'checkout/session' )->getQuote()->getTotals();

			if ( isset( $totals['discount'] ) && ! $discountAdded )
			{
				$totalDiscountInclTax = $totals['discount']->getValue();
				$subtotal             = $totalValue;
				foreach ( $discounts as $percent => $amount )
				{
					$discountPercent           = $amount / $subtotal;
					$floor                     = 1 + ( $percent / 100 );
					$marginal                  = 1 / $floor;
					$discountAmount            = $discountPercent * $totalDiscountInclTax;
					$orderValues['Articles'][] = array(
						'quantity'   => (int) 1,
						'artnr'      => 'discount',
						'title'      => Mage::helper( 'payment' )
						                    ->__( 'Discount' ) . ' ' . Mage::helper( 'billmateinvoice' )
						                                                   ->__( '%s Vat', $percent ),
						'aprice'     => round( ( $discountAmount * $marginal ) * 100 ),
						'taxrate'    => (float) $percent,
						'discount'   => 0.0,
						'withouttax' => round( ( $discountAmount * $marginal ) * 100 ),

					);
					$totalValue += ( 1 * round( $discountAmount * $marginal * 100 ) );
					$totalTax += ( 1 * round( ( $discountAmount * $marginal ) * 100 ) * ( $percent / 100 ) );
				}
			}


			$rates = $quote->getShippingAddress()->getShippingRatesCollection();
			if ( ! empty( $rates ) )
			{
				if ( $Shipping->getBaseShippingTaxAmount() > 0 )
				{
					$taxCalculation = Mage::getModel('tax/calculation');
					$request = $taxCalculation->getRateRequest($Shipping,$Billing,null,$quote->getStore());
					$taxRateId = Mage::getStoreConfig('tax/classes/shipping_tax_class',$quote->getStore());
					$rate = $taxCalculation->getRate($request->setProductClassId($taxRateId));
				}
				else
				{
					$rate = 0;
				}
				if($Shipping->getShippingAmount() > 0) {
					$orderValues['Cart']['Shipping'] = array(
						'withouttax' => $Shipping->getShippingAmount() * 100,
						'taxrate' => (int)$rate
					);
					$totalValue += $Shipping->getShippingAmount() * 100;
					$totalTax += ($Shipping->getShippingAmount() * 100) * ($rate / 100);
				}
			}


			if ( $methodname == 'billmateinvoice' )
			{
				$invoiceFee = Mage::getStoreConfig( 'payment/billmateinvoice/billmate_fee' );
				$invoiceFee = Mage::helper( 'billmateinvoice' )->replaceSeparator( $invoiceFee );

				//if(Mage::getStoreConfig('payment/billmateinvoice/tax_class')){
				$feeinfo = Mage::helper( 'billmateinvoice' )
				               ->getInvoiceFeeArray( $invoiceFee, $Shipping, $quote->getCustomerTaxClassId() );
				//}
				if ( ! empty( $invoiceFee ) && $invoiceFee > 0 )
				{
					// $invoiceFee = $_directory->currencyConvert($invoiceFee,$baseCurrencyCode,$currentCurrencyCode);

					$orderValues['Cart']['Handling'] = array(
						'withouttax' => round($Shipping->getFeeAmount() * 100),
						'taxrate'    => $feeinfo['rate']
					);
					$totalValue += $Shipping->getFeeAmount() * 100;
					$totalTax += ( $Shipping->getFeeAmount() * 100 ) * ( $feeinfo['rate'] / 100 );
				}
			}
			$round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);


			$orderValues['Cart']['Total'] = array(
				'withouttax' => round($totalValue),
				'tax'        => round($totalTax),
				'rounding'   => round($round),
				'withtax'    => round($totalValue +  $totalTax +  $round)
			);
			$result                       = $k->addPayment( $orderValues );

			if ( isset( $result['code'] ) )
			{

				switch($result['code']){
					case 2401:
					case 2402:
					case 2403:
					case 2404:
					case 2405:
						$this->init();
						echo Mage::app()->getLayout()->createBlock('billmateinvoice/changeaddress')->toHtml();
						die();
						break;
					default:
						Mage::throwException( utf8_encode( $result['message'] ) );
				}


			}
			else
			{
				$session = Mage::getSingleton( 'core/session', array( 'name' => 'frontend' ) );
				$session->setData( 'billmateinvoice_id', $result['number'] );
				$session->setData( 'billmateorder_id', $result['orderid'] );
				return $result['number'];
			}
		}


		function init( $update = false )
		{

			$payment = Mage::app()->getRequest()->getPost( 'payment' );

			$methodname = $payment['method'];
			$k          = Mage::helper('billmateinvoice')->getBillmate( true, false );
			$quote      = Mage::getSingleton( 'checkout/session' )->getQuote();
			$Billing    = $quote->getBillingAddress();
			$Shipping   = $quote->getShippingAddress();

			$pno = ( empty( $payment[ $methodname . '_pno' ] ) ) ? $payment['person_number'] : $payment[ $methodname . '_pno' ];

			try
			{
				$addr = $k->getAddress( array( 'pno' => $pno ) );

				if ( ! is_array( $addr ) )
				{
					Mage::throwException( Mage::helper( 'payment' )->__( utf8_encode( $addr ) ) );
				}

				if ( isset( $addr['code'] ) )
				{
					Mage::throwException( $addr['message'] );

				}
				foreach ( $addr as $key => $col )
				{
					$addr[ $key ] = mb_convert_encoding( $col, 'UTF-8', 'auto' );
				}

				if ( empty( $addr['firstname'] ) )
				{
					$this->firstname = $Billing->getFirstname();
					$this->lastname  = $Billing->getLastname();
					$this->company   = $addr['company'];
				}
				else
				{
					$this->firstname = $addr['firstname'];
					$this->lastname  = $addr['lastname'];
					$this->company   = '';
				}
				$this->street       = $addr['street'];
				$this->postcode     = $addr['zip'];
				$this->city         = $addr['city'];

				if(Mage::getSingleton('customer/session')->isLoggedIn())
					$this->telephone = $Billing->getTelephone();
				$this->country      = $addr['country'];
				$this->country_name = Mage::getModel( 'directory/country' )->loadByCode( $this->country )->getName();

			}
			catch ( Exception $ex )
			{
				Mage::logException( $ex );

				die( 'alert("' . utf8_encode( $ex->getMessage() )/*mb_convert_encoding(strip_tags( str_replace("<br> ",'\n\n',preg_replace_callback('/[\\\\]([a-f0-9]{2})/i','hex2dec',$ex->getMessage())) ),'UTF-8','auto')*/ . '");' );
			}

			$fullname = $Billing->getFirstname() . ' ' . $Billing->getLastname() . ' ' . $Billing->getCompany();
			if ( empty( $addr['firstname'] ) )
			{
				$apiName = $Billing->getFirstname() . ' ' . $Billing->getLastname() . ' ' . $Billing->getCompany();
			}
			else
			{
				$apiName = $addr['firstname'] . ' ' . $addr['lastname'];
			}
			$billingStreet = $Billing->getStreet();

			$addressNotMatched = ! isEqual( $addr['street'], $billingStreet[0] ) ||
			                     ! isEqual( $addr['zip'], $Billing->getPostcode() ) ||
			                     ! isEqual( $addr['city'], $Billing->getCity() ) ||
			                     ! isEqual( strtolower( $addr['country'] ), $Billing->getCountryId() );


			$shippingStreet = $Shipping->getStreet();

			$shippingAndBilling = ! match_usernamevp( $fullname, $apiName ) ||
			                      ! isEqual( $shippingStreet[0], $billingStreet[0] ) ||
			                      ! isEqual( $Shipping->getPostcode(), $Billing->getPostcode() ) ||
			                      ! isEqual( $Shipping->getCity(), $Billing->getCity() ) ||
			                      ! isEqual( $Shipping->getCountryId(), $Billing->getCountryId() );
			if ( $addressNotMatched || $shippingAndBilling )
			{
				$this->isMatched = false;
			}
			if ( $update )
			{

				$this->isMatched = true;
				$data            = array(
					'firstname'  => $this->firstname,
					'lastname'   => $this->lastname,
					'street'     => $this->street,
					'company'    => $this->company,
					'postcode'   => $this->postcode,
					'city'       => $this->city,
					'country_id' => strtoupper( $this->country ),
					'telephone' => $Billing->getTelephone()
				);

				if ( Mage::getStoreConfig( 'firecheckout/general/enabled' ) )
				{
					$data['person_number'] = $pno;
				}
				$Billing->addData( $data )->save();
				$Shipping->addData( $data )->save();


				Mage::getModel( 'checkout/session' )->loadCustomerQuote();
			}

		}
	}