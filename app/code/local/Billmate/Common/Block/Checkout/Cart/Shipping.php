<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-05-19
 * Time: 09:42
 */
class Billmate_Common_Block_Checkout_Cart_Shipping extends Mage_Checkout_Block_Cart_Shipping
{

    public function getEstimateRates()
    {

        if(strlen($this->getAddress()->getCountry()) < 2){
            $this->getAddress()->setCountryId('SE');
            $this->getAddress()->setPostcode('12345');
        }


        //Mage::log('print_r'.print_r($shipping->getResult()->getAllRates(),true),1,'billmate.log');
        return parent::getEstimateRates();
    }

}