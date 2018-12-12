<?php

class Billmate_Common_Model_Observer extends Mage_Core_Model_Abstract
{

    public function adminSystemConfigChangedSectionBillmate()
    {

    }

    public function salesOrderPlaceAfter(Varien_Event_Observer $observer)
    {
        Mage::getSingleton('checkout/session')->unsBillmateHash();
    }

    public function redirectToCancelUrl(Varien_Event_Observer $observer)
    {
        $controllerAction = $observer->getEvent()->getControllerAction();
        $session = Mage::getSingleton('checkout/session');
        Mage::log('time observer'.date('Y-m-d H:i:s'));
        if($session->getRebuildCart()){

            $order = Mage::getModel('sales/order');
            $message = 'Order canceled by observer';
            $order_id = $session->getLastRealOrderId();
            $order->loadByIncrementId($order_id);

            if (!$order->isCanceled() && !$order->hasInvoices()) {
                $order->cancel();
                $order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, $message);
                $order->save();
            }

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
        }
    }


}