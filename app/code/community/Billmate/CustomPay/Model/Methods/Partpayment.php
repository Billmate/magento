<?php  
class Billmate_CustomPay_Model_Methods_Partpayment extends Billmate_CustomPay_Model_Methods
{
    const PARTIAL_PAYMENT_CODE = 'bmcustom_partpayment_pclass';

    const ALLOWED_CURRENCY_CODES = [
        'SEK'
    ];

    /**
     * @var string
     */
    protected $_code = 'bmcustom_partpayment';

    /**
     * @var string
     */
    protected $_formBlockType = 'billmatecustompay/partpayment_form';

    /**
     * @var array
     */
    protected $allowedRefundStatuses = [
        'Paid',
        'Partpayment'
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
     * @param null $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        $isAvailable = $this->isAllowedToUse($quote);
        if (!$isAvailable) {
            return false;
        }

        $collection = Mage::getModel('billmatecustompay/pclass')->getCollection();
        $collection->addFieldToFilter('store_id',Mage::app()->getStore()->getId());

        $defaultCollection = Mage::getModel('billmatecustompay/pclass')->getCollection();
        $defaultCollection->addFieldToFilter('store_id',0);

        if ($collection->getSize() == 0 && $defaultCollection->getSize() == 0) {
            return false;
        }

        $quote = Mage::getSingleTon('checkout/session')->getQuote();
        $address = $quote->getShippingAddress();
        $title = '';
        if ($address) {
            $total = $address->getGrandTotal();
            $title = $this->getHelper()->getLowPclass($total);
        }
        return !empty($title);
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
    public function getTitle()
    {
        $quote = Mage::getSingleTon('checkout/session')->getQuote();
	 	$address = $quote->getShippingAddress();
	 	$title = '';
	 	if ($address) {
            $selectedPClass = $this->getInfoInstance()->getAdditionalInformation(
                self::PARTIAL_PAYMENT_CODE
            );
            $subTotal = $address->getSubtotal();
            if ($this->getCurrentOrder()) {
                $subTotal = $this->getCurrentOrder()->getGrandTotal();
            }
            $title = $this->getHelper()->getLowPclass($subTotal, $selectedPClass);
	    }

	    $preTitle = parent::getTitle();
        return $preTitle . $title;
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
        if ($this->isPushEvents()) {
            return $this->doRefund($payment, $amount);
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
            $gateway = Mage::getSingleton('billmatecustompay/gateway_partpayment');
            $invoiceId = $gateway->makePayment();
            $payment->setTransactionId($invoiceId);
        }
        $payment->setIsTransactionClosed(0);
    }

    /**
     *
     */
    public function validate()
    {
        parent::validate();
        $paymentData = $this->getInfoInstance()->getData();
        if (!isset($paymentData[Billmate_CustomPay_Block_Partpayment_Form::PNO_INPUT_CODE])) {
            return $this;
        }

        if (Mage::getStoreConfig('firecheckout/general/enabled') || Mage::getStoreConfig('streamcheckout/general/enabled')) {
            if (empty($paymentData['person_number']) &&
                empty($paymentData[Billmate_CustomPay_Block_Partpayment_Form::PNO_INPUT_CODE])
            ) {
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number'));
            }
        } else {
            if (empty($paymentData[Billmate_CustomPay_Block_Partpayment_Form::PNO_INPUT_CODE])) {
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number'));
            }
        }
        if (empty($paymentData[Billmate_CustomPay_Block_Partpayment_Form::PHONE_INPUT_CODE])) {
            Mage::throwException(Mage::helper('payment')->__('Missing phone number'));
        }

        if (empty($paymentData[self::PARTIAL_PAYMENT_CODE])) {
            Mage::throwException(Mage::helper('payment')->__('Missing partial type'));
        }

        $this->getInfoInstance()->setAdditionalInformation(
            self::PARTIAL_PAYMENT_CODE,
            $paymentData[self::PARTIAL_PAYMENT_CODE]
        );
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    protected function getCurrentOrder()
    {
        return $this->getHelper()->getCurrentOrder();
    }

    /**
     * @return Billmate_CustomPay_Helper_Methods
     */
    public function getHelper()
    {
        return Mage::helper('billmatecustompay/methods');
    }
}
