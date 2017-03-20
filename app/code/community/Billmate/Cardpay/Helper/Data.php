<?php
class Billmate_Cardpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    function getBillmate($ssl = true, $debug = false ){
        if(!defined('BILLMATE_CLIENT')) define('BILLMATE_CLIENT','MAGENTO:3.0.3');
        if(!defined('BILLMATE_SERVER')) define('BILLMATE_SERVER','2.1.9');
        $lang = explode('_',Mage::getStoreConfig('general/locale/code'));
        if(!defined('BILLMATE_LANGUAGE'))define('BILLMATE_LANGUAGE',$lang[0]);
        require_once Mage::getBaseDir('lib').'/Billmate/Billmate.php';
        require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';

        $eid = Mage::getStoreConfig('billmate/credentials/eid');
        $secret=Mage::getStoreConfig('billmate/credentials/secret');
        $testmode =(float)Mage::getStoreConfig('payment/billmatecardpay/test_mode');
        
        return new Billmate($eid, $secret, $ssl, $testmode, $debug);
    }
    
}