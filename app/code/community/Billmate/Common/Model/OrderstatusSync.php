<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-12-01
 * Time: 14:49
 */
class Billmate_Common_Model_OrderstatusSync
{
    public function checkOrders(){
        Mage::log('orderstatuscheck');
        if(Mage::getStoreConfig('billmate/fraud_check/order_status_check')) {
            $orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('status', array('in' => Mage::getStoreConfig('billmate/fraud_check/checkstatus').',payment_review'));
            foreach ($orders as $order) {
                $payment = $order->getPayment();
                $paymentCode = $payment->getMethodInstance()->getCode();
                $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');

                $values = array(
                    'number' => $invoiceId
                );

                $billmate = Mage::helper('billmatecommon')->getBillmate();

                $result = $billmate->getPaymentinfo($values);
                Mage::log('resultPaymentInfo'.$result);
                if(isset($result['code'])){
                    return false;
                }
                switch (strtolower($result['PaymentData']['status'])) {
                    case 'created':
                        $order->addStatusHistoryComment('',Mage::getStoreConfig('payment/'.$paymentCode.'/order_status'));
                        $order->save();
                        break;
                    case 'pending':
                        $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('The order is reviewed by Billmate'),'payment_review');
                        $order->save();
                        break;
                    case 'denied':
                        $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('The order is denied by Billmate'),(Mage::getStoreConfig('billmate/fraud_check/denied_status')) ? Mage::getStoreConfig('billmate/fraud_check/deniedstatus') : 'cancelled');
                        $order->save();
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
                            $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('The order is marked as activated in BillmateOnline'), (Mage::getStoreConfig('billmate/fraud_check/activatedstatus')) ? Mage::getStoreConfig('billmate/fraud_check/activatedstatus') : 'proceccing');
                            $order->save();
                        }
                        break;
                    case 'cancelled':
                        $order->addStatusHistoryComment(Mage::helper('billmatecommon')->__('The order is marked as canceled in BillmateOnline'),(Mage::getStoreConfig('billmate/fraud_check/denied_status')) ? Mage::getStoreConfig('billmate/fraud_check/deniedstatus') : 'cancelled');
                        $order->save();
                        break; 


                }
            }
            return $this;
        }
    }
}