<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-02-17
 * Time: 12:02
 */
class Billmate_Common_Helper_Url extends Mage_Checkout_Helper_Url
{

    /**
     * Retrieve checkout url
     *
     * @return string
     */
    public function getCheckoutUrl()
    {
        if (Mage::getStoreConfig("billmate/checkout/active") == 1) {
            return $this->_getUrl('billmatecommon/billmatecheckout', array('_secure'=>true));
        }
        return parent::getCheckoutUrl();
    }
}