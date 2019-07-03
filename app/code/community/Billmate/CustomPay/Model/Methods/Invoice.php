<?php

class Billmate_CustomPay_Model_Methods_Invoice extends Billmate_CustomPay_Model_Methods
{
    const ALLOWED_CURRENCY_CODES = [
        'SEK',
        'USD',
        'EUR',
        'GBP'
    ];

    /**
     * @var string
     */
    protected $_code = 'bmcustom_invoice';

    /**
     * @var string
     */
    protected $_formBlockType = 'billmatecustompay/invoice_form';

    /**
     * @var array
     */
    protected $allowedRefundStatuses = [
        'Paid',
        'Factoring'
    ];

    /**
     * @var array
     */
    protected $allowedCaptureStatuses = [
        'Created'
    ];
    
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
     * @param Varien_Object $payment
     *
     * @return $this
     */
	public function void( Varien_Object $payment )
	{
        if ($this->isPushEvents()) {
            $this->doVoid($payment);
        }
        return $this;
	}

    /**
     * @param Varien_Object $payment
     * @param float         $amount
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $hash = $this->getCheckoutSession()->getBillmateHash();
        if ($hash && $this->isBmCheckoutComplete()) {
            $bmResponse = $this->getBMConnection()->getCheckout(array('PaymentData' => array('hash' => $hash)));
            $payment->setTransactionId($bmResponse['PaymentData']['order']['number']);
        } else {
            $gateway = Mage::getSingleton('billmatecustompay/gateway_invoice');
            $invoiceId = $gateway->makePayment();
            $payment->setTransactionId($invoiceId);
        }
        $payment->setIsTransactionClosed(0);
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $invoiceFee = $this->getHelper()->getInvoiceFee();
        $methodTitle = $this->getHelper()->getTitle($this->getCode());
        if ($invoiceFee > 0) {
            $quote = Mage::getModel('checkout/cart')->getQuote();
            $shipping = $quote->getShippingAddress();

            $feeinfo = $this->getHelper()->getInvoiceFeeArray($invoiceFee, $shipping, $quote->getCustomerTaxClassId());

            $invFee = (isset($feeinfo['rate']) && $feeinfo['rate'] != 0 && Mage::getStoreConfig('payment/bmcustom_invoice/include_tax')) ? ($feeinfo['rate'] / 100 + 1) * $invoiceFee : $invoiceFee;


            $invFee = Mage::helper('core')->currency($invFee, true, false);
            return $this->getHelper()->__($methodTitle, $invFee);
        }
        return parent::getTitle();
    }

    /**
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if ($this->isPushEvents()) {
            return $this->doCapture($payment, $amount);
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
        if (!$this->isPushEvents()) {
            return $this;
        }

        return $this->doRefund($payment, $amount);
    }

    /**
     * @return $this
     */
    public function validate()
    {
        parent::validate();
        $paymentData = $this->getInfoInstance()->getData();
        if (!isset($paymentData[Billmate_CustomPay_Block_Invoice_Form::PNO_INPUT_CODE])) {
            return $this;
        }

        if (Mage::getStoreConfig('firecheckout/general/enabled') || Mage::getStoreConfig('streamcheckout/general/enabled')) {
            if (empty($paymentData['person_number']) && empty($paymentData[Billmate_CustomPay_Block_Invoice_Form::PNO_INPUT_CODE])) {
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number'));
            }
        } else {
            if (empty($paymentData[Billmate_CustomPay_Block_Invoice_Form::PNO_INPUT_CODE])) {
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number'));
            }
        }

        if (empty($paymentData[Billmate_CustomPay_Block_Invoice_Form::PHONE_INPUT_CODE])) {
            Mage::throwException(Mage::helper('payment')->__('Missing phone number'));
        }

    }
}
