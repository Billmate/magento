<?php
class Billmate_CustomPay_Model_Methods_Card extends Billmate_CustomPay_Model_Methods
{
    const ALLOWED_PAYMENT_ACTION = 'authorize';

    /**
     * @var string
     */
    protected $_code = 'bmcustom_card';

    /**
     * @var string
     */
    protected $_formBlockType = 'billmatecustompay/card_form';

    /**
     * @var array
     */
    protected $allowedRefundStatuses = [
        'Paid',
    ];

    /**
     * @var array
     */
    protected $allowedCaptureStatuses = [
        'Created'
    ];
    
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_isInitializeNeeded      = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;

    /**
     * @param string $paymentAction
     * @param object $stateObject
     */
    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }

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
     *
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if (!$this->isAllowedToCaptureProcess()) {
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $payment->setTransactionId($invoiceId);
            $payment->setIsTransactionClosed(1);
            return $this;
        }

        return $this->doCapture($payment, $amount);
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
            $payment->setTransactionId($this->getInvoiceIdFromPayment($payment));
            $payment->setIsTransactionClosed(1);
            return $this;
        }

        return $this->doRefund($payment, $amount);
    }

    /**
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        $this->updateBmDataInSession();
        $gateway = Mage::getSingleton('billmatecustompay/gateway_card');
        $result = $gateway->makePayment();
        
        return $result['url'];
    }

    /**
     * @return bool
     */
    protected function isAllowedToCaptureProcess()
    {
        return $this->isPushEvents() &&
            $this->getHelper()->getPaymentAction($this->getCode()) == self::ALLOWED_PAYMENT_ACTION;
    }
}
