<?php

class Billmate_Bankpay_BankpayController extends Mage_Core_Controller_Front_Action{
    /**
     * When a customer chooses Billmate on Checkout/Payment page
     *
     */
    public function notifyAction(){
        //global $_POST, $_GET;

        $_POST = file_get_contents('php://input');

        $_POST = empty($_POST) ? $_GET : $_POST;
        $k = Mage::helper('billmatebankpay')->getBillmate(true,false);
        $session = Mage::getSingleton('checkout/session');
        $data = $k->verify_hash($_POST);

        $quote = Mage::getModel('sales/quote')->load($data['orderid']);

        $session->setData('last_real_order_id', $quote->getReservedOrderId());


        $order = Mage::getModel('sales/order')->loadByIncrementId($quote->getReservedOrderId());
        Mage::log('order_id'.$order->getId());
        if($data['status'] == 'Cancelled' && !$order->isCanceled()){

            if (!$order->isCanceled() && !$order->hasInvoices()) {

                $message = Mage::helper('billmatecardpay')->__('Order canceled by user');
                $order->cancel();
                $order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, $message);
                $order->save();

                // Rollback stock
                // Mage::helper('billmatecardpay')->rollbackStockItems($order);
            }

            //$session->setQuoteId($session->getBillmateQuoteId(true));
            if ($data['orderid']) {
                $quote = Mage::getModel('sales/quote')->load($data['orderid']);
                if ($quote->getId()) {
                    $quote->setIsActive(true)->save();
                    $session->setQuoteId($quote->getId());
                }

                $quoteItems = $quote->getAllItems();
                if( sizeof( $quoteItems ) <=0 ){
                    $items = $order->getAllItems();
                    if( $items ){
                        foreach( $items as $item ){
                            $product1 = Mage::getModel('catalog/product')->load($item->getProductId());
                            $qty = $item->getQtyOrdered();
                            $quote->addProduct($product1, $qty);
                        }
                    }else{
                        $quote->setIsActive(false)->save();

                    }
                    $quote->setReservedOrderId(null);
                    $quote->collectTotals()->save();
                }
            }
            die('OK');
        }
        if($order->isCanceled()){
            die('OK');
        }
        try{

	        $payment = $order->getPayment();
            $status = Mage::getStoreConfig('payment/billmatebankpay/order_status');

            if( $order->getStatus() == $status ){
                $session->setOrderId($quote->getReservedOrderId());
                $session->setQuoteId($session->getBillmateQuoteId(true));
                Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
                $order->sendNewOrderEmail();
                $session->unsRebuildCart();

                $this->_redirect('checkout/onepage/success', array('_secure'=>true));
                return;
            }


            $info = $payment->getMethodInstance()->getInfoInstance();
            $info->setAdditionalInformation('invoiceid',$data['number']);

            $data1 = $data;
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed'.'<br/>Billmate status: '.$data1['status'].'<br/>'.'Transaction ID: '.$data1['number']));

            $payment->setTransactionId($data['number']);
	        $payment->setIsTransactionClosed(0);
	        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,null,false, false);
	        $transaction->setOrderId($order->getId())->setIsClosed(0)->setTxnId($data['number'])->setPaymentId($payment->getId())
	                    ->save();
	        $payment->save();
            $isCustomerNotified = false;
            $order->setState('new', $status, '', $isCustomerNotified);
            $order->save();
            $order->sendNewOrderEmail();

