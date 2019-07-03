<?php

abstract class Billmate_CustomPay_Model_Methods extends Mage_Payment_Model_Method_Abstract
{
    const ALLOWED_CURRENCY_CODES = [];

    /**
     * @var array
     */
    protected $allowedRefundStatuses = [];

    /**
     * @var array
     */
    protected $allowedCaptureStatuses = [];

    /**
     * @param null $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return $this->isAllowedToUse($quote);
    }

    /**
     * @return Billmate_CustomPay_Helper_Methods
     */
    protected function getHelper()
    {
        return Mage::helper('billmatecustompay/methods');
    }

    /**
     * @return Billmate_CustomPay_Helper_Data
     */
    protected function getDataHelper()
    {
        return Mage::helper('billmatecustompay');
    }

    /**
     * @return BillMate
     */
    protected function getBMConnection()
    {
        return $this->getDataHelper()->getBillmate();
    }

    /**
     * @return bool
     */
    protected function isPushEvents()
    {
        return $this->getHelper()->isPushEvents();
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @param Varien_Object $payment
     *
     * @return $this
     */
    public function cancel( Varien_Object $payment )
    {
        $this->void($payment);
        return $this;
    }

    /**
     * @param $quote
     *
     * @return bool
     */
    protected function isAllowedToUse($quote)
    {
        if($this->getCheckoutSession()->getBillmateHash()) {
            return true;
        }

        if (is_null($quote) || !$this->getHelper()->isActivePayment($this->getCode())) {
            return false;
        }

        $isAllowed = false;
        $countries = $this->getHelper()->getMethodCountries($this->getCode());
        if (in_array($quote->getShippingAddress()->getCountry(), $countries ) ) {
            $isAllowed = $this->isAllowedByTotal($quote->getSubtotal());
        }

        return $isAllowed;
    }

    /**
     * @param $total
     *
     * @return bool
     */
    protected function isAllowedByTotal($total)
    {
        $status = true;
        $min_total = $this->getHelper()->getMinAmount($this->getCode());
        $max_total = $this->getHelper()->getMaxAmount($this->getCode());

        if (!(($total > $min_total) && ($total < $max_total))
            && (!empty($min_total) && !empty($max_total))
        ) {
            $status = false;
        }

        return $status;
    }

    /**
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        $allowedCurrencies = $this->getAllowedCurrencies();
        if(!$allowedCurrencies) {
            return parent::canUseForCurrency($currencyCode);
        }

        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        return in_array($currencyCode, $allowedCurrencies);
    }

    /**
     * @param $payment
     *
     * @return $this
     */
    public function doVoid($payment)
    {
        $bmRequestData = $this->getBmCallbackRequestData($payment);
        $paymentInfo = $this->getBMConnection()->getPaymentInfo($bmRequestData);

        if ($paymentInfo['PaymentData']['status'] == 'Created') {
            $result = $this->getBMConnection()->cancelPayment($bmRequestData);
            if (isset($result['code'])) {
                Mage::throwException($result['message']);
            }
            $payment->setTransactionId($result['number']);
            $payment->setIsTransactionClosed(1);
            Mage::dispatchEvent($this->getCode() . '_voided',array('payment' => $payment));
        }

        if ($paymentInfo['PaymentData']['status'] == 'Paid') {
            $values['partcredit'] = false;
            $paymentData['PaymentData'] = $bmRequestData;
            $result = $this->getBMConnection()->creditPayment($paymentData);
            if (!isset($result['code'])) {
                $this->getBMConnection()->activatePayment(array('number' => $result['number']));
                $payment->setTransactionId($result['number']);
                $payment->setIsTransactionClosed(1);
                Mage::dispatchEvent($this->getCode() . '_voided',array('payment' => $payment));

            }
        }
        return $this;
    }

    /**
     * @param $payment
     * @param $amount
     *
     * @return $this
     */
    public function doCapture($payment, $amount)
    {
        $bmRequestData = $this->getBmCallbackRequestData($payment);
        $paymentInfo = $this->getBMConnection()->getPaymentInfo($bmRequestData);

        if ($this->isAllowedToCapture($paymentInfo)) {
            $boTotal = $paymentInfo['Cart']['Total']['withtax']/100;
            if ($amount != $boTotal) {
                Mage::throwException($this->getHelper()
                    ->__(
                        'The amounts don\'t match. Billmate Online %s and Store %s. Activate manually in Billmate.',
                        $boTotal,
                        $amount
                    ));
            }
            $result = $this->getBMConnection()->activatePayment(['PaymentData' => $bmRequestData]);
            if (isset($result['code']) ) {
                Mage::throwException(utf8_encode($result['message']));
            }

            if (!isset($result['code'])) {
                $payment->setTransactionId($result['number']);
                $payment->setIsTransactionClosed(1);
                Mage::dispatchEvent($this->getCode() . '_capture',
                    [
                        'payment' => $payment,
                        'amount' => $amount
                    ]);
            }

        }

        return $this;
    }

    /**
     * @param $payment
     * @param $amount
     *
     * @return $this
     */
    protected function doRefund($payment, $amount)
    {
        $bmRequestData = $this->getBmCallbackRequestData($payment);
        $paymentInfo = $this->getBMConnection()->getPaymentInfo($bmRequestData);

        if ($this->isAllowedToRefund($paymentInfo)) {
            $bmRequestData['partcredit'] = false;
            $bmResponseData = $this->getBMConnection()
                ->creditPayment([
                    'PaymentData' => $bmRequestData
                ]);
            if (isset($bmResponseData['code'])) {
                Mage::throwException(utf8_encode($bmResponseData['message']));
            }

            if (!isset($bmResponseData['code'])) {
                $payment->setTransactionId($bmResponseData['number']);
                $payment->setIsTransactionClosed(1);
                Mage::dispatchEvent(
                    $this->getCode() . '_refund',
                    ['payment' => $payment, 'amount' => $amount]
                );
            }
        }
        return $this;
    }

    /**
     * @return array
     */
    protected function getAllowedCurrencies()
    {
        return static::ALLOWED_CURRENCY_CODES;
    }

    /**
     * @return $this
     */
    protected function updateBmDataInSession()
    {
        $session = $this->getCheckoutSession();
        $session->setBillmateQuoteId($session->getQuoteId());
        $session->setRebuildCart(true);
        return $this;
    }

    /**
     * @param $bmStatus
     *
     * @return bool
     */
    protected function isAllowedToRefund($bmResponseData)
    {
        return in_array(
            $bmResponseData['PaymentData']['status'],
            $this->getAllowedRefundStatuses()
        );
    }


    /**
     * @param $bmStatus
     *
     * @return bool
     */
    protected function isAllowedToCapture($bmResponseData)
    {
        return in_array(
            $bmResponseData['PaymentData']['status'],
            $this->getAllowedCaptureStatuses()
        );
    }

    /**
     * @return array
     */
    protected function getAllowedRefundStatuses()
    {
        return $this->allowedRefundStatuses;
    }

    /**
     * @return array
     */
    protected function getAllowedCaptureStatuses()
    {
        return $this->allowedCaptureStatuses;
    }

    /**
     * @param $payment
     *
     * @return int
     */
    protected function getInvoiceIdFromPayment($payment)
    {
        return $payment->getMethodInstance()
            ->getInfoInstance()
            ->getAdditionalInformation('invoiceid');
    }

    /**
     * @param $payment
     *
     * @return array
     */
    protected function getBmCallbackRequestData($payment)
    {
        $invoiceId = $this->getInvoiceIdFromPayment($payment);
        $bmRequestData = array(
            'number' => $invoiceId
        );
        return $bmRequestData;
    }

    /**
     * @return mixed
     */
    protected function isBmCheckoutComplete()
    {
        return Mage::registry('billmate_checkout_complete');
    }
}