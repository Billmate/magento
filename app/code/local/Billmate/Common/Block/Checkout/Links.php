<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-09-26
 * Time: 10:23
 */
class Billmate_Common_Block_Checkout_Links extends Mage_Checkout_Block_Links
{

    public function addCheckoutLink()
    {
        if(Mage::getStoreConfig('billmate/checkout/active') == 1){
            if (!$this->helper('checkout')->canOnepageCheckout()) {
                return $this;
            }
            if ($parentBlock = $this->getParentBlock()) {
                $text = $this->__('Checkout');
                $parentBlock->addLink($text, 'billmatecommon/billmatecheckout', $text, true, array('_secure'=>true), 60, null, 'class="top-link-onestepcheckout"');
            }
            return $this;
        } else{
            parent::addCheckoutLink();
        }
    }
}