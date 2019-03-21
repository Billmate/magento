<?php

class Billmate_Common_Model_OrderstatusSync
{
    /**
     * @return $this
     */
    public function checkOrders()
    {
        if (Mage::getStoreConfig('billmate/fraud_check/order_status_check')) {

            $statusesToCheck = array();

            if(strlen(Mage::getStoreConfig('billmate/fraud_check/checkstatus'))) {
                $statusesToCheck = explode(',', Mage::getStoreConfig('billmate/fraud_check/checkstatus'));
            }
            array_push($statusesToCheck,'payment_review');


            $stores = Mage::app()->getStores();
            foreach ($stores as $key => $value) {
                foreach (array('billmateinvoice', 'billmatebankpay', 'billmatecardpay', 'billmatepartpayment') as $payment) {
                    if (!in_array(Mage::getStoreConfig('payment/' . $payment . '/order_status',$key), $statusesToCheck)) {
                        array_push($statusesToCheck, Mage::getStoreConfig('payment/' . $payment . '/order_status',$key));
                        if ($payment == 'billmateinvoice' || $payment == 'billmatepartpayment') {
                            array_push($statusesToCheck, Mage::getStoreConfig('payment/' . $payment . '/pending_status',$key));
                        }
                    }
                }
            }


            array_push($statusesToCheck,'pending_payment');

            $orders = Mage::getModel('sales/order')->getCollection()->addAttributeToSelect('*')->addFieldToFilter('status', array('in' => $statusesToCheck));
            foreach ($orders as $order) {
                $payment = $order->getPayment();
                $storeId = $order->getStoreId();

                $paymentCode = $payment->getMethodInstance()->getCode();

                if(!in_array($paymentCode,array('billmateinvoice','billmatebankpay','billmatecardpay','billmatepartpayment')))
                    continue;

                $invoiceId = $payment->getMethodInstance()
                                    ->getInfoInstance()
                                    ->getAdditionalInformation(
                                        Billmate_BillmateCheckout_Model_Billmatecheckout::BM_INVOICE_NUMBER_CODE_PARAM
                                    );

                if(!$invoiceId)
                    continue;
                $values = array(
                    'number' => $invoiceId
                );

                if(!$invoiceId)
                    continue;
                $billmate = Mage::helper('billmatecommon')->getBillmate(Mage::getStoreConfig('payment/'.$paymentCode.'/test_mode',$storeId));

                $result = $billmate->getPaymentinfo($values);
                if(isset($result['code'])){
                    continue;
                }
                $logid = $result['apiLogsid'];
                switch (strtolower($result['PaymentData']['status'])) {
                    case 'created':
                        if($order->getStatus() != Mage::getStoreConfig('payment/'.$paymentCode.'/order_status',$storeId)) {
                            $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('Order is created and approved. (Data from Billmate API, API log ID %s)',$logid), Mage::getStoreConfig('payment/' . $paymentCode . '/order_status',$storeId));
                            $order->save();
                        }
                        break;
                    case 'pending':
                        if($order->getStatus() != Mage::getStoreConfig('payment/'.$paymentCode.'/pending_status',$storeId) && $order->getStatus() != 'payment_review') {
                            $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('Order is pending. (Data from Billmate API, API log ID %s)',$logid),(Mage::getStoreConfig('payment/'.$paymentCode.'/pending_status',$storeId)) ? Mage::getStoreConfig('payment/'.$paymentCode.'/pending_status',$storeId) : 'payment_review');
                            $order->save();
                        }
                        break;
                    case 'denied':
                        if($order->getStatus() != Mage::getStoreConfig('billmate/fraud_check/denied_status') && $order->getStatus() != 'canceled') {
                            $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('Order is denied. (Data from Billmate API, API log id %s)',$logid), (Mage::getStoreConfig('billmate/fraud_check/deniedstatus',$storeId)) ? Mage::getStoreConfig('billmate/fraud_check/deniedstatus',$storeId) : 'canceled');
                            $order->save();
                        }
                        break;
                    case 'factoring':
                    case 'paid':
                    case 'partpayment':
                    case 'handling':
                        if(Mage::getStoreConfig('billmate/fraud_check/createinvoice')){
                            if($order->canInvoice()){
                                $invoice = Mage::getModel('sales/service_order',$order)->prepareInvoice();
                                if($invoice->getTotalQty()){
                                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                                    $invoice->register();
                                    $transaction = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
                                    $transaction->save();
                                }
                            }
                        } else {
                            if($order->getStatus() != Mage::getStoreConfig('billmate/fraud_check/activatedstatus',$storeId) && $order->getStatus() != 'processing') {
                                $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('Order is paid. (Data from Billmate API, API log ID %s)',$logid), (Mage::getStoreConfig('billmate/fraud_check/activatedstatus',$storeId)) ? Mage::getStoreConfig('billmate/fraud_check/activatedstatus',$storeId) : 'processing');
                                $order->save();
                            }
                        }
                        break;
                    case 'cancelled':
                        if($order->getStatus() != Mage::getStoreConfig('billmate/fraud_check/cancelstatus',$storeId) && $order->getStatus() != 'canceled') {
                            $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('Order is canceled. (Data from Billmate API, API log ID %s)',$logid), (Mage::getStoreConfig('billmate/fraud_check/cancelstatus',$storeId)) ? Mage::getStoreConfig('billmate/fraud_check/cancelstatus',$storeId) : 'cancelled');
                            $order->save();
                        }
                        break; 


                }
            }
            return $this;
        }
    }
}