<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-09-26
 * Time: 10:25
 */
class Billmate_Common_Block_Checkout_Onepage_Link extends Mage_Checkout_Block_Onepage_Link
{

    public function getCheckoutUrl()
    {
        if (Mage::getStoreConfig('billmate/checkout/active') == 1) {
            return $this->getUrl('billmatecommon/billmatecheckout', array('_secure'=>true));
        }else{
            return parent::getCheckoutUrl();
        }
    }
}