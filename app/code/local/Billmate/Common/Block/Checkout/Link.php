<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-04-12
 * Time: 16:01
 */
class Billmate_Common_Block_Checkout_Link extends Mage_Core_Block_Template
{

    public function getCheckoutUrl()
    {
        return $this->getUrl('billmatecommon/billmatecheckout', array('_secure'=>true));
    }

}