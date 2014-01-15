<?php
class Billmate_Cardpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    function getBillmate($ssl = true, $debug = false ){

        require_once Mage::getBaseDir('lib').'/Billmate/BillMate.php';
        require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';
        include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpc.inc");
        include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpcs.inc");


        $eid = (int)Mage::getStoreConfig('payment/billmatecardpay/eid');
        $secret=(float)Mage::getStoreConfig('payment/billmatecardpay/secret');
        $testmode =(float)Mage::getStoreConfig('payment/billmatecardpay/test_mode');
        
        return new Billmate($eid, $secret, $ssl, $debug, $testmode);
    }
    
}