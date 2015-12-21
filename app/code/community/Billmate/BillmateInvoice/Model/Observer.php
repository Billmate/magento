<?php

class Billmate_BillmateInvoice_Model_Observer extends Mage_Core_Model_Abstract
{
	public function activate(Varien_Event_Observer $observer)
	{	/** @var $order Mage_Sales_Model_Order */
		$order = $observer->getEvent()->getOrder();
        if(!$order->getPayment()->hasMethodInstance()) return;
        $method = $order->getPayment()->getMethodInstance()->getCode();
        Mage::getSingleton('checkout/session')->unsBillmatePno();
        if(!in_array($method,array('billmateinvoice','billmatepartpayment'))) return;
		$session = Mage::getSingleton("core/session",  array("name"=>"frontend"));
		$liveid = $session->getData("billmateinvoice_id");
		$session->unsetData('billmateinvoice_id');
        $k = Mage::helper('billmateinvoice')->getBillmate(true, false);

        $orderValues = array();
        if($liveid) {

            $payment = $order->getPayment();
            $info = $payment->getMethodInstance()->getInfoInstance();
            $info->setAdditionalInformation('invoiceid',$liveid);
            $order->save();
        }
	}

    public function salesOrderPaymentPlaceEnd(Varien_Event_Observer $observer)
    {

        $payment = $observer->getPayment();
        if ($payment->getMethodInstance()->getCode() != 'billmateinvoice') {
            return;
        }

        $info = $payment->getMethodInstance()->getInfoInstance();
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if (! $quote->getId()) {
            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        }

        //Set the invoice fee included tax value
        $info->setAdditionalInformation('billmateinvoice_fee',$quote->getFeeAmount());
		$info->setAdditionalInformation('billmateinvoice_fee_tax',$quote->getFeeTaxAmount());
        $info->save();
    }

    public function invoiceSaveInvoiceFee(Varien_Event_Observer $observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        if($invoice->getBaseFeeAmount() && $invoice->getBaseFeeAmount() > $order->getBaseFeeAmountInvoiced()){

            $order->setFeeAmountInvoiced($order->getFeeAmountInvoiced() + $invoice->getFeeAmount());

            $order->setBaseFeeAmountInvoiced($order->getBaseFeeAmountInvoiced() + $invoice->getBaseFeeAmount());
            $order->save();
        }
        return $this;
    }

    public function creditmemoSaveAfter(Varien_Event_Observer $observer)
    {
        /* @var $creditmemo Mage_Sales_Model_Order_Creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
        if ($creditmemo->getFeeAmount() && $creditmemo->getFeeAmount() > $order->getFeeAmountRefunded()) {

            $order->setFeeAmountRefunded($order->getFeeAmountRefunded() + $creditmemo->getFeeAmount());
            $order->setBaseFeeAmountRefunded($order->getBaseFeeAmountRefunded() + $creditmemo->getBaseFeeAmount());
        }
        return $this;
    }
}
