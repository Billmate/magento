<?php

class Billmate_Common_Block_Checkout_Cart extends Mage_Checkout_Block_Cart
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