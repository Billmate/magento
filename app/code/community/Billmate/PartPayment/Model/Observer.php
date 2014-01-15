<?php

class Billmate_PartPayment_Model_Observer extends Mage_Core_Model_Abstract
{

    public function adminSystemConfigChangedSectionPartpayment($observer)
    {   
        $enabled = (int)$_POST['groups']['partpayment']['fields']['active']['value'];
        if($enabled){
            $collection = Mage::getModel('partpayment/pclass')->getCollection();
            foreach( $collection as $item ){
                $item->delete();
            }

			
			if( isset($_POST['groups']['partpayment']['fields']['eid']['inherit'] )){
				$eid = (int) Mage::getStoreConfig('payment/partpayment/eid', Mage::app()->getStore());
			} else {
            	$eid = (int)$_POST['groups']['partpayment']['fields']['eid']['value'];
            }
            
            
			if( isset($_POST['groups']['partpayment']['fields']['secret']['inherit'] )){
				$secret = (float)Mage::getStoreConfig('payment/partpayment/secret', Mage::app()->getStore());
			} else {
	            $secret = (float)$_POST['groups']['partpayment']['fields']['secret']['value'];
	     	}
	     	
            //$countries=$_POST['groups']['partpayment']['fields']['countries']['value'];
            
			if( isset($_POST['groups']['partpayment']['fields']['test_mode']['inherit'] )){
				$testmode = (float)Mage::getStoreConfig('payment/test_mode/test_mode', Mage::app()->getStore());
			} else {
	            $testmode=$_POST['groups']['partpayment']['fields']['test_mode']['value'];
	     	}
			$countries = array('SE');
            $gateway = Mage::helper("partpayment");
			
            foreach( $countries as $country ){
                $gateway->savePclasses($eid, $secret, $country, $testmode);
            }
        }
    }
}
