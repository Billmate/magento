<?php
class Billmate_Bankpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @param bool $ssl
     * @param bool $debug
     *
     * @return Billmate
     */
    public function getBillmate($ssl = true, $debug = false )
    {
        return Mage::helper('billmatecommon')->getBillmate();
    }
    
}