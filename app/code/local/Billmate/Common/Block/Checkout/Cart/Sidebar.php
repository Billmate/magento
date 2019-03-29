<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-09-26
 * Time: 11:08
 */
class Billmate_Common_Block_Checkout_Cart_Sidebar extends Mage_Checkout_Block_Cart_Sidebar
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
        } else {
            return parent::getCheckoutUrl();
        }
    }
}