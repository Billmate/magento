<?php

class Billmate_Cardpay_CardpayController extends Mage_Core_Controller_Front_Action{
    /**
     * When a customer chooses Billmate on Checkout/Payment page
     *
     */

    public function notifyAction(){
        $_POST = file_get_contents('php://input');
        $_POST = empty($_POST) ? $_GET : $_POST;
        $k = Mage::helper('billmatecardpay')->getBillmate(true,false);
        $session = Mage::getSingleton('checkout/session');
        $data = $k->verify_hash($_POST);

        //$quote = Mage::getModel('sales/quote')->load($data['orderid']);

        $session->setData('last_real_order_id', $data['orderid']);


        $order = Mage::getModel('sales/order')->loadByIncrementId($data['orderid']);
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
                $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
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
                $session->unsRebuildCart();

            }
            die('OK');
        }
        if($order->isCanceled()){
            die('OK');
        }

        try{


            $status = Mage::getStoreConfig('payment/billmatecardpay/order_status');

            if( $order->getStatus() == $status ){

                $session->setLastSuccessQuoteId($order->getQuoteId());
                $session->setOrderId($data['orderid']);
                $session->setQuoteId($order->getQuoteId());
                Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
                /*
                $magentoVersion = Mage::getVersion();
                if(version_compare($magentoVersion,'1.9.1','>='))
                    $order->queueNewOrderEmail();
                else
                    $order->sendNewOrderEmail();
                */
                $session->unsRebuildCart();
                die('OK');

            }
            $payment = $order->getPayment();
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
            $session->unsRebuildCart();

            $isCustomerNotified = false;
            $order->setState('new', $status, '', $isCustomerNotified);
            $order->save();
            $magentoVersion = Mage::getVersion();
            $isEE = Mage::helper('core')->isModuleEnabled('Enterprise_Enterprise');
            if(version_compare($magentoVersion,'1.9.1','>=') && !$isEE)
                $order->queueNewOrderEmail();
            else
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
        $k = Mage::helper('billmatebankpay')->getBillmate(true, false);

        if(empty($_POST)) $_POST = $_GET;

        $data = $k->verify_hash($_POST);


        if(isset($data['code'])){
            Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Unfortunately your card payment was not processed with the provided card details. Please try again or choose another payment method.'));
            $this->getResponse()->setRedirect(Mage::helper('checkout/url')->getCheckoutUrl());
            return;
        }
        if(isset($data['status'])){
            switch(strtolower($data['status'])){
                case 'cancelled':
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('The card payment has been canceled. Please try again or choose a different payment method.'));
                    $this->getResponse()->setRedirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;
                    break;
                case 'failed':
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Unfortunately your card payment was not processed with the provided card details. Please try again or choose another payment method.'));
                    $this->getResponse()->setRedirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;
                    break;
            }
        }
        $this->getResponse()->setRedirect(Mage::helper('checkout/url')->getCheckoutUrl());
        return;
    }

    public function callbackAction()
    {
        $_POST = file_get_contents('php://input');
        $quoteId = $this->getRequest()->getParam('billmate_quote_id');

        $_POST = empty($_POST) ? $_GET : $_POST;
        $k = Mage::helper('billmatecardpay')->getBillmate(true,false);
        $session = Mage::getSingleton('checkout/session');
        $data = $k->verify_hash($_POST);


        if(isset($data['code'])){
            Mage::log('Something went wrong billmate bank'. print_r($data,true),0,'billmate.log',true);
            return;
        }

        $quote = Mage::getModel('sales/quote')->load($quoteId);
        if(!$quote->getId()){

        }
        Mage::log('quote'.print_r($quote->getData(),true));
        Mage::log('data from billmate'.print_r($data,true));

        switch(strtolower($data['status']))
        {
            case 'pending':
                $order = $this->place($quote);

                if($order ) {
                    if($order->getStatus() != Mage::getStoreConfig('payment/billmatecardpay/order_status')) {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $data['status'] . '<br/>' . 'Transaction ID: ' . $data['number']));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->save();
                        $this->sendNewOrderMail($order);

                    }
                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Unfortunately your card payment was not processed with the provided card details. Please try again or choose another payment method.'));
                    $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;
                }
                break;
            case 'paid':
                $order = $this->place($quote);
                if($order) {

                    if($order->getStatus() != Mage::getStoreConfig('payment/billmatecardpay/order_status')) {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $data['status'] . '<br/>' . 'Transaction ID: ' . $data['number']));
                        $order->setState('new', Mage::getStoreConfig('payment/billmatecardpay/order_status'), '', false);
                        $order->save();
                        $this->addTransaction($order, $data);
                        $this->sendNewOrderMail($order);
                    }
                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Unfortunately your card payment was not processed with the provided card details. Please try again or choose another payment method.'));
                    $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;
                }
                break;
            case 'cancelled':
                Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('The card payment has been canceled. Please try again or choose a different payment method.'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                return;
                break;
            case 'failed':
                Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Unfortunately your card payment was not processed with the provided card details. Please try again or choose another payment method.'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                return;
                break;

        }
    }

    public function acceptAction()
    {
        $quoteId = Mage::getSingleton('checkout/session')->getBillmateQuoteId();

        /** @var  $quote Mage_Sales_Model_Quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $k = Mage::helper('billmatecardpay')->getBillmate(true, false);

        if(empty($_POST)) $_POST = $_GET;
        $data = $k->verify_hash($_POST);
        if(isset($data['code'])){
            Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Something went wrong with your payment'));
            $this->getResponse()->setRedirect(Mage::helper('checkout/url')->getCheckoutUrl());
            return;
        }
        switch(strtolower($data['status']))
        {
            case 'pending':
                $order = $this->place($quote);
                if($order && $order->getStatus()) {
                    if($order->getStatus() != Mage::getStoreConfig('payment/billmatecardpay/order_status')) {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $data['status'] . '<br/>' . 'Transaction ID: ' . $data['number']));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->save();
                        $this->addTransaction($order, $data);

                        $this->sendNewOrderMail($order);
                    }
                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Something went wrong with your order'));
                    $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;
                }
                break;
            case 'paid':
                $order = $this->place($quote);
                if($order) {
                    if($order->getStatus() != Mage::getStoreConfig('payment/billmatecardpay/order_status')) {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $data['status'] . '<br/>' . 'Transaction ID: ' . $data['number']));
                        $order->setState('new', Mage::getStoreConfig('payment/billmatecardpay/order_status'), '', false);
                        $order->save();

                        $this->addTransaction($order, $data);
                        $this->sendNewOrderMail($order);
                    }

                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Something went wrong with your order'));
                    $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;                }
                break;
            case 'cancelled':
                Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('You have cancelled your payment, do you want to use another payment method?'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                return;
                break;
            case 'failed':
                Mage::getSingleton('core/session')->addError(Mage::helper('billmatecardpay')->__('Something went wrong with your payment'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                return;
                break;

        }

        $this->_redirect('checkout/onepage/success',array('_secure' => true));
        return;


    }

    public function place($quote)
    {
        /** @var  $quote Mage_Sales_Model_Quote */
        $orderModel = Mage::getModel('sales/order');
        $orderModel->load($quote->getId(), 'quote_id');
        if($orderModel->getId()){
            return $orderModel;
        }
        $quote->collectTotals();
        $service = Mage::getModel('sales/service_quote',$quote);
        $service->submitAll();
        Mage::getSingleton('checkout/session')->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();
        $order = $service->getOrder();
        if($order){
            Mage::getSingleton('checkout/session')->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());

        }
        $quote->setIsActive(false)->save();
        return ($order) ? $order : false;
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
        if( $order->getStatus() == $status ){

            $session->setLastSuccessQuoteId($session->getLastRealOrderId());
            $session->setOrderId($data['orderid']);
            $session->setQuoteId($session->getBillmateQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
            /*
            $magentoVersion = Mage::getVersion();
            if(version_compare($magentoVersion,'1.9.1','>='))
                $order->queueNewOrderEmail();
            else
                $order->sendNewOrderEmail();
            */
            $session->unsRebuildCart();

            $this->_redirect('checkout/onepage/success', array('_secure'=>true));

            return;
        }
        
        if(isset($data['code']) || isset($data['error'])){
            
            $status = 'pending_payment';
            $comment = $this->__('Unable to complete order, Reason : ').$data['message'] ;
            $isCustomerNotified = true;
            $order->setState('new', $status, $comment, $isCustomerNotified);
            $order->save();
            $magentoVersion = Mage::getVersion();
            if(version_compare($magentoVersion,'1.9.1','>='))
                $order->queueOrderUpdateEmail(true, $comment);
            else
                $order->sendOrderUpdateEmail(true,$comment);

            
            Mage::getSingleton('core/session')->addError($this->__('Unable to process with payment gateway :').$data['message']);
            if(isset($data['code'])){
                Mage::log('hash:'.$data['hash'].' recieved'.$data['hash_received']);
            }
            $checkouturl = $session->getBillmateCheckOutUrl();
            $checkouturl = empty($checkouturl)?Mage::helper('checkout/url')->getCheckoutUrl():$checkouturl;
            $this->_redirect($checkouturl);
        }else{

			$status = Mage::getStoreConfig('payment/billmatecardpay/order_status');
			
			$isCustomerNotified = true;
			$order->setState('new', $status, '', $isCustomerNotified);
            $payment = $order->getPayment();
            $info = $payment->getMethodInstance()->getInfoInstance();
            $info->setAdditionalInformation('invoiceid',$data['number']);
            $data1 = $data;

            $session->unsRebuildCart();

            $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed'.'<br/>Billmate status: '.$data1['status'].'<br/>'.'Transaction ID: '.$data1['number']));

            $payment->setTransactionId($data['number']);
	        $payment->setIsTransactionClosed(0);
	        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,null,false, false);
	        $transaction->setOrderId($order->getId())->setIsClosed(0)->setTxnId($data['number'])->setPaymentId($payment->getId())
	                    ->save();
	        $payment->save();

			$order->save();
            $session->setQuoteId($session->getBillmateQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();

            $magentoVersion = Mage::getVersion();
            $isEE = Mage::helper('core')->isModuleEnabled('Enterprise_Enterprise');
            if(version_compare($magentoVersion,'1.9.1','>=') && !$isEE)
                $order->queueNewOrderEmail();
            else
                $order->sendNewOrderEmail();

            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        }
    }

    /**
     * @param $order
     * @param $data
     */
    public function addTransaction($order, $data)
    {
        $payment = $order->getPayment();
        $info = $payment->getMethodInstance()->getInfoInstance();
        $info->setAdditionalInformation('invoiceid', $data['number']);

        $payment->setTransactionId($data['number']);
        $payment->setIsTransactionClosed(0);
        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, false, false);
        $transaction->setOrderId($order->getId())->setIsClosed(0)->setTxnId($data['number'])->setPaymentId($payment->getId())
            ->save();
        $payment->save();
    }

    /**
     * @param $order
     */
    public function sendNewOrderMail($order)
    {
        $magentoVersion = Mage::getVersion();
        $isEE = Mage::helper('core')->isModuleEnabled('Enterprise_Enterprise');
        if (version_compare($magentoVersion, '1.9.1', '>=') && !$isEE)
            $order->queueNewOrderEmail();
        else
            $order->sendNewOrderEmail();
    }
}