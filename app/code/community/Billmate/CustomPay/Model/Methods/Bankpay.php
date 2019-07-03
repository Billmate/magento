<?php
class Billmate_CustomPay_Model_Methods_Bankpay extends Billmate_CustomPay_Model_Methods
{

    const ALLOWED_CURRENCY_CODES = ['SEK'];

    /**
     * @var string
     */
    protected $_code = 'bmcustom_bankpay';

    /**
     * @var string
     */
    protected $_formBlockType = 'billmatecustompay/bankpay_form';

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
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        $this->updateBmDataInSession();
        $gateway = Mage::getSingleton('billmatecustompay/gateway_bankpay');
        $result = $gateway->makePayment();

        return $result['url'];
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
}