<?php

class Billmate_PartPayment_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * @param $observer
     */
    public function adminSystemConfigChangedSectionPartpayment($observer)
    {   
        $enabled = (int)$_POST['groups']['partpayment']['fields']['active']['value'];
        if ($enabled) {

        }
    }
}