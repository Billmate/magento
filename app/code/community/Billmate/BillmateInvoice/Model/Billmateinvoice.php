<?php

class Billmate_BillmateInvoice_Model_BillmateInvoice extends Mage_Payment_Model_Method_Abstract{
    protected $_code = 'billmateinvoice';
    protected $_formBlockType = 'billmateinvoice/form';
//    protected $_infoBlockType = 'billmateinvoice/form';
    
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
    public function getTitle(){
        return Mage::helper('billmateinvoice')->__(Mage::getStoreConfig('payment/billmateinvoice/title'));
    }

    public function capture(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('billmateinvoice')->getBillmate(true, false);
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
            $k = Mage::helper('billmateinvoice')->getBillmate(true, false);
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
            }
        }
        return $this;
    }

    public function validate()
    {
		
        parent::validate();
        $payment = $_POST['payment'];
        if(Mage::getStoreConfig('billmate/settings/firecheckout')){
            if( empty( $payment['person_number'] ) && empty( $payment['billmateinvoice_pno'] )){
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number') );
            }
        } else {
            if( empty( $payment['billmateinvoice_pno'] )){
                Mage::throwException(Mage::helper('payment')->__('Missing Personal number') );
            }
        }

        if( empty( $payment['billmateinvoice_phone'] ) ){
            Mage::throwException(Mage::helper('payment')->__('Missing phone number') );
        }
    }
}
