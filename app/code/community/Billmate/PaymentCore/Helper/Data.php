<?php
require_once Mage::getBaseDir('lib').'/Billmate/Billmate.php';
require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';

class Billmate_PaymentCore_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @param bool $ssl
     * @param bool $debug
     *
     * @return Billmate
     */
    public function getBillmate($ssl = true, $debug = false)
    {
        if(!defined('BILLMATE_CLIENT')) define('BILLMATE_CLIENT','MAGENTO:3.1.0');
        if(!defined('BILLMATE_SERVER')) define('BILLMATE_SERVER','2.1.9');

        $lang = explode('_',Mage::getStoreConfig('general/locale/code'));
        if(!defined('BILLMATE_LANGUAGE'))define('BILLMATE_LANGUAGE',$lang[0]);
        //include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpc.inc");
        //include_once(Mage::getBaseDir('lib')."/Billmate/xmlrpc-2.2.2/xmlrpcs.inc");

        $eid = Mage::getStoreConfig('billmate/credentials/eid');
        $secret= Mage::getStoreConfig('billmate/credentials/secret');
        $testmode=(boolean)Mage::getStoreConfig('payment/billmateinvoice/test_mode');

        return new Billmate($eid, $secret, $ssl, $testmode,$debug);
    }
}
