<?php

class Billmate_Common_Block_Checkout_Onepage_Link extends Mage_Checkout_Block_Onepage_Link
{

    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        if (Mage::getStoreConfig('billmate/checkout/active') == 1) {
            return $this->getUrl('billmatecommon/billmatecheckout', array('_secure'=>true));
        }else{
            return parent::getCheckoutUrl();
        }
    }
}