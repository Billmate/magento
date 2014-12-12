<?php
class Billmate_Bankpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    function getBillmate($ssl = true, $debug = false ){

        require_once Mage::getBaseDir('lib').'/Billmate/Billmate.php';
        require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';


        $eid = (int)Mage::getStoreConfig('payment/billmatebankpay/eid');
        $secret=(float)Mage::getStoreConfig('payment/billmatebankpay/secret');
        $testmode =(float)Mage::getStoreConfig('payment/billmatebankpay/test_mode');
        
        return new Billmate($eid, $secret, $ssl, $testmode,$debug);
    }
    
}