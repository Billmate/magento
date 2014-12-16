<?php

class Billmate_BillmateInvoice_Model_Observer extends Mage_Core_Model_Abstract
{
	public function activate($observer)
	{		
		$order = $observer->getEvent()->getOrder();

		$session = Mage::getSingleton("core/session",  array("name"=>"frontend"));
		$liveid = $session->getData("billmateinvoice_id");
		$session->unsetData('billmateinvoice_id');
        $k = Mage::helper('billmateinvoice')->getBillmate(true, false);
		$k->UpdateOrderNo($liveid, $order->getIncrementId());
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
        $info->save();
    }
}
