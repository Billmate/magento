<?php
class Billmate_Bankpay_Model_BillmateBankpay extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'billmatebankpay';
    protected $_formBlockType = 'billmatebankpay/form';
    
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_liveurl                 = 'https://cardpay.billmate.se/pay';
    protected $_testurl                 = 'https://cardpay.billmate.se/pay/test';
    
    public function isAvailable($quote = null)
    {
        if($quote == null ) return false;
		if( Mage::getStoreConfig('payment/billmatebankpay/active') != 1 ) return false;
        $countries = explode(',', Mage::getStoreConfig('payment/billmatebankpay/countries'));

        if( in_array($quote->getShippingAddress()->getCountry(), $countries ) ){
			$data = $quote->getTotals();
			$total = $data['subtotal']->getValue();
			$min_total = Mage::getStoreConfig('payment/billmatebankpay/min_amount');
			$max_total = Mage::getStoreConfig('payment/billmatebankpay/max_amount');
			return $total >= $min_total && $total <= $max_total;
		}
		return false;
    }

    public function getConfig()
    {
        if (null === $this->_config) {
            $params = array($this->_code);
            if ($store = $this->getStore()) {
                $params[] = is_object($store) ? $store->getId() : $store;
            }
            $this->_config = Mage::getModel('billmatebankpay/config', $params);
        }
        return $this->_config;
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    
    public function getStandardCheckoutFormFields(){

		$session = Mage::getSingleton("core/session",  array("name"=>"frontend"));
        $session->unsetData('bank_api_called');
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        /* @var $api Mage_Paypal_Model_Api_Standard */

        $api = Mage::getModel('billmatebankpay/api_standard')->setConfigObject($this->getConfig());
        $api->setOrderId($orderIncrementId)
            ->setCurrencyCode($order->getOrderCurrencyCode())
            //->setPaymentAction()
            ->setOrder($order)
            ->setNotifyUrl('http://api.billmate.se/callback.php')
            ->setReturnUrl(Mage::getUrl('bankpay/bankpay/success'))
            ->setCancelUrl(Mage::getUrl('bankpay/bankpay/cancel'));
            
        // add cart totals and line items
        $api->setPaypalCart(Mage::getModel('paypal/cart', array($order)))
            ->setIsLineItemsEnabled($this->_config->lineItemsEnabled);
        
        $result = $api->getStandardCheckoutRequest();

        return $result;        
    }
    
   
    public function getTitle(){
        return Mage::getStoreConfig('payment/billmatebankpay/title');
    }
    public function getBillmateUrl(){
        
        if( Mage::getStoreConfig('payment/billmatebankpay/test_mode') == '1'){
            return $this->_testurl;
        } else {
            return $this->_liveurl;
        }
    }
    public function getOrderPlaceRedirectUrl()
    {
        //when you click on place order you will be redirected on this url, if you don't want this action remove this method
        return Mage::getUrl('bankpay/bankpay/redirect', array('_secure' => true));
    }
    
    /*public function authorize(Varien_Object $payment, $amount){
    }
    public function validate(){
    }*/
}
?>