<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Mage
 * @package    Phoenix_BankPayment
 * @copyright  Copyright (c) 2008 Andrej Sinicyn
 * @copyright  Copyright (c) 2010 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Billmate_PartPayment_Block_Changeaddress extends Mage_Payment_Block_Form
{
    private $matched = null;
    
    protected function _construct()
    {
        parent::_construct();

        $gateway = Mage::getSingleton('partpayment/gateway');
        $this->matched = $gateway->isMatched;
        $this->setTemplate('billmate/partpayment/changeaddress.phtml');
    }

    /**
     * @return bool
     */
    public function isMatched()
    {
        return $this->matched;
    }
}
