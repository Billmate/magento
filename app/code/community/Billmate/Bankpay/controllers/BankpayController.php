<?php

class Billmate_Bankpay_BankpayController extends Mage_Core_Controller_Front_Action{
    /**
     * When a customer chooses Paypal on Checkout/Payment page
     *
     */
    public function notifyAction(){
        global $_POST, $_GET;

        $input = file_get_contents("php://input");
        //$input = '{"status": 0, "order_id": "100000468", "error_message": "Approved", "amount": "301000", "currency": "SEK", "mac": "d59232a71f5cbe44186d4d7b314e5171b7c7d99ffd8d20d77a2c283081cc94c8", "time": "2014-05-10 07:15:56.043216", "test": "static data", "merchant_id": "7270", "pay_method": "handelsbanken", "trans_id": "800674228"}';
        $data = $_POST = $_GET = (array)json_decode($input);

        $session = Mage::getSingleton('checkout/session');
        $session->setData('last_real_order_id', $data['order_id']);

        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());;

        $gateway =  Mage::getSingleton('billmatebankpay/gateway');
        try{
            $result = $gateway->makePayment($order);

            $status = Mage::getStoreConfig('payment/billmatebankpay/order_status');

            $order->addStatusHistoryComment(Mage::helper('payment')->__('Order completed by ipn.'));
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Transaction Id: #'.$data['trans_id']));
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Billmate Id: #'.$result[0]));

            $isCustomerNotified = false;
            $order->setState($status, $status, '', $isCustomerNotified);
            $order->save();
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
        $session = Mage::getSingleton('checkout/session');
        $orderIncrementId = $session->getBillmateStandardQuoteId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());

        $status = Mage::getStoreConfig('payment/billmatebankpay/order_status');
        
		$session->setLastSuccessQuoteId($session->getBillmateQuoteId());
        $session->setLastQuoteId($session->getBillmateQuoteId());
        $session->setLastOrderId($session->getLastOrderId());

		if(empty($_POST)) $_POST = $_GET;
        Mage::log(print_r($_POST,true));
        die();
        if( $order->getState() == $status ){
            $session->setLastSuccessQuoteId($session->getLastRealOrderId());
            $session->setOrderId($_POST['order_id']);
            $session->setQuoteId($session->getBillmateQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
            $order->sendNewOrderEmail();

            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
            return;
        }
		
        if( !empty($_POST['status']) && $_POST['status'] != 0 ){
            
            $status = 'pending_payment';
            $comment = $this->__('Unable to complete order, Reason : ').$_POST['error_message'] ;
            $isCustomerNotified = true;
            $order->setState('new', $status, $comment, $isCustomerNotified);
            $order->save();
            $order->sendOrderUpdateEmail(true, $comment);
            
            Mage::getSingleton('core/session')->addError($this->__('Unable to process with payment gateway :').$_POST['error_message']);

            $this->_redirect(Mage::getStoreConfig('payment/billmatebankpay/bank_error_page'));
        }else{
            
            //$gateway =  Mage::getSingleton('billmatebankpay/gateway');
            //$result = $gateway->makePayment($order);
            
			$status = Mage::getStoreConfig('payment/billmatebankpay/order_status');
			
			$isCustomerNotified = false;
			$order->setState('new', $status, '', $isCustomerNotified);

            $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed.'));
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Transaction Id: #'.$_POST['trans_id']));
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Billmate Id: #'.$result[0]));

			$order->save();

            $session->setQuoteId($session->getBillmateStandardQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
			$order->sendNewOrderEmail(); 
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        }
    }
}