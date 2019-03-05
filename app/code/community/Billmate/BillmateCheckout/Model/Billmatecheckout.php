<?php

class Billmate_BillmateCheckout_Model_Billmatecheckout extends Mage_Payment_Model_Method_Abstract
{
    const METHOD_CODE = 'billmatecheckout';
    
    protected $_code = 'billmatecheckout';

    protected $_formBlockType = 'billmatecheckout/form';
    
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = false;
	
    public function isAvailable($quote = null)
    {
        if($quote == null ) return false;
        if(Mage::getSingleton('checkout/session')->getBillmateHash()) return true;

        if( Mage::getStoreConfig('payment/billmatecheckout/active') != 1 ) return false;
        $countries = explode(',', Mage::getStoreConfig('payment/billmatecheckout/countries'));

        if( in_array($quote->getShippingAddress()->getCountry(), $countries ) ){
			//$data = $quote->getTotals();
			$total = $quote->getSubtotal();
            $status = false;
			$min_total = Mage::getStoreConfig('payment/billmatecheckout/min_amount');
			$max_total = Mage::getStoreConfig('payment/billmatecheckout/max_amount');
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

	public function cancel( Varien_Object $payment )
	{

		$this->void($payment);
		return $this;
	}

	public function void( Varien_Object $payment )
	{
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('billmatecheckout')->getBillmate();
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
            if($paymentInfo['PaymentData']['status'] == 'Paid'){
                $values['partcredit'] = false;
                $paymentData['PaymentData'] = $values;
                $result = $k->creditPayment($paymentData);
                if(!isset($result['code'])){
                    $k->activatePayment(array('number' => $result['number']));

                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                    Mage::dispatchEvent('billmate_invoice_voided',array('payment' => $payment));

                }
            }

            return $this;
        }
	}

    public function authorize(Varien_Object $payment, $amount)
    {

    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $invoiceFee = Mage::getStoreConfig( 'payment/billmatecheckout/billmate_fee' );
        $invoiceFee = Mage::helper( 'billmatecheckout' )->replaceSeparator( $invoiceFee );
        $invoiceFee = ($invoiceFee) ? $invoiceFee : 0;


        $quote = Mage::getModel('checkout/cart')->getQuote();
        $Shipping = $quote->getShippingAddress();
        if(strlen(Mage::getStoreConfig( 'payment/billmatecheckout/billmate_fee' )) > 0
            && Mage::getStoreConfig( 'payment/billmatecheckout/billmate_fee' ) > 0) {
            $feeinfo = Mage::helper('billmatecheckout')
                ->getInvoiceFeeArray($invoiceFee, $Shipping, $quote->getCustomerTaxClassId());

            $invFee = (isset($feeinfo['rate']) && $feeinfo['rate'] != 0 && Mage::getStoreConfig('payment/billmatecheckout/include_tax')) ? ($feeinfo['rate'] / 100 + 1) * $invoiceFee : $invoiceFee;


            $invFee = Mage::helper('core')->currency($invFee, true, false);
            return (strlen(Mage::getStoreConfig('payment/billmatecheckout/title')) > 0) ? Mage::helper('billmatecheckout')->__(Mage::getStoreConfig('payment/billmatecheckout/title'), $invFee) : Mage::helper('billmatecheckout')->__('Billmate Сheckout - %s invoice fee is added to the order.', $invFee);
        } else {
            return (strlen(Mage::getStoreConfig('payment/billmatecheckout/title')) > 0) ? Mage::helper('billmatecheckout')->__(Mage::getStoreConfig('payment/billmatecheckout/title')) : Mage::helper('billmatecheckout')->__('Billmate Сheckout');

        }

    }

    public function canUseForCurrency($currencyCode)
    {
        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        if(in_array($currencyCode,array('SEK','USD','EUR','GBP')))
            return true;
        return false;
    }

    /**
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('billmatecheckout')->getBillmate();
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
                    Mage::dispatchEvent('billmate_invoice_capture',array('payment' => $payment, 'amount' => $amount));

                }

            }
        }
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('billmatecheckout')->getBillmate();
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
                    Mage::dispatchEvent('billmate_bankpay_refund',array('payment' => $payment, 'amount' => $amount));

                }
            }
        }
        return $this;
    }

    public function validate()
    {
        parent::validate();
        if(isset($_POST['payment'])) {
            $payment = $_POST['payment'];
            if (Mage::getStoreConfig('firecheckout/general/enabled') || Mage::getStoreConfig('streamcheckout/general/enabled')) {
                if (empty($payment['person_number']) && empty($payment['billmatecheckout_pno'])) {
                    Mage::throwException(Mage::helper('payment')->__('Missing Personal number'));
                }
            } else {
                if (empty($payment['billmatecheckout_pno'])) {
                    Mage::throwException(Mage::helper('payment')->__('Missing Personal number'));
                }
            }

            if (empty($payment['billmatecheckout_phone'])) {
                Mage::throwException(Mage::helper('payment')->__('Missing phone number'));
            }
        }
    }
}
