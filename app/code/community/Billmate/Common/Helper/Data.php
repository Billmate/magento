<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-28
 * Time: 17:48
 */
require_once Mage::getBaseDir('lib').'/Billmate/Billmate.php';
require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';

class  Billmate_Common_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getBillmate()
    {
        if(!defined('BILLMATE_CLIENT')) define('BILLMATE_CLIENT','MAGENTO:2.0');
        $lang = explode('_',Mage::getStoreConfig('general/locale/code'));
        if(!defined('BILLMATE_LANGUAGE'))define('BILLMATE_LANGUAGE',$lang[0]);
        $eid = Mage::getStoreConfig('billmate/credentials/eid');
        $secret = Mage::getStoreConfig('billmate/credentials/secret');
        return new Billmate($eid, $secret, true, false,false);
    }

    public function verifyCredentials($eid,$secret)
    {

        $billmate = new Billmate($eid, $secret, true, false,false);

        $additionalinfo['PaymentData'] = array(
            "currency"=> 'SEK',//SEK
            "country"=> 'se',//Sweden
            "language"=> 'sv',//Swedish
        );

        $result = $billmate->GetPaymentPlans($additionalinfo);
        if(isset($result['code']) && $result['code'] == '9013'){
            return false;
        }
        return true;

    }

    public function getAddress($pno)
    {
        $billmate = $this->getBillmate();

        $values = array(
            'pno' => $pno
        );

        $result = $billmate->GetAddress($values);
        if(!isset($result['code'])){

            $lang = explode('_',Mage::getStoreConfig('general/locale/code'));
            $countryIso = '';
            if(isset($result['country'])) {
                $countryCollection = Mage::getModel('directory/country')->getCollection();
                foreach ($countryCollection as $country) {
                    $countryName = $country->getName();
                    if($lang[0] != 'sv') {

                        $this->_locale = new Zend_Locale('en_US');
                        $countryName = $this->_locale->getTranslation($country->getId(), 'country', 'en_US');
                        Mage::log('countryName' . $countryName);
                    }
                    if (strtolower($result['country']) == strtolower($countryName)) {
                        $countryIso = $country->getIso2Code();
                        break;
                    }
                }
                $countryCollection = null;
                $result['country'] = $countryIso;
            }
            return $result;
        }
        else {
            return false;
        }
    }

}
