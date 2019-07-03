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

class Billmate_CustomPay_Block_Invoice_Form extends Billmate_CustomPay_Block_Form
{
    const PNO_INPUT_CODE = 'bmcustom_invoice_pno';

    const PHONE_INPUT_CODE = 'bmcustom_invoice_phone';

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('billmatecustompay/method/invoice.phtml');
    }

    /**
     * @return string
     */
    public function getCustomerEmail()
    {

        if ($this->getCustomerSession()->isLoggedIn()) {
            $email = Mage::getModel('customer/customer')
                ->load($this->getCustomerSession()->getId())
                ->getEmail();
        } else {
            $email = Mage::getSingleton('checkout/session')
                ->getQuote()->getEmail();
        }
        return $email;
    }


    /**
     * @return bool
     */
    public function useCustomStyles()
    {
        return Mage::getStoreConfig('firecheckout/general/enabled') &&
            Mage::getStoreConfig('billmate/settings/getaddress') &&
            !$this->getCustomerSession()->isLoggedIn();
    }

    /**
     * @return Mage_Customer_Model_Session
     */
    protected function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * @return string
     */
    public function getFieldPnoCode()
    {
        return self::PNO_INPUT_CODE;
    }

    /**
     * @return string
     */
    public function getFieldPhoneCode()
    {
        return self::PHONE_INPUT_CODE;
    }
}
