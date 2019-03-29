<?php

class Billmate_Common_Block_Checkout_Links extends Mage_Checkout_Block_Links
{
    /**
     * @return $this
     */
    public function addCheckoutLink()
    {
        if(Mage::getStoreConfig('billmate/checkout/active') == 1){

            if ($parentBlock = $this->getParentBlock()) {
                $text = $this->__('Checkout');
                $parentBlock->addLink($text, 'billmatecommon/billmatecheckout', $text, true, array('_secure'=>true), 60, null, 'class="top-link-onestepcheckout"');
            }

        } else{
            parent::addCheckoutLink();
        }
        return $this;
    }
}