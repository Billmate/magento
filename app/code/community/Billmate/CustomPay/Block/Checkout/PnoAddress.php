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

class Billmate_CustomPay_Block_Checkout_PnoAddress extends Mage_Core_Block_Template
{

    /**
     * @var Billmate_CustomPay_Helper_Data
     */
    protected $helper;

    /**
     * Billmate_CustomPay_Block_Checkout_PnoAddress constructor.
     *
     * @param array $args
     */
    public function __construct(array $args = array())
    {
        parent::__construct($args);
        $this->helper = Mage::helper('billmatecustompay');
    }

    /**
     * @return bool
     */
    public function isAvailableToShow()
    {
        return $this->getDataHelper()->isShowPnoForm();
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
    public function isEnabledStreamcheckout()
    {
        return (bool)Mage::getStoreConfig('streamcheckout/general/enabled');
    }

    /**
     * @return bool
     */
    public function isEnabledOnestepcheckout()
    {
        return (bool)Mage::getStoreConfig('onestepcheckout/general/active');
    }

    /**
     * @return string
     */
    public function getPno()
    {
        $sessionPno = Mage::getSingleton('checkout/session')->getBillmatePno();
        if ($sessionPno) {
            return $sessionPno;
        }
        return  '';
    }

    /**
     * @param $content
     *
     * @return string
     */
    public function prepareForJsView($content)
    {
        return str_replace(["\r","\n"], "", $content);
    }

    /**
     * @return Billmate_CustomPay_Helper_Data
     */
    public function getDataHelper()
    {
        return $this->helper;
    }
}
