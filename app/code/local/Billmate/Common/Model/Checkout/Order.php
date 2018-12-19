<?php
class Billmate_Common_Model_Checkout_Order extends Varien_Object
{
    public function __construct()
    {

    }

    /**
     * @param $quote
     *
     * @return bool|false|Mage_Core_Model_Abstract
     */
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
}