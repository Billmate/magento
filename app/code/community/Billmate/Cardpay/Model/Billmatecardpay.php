<?php
class Billmate_Cardpay_Model_BillmateCardpay extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'billmatecardpay';
    protected $_formBlockType = 'billmatecardpay/form';
    
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
		if( Mage::getStoreConfig('payment/billmatecardpay/active') != 1 ) return false;
        $countries = explode(',', Mage::getStoreConfig('payment/billmatecardpay/countries'));	
        if( in_array($quote->getShippingAddress()->getCountry(), $countries ) ){
			$data = $quote->getTotals();
			$total = $data['subtotal']->getValue();
			$min_total = Mage::getStoreConfig('payment/billmatecardpay/min_amount');
			$max_total = Mage::getStoreConfig('payment/billmatecardpay/max_amount');
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
            $this->_config = Mage::getModel('billmatecardpay/config', $params);
        }
        return $this->_config;
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    
    public function getStandardCheckoutFormFields(){

		$session = Mage::getSingleton("core/session",  array("name"=>"frontend"));
        $session->unsetData('card_api_called');
        
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        /* @var $api Mage_Paypal_Model_Api_Standard */

		$prompt_name = Mage::getStoreConfig('payment/billmatecardpay/prompt_name') == 1 ? 'YES' : 'NO';
		$do3dsecure = Mage::getStoreConfig('payment/billmatecardpay/do_3d_secure') == 0 ? 'NO' : 'YES';

        $api = Mage::getModel('billmatecardpay/api_standard')->setConfigObject($this->getConfig());
        $api->setOrderId($orderIncrementId)
            ->setCurrencyCode($order->getOrderCurrencyCode())
            //->setPaymentAction()
            ->setOrder($order)
			->setReturnMethod('GET')
			->setPromptNameEntry($prompt_name)
			->setDo3dSecure($do3dsecure)
            ->setNotifyUrl('http://api.billmate.se/callback.php')
            ->setReturnUrl(Mage::getUrl('cardpay/cardpay/success'))
            ->setCancelUrl(Mage::getUrl('cardpay/cardpay/cancel'));
            
        // add cart totals and line items
        $api->setPaypalCart(Mage::getModel('paypal/cart', array($order)))
            ->setIsLineItemsEnabled($this->_config->lineItemsEnabled);
        
        $result = $api->getStandardCheckoutRequest();

        return $result;        
    }
    
   
    public function getTitle(){
        return Mage::getStoreConfig('payment/billmatecardpay/title');
    }
    public function getBillmateUrl(){
        
        if( Mage::getStoreConfig('payment/billmatecardpay/test_mode') == '1'){
            return $this->_testurl;
        } else {
            return $this->_liveurl;
        }
    }
    public function getOrderPlaceRedirectUrl()
    {
        //when you click on place order you will be redirected on this url, if you don't want this action remove this method
        return Mage::getUrl('cardpay/cardpay/redirect', array('_secure' => true));
    }
    
    /*public function authorize(Varien_Object $payment, $amount){
    }
    public function validate(){
    }*/
}
?>