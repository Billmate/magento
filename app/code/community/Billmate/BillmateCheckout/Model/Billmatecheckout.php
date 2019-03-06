<?php

class Billmate_BillmateCheckout_Model_Billmatecheckout extends Mage_Payment_Model_Method_Abstract
{
    const METHOD_CODE = 'billmatecheckout';

    const BM_ADDITIONAL_INFO_CODE = 'bm_payment_method';

    protected $_code = 'billmatecheckout';

    protected $_formBlockType = 'billmatecheckout/form';
    
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = false;
	
    public function isAvailable($quote = null)
    {
        if ($quote == null ) {
            return false;
        }

        if(Mage::getSingleton('checkout/session')->getBillmateHash()) {
            return true;
        }

        if( Mage::getStoreConfig('payment/billmatecheckout/active') != 1 ) {
            return false;
        }


		return false;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $paymentTitle = parent::getTitle();
        if ($this->isOrderPage()) {
            $paymentTitle .= $this->getPaymentType();
        }
        return $paymentTitle;
    }

    /**
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return $this
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        return $this;
    }

    /**
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        if(in_array($currencyCode,array('SEK','USD','EUR','GBP')))
            return true;
        return false;
    }

    /**
     * @return bool
     */
    protected function isOrderPage()
    {
        return (bool)$this->getCurrentOrder();
    }

    /**
     * @return mixed
     */
    protected function getCurrentOrder()
    {
        if($this->getCurrentInvoice()) {
            return $this->getCurrentInvoice()->getOrder();
        }

        if($this->getCurrentShipment()) {
            return $this->getCurrentShipment()->getOrder();
        }
        return Mage::registry('current_order');
    }

    public function getCurrentInvoice()
    {
        return Mage::registry('current_invoice');
    }

    public function getCurrentShipment()
    {
        return Mage::registry('current_shipment');
    }

    /**
     * @return string
     */
    protected function getPaymentType()
    {
        $payment = $this->getCurrentOrder()->getPayment();
        $additionalInformation = $payment->getAdditionalInformation(self::BM_ADDITIONAL_INFO_CODE);
        if ($additionalInformation) {
           return  $this->_getHelper()->__(" - %s", $additionalInformation);
        }
        return '';
    }
}
