<?php
class Billmate_CustomPay_Model_Checkout_Order extends Varien_Object
{
    /**
     * @var Billmate_CustomPay_Helper_Data
     */
    protected $helper;

    /**
     * @var Mage_Sales_Model_Order
     */
    protected $order;

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $quote;

    /**
     * @var array
     */
    protected $bmResponseData;


    public function __construct()
    {
        $this->helper = Mage::helper('billmatecustompay');
    }

    /**
     * @param $quote
     *
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function placeOrder($quote)
    {
        /** @var  $quote Mage_Sales_Model_Quote */
        $orderModel = Mage::getModel('sales/order');
        $orderModel->load($quote->getId(), 'quote_id');
        if ($orderModel->getId()) {
            $this->setQuote($quote);
            $this->setOrder($orderModel);
            return $orderModel;
        }

        $quote->collectTotals();
        $service = Mage::getModel('sales/service_quote',$quote);
        $service->submitAll();
        $this->getCheckoutSession()
            ->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();
        $order = $service->getOrder();
        if ($order) {
            $this->getCheckoutSession()
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());
        }
        $quote->setIsActive(false)->save();

        if (!$order) {
            return false;
        }

        $this->setQuote($quote);
        $this->setOrder($order);

        return $order;
    }

    /**
     * @param $message
     * @param $status
     *
     * @return $this
     */
    public function addComment($message, $status)
    {
        $order = $this->getOrder();
        $order->addStatusHistoryComment($message);
        $order->setState('new', $status, '', false);
        $order->save();
        return $this;
    }

    /**
     * @param $order
     * @param $bmResponseData
     */
    public function addTransaction($bmResponseData)
    {
        $order = $this->getOrder();
        $payment = $order->getPayment();
        $info = $payment->getMethodInstance()->getInfoInstance();
        $info->setAdditionalInformation('invoiceid', $bmResponseData['number']);

        $payment->setTransactionId($bmResponseData['number']);
        $payment->setIsTransactionClosed(0);
        $transaction = $payment->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
            null,
            false,
            false
        );
        $transaction->setOrderId($order->getId())
            ->setIsClosed(0)
            ->setTxnId($bmResponseData['number'])
            ->setPaymentId($payment->getId())
            ->save();
        $payment->save();
        return $this;
    }

    /**
     * @param $order
     */
    public function sendNewOrderMail()
    {
        if ($this->getHelper()->useEmailQueue()) {
            $this->getOrder()->queueNewOrderEmail();
        } else {
            $this->getOrder()->sendNewOrderEmail();
        }
        return $this;
    }

    public function setBmResponseData($bmRespData)
    {
        $this->bmResponseData = $bmRespData;
        return $this;
    }

    public function getBmResponseData()
    {
        return $this->bmResponseData;
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->quote;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     */
    public function setQuote($quote)
    {
        $this->quote = $quote;
    }

    /**
     * @return Billmate_CustomPay_Helper_Data
     */
    public function getHelper()
    {
        return $this->helper;
    }


    /**
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }
}