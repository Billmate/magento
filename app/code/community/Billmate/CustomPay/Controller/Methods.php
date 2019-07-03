<?php

abstract class Billmate_CustomPay_Controller_Methods extends Mage_Core_Controller_Front_Action
{
    /**
     * @var Billmate_CustomPay_Helper_Data
     */
    protected $helper;

    /**
     * @var Billmate_CustomPay_Helper_Methods
     */
    protected $methodsHelper;

    /**
     * @var Billmate_CustomPay_Model_Checkout_Order
     */
    protected $checkoutOrder;

    /**
     * Billmate_CustomPay_Controller_Methods constructor.
     *
     * @param Zend_Controller_Request_Abstract  $request
     * @param Zend_Controller_Response_Abstract $response
     * @param array                             $invokeArgs
     */
    public function __construct(
        Zend_Controller_Request_Abstract $request,
        Zend_Controller_Response_Abstract $response,
        array $invokeArgs = array()
    ) {
        $this->helper = Mage::helper('billmatecustompay');
        $this->methodsHelper = Mage::helper('billmatecustompay/methods');
        $this->checkoutOrder = Mage::getModel('billmatecustompay/checkout_order');
        parent::__construct($request, $response, $invokeArgs);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function getBmRequestData()
    {
        $bmRequestData = $this->getRequest()->getParam('data');
        $bmRequestCredentials = $this->getRequest()->getParam('credentials');

        if ($bmRequestData && $bmRequestCredentials) {
            $postData['data'] = json_decode($bmRequestData, true);
            $postData['credentials'] = json_decode($bmRequestCredentials, true);
            return $postData;
        }

        $jsonBodyRequest = file_get_contents('php://input');
        if ($jsonBodyRequest) {
            return json_decode($jsonBodyRequest, true);
        }
        throw new Exception('The request does not contain information');
    }

    /**
     * @return Billmate_CustomPay_Helper_Data
     */
    protected function getHelper()
    {
        return $this->helper;
    }

    /**
     * @return Billmate_CustomPay_Helper_Methods
     */
    protected function getMethodsHelper()
    {
        return $this->methodsHelper;
    }

    /**
     * @return BillMate
     */
    public function getBmConnection()
    {
        return $this->getHelper()->getBillmate();
    }

    /**
     * @return mixed
     */
    public function getDefOrderStatus()
    {
        return $this->getMethodsHelper()->getDefaultOrderStatus(static::PAYMENT_METHOD_CODE);
    }


    /**
     * @return Billmate_CustomPay_Model_Methods_Message_Bmcustom
     */
    public function getMethodMessageModel()
    {
        return Mage::getSingleton('billmatecustompay/methods_message_' . static::PAYMENT_METHOD_CODE);
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return Billmate_CustomPay_Model_Checkout_Order|false|Mage_Core_Model_Abstract
     */
    public function getCheckoutOrderModel()
    {
        return $this->checkoutOrder;
    }
}