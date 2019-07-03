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

class Billmate_CustomPay_Block_Invoice_Complete extends Mage_Payment_Block_Form
{
    /**
     * @var array
     */
    protected $addressGroups = [
        'billing',
        'shipping',
    ];

    /**
     * @return Billmate_CustomPay_Model_Gateway_Invoice
     */
    public function getGatewayPayment()
    {
        return Mage::getSingleton('billmatecustompay/gateway_invoice');
    }

    /**
     * @return array
     */
    public function getAddressFields()
    {
        $addressFields = [];
        $gateway = $this->getGatewayPayment();

        $addressFields['street][']  = $gateway->getStreet();
        $addressFields['city'] = $gateway->getCity();
        $addressFields['postcode']  = $gateway->getPostcode();
        $addressFields['country_id'] = $gateway->getCountry();

        if ($gateway->getFirstname()) {
            $addressFields['firstname'] = $gateway->getFirstname();
        }

        if ($gateway->getLastname()) {
            $addressFields['lastname'] = $gateway->getLastname();
        }

        if ($gateway->getCompany()) {
            $addressFields['company'] = $gateway->getCompany();
        }

        if ($this->isCustomerLoggedIn()) {
            $addressFields['telephone'] = $gateway->getTelephone();
        }

        return $addressFields;
    }

    /**
     * @return array
     */
    public function getAddressGroups()
    {
        return $this->addressGroups;
    }

    /**
     * @return bool
     */
    public function isEnabledFirecheckout()
    {
        return (bool)Mage::getStoreConfig('firecheckout/general/enabled');
    }

    /**
     * @return bool
     */
    public function isCustomerLoggedIn()
    {
        return Mage::getSingleton('customer/session')->isLoggedIn();
    }
}
