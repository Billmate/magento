<?php
class Billmate_Common_Block_Checkout_Cart_Shipping extends Mage_Checkout_Block_Cart_Shipping
{
    /***
     * @return array
     */
    public function getEstimateRates()
    {
        if (!$this->getAddress()->getCountry()) {
            $this->getAddress()->addData([
                'postcode' => $this->getDataHelper()->getDefaultPostcode(),
                'country_id' => $this->getDataHelper()->getContryId(),
            ]);
        }

        return parent::getEstimateRates();
    }

    /**
     * @return Billmate_Common_Helper_Data
     */
    protected function getDataHelper()
    {
        return Mage::helper('billmatecommon');
    }

}