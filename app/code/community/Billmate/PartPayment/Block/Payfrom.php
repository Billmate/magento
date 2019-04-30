<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-04-10
 * Time: 16:40
 */
class Billmate_PartPayment_Block_Payfrom extends Mage_Core_Block_Template
{
    public function getPayFrom()
    {
        if (!Mage::getStoreConfig('billmate/settings/show_payfrom',Mage::app()->getStore()->getId())) {
            return false;
        }

        if (($product = Mage::registry('product'))) {
            if(!Mage::getSingleton('customer/session')->isLoggedIn()) {
                return $this->helper('partpayment')->getLowPclass($product->getPrice());
            } else {
                $bill = Mage::getSingleton('customer/session')->getCustomer()->getDefaultBillingAddress();
                if ($bill && $bill->getCountryId() && $bill->getCountryId() == 'SE') {
                    return $this->helper('partpayment')->getLowPclass($product->getPrice());
                }
            }
        } else {
            return false;
        }
    }
}