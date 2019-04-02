<?php

class Billmate_Common_Block_Checkout_Cart extends Mage_Checkout_Block_Cart
{
    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        /** @var @ $helper Billmate_Common_Helper_Url*/
        $helper = Mage::helper('billmatecommon/url');
        if ($helper->isBMCheckoutActive()) {
            return $helper->getBMCheckoutUrl();
        }else{
            return parent::getCheckoutUrl();
        }
    }
}