<?php
class Billmate_Cardpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getBillmate($ssl = true, $debug = false )
    {
        return Mage::helper('bmpaymentcore')->getBillmate($ssl, $debug);
    }
    
}