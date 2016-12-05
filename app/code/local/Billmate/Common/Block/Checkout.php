<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-10-19
 * Time: 12:52
 */
class Billmate_Common_Block_Checkout extends Mage_Core_Block_Template
{
    public function getCheckoutUrl()
    {
        $checkout = Mage::getModel('billmatecommon/checkout')->init();

        Mage::log('checkout'.print_r($checkout,true));
        if(!isset($checkout['code'])){
            return $checkout['url'];
        }
    }
}