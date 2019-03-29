<?php

class Billmate_Common_Block_Checkout_Link extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        return Mage::helper('billmatecommon/url')->getBMCheckoutUrl();
    }

}