<?php

class Billmate_Common_Helper_Url extends Mage_Checkout_Helper_Url
{
    /**
     * @return bool
     */
    public function isBMCheckoutActive()
    {
        return (bool)Mage::getStoreConfig("billmate/checkout/active");
    }

    /**
     * Retrieve checkout url
     *
     * @return string
     */
    public function getCheckoutUrl()
    {
        if ($this->isBMCheckoutActive()) {
            return $this->getBMCheckoutUrl();
        }
        return parent::getCheckoutUrl();
    }

    /**
     * @return string
     */
    public function getBMCheckoutUrl()
    {
        return $this->_getUrl('billmatecommon/billmatecheckout', array('_secure'=>true));
    }
}