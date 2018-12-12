<?php
class Billmate_Common_Block_Checkout_Cart_Shipping extends Mage_Checkout_Block_Cart_Shipping
{
    /***
     * @return array
     */
    public function getEstimateRates()
    {
        if(strlen($this->getAddress()->getCountry()) < 2){
            $this->getAddress()->setCountryId('SE');
            $this->getAddress()->setPostcode('12345');
        }

        return parent::getEstimateRates();
    }

}