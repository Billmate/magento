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
        if(Mage::getSingleton('checkout/session')->getBillmateHash()){
            $billmate = Mage::helper('billmatecommon')->getBillmate();
            $checkout = $billmate->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));
            if(!isset($checkout['code'])){
                return $checkout['PaymentData']['url'];
            }

        } else {
            $checkout = Mage::getModel('billmatecommon/checkout')->init();
            Mage::getSingleton('checkout/session')->setBillmateInvoiceId($checkout['number']);
            Mage::log('checkout'.print_r($checkout,true));
            if(!isset($checkout['code'])){
                return $checkout['url'];
            }
        }

    }
}