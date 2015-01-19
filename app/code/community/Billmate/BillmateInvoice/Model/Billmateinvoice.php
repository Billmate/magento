<?php

class Billmate_BillmateInvoice_Model_BillmateInvoice extends Mage_Payment_Model_Method_Abstract{
    protected $_code = 'billmateinvoice';
    protected $_formBlockType = 'billmateinvoice/form';
//    protected $_infoBlockType = 'billmateinvoice/form';
    
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
	
    public function isAvailable($quote = null)
    {
        if($quote == null ) return false;
        if( Mage::getStoreConfig('payment/billmateinvoice/active') != 1 ) return false;
        $countries = explode(',', Mage::getStoreConfig('payment/billmateinvoice/countries'));

        if( in_array($quote->getShippingAddress()->getCountry(), $countries ) ){
			//$data = $quote->getTotals();
			$total = $quote->getSubtotal();
			$min_total = Mage::getStoreConfig('payment/billmateinvoice/min_amount');
			$max_total = Mage::getStoreConfig('payment/billmateinvoice/max_amount');
			return $total >= $min_total && $total <= $max_total;
		}
		return false;
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
        if( empty( $payment['billmateinvoice_pno'] ) ){
            Mage::throwException(Mage::helper('payment')->__('Missing Personal number') );
        }
        if( empty( $payment['billmateinvoice_phone'] ) ){
            Mage::throwException(Mage::helper('payment')->__('Missing phone number') );
        }
    }
}
