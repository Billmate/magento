<?php
class Billmate_PartPayment_Helper_data extends Mage_Core_Helper_Abstract{
    function getBillmate($ssl = true, $debug = false, $store,$eid = false, $secret = false,$testmode = false){
        if(!defined('BILLMATE_CLIENT')) define('BILLMATE_CLIENT','MAGENTO:2.1.9');
        if(!defined('BILLMATE_SERVER')) define('BILLMATE_SERVER','2.1.7');
		$store = $store ? $store : Mage::app()->getStore()->getId();
        $lang = explode('_',Mage::getStoreConfig('general/locale/code'));
        //if(!defined('BILLMATE_LANGUAGE'))define('BILLMATE_LANGUAGE',$lang[0]);
        require_once Mage::getBaseDir('lib').'/Billmate/Billmate.php';
        require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';
        //include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpc.inc");
        //include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpcs.inc");


        $eid = $eid ? $eid : Mage::getStoreConfig('billmate/credentials/eid',$store);
        $secret= $secret ? $secret : Mage::getStoreConfig('billmate/credentials/secret',$store);
        $testmode= $testmode ? $testmode : (boolean)Mage::getStoreConfig('payment/billmatepartpayment/test_mode',$store);
        return new Billmate($eid, $secret, $ssl, $testmode,$debug);
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

    /**
     * Check if Paymentplans is more than a week old.
     * If they are refresh.
     *
     * @throws Exception
     */
    public function checkPclasses($frondend = false){
        $collection = Mage::getModel('partpayment/pclass')->getCollection();
        $collection->addFieldToFilter('store_id',($frondend) ? Mage::app()->getStore()->getId() :Mage::helper('partpayment')->getStoreIdForConfig());
        $first = $collection->getFirstItem();

        if($collection->getSize() == 0 || (strtotime($first->getCreated() <= strtotime('-1 week')))){
            $collectionPclass = Mage::getModel('partpayment/pclass')->getCollection();
            $collectionPclass->addFieldToFilter('store_id',($frondend) ? Mage::app()->getStore()->getId() :Mage::helper('partpayment')->getStoreIdForConfig());
            if($collection->getSize() > 0) {
                foreach ($collectionPclass as $row) {
                    $row->delete();
                }
            }

            // Fetch new Pclasses
            $countries = explode(',',Mage::getStoreConfig('payment/billmatepartpayment/countries'));
            $lang = explode('_',Mage::getStoreConfig('general/locale/code'));
            $eid = (int)Mage::getStoreConfig('billmate/credentials/eid');
            $secret=(float)Mage::getStoreConfig('billmate/credentials/secret');
            $testMode=(boolean)Mage::getStoreConfig('payment/billmatepartpayment/test_mode');


            foreach($countries as $country)
                $this->savePclasses($eid, $secret, $country, $testMode,$lang[0]);

            return;
        }
        return;

    }
    public function getStoreIdForConfig()
    {
        if(strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore()))
        {
            $store_id = Mage::getModel('core/store')->load($code)->getId();
        }
        elseif(strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())){

            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        }
        else{
            $store_id = 0;
        }


