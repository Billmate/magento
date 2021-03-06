<?php  
class Billmate_PartPayment_Model_PartPayment extends Mage_Payment_Model_Method_Abstract
{
    const PARTIAL_PAYMENT_CODE = 'pclass';

    protected $_code = 'billmatepartpayment';
    protected $_formBlockType = 'partpayment/form';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    
    public function isAvailable($quote = null)
    {
        if($quote == null ) return false;

        if(Mage::getSingleton('checkout/session')->getBillmateHash()) return true;
        if( Mage::getStoreConfig('payment/billmatepartpayment/active') != 1 ){
            return false;
        }
        $countries = explode(',', Mage::getStoreConfig('payment/billmatepartpayment/countries'));
        /**
         * @var $quote Mage_Sales_Model_Quote
         */
        //TODO Check active Paymentplan country instead.
        $collection = Mage::getModel('partpayment/pclass')->getCollection();
        $collection->addFieldToFilter('store_id',Mage::app()->getStore()->getId());
        $defaultCollection = Mage::getModel('partpayment/pclass')->getCollection();
        $defaultCollection->addFieldToFilter('store_id',0);
        if($collection->getSize() == 0 && $defaultCollection->getSize() == 0){
            return false;
        }
        //$countries = $collection->getColumnValues('country');


        $avail = in_array($quote->getBillingAddress()->getCountry(), $countries );
		if( $avail ){
			$quote = Mage::getSingleTon('checkout/session')->getQuote();
			$address = $quote->getShippingAddress();
			$title = '';
			if ($address) {
				$total = $address->getGrandTotal();
				$title = Mage::helper('partpayment')->getLowPclass($total);
			}
			$avail = !empty($title);
		} 
        if( $avail ){
            $total = $quote->getSubtotal();
            $status = false;
			$min_total = Mage::getStoreConfig('payment/billmatepartpayment/min_amount');
			$max_total = Mage::getStoreConfig('payment/billmatepartpayment/max_amount');
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
		return $avail;
    }

    public function canUseForCurrency($currencyCode)
    {
        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        if($currencyCode != 'SEK')
            return false;
        return true;
    }

	public function cancel( Varien_Object $payment )
	{
		$this->void($payment);
		return $this;
	}

	public function void( Varien_Object $payment )
	{
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('partpayment')->getBillmate();
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
            if($paymentInfo['PaymentData']['status'] == 'Partpayment'){
                $values['partcredit'] = false;
                $paymentData['PaymentData'] = $values;
                $result = $k->creditPayment($paymentData);
                if(!isset($result['code'])){
                    $k->activatePayment(array('number' => $result['number']));

                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                    Mage::dispatchEvent('billmate_partpay_voided',array('payment' => $payment));
                }
            }

            return $this;
        }
	}

    public function getTitle()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
	 	$address = $quote->getShippingAddress();
	 	$title = '';
	 	if ($address) {
            $selectedPClass = $this->getInfoInstance()->getAdditionalInformation('pclass');
	        $subTotal = $address->getSubtotal();
	        if ($this->getCurrentOrder()) {
                $subTotal = $this->getCurrentOrder()->getSubtotal();
            }
	        $title = Mage::helper('partpayment')->getLowPclass($subTotal, $selectedPClass);
	    }

	    $preTitle = Mage::helper('partpayment')->__('Billmate Partpayment');
        return $preTitle . $title;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('partpayment')->getBillmate();
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
                    Mage::dispatchEvent('billmate_partpay_capture',array('payment' => $payment, 'amount' => $amount));

                }
            }

        }
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('partpayment')->getBillmate();
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $values = array(
                'number' => $invoiceId
            );
            $paymentInfo = $k->getPaymentInfo($values);
            if ($paymentInfo['PaymentData']['status'] == 'Paid' || $paymentInfo['PaymentData']['status'] == 'Partpayment') {
                $values['partcredit'] = false;
                $result = $k->creditPayment(array('PaymentData' => $values));
                if(isset($result['code']) )
                    Mage::throwException(utf8_encode($result['message']));
                if(!isset($result['code'])){
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                    Mage::dispatchEvent('billmate_partpay_refund',array('payment' => $payment, 'amount' => $amount));

                }
            }
        }
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float         $amount
     */
    public function authorize(Varien_Object $payment, $amount)
    {

        if($hash = Mage::getSingleton('checkout/session')->getBillmateHash() && Mage::registry('billmate_checkout_complete')) {
            $result = Mage::helper('billmatecommon')->getBillmate()->getCheckout(array('PaymentData' => array('hash' => $hash)));
            $payment->setTransactionId($result['PaymentData']['order']['number']);

            $payment->setIsTransactionClosed(0);
        } else {
            $gateway = Mage::getSingleton('partpayment/gateway');
            $invoiceId = $gateway->makePayment();
            $payment->setTransactionId($invoiceId);

            $payment->setIsTransactionClosed(0);
        }
    }

    public function validate()
    {
        parent::validate();
        if(isset($_POST['payment'])) {
            $payment = $_POST['payment'];
            if (Mage::getStoreConfig('firecheckout/general/enabled') || Mage::getStoreConfig('streamcheckout/general/enabled')) {
                if (empty($payment['person_number']) && empty($payment['billmatepartpayment_pno'])) {
                    Mage::throwException(Mage::helper('payment')->__('Missing Personal number'));
                }
            } else {
                if (empty($payment['billmatepartpayment_pno'])) {
                    Mage::throwException(Mage::helper('payment')->__('Missing Personal number'));
                }
            }
            if (empty($payment['billmatepartpayment_phone'])) {
                Mage::throwException(Mage::helper('payment')->__('Missing phone number'));
            }

            if (empty($payment[self::PARTIAL_PAYMENT_CODE])) {
                Mage::throwException(Mage::helper('payment')->__('Missing partial type'));
            }

            $this->getInfoInstance()->setAdditionalInformation(self::PARTIAL_PAYMENT_CODE, $payment[self::PARTIAL_PAYMENT_CODE]);
        }
    }

    public function importData(array $data)
    {
        parent::importData();
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    protected function getCurrentOrder()
    {
        return $this->getHelper()->getCurrentOrder();
    }

    /**
     * @return Billmate_PartPayment_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('partpayment'); // TODO: Change the autogenerated stub
    }
}
