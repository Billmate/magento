<?php  
class Billmate_PartPayment_Model_PartPayment extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'partpayment';
    protected $_formBlockType = 'partpayment/form';


    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    
    public function isAvailable($quote = null)
    {
    	if( Mage::getStoreConfig('payment/partpayment/active') != 1 ){
    		return false;
    	}

        if($quote == null ) return false;
        $countries = explode(',', Mage::getStoreConfig('payment/partpayment/countries'));
		
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
        return $this->getConfigData('title').$title;
    }

    public function authorize(Varien_Object $payment, $amount)
    {
    	
       $gateway =  Mage::getSingleton('billmateinvoice/gateway');
       $gateway->makePayment();
    }
    public function validate()
    {
        parent::validate();
        $payment = $_POST['payment'];
        if( empty( $payment['partpayment_pno'] ) ){
            Mage::throwException(Mage::helper('payment')->__('Missing Personal number') );
        }
        if( empty( $payment['partpayment_phone'] ) ){
            Mage::throwException(Mage::helper('payment')->__('Missing phone number') );
        }
    }
}
