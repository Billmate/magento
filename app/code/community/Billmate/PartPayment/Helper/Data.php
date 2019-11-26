<?php
class Billmate_PartPayment_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var
     */
    protected $paymentOptions;

    public function getBillmate()
    {
        return Mage::helper('billmatecommon')->getBillmate();
    }

    private function getLowestPaymentAccount($country)
    {
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
    public function checkPclasses($frondend = false)
    {
        $collection = Mage::getModel('partpayment/pclass')->getCollection();
        $collection->addFieldToFilter('store_id',($frondend) ? Mage::app()->getStore()->getId() :Mage::helper('partpayment')->getStoreIdForConfig());
        $first = $collection->getFirstItem();

        if($collection->getSize() == 0 || (strtotime($first->getCreated() <= strtotime('-1 week')))){
            $collectionPclass = Mage::getModel('partpayment/pclass')->getCollection();
            $collectionPclass->addFieldToFilter('store_id',($frondend) ? Mage::app()->getStore()->getId() :Mage::helper('partpayment')->getStoreIdForConfig());
            if ($collection->getSize() > 0) {
                foreach ($collectionPclass as $row) {
                    $row->delete();
                }
            }

            // Fetch new Pclasses
            $countries = explode(',',Mage::getStoreConfig('payment/billmatepartpayment/countries'));
            $lang = explode('_',Mage::getStoreConfig('general/locale/code'));

            foreach ($countries as $country) {
                $this->savePclasses($country, $lang[0]);
            }
            return;
        }
        return;

    }

    /**
     * @return int
     */
    public function getStoreIdForConfig()
    {
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) {
            $store_id = Mage::getModel('core/store')->load($code)->getId();
        } elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) {
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        } else {
            $store_id = 0;
        }

        return $store_id;
    }

    /**
     * @param      $eid
     * @param      $secret
     * @param      $countrycode
     * @param      $testmode
     * @param      $lang
     * @param bool $store
     */
    public function savePclasses($countrycode, $lang, $store = false)
    {
        $store_id = $store ? $store : Mage::app()->getStore()->getId();
        $billmate = $this->getBillmate();

		switch ($countrycode) {
			// Sweden
			case 'SE':
				$currency = 'SEK';
				break;
			// Finland
			case 'FI':
				$currency = 'EUR';
				break;
			// Denmark
			case 'DK':
				$currency = 'DKK';
				break;
			// Norway	
			case 'NO':
				$currency = 'NOK';
				break;
			// Germany	
			case 'DE':
				$currency = 'EUR';
				break;
			// Netherlands															
			case 'NL':
				$currency = 'EUR';
				break;
		}
        
        $additionalinfo['PaymentData'] = array(
	        "currency"=>$currency,//SEK
	        "country"=>strtolower($countrycode),//Sweden
	        "language"=>$lang,//Swedish
            "totalwithtax"=> "550000",
        );

        $eid = (int)Mage::getStoreConfig('billmate/credentials/eid');
        $data = $billmate->getPaymentplans($additionalinfo);
        if (!isset($data['code'])) {

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

    /**
     * @param $total
     *
     * @return array
     */
    public function getPlclass($total)
    {
        if (!is_null($this->paymentOptions)) {
            return $this->paymentOptions;
        }

        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $_directory = Mage::helper('directory');

        $payment_option = array();
        $isoCode3 =  'SWE';
        $collection = $this->getPartialPlansCollection();

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
            $payment_option[$payment_option_temp['pclass_id']] = $payment_option_temp;
            $this->paymentOptions = $payment_option;
		}
		
		return $this->paymentOptions;
    }

    /**
     * @param $total
     *
     * @return string
     */
    public function getLowPclass($amount, $selectedOption = null)
    {
        $coreHelper = Mage::helper('core');
        $this->checkPclasses(true);

		$paymentOptions = $this->getPlclass($amount);

		if (!$paymentOptions) {
			return '';
		}

        $activeOption = current($paymentOptions);

		if ($selectedOption && isset($paymentOptions[$selectedOption])) {
            $activeOption = $paymentOptions[$selectedOption];
        }

        $currency = Mage::app()->getStore()->getCurrentCurrencyCode();
        $price = $coreHelper->currency(
            $activeOption['monthly_cost'],
            false,
            true
        );

        $title = $this->__(' %s from %s %s / month',
            $activeOption['description'],
            $price,
            $currency
        );

		return $title;
    }

    /**
     * @param $item
     * @param $index
     */
    public function correct_lang_billmate(&$item, $index)
    {
        $item['startfee'] = $item['startfee'] / 100;
        $item['handlingfee'] = $item['handlingfee'] / 100;
        $item['interestrate'] = $item['interestrate'] / 100;
        $item['minamount'] = $item['minamount'] / 100;
        $item['maxamount'] = $item['maxamount'] / 100;
    }

    public function getPartialPlansCollection()
    {
        $shippingAddress = $this->getCurrentShippingAddress();
        $isoCode2 =  Mage::getModel('directory/country')->load(
            $shippingAddress->getCountryId()
        )->getIso2Code();

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
        $collection->setOrder('nbrofmonths', 'ASC');
        return $collection;
    }

    public function getCurrentShippingAddress()
    {
        $shippingAddress = $this->getQuote()->getShippingAddress();
        if ($this->getCurrentOrder()) {
            return $this->getCurrentOrder()->getShippingAddress();
        }

        return $shippingAddress;
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    public function getCurrentOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }
}
