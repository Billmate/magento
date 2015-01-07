<?php

class Billmate_Cardpay_CardpayController extends Mage_Core_Controller_Front_Action{
    /**
     * When a customer chooses Billmate on Checkout/Payment page
     *
     */

    public function notifyAction(){
        if(empty($_POST)) $_POST = $_GET;

        $k = Mage::helper('billmatecardpay')->getBillmate(true,false);
        $session = Mage::getSingleton('checkout/session');
        $data = $k->verify_hash($_POST);
        $session->setData('last_real_order_id', $data['orderid']);

        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());;

        try{


            $status = Mage::getStoreConfig('payment/billmatecardpay/order_status');

            $order->addStatusHistoryComment(Mage::helper('payment')->__('Order completed by ipn.'));
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Payment Status: #'.$data['status']));
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Billmate Id: #'.$data['number']));

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
        $session->setBillmateQuoteId($session->getQuoteId());
		$session->setBillmateCheckOutUrl($_SERVER['HTTP_REFERER']);

        $orderIncrementId = $session->getBillmateQuoteId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());;
		
		$status = 'pending_payment';
		$isCustomerNotified = false;
		$order->setState('new', $status, '', $isCustomerNotified);
		$order->save();

		$session->getQuote()->setIsActive(false)->save();
		$session->clear();	
		
        $this->getResponse()->setBody($this->getLayout()->createBlock('billmatecardpay/cardpay_redirect')->toHtml());
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
     * when paypal returns
     * The order information at this point is in POST
     * variables.  However, you don't want to "process" the order until you
     * get validation from the IPN.
     */
    public function successAction()
    {
        $k = Mage::helper('billmatecardpay')->getBillmate(true, false);
        $session = Mage::getSingleton('checkout/session');
        $orderIncrementId = $session->getBillmateQuoteId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());


        $status = Mage::getStoreConfig('payment/billmatecardpay/order_status');

		$session->setLastSuccessQuoteId($session->getBillmateQuoteId());
        $session->setLastQuoteId($session->getBillmateQuoteId());
        $session->setLastOrderId($session->getLastOrderId());

		if(empty($_POST)) $_POST = $_GET;
        $data = $k->verify_hash($_POST);
        if( $order->getState() == $status ){

            $session->setLastSuccessQuoteId($session->getLastRealOrderId());
            $session->setOrderId($data['orderid']);
            $session->setQuoteId($session->getBillmateQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
            $order->sendNewOrderEmail();
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));

            return;
        }
        
        if(isset($data['code']) || isset($data['error'])){
            
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

            $this->_redirect(Mage::getStoreConfig('payment/billmatecardpay/card_error_page'));
        }else{

			$status = Mage::getStoreConfig('payment/billmatecardpay/order_status');
			
			$isCustomerNotified = false;
			$order->setState('new', $status, '', $isCustomerNotified);

            $order->addStatusHistoryComment(Mage::helper('payment')->__('Order completed by ipn.'));
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Payment Status: #'.$data['status']));
            $order->addStatusHistoryComment(Mage::helper('payment')->__('Billmate Id: #'.$data['number']));

			$order->save();
            $session->setQuoteId($session->getBillmateQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
			$order->sendNewOrderEmail(); 

            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        }
    }
}