<?php
class Billmate_Cardpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getBillmate()
    {
        return Mage::helper('billmatecommon')->getBillmate();
    }
    
}