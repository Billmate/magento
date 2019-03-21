<?php

class Billmate_BillmateCheckout_Model_Billmatecheckout extends Mage_Payment_Model_Method_Abstract
{
    const BM_INVOICE_NUMBER_CODE_PARAM = 'invoiceid';

    const METHOD_CODE = 'billmatecheckout';

    const BM_ADDITIONAL_INFO_CODE = 'bm_payment_method';

    protected $_code = 'billmatecheckout';

    protected $_formBlockType = 'billmatecheckout/form';
    
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;

    /**
     * @var Billmate_BillmateCheckout_Helper_Data
     */
    protected $helper;

    public function __construct()
    {

        $this->helper = Mage::helper('billmatecheckout');
    }

    /**
     * @param null $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        if ($quote == null ) {
            return false;
        }

		return true;
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


    /**
     * @param Varien_Object $payment
     *
     * @return $this
     */
    public function void( Varien_Object $payment )
    {
        if ($this->isAllowedToProcess()) {

            $bmRequestData = $this->getBmRequestData($payment);
            if (!$bmRequestData) {
                return $this;
            }
            $paymentInfo = $this->getActualBmPaymentInfo($bmRequestData);

            if ($paymentInfo['PaymentData']['status'] == 'Created') {
                $result = $this->getBMConnection()->cancelPayment($bmRequestData);
                if (isset($result['code'])) {
                    Mage::throwException($result['message']);
                }
                $payment->setTransactionId($result['number']);
                $payment->setIsTransactionClosed(1);
            }

            if ($paymentInfo['PaymentData']['status'] == 'Paid') {
                $values['partcredit'] = false;
                $paymentData['PaymentData'] = $values;
                $result = $this->getBMConnection()->creditPayment($paymentData);
                if (!isset($result['code'])) {
                    $this->getBMConnection()->activatePayment(array('number' => $result['number']));
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                    Mage::dispatchEvent('billmate_checkout_voided',array('payment' => $payment));
                }
            }

            return $this;
        }
    }

    /**
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if ($this->isAllowedToProcess()) {

            $bmRequestData = $this->getBmRequestData($payment);
            if (!$bmRequestData) {
                return $this;
            }

            $paymentInfo = $this->getActualBmPaymentInfo($bmRequestData);
            if (isset($paymentInfo['PaymentData']['status']) &&
                $paymentInfo['PaymentData']['status'] == 'Created'
            ) {
                $boTotal = $paymentInfo['Cart']['Total']['withtax']/100;
                if ($amount != $boTotal) {
                    Mage::throwException(Mage::helper('billmatecommon')->__('The amounts don\'t match. Billmate Online %s and Store %s. Activate manually in Billmate.',$boTotal,$amount));
                }
                $result = $this->getBMConnection()->activatePayment(array('PaymentData' => $bmRequestData));
                if(isset($result['code']) )
                    Mage::throwException(utf8_encode($result['message']));
                if(!isset($result['code'])){
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                    Mage::dispatchEvent('billmate_checkout_capture',array('payment' => $payment, 'amount' => $amount));
                }
            }
        }
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount)
    {
        if ($this->isAllowedToProcess()) {
            $bmRequestData = $this->getBmRequestData($payment);
            if (!$bmRequestData) {
                return $this;
            }
            $paymentInfo = $this->getActualBmPaymentInfo($bmRequestData);

            if ($paymentInfo['PaymentData']['status'] == 'Paid' || $paymentInfo['PaymentData']['status'] == 'Factoring') {
                $values['partcredit'] = false;
                $result = $this->getBMConnection()->creditPayment(array('PaymentData' => $values));
                if(isset($result['code']) )
                    Mage::throwException(utf8_encode($result['message']));
                if(!isset($result['code'])){
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                    Mage::dispatchEvent('billmate_checkout_refund',array('payment' => $payment, 'amount' => $amount));

                }
            }
        }
        return $this;
    }

    /**
     * @param $payment
     *
     * @return int | false
     */
    protected function getBmRequestData($payment)
    {
         $bmInvoiceId = $payment->getMethodInstance()
            ->getInfoInstance()
            ->getAdditionalInformation(self::BM_INVOICE_NUMBER_CODE_PARAM);

         if(!$bmInvoiceId) {
             return false;
         }

         return ['number' => $bmInvoiceId];
    }

    /**
     * @param $magentoPayment
     *
     * @return array
     */
    protected function getActualBmPaymentInfo($bmRequestData)
    {
        $paymentInfo = $this->getBMConnection()->getPaymentInfo($bmRequestData);
        return $paymentInfo;
    }

    /**
     * @return Billmate_Billmate
     */
    protected function getBMConnection()
    {
        return $this->helper->getBillmate();
    }

    /**
     * @return bool
     */
    protected function isAllowedToProcess()
    {
        return $this->helper->isAllowedBackEvents();
    }
}