        return $store_id;
    }

    /**
     * Save Paymentplans
     * @param $eid
     * @param $secret
     * @param $countrycode
     * @param $testmode
     * @param $lang
     */
    function savePclasses($eid, $secret, $countrycode, $testmode ,$lang, $store = false){
    

        $store_id =$store ? $store : Mage::app()->getStore()->getId();

        $eid = (int)$eid;
        $secret=(float)$secret;
        $ssl=true;
        $debug = false;
        $billmate = $this->getBillmate($ssl,$debug,$store_id,$eid,$secret);

		switch ($countrycode) {
			// Sweden
			case 'SE':
				$country = 209;
				$language = 'sv';
				$encoding = 2;
				$currency = 'SEK';
				break;
			// Finland
			case 'FI':
				$country = 73;
				$language = 'fi';
				$encoding = 4;
				$currency = 'EUR';
				break;
			// Denmark
			case 'DK':
				$country = 59;
				$language = 'da';
				$encoding = 5;
				$currency = 'DKK';
				break;
			// Norway	
			case 'NO':
				$country = 164;
				$language = 'no';
				$encoding = 3;
				$currency = 'NOK';

				break;
			// Germany	
			case 'DE':
				$country = 81;
				$language = 'de';
				$encoding = 6;
				$currency = 'EUR';
				break;
			// Netherlands															
			case 'NL':
				$country = 154;
				$language = 'nl';
				$encoding = 7;
				$currency = 'EUR';
				break;
		}
        
        $additionalinfo['PaymentData'] = array(
	        "currency"=>$currency,//SEK
	        "country"=>strtolower($countrycode),//Sweden
	        "language"=>$lang,//Swedish
        );

        $data = $billmate->getPaymentplans($additionalinfo);
        if(!isset($data['code'])) {

            array_walk($data, array($this, 'correct_lang_billmate'));
            foreach ($data as $_row) {
                $_row['eid'] = $eid;
                $_row['country_code'] = (string)$countrycode;
                $_row['paymentplanid'] = (string)$_row['paymentplanid'];
                $_row['currency'] = (string)$_row['currency'];
                $_row['language'] = (string)$_row['language'];
                $_row['country'] = (string)$_row['country'];
                $_row['store_id'] = ($store != false) ? $store :$store_id;

                Mage::getModel('partpayment/pclass')
                    ->addData($_row)
                    ->save();
            }
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
	   $isoCode2 =  Mage::getModel('directory/country')->load($address->getCountryId())->getIso2Code();
	   $collection = Mage::getModel('partpayment/pclass')
	   		   ->getCollection()
	   		   ->addFieldToFilter('country', $isoCode2 )
                ->addFieldToFilter('store_id',Mage::app()->getStore()->getId());

        if($collection->getSize() == 0) {
            $collection = Mage::getModel('partpayment/pclass')
                ->getCollection()
                ->addFieldToFilter('country', $isoCode2)
                ->addFieldToFilter('store_id', 0);
        }
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
	
					$monthly_fee = $pclass->getHandlingfee();
					$start_fee = $pclass->getStartfee();
	
					$sum += $start_fee;
	
					$base = ($pclass->getType() == 1);
	
					$minimum_payment = ($pclass->getType() === 1) ? $this->getLowestPaymentAccount($isoCode3) : 0;
	
					if ($pclass->getNbrofmonths() == 0) {
						$payment = $sum;
					} elseif ((int)$pclass->getInterestrate() == 0) {
						$payment = $sum / $pclass->getNbrofmonths();
					} else {
						// Because Interest rate is in decimal for example 0.12 no need to multiply by 100
						$interest_rate = $pclass->getInterestrate() / 12;
						$payment = $sum * $interest_rate / (1 - pow((1 + $interest_rate), -$pclass->getNbrofmonths()));
					}
	
					$payment += $monthly_fee;
	
					$balance = $sum;
					$pay_data = array();
	
					$months = $pclass->getNbrofmonths();
					
					while (($months != 0) && ($balance > 0.01)) {
						// Because Interest rate is in decimal for example 0.12 no need to multiply by 100
						$interest = $balance * $pclass->getInterestrate()/ 12;
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

					$monthly_cost = round(isset($pay_data[0]) ? ($pay_data[0]) : 0, 0);
	
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
	
			$payment_option_temp['monthly_cost'] = $monthly_cost;
            $payment_option_temp['nbrofmonths'] = $pclass->getNbrofmonths();
			$payment_option_temp['pclass_id'] = $pclass->getPaymentplanid();
			$payment_option_temp['months'] = $pclass->getNbrofmonths();
			$payment_option_temp['description'] = $pclass->getDescription();
            $payment_option[] = $payment_option_temp;
		}
		
		return $payment_option;
    }
    function getLowPclass($total){
        $method = array();
        $this->checkPclasses(true);
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
			$title = ' '.Mage::helper('partpayment')->__('from').' '.$price.' '.$currency.' / '. Mage::helper('partpayment')->__('month');
		}
		return $title;
    }
    function correct_lang_billmate(&$item, $index){
        //$keys = array('paymentplanid', 'description','nbrofmonths', 'startfee','handlingfee','interestrate', 'minamount', 'country', 'type', 'expirydate','currency', 'maxamount','language' );


        //$item = array_combine( $keys, $item );
        $item['startfee'] = $item['startfee'] / 100;
        $item['handlingfee'] = $item['handlingfee'] / 100;
        $item['interestrate'] = $item['interestrate'] / 100;
        $item['minamount'] = $item['minamount'] / 100;
        $item['maxamount'] = $item['maxamount'] / 100;
    }
}