            $this->clearAllCache();

        }catch(Exception $ex){
            Mage::log($ex->getMessage());
        }
    }
    function clearAllCache(){
        try {
            $cacheTypes = Mage::app()->useCache();
            foreach ($cacheTypes as $type => $option) {
                Mage::app()->getCacheInstance()->cleanType($type);
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }

    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->getBillmateStandardQuoteId($session->getQuoteId());
		$session->setBillmateCheckOutUrl($_SERVER['HTTP_REFERER']);
        $orderIncrementId = $session->getBillmateStandardQuoteId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());;
		
		$status = 'pending_payment';
		$isCustomerNotified = false;
		$order->setState('new', $status, '', $isCustomerNotified);
		$order->save();

		$session->getQuote()->setIsActive(false)->save();
		$session->clear();	

        $this->getResponse()->setBody($this->getLayout()->createBlock('billmatebankpay/bankpay_redirect')->toHtml());
        $session->unsQuoteId();
        $session->unsRedirectUrl();
    }
    /**
     * When a customer cancel payment from paypal.
     */
    public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $order = Mage::getModel('sales/order');
		$message = 'Order canceled by user';
        $order_id = $session->getLastRealOrderId();
        $order->loadByIncrementId($order_id);

        if (!$order->isCanceled() && !$order->hasInvoices()) {
            $order->cancel();
            $order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, $message);
            $order->save();

            // Rollback stock
           // Mage::helper('billmatecardpay')->rollbackStockItems($order);
        }
		
        //$session->setQuoteId($session->getBillmateQuoteId(true));
        if ($quoteId = $session->getLastQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                $session->setQuoteId($quoteId);
            }
			
			$quoteItems = $quote->getAllItems();
			if( sizeof( $quoteItems ) <=0 ){
				$items = $order->getAllItems();
				if( $items ){
					foreach( $items as $item ){
						$product1 = Mage::getModel('catalog/product')->load($item->getProductId());
						$qty = $item->getQtyOrdered();
						$quote->addProduct($product1, $qty);
					}
				}else{
					$quote->setIsActive(false)->save();
					$this->_redirect('/');
				}
                $quote->setReservedOrderId(null);
				$quote->collectTotals()->save();
			}
        }
		$checkouturl = $session->getBillmateCheckOutUrl();
		$checkouturl = empty($checkouturl)?Mage::helper('checkout/url')->getCheckoutUrl():$checkouturl;
        $session->unsRebuildCart();
		Mage::getSingleton('core/session')->setFailureMsg('order_failed');
		Mage::getSingleton('checkout/session')->setFirstTimeChk('0');
		Mage::dispatchEvent('sales_model_service_quote_submit_failure', array('order'=>$order, 'quote'=>$quote));
        header('location:'. $checkouturl);
		exit;
    }

    /**
     * when Billmate returns
     * The order information at this point is in POST
     * variables.  However, you don't want to "process" the order until you
     * get validation from the IPN.
     */
    public function successAction()
    {
        $k = Mage::helper('billmatebankpay')->getBillmate(true, false);
        $session = Mage::getSingleton('checkout/session');
        $orderIncrementId = $session->getBillmateStandardQuoteId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());

        $status = Mage::getStoreConfig('payment/billmatebankpay/order_status');
        
		$session->setLastSuccessQuoteId($session->getBillmateQuoteId());
        $session->setLastQuoteId($session->getBillmateQuoteId());
        $session->setLastOrderId($session->getLastOrderId());

		if(empty($_POST)) $_POST = $_GET;
        //$_POST['data'] = json_decode(stripslashes($_POST['data']),true);
        $data = $k->verify_hash($_POST);

        if( $order->getState() == $status ){
            $session->setOrderId($data['orderid']);
            $session->setQuoteId($session->getBillmateQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
            $order->sendNewOrderEmail();
            $session->unsRebuildCart();

            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
            return;
        }
		
        if( isset($data['code']) || isset($data['error'])){
            
            $status = 'pending_payment';
            $comment = $this->__('Unable to complete order, Reason : ').$data['message'] ;
            $isCustomerNotified = true;
            $order->setState('new', $status, $comment, $isCustomerNotified);
            $order->save();
            $order->sendOrderUpdateEmail(true, $comment);
            
            Mage::getSingleton('core/session')->addError($this->__('Unable to process with payment gateway :').$data['message']);
            if(isset($data['error'])){
                Mage::log('hash:'.$data['hash'].' recieved'.$data['hash_recieved']);
            }
            $checkouturl = $session->getBillmateCheckOutUrl();
            $checkouturl = empty($checkouturl)?Mage::helper('checkout/url')->getCheckoutUrl():$checkouturl;
            $this->_redirect($checkouturl);
        }else{

            
			$status = Mage::getStoreConfig('payment/billmatebankpay/order_status');
			
			$isCustomerNotified = true;
			$order->setState('new', $status, '', $isCustomerNotified);
            $payment = $order->getPayment();
            $info = $payment->getMethodInstance()->getInfoInstance();
            $info->setAdditionalInformation('invoiceid',$data['number']);

            $values['PaymentData'] = array(
                'number' => $data['number'],
                'orderid' => $order->getIncrementId()
            );
            $data1 = $k->updatePayment($values);
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed'.'<br/>Billmate status: '.$data1['status'].'<br/>'.'Transaction ID: '.$data1['number']));
	        $payment->setTransactionId($data['number']);
	        $payment->setIsTransactionClosed(0);
	        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,null,false, false);
	        $transaction->setOrderId($order->getId())->setIsClosed(0)->setTxnId($data['number'])->setPaymentId($payment->getId())
	                    ->save();
	        $payment->save();
			$order->save();

            $session->setQuoteId($session->getBillmateStandardQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
			$order->sendNewOrderEmail(); 
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        }
    }
}