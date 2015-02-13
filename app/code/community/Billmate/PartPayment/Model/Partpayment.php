<?php  
class Billmate_PartPayment_Model_PartPayment extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'partpayment';
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
    	if( Mage::getStoreConfig('payment/partpayment/active') != 1 ){
    		return false;
    	}

        if($quote == null ) return false;
        $countries = explode(',', Mage::getStoreConfig('payment/partpayment/countries'));

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
			$min_total = Mage::getStoreConfig('payment/partpayment/min_amount');
			$max_total = Mage::getStoreConfig('payment/partpayment/max_amount');

			return $total >= $min_total && $total <= $max_total;
		}
		return $avail;
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

        return Mage::helper('partpayment')->__(Mage::getStoreConfig('payment/partpayment/title')).$title;
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

                $result = $k->activatePayment(array('PaymentData' => $values));
                if(isset($result['code']) )
                    Mage::throwException(mb_convert_encoding($result['message'],'UTF-8','auto'));
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
                    Mage::throwException(mb_convert_encoding($result['message'],'UTF-8','auto'));
                if(!isset($result['code'])){
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                }
                Mage::log('result' . print_r($result, true));
            }
        }
        return $this;
    }

    public function authorize(Varien_Object $payment, $amount)
    {
    	
       $gateway =  Mage::getSingleton('partpayment/gateway');
       $gateway->makePayment();
    }
    public function validate()
    {
        parent::validate();
        $payment = $_POST['payment'];
        if(Mage::getStoreConfig('billmate/settings/firecheckout')){
            if( empty( $payment['person_number'] ) ){
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number') );
            }
        } else {
            if( empty( $payment['partpayment_pno'] ) ){
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number') );
            }
        }
        if( empty( $payment['partpayment_phone'] ) ){
            Mage::throwException(Mage::helper('payment')->__('Missing phone number') );
        }
    }
}
