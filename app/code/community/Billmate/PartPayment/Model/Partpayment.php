<?php  
class Billmate_PartPayment_Model_PartPayment extends Mage_Payment_Model_Method_Abstract
{
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
    	if( Mage::getStoreConfig('payment/billmatepartpayment/active') != 1 ){
    		return false;
    	}

        if($quote == null ) return false;
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
            $k = Mage::helper('partpayment')->getBillmate(true, false);
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
	        
        $quote = Mage::getSingleTon('checkout/session')->getQuote();
	 	$address = $quote->getShippingAddress();
	 	$title = '';
	 	if ($address) {
	        $total = $address->getGrandTotal();
	        $title = Mage::helper('partpayment')->getLowPclass($total);
	    }

	    $preTitle = (strlen(Mage::getStoreConfig('payment/partpayment/title')) > 0) ? Mage::helper('partpayment')->__(Mage::getStoreConfig('payment/partpayment/title')) : Mage::helper('partpayment')->__('Billmate Partpayment');
        return $preTitle.$title;
        //return $this->getConfigData('title').$title;
    }
    public function capture(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('partpayment')->getBillmate(true, false);
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
            $k = Mage::helper('partpayment')->getBillmate(true, false);
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $values = array(
                'number' => $invoiceId
            );
            $paymentInfo = $k->getPaymentInfo($values);
            if ($paymentInfo['PaymentData']['status'] == 'Paid' || $paymentInfo['PaymentData']['status'] == 'Factoring') {
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

    public function authorize(Varien_Object $payment, $amount)
    {
    	
       $gateway =  Mage::getSingleton('partpayment/gateway');
       $invoiceId = $gateway->makePayment();
	    $payment->setTransactionId($invoiceId);
	    $payment->setIsTransactionClosed(0);
    }
    public function validate()
    {
        parent::validate();
        $payment = $_POST['payment'];
        if(Mage::getStoreConfig('firecheckout/general/enabled') || Mage::getStoreConfig('streamcheckout/general/enabled')){
            if( empty( $payment['person_number'] ) && empty( $payment['billmatepartpayment_pno'] ) ){
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number') );
            }
        } else {
            if( empty( $payment['billmatepartpayment_pno'] ) ){
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number') );
            }
        }
        if( empty( $payment['billmatepartpayment_phone'] ) ){
            Mage::throwException(Mage::helper('payment')->__('Missing phone number') );
        }
    }
}
