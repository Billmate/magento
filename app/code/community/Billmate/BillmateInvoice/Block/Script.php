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

class Billmate_BillmateInvoice_Block_Script extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    public function getScriptPath()
    {
        if (Mage::getStoreConfig('firecheckout/general/enabled')) {
            return $this->getSkinUrl('js/billmatepopup-fc.js');
        }
        if (Mage::getStoreConfig('onestepcheckout/general/active')) {
            return $this->getSkinUrl('js/billmatepopup-osc.js');
        }
        if (Mage::getStoreConfig('streamcheckout/general/enabled')) {
            return $this->getSkinUrl('js/billmatepopup-stream.js');
        }

        return $this->getSkinUrl('js/billmatepopup.js');
    }

    /**
     * @return string
     */
    public function getMediaUrl()
    {
        return $this->_getUrlModel()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
    }

    /**
     * @return int
     */
    public function getPartPaymentEid()
    {
        return Mage::getStoreConfig('payment/billmatepartpayment/eid');
    }

    /**
     * @return bool
     */
    public function isCustomerLogged()
    {
        return $this->getCustomerSession()->isLoggedIn();
    }

    /**
     * @return Mage_Customer_Model_Session
     */
    protected function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }
}
