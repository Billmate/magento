<?php

class Billmate_CustomPay_CardController extends Billmate_CustomPay_Controller_InstantMethods
{
    const PAYMENT_METHOD_CODE = 'bmcustom_card';

    public function cancelAction()
    {
        $this->doProcessCancel();
        $this->getResponse()->setRedirect($this->getCheckoutUrl());
    }

    public function callbackAction()
    {
        return $this->doCallbackProcess();
    }

    public function acceptAction()
    {
        /** @var  $quote Mage_Sales_Model_Quote */
        $quote = $this->getCheckoutSession()->getQuote();

        $bmConnection = $this->getBmConnection();
        $bmRequestData = $this->getBmRequestData();

        $bmResponseData = $bmConnection->verify_hash($bmRequestData);

        if (isset($bmResponseData['code'])) {
            $this->getCoreSession()->addError($this->getHelper()->__('Something went wrong with your payment'));
            $this->getResponse()->setRedirect($this->getCheckoutUrl());
            return;
        }

        switch (strtolower($bmResponseData['status'])) {
            case 'pending':
                $order = $this->placeOrder($quote);
                if ($order && $order->getStatus()) {
                    if($order->getStatus() != $this->getMethodsHelper()->getDefaultOrderStatus(self::PAYMENT_METHOD_CODE)) {
                        $order->addStatusHistoryComment($this->getHelper()->__('Order processing completed' . '<br/>Billmate status: ' . $bmResponseData['status'] . '<br/>' . 'Transaction ID: ' . $bmResponseData['number']));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);

                        $order->save();
                        $this->addTransaction($order, $bmResponseData);

                        $this->sendNewOrderMail($order);
                    } else {

                        if (isset($_GET['billmate_checkout']) && $_GET['billmate_checkout'] == 1) {
                            $this->_redirect('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $this->getCheckoutSession()->getBillmateHash()),'_secure' => true));
                            return;
                        }
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        return;
                    }
                } else {
                    $this->getCoreSession()->addError($this->getHelper()->__('Something went wrong with your order'));
                    $this->_redirect($this->getCheckoutUrl());
                    return;
                }
                break;
            case 'paid':
                $order = $this->placeOrder($quote);
                if ($order) {
                    if ($order->getStatus() != $this->getDefOrderStatus()) {
                        $order->addStatusHistoryComment($this->getHelper()->__('Order processing completed' . '<br/>Billmate status: ' . $bmResponseData['status'] . '<br/>' . 'Transaction ID: ' . $bmResponseData['number']));
                        $order->setState('new', $this->getDefOrderStatus(), '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);

                        $order->save();

                        $this->addTransaction($order, $bmResponseData);
                        $this->sendNewOrderMail($order);
                    } else {
                        $order->addStatusHistoryComment($this->getHelper()->__('Order processing completed' . '<br/>Billmate status: ' . $bmResponseData['status'] . '<br/>' . 'Transaction ID: ' . $bmResponseData['number']));
                        $order->setState('new', $this->getDefOrderStatus(), '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);

                        $order->save();
                        if(isset($_GET['billmate_checkout']) && $_GET['billmate_checkout'] == 1){
                            $this->_redirect('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $this->getCheckoutSession()->getBillmateHash()),'_secure' => true));
                            return;

                        }
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        return;
                    }

                } else {
                    $this->getCoreSession()->addError($this->getHelper()->__('Something went wrong with your order'));
                    $this->_redirect($this->getCheckoutUrl());
                    return;
                }
                break;
            case 'cancelled':
                $this->getCoreSession()->addError($this->getHelper()->__('You have cancelled your payment, do you want to use another payment method?'));
                $this->_redirect($this->getCheckoutUrl());
                return;
                break;
            case 'failed':
                $this->getCoreSession()->addError($this->getHelper()->__('Something went wrong with your payment'));
                $this->_redirect($this->getCheckoutUrl());
                return;
                break;

        }
        if(isset($_GET['billmate_checkout']) && $_GET['billmate_checkout'] == 1){
            $this->_redirect('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $this->getCheckoutSession()->getBillmateHash()),'_secure' => true));
            return;

        }
        $this->_redirect('checkout/onepage/success',array('_secure' => true));
        return;


    }

    public function successAction()
    {
        $bmConnection = $this->getBmConnection();
        $session = Mage::getSingleton('checkout/session');
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());

        $status = $this->getDefOrderStatus();

		$session->setLastSuccessQuoteId($session->getBillmateQuoteId());
        $session->setLastQuoteId($session->getBillmateQuoteId());
        $session->setLastOrderId($session->getLastOrderId());

        $bmRequestData = $this->getBmRequestData();
        $bmResponseData = $bmConnection->verify_hash($bmRequestData);

        if ( $order->getStatus() == $status ) {
            $session->setLastSuccessQuoteId($session->getLastRealOrderId());
            $session->setOrderId($bmResponseData['orderid']);
            $session->setQuoteId($session->getBillmateQuoteId(true));
            $this->getCheckoutSession()->getQuote()->setIsActive(false)->save();
            $session->unsRebuildCart();

            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
            return;
        }
        
        if (isset($bmResponseData['code']) || isset($bmResponseData['error'])) {

            $status = 'pending_payment';
            $comment = $this->__('Unable to complete order, Reason : ').$bmResponseData['message'] ;
            $isCustomerNotified = true;
            $order->setState('new', $status, $comment, $isCustomerNotified);
            $order->save();

            if ($this->getHelper()->useEmailQueue()) {
                $order->queueOrderUpdateEmail(true, $comment);
            } else {
                $order->sendOrderUpdateEmail(true,$comment);
            }
            
            $this->getCoreSession()->addError($this->__('Unable to process with payment gateway :').$bmResponseData['message']);
            if(isset($bmResponseData['code'])){
                Mage::log('hash:'.$bmResponseData['hash'].' recieved'.$bmResponseData['hash_received']);
            }
            $checkoutUrl = $session->getBillmateCheckOutUrl();
            $checkoutUrl = empty($checkoutUrl)?$this->getCheckoutUrl():$checkoutUrl;
            $this->_redirect($checkoutUrl);
        } else {
			$status = $this->getDefOrderStatus();
			$isCustomerNotified = true;
			$order->setState('new', $status, '', $isCustomerNotified);
            $payment = $order->getPayment();
            $info = $payment->getMethodInstance()->getInfoInstance();
            $info->setAdditionalInformation('invoiceid',$bmResponseData['number']);
            $data1 = $bmResponseData;

            $session->unsRebuildCart();

            $order->addStatusHistoryComment($this->getHelper()->__('Order processing completed'.'<br/>Billmate status: '.$data1['status'].'<br/>'.'Transaction ID: '.$data1['number']));

            $payment->setTransactionId($bmResponseData['number']);
	        $payment->setIsTransactionClosed(0);
	        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,null,false, false);
	        $transaction->setOrderId($order->getId())->setIsClosed(0)->setTxnId($bmResponseData['number'])->setPaymentId($payment->getId())
	                    ->save();
	        $payment->save();

			$order->save();
            $session->setQuoteId($session->getBillmateQuoteId(true));
            $this->getCheckoutSession()->getQuote()->setIsActive(false)->save();

            $this->sendNewOrderMail($order);

            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        }
    }
}