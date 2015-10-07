<?php
class Billmate_Bankpay_Model_BillmateBankpay extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'billmatebankpay';
    protected $_formBlockType = 'billmatebankpay/form';
    
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

    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }

	public function cancel( Varien_Object $payment )
	{

		$this->void($payment);
		return $this;
	}

	public function void( Varien_Object $payment )
	{
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('billmatebankpay')->getBillmate(true, false);
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $values = array(
                'number' => $invoiceId
            );
            $paymentInfo = $k->getPaymentInfo($values);
            if ($paymentInfo['PaymentData']['status'] == 'Created') {
                $result = $k->cancelPayment($values);
                if (isset($result['code'])) {
                    Mage::throwException($result['message']);
                }
                $payment->setTransactionId($result['number']);
                $payment->setIsTransactionClosed(1);
            }

            return $this;
        }
	}

    public function isAvailable($quote = null)
    {
        if($quote == null ) return false;
		if( Mage::getStoreConfig('payment/billmatebankpay/active') != 1 ) return false;
        $countries = explode(',', Mage::getStoreConfig('payment/billmatebankpay/countries'));

        if( in_array($quote->getShippingAddress()->getCountry(), $countries ) ){
			//$data = $quote->getTotals();
			$total = $quote->getSubtotal();
            $status = false;
			$min_total = Mage::getStoreConfig('payment/billmatebankpay/min_amount');
			$max_total = Mage::getStoreConfig('payment/billmatebankpay/max_amount');
            if(!empty($min_total) && $min_total > 0){
                
                $status = $total >= $min_total;

            } else {
                $status = true;
            }

            if($status && (!empty($max_total) && $max_total > 0))
                $status = $total <= $max_total;
            else
                $status = $status;
            return $status;
		}
		return false;
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getTitle(){
        return (strlen(Mage::getStoreConfig('payment/billmatebankpay/title')) > 0) ? Mage::helper('billmatebankpay')->__(Mage::getStoreConfig('payment/billmatebankpay/title')) : Mage::helper('billmatebankpay')->__('Billmate Bank');
    }

    public function getOrderPlaceRedirectUrl()
    {
        //when you click on place order you will be redirected on this url, if you don't want this action remove this method
        $session = Mage::getSingleton('checkout/session');
        $session->setBillmateQuoteId($session->getQuoteId());
        $session->setRebuildCart(true);

        $gateway = Mage::getSingleton('billmatebankpay/gateway');
        $result = $gateway->makePayment();

        return $result['url'];
    }


    public function capture(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('billmatebankpay')->getBillmate(true, false);
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $values = array(
                'number' => $invoiceId
            );

            $paymentInfo = $k->getPaymentInfo($values);
            if ($paymentInfo['PaymentData']['status'] == 'Created') {
                $boTotal = $paymentInfo['Cart']['Total']['withtax']/100;
                if($amount != $boTotal){
                    Mage::throwException(Mage::helper('billmatecommon')->__('The amounts don\'t match. Billmate Online %s and Store %s. Activate manually in Billmate.',$boTotal,$amount));
                }
                $result = $k->activatePayment(array('PaymentData' => $values));
                if(isset($result['code']) )
                    Mage::throwException(utf8_encode($result['message']));
                if(!isset($result['code'])){
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);

                }
            }

        }
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('billmatebankpay')->getBillmate(true, false);
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $values = array(
                'number' => $invoiceId
            );
            $paymentInfo = $k->getPaymentInfo($values);
            if ($paymentInfo['PaymentData']['status'] == 'Paid') {
                $values['partcredit'] = false;
                $result = $k->creditPayment(array('PaymentData' => $values));
                if(isset($result['code']) )
                    Mage::throwException(utf8_encode($result['message']));
                if(!isset($result['code'])){
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                }
            }
        }
        return $this;
    }
    /*public function authorize(Varien_Object $payment, $amount){
    }
    public function validate(){
    }*/
}
?>