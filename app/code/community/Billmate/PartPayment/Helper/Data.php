<?php
class Billmate_PartPayment_Helper_data extends Mage_Core_Helper_Abstract{
    function getBillmate($ssl = true, $debug = false ){

        require_once Mage::getBaseDir('lib').'/Billmate/BillMate.php';
        require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';
        include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpc.inc");
        include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpcs.inc");


        $eid = (int)Mage::getStoreConfig('payment/partpayment/eid');
        $secret=(float)Mage::getStoreConfig('payment/partpayment/secret');
        $testmode=(boolean)Mage::getStoreConfig('payment/partpayment/test_mode');
        return new Billmate($eid, $secret, $ssl, $debug, $testmode);
    }
    private function getLowestPaymentAccount($country) {
        switch ($country) {
            case 'SWE':
                $amount = 50.0;
                break;
            case 'NOR':
                $amount = 95.0;
                break;
            case 'FIN':
                $amount = 8.95;
                break;
            case 'DNK':
                $amount = 89.0;
                break;
            case 'DEU':
            case 'NLD':
                $amount = 6.95;
                break;
            default:
                $log = new Log('billmate_account.log');
                $log->write('Unknown country ' . $country);
                
				$amount = NULL;
                break;
        }

        return $amount;
    }
    function savePclasses($eid, $secret, $countrycode, $testmode ){
    
        require_once Mage::getBaseDir('lib').'/Billmate/BillMate.php';
        require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';
        include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpc.inc");
        include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpcs.inc");


        $eid = (int)$eid;
        $secret=(float)$secret;
        $ssl=true;
        $debug = false;
        $billmate = new Billmate($eid, $secret, $ssl, $debug, $testmode);

		switch ($countrycode) {
			// Sweden
			case 'SE':
				$country = 209;
				$language = 138;
				$encoding = 2;
				$currency = 0;
				break;
			// Finland
			case 'FI':
				$country = 73;
				$language = 37;
				$encoding = 4;
				$currency = 2;
				break;
			// Denmark
			case 'DK':
				$country = 59;
				$language = 27;
				$encoding = 5;
				$currency = 3;
				break;
			// Norway	
			case 'NO':
				$country = 164;
				$language = 97;
				$encoding = 3;
				$currency = 1;

				break;
			// Germany	
			case 'DE':
				$country = 81;
				$language = 28;
				$encoding = 6;
				$currency = 2;
				break;
			// Netherlands															
			case 'NL':
				$country = 154;
				$language = 101;
				$encoding = 7;
				$currency = 2;
				break;
		}
        
        $additionalinfo = array(
	        "currency"=>$currency,//SEK
	        "country"=>$country,//Sweden
	        "language"=>$language,//Swedish
        );

        $data = $billmate->FetchCampaigns($additionalinfo);

        array_walk($data, array($this,'correct_lang_billmate'));

//        $model = Mage::getModel('partpayment/pclass');
        foreach($data as $_row ){
            $_row['eid'] = $eid;
            $_row['country_code'] = (string)$countrycode ;
            Mage::getModel('partpayment/pclass')
            ->addData($_row)
            ->save();
        }

    }
    function getPlclass($total){

	   $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
       $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
       $_directory = Mage::helper('directory');

       $payment_option = array();
       $quote = Mage::getSingleTon('checkout/session')->getQuote();
	   $address = $quote->getShippingAddress();
	   $isoCode3 =  'SWE';//Mage::getModel('directory/country')->load($address->getCountryId())->getIso3Code();
	   $isoCode2 =  'SE'; //Mage::getModel('directory/country')->load($address->getCountryId())->getIso2Code();
	   $collection = Mage::getModel('partpayment/pclass')
	   		   ->getCollection()
	   		   ->addFieldToFilter('country_code', $isoCode2 );

		// Maps countries to currencies
		$country_to_currency = array(
			'NOR' => 'NOK',
			'SWE' => 'SEK',
			'FIN' => 'EUR',
			'DNK' => 'DKK',
			'DEU' => 'EUR',
			'NLD' => 'EUR'
		);

		$country_to_currency = array(
			'NOR' => 'NOK',
			'SWE' => 'SEK',
			'FIN' => 'EUR',
			'DNK' => 'DKK',
			'DEU' => 'EUR',
			'NLD' => 'EUR'
		);
		

		foreach ($collection as $pclass) {
	
			// 0 - Campaign
			// 1 - Account
			// 2 - Special
			// 3 - Fixed
			if (!in_array($pclass->getType() , array(0, 2, 1, 3, 4))) {
				continue;
			}
	
			if ($pclass->getType() == 2) {
				$monthly_cost = -1;
			} else {
				if ($total < $pclass->getMinamount() || ($total > $pclass->getMaxamount() && $pclass->getMaxamount() > 0)) {
					continue;
				}
	
				if ($pclass->getType() == 3) {
					continue;
				} else {
					$sum = $total;
	
					$lowest_payment = $this->getLowestPaymentAccount($isoCode3);
					$monthly_cost = 0;
	
					$monthly_fee = $pclass->getInvoicefee();
					$start_fee = $pclass->getStartfee();
	
					$sum += $start_fee;
	
					$base = ($pclass->getType() == 1);
	
					$minimum_payment = ($pclass->getType() === 1) ? $this->getLowestPaymentAccount($isoCode3) : 0;
	
					if ($pclass->getMonths() == 0) {
						$payment = $sum;
					} elseif ($pclass->getInterestrate() == 0) {
						$payment = $sum / $pclass->getMonths();
					} else {
						$interest_rate = $pclass->getInterestrate() / (100.0 * 12);
						
						$payment = $sum * $interest_rate / (1 - pow((1 + $interest_rate), -$pclass->getMonths()));
					}
	
					$payment += $monthly_fee;
	
					$balance = $sum;
					$pay_data = array();
	
					$months = $pclass->getMonths();
					
					while (($months != 0) && ($balance > 0.01)) {
						$interest = $balance * $pclass->getInterestrate() / (100.0 * 12);
						$new_balance = $balance + $interest + $monthly_fee;
	
						if ($minimum_payment >= $new_balance || $payment >= $new_balance) {
							$pay_data[] = $new_balance;
							break;
						}
	
						$new_payment = max($payment, $minimum_payment);
						
						if ($base) {
							$new_payment = max($new_payment, $balance / 24.0 + $monthly_fee + $interest);
						}
	
						$balance = $new_balance - $new_payment;
						
						$pay_data[] = $new_payment;
						
						$months -= 1;
					}
	
					$monthly_cost = round(isset($pay_data[0]) ? ($pay_data[0]) : 0, 2);
	
					if ($monthly_cost < 0.01) {
						continue;
					}
	
					if ($pclass->getType() == 1 && $monthly_cost < $lowest_payment) {
						$monthly_cost = $lowest_payment;
					}
	
					if ($pclass->getType() == 0 && $monthly_cost < $lowest_payment) {
						continue;
					}
				}
			}
			$monthly_cost = $_directory->currencyConvert($monthly_cost,$baseCurrencyCode,$currentCurrencyCode);
	
			$payment_option[$pclass['pclassid']]['monthly_cost'] = round($monthly_cost,2);
			$payment_option[$pclass['pclassid']]['pclass_id'] = $pclass->getPclassid();
			$payment_option[$pclass['pclassid']]['months'] = $pclass->getMonths();
			$payment_option[$pclass['pclassid']]['description'] = $pclass->getDescription();
		}
		
		return $payment_option;
    }
    function getLowPclass($total){
        $method = array();
	   		   		
		$payment_option = $this->getPlclass($total);
		
		$status = true;
		if (!$payment_option) {
			$status = false;
		}
		
		$sort_order = array(); 
		  
		foreach ($payment_option as $key => $value) {
			$sort_order[$key] = $value['monthly_cost'];
		}
	
		array_multisort($sort_order, SORT_ASC, $payment_option);	
		$method = array();
		$title = '';
		if ($status) {
			$currency = Mage::app()->getStore()->getCurrentCurrencyCode(); 
			$price = round(Mage::helper('core')->currency($payment_option[0]['monthly_cost'], false, true),2);
			$title = ' '.$price.' '.$currency.' / per '. Mage::helper('payment')->__('Month');
		}
		return $title;
    }
    function correct_lang_billmate(&$item, $index){
        $keys = array('pclassid', 'description','months', 'startfee','invoicefee','interestrate', 'minamount', 'country', 'type', 'expire', 'maxamount' );
        $item[1] = utf8_encode($item[1]);
        if( !is_array($item ) ){
            Mage::log('Not and array');
            Mage::log($item);
        }
        $item = array_combine( $keys, $item );
        $item['startfee'] = $item['startfee'] / 100;
        $item['invoicefee'] = $item['invoicefee'] / 100;
        $item['interestrate'] = $item['interestrate'] / 100;
        $item['minamount'] = $item['minamount'] / 100;
        $item['maxamount'] = $item['maxamount'] / 100;
    }
}
