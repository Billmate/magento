<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-09-26
 * Time: 11:52
 */
class Billmate_Common_BillmatecheckoutController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if(!$quote->isVirtual() && $quote->getShippingAddress()->getCountryId() == ''){
            $quote->getShippingAddress()->addData(array('postcode' => (strlen(Mage::getStoreConfig('shipping/origin/postcode')) > 0) ? Mage::getStoreConfig('shipping/origin/postcode') : '12345' ,'country_id' => Mage::getStoreConfig('general/country/default')));            $method = Mage::getStoreConfig('billmate/checkout/shipping_method');
            $freeshipping = false;
            if($method == 'freeshipping_freeshipping')
                $freeshipping = true;
            $quote->getShippingAddress()->setFreeShipping($freeshipping)->collectShippingRates(true)->setShippingMethod($method)->collectTotals()->save();
            
            $quote->save();
        }
        
        $this->loadLayout();
        $this->renderLayout();
        
        
    }

    public function confirmationAction()
    {
        $redirect = false;
        $billmate = Mage::helper('billmatecommon')->getBillmate();

        $hash = $this->getRequest()->getParam('hash');

        $checkout = $billmate->getCheckout(array('PaymentData' => array('hash' => $hash)));
        $status = $checkout['PaymentData']['order']['status'];

        if(in_array(strtolower($status),array('paid','created','pending'))){
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $quote->setIsActive(false)->save();
            Mage::getSingleton('checkout/session')->clear();
            $this->loadLayout();

            Mage::register('billmate_confirmation_url',$checkout['PaymentData']['url']);
            $this->renderLayout();
        }
        else {
            $redirect = true;
        }

        if($redirect){
            header('Location: ' . Mage::helper('checkout/url')->getCartUrl());
            exit();
        }
    }

    

    public function termsAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function updatequoteAction()
    {
        // Set shipping and billing on quote.
    }

    public function updateaddressAction()
    {
        $post = $this->getRequest()->getParams();
        $cart = Mage::getSingleton('checkout/cart');

        $connection = Mage::helper('billmatecommon')->getBillmate();
        $result = $connection->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));
        if(!isset($result['code'])) {

            $billingAddress = $cart->getQuote()->getBillingAddress();
            $cart->getQuote()->setCustomerEmail($result['Customer']['Billing']['email']);

            $billingAddress->setFirstname($result['Customer']['Billing']['firstname']);
            $billingAddress->setLastname($result['Customer']['Billing']['lastname']);
            $billingAddress->setEmail($result['Customer']['Billing']['email']);
            $billingAddress->setStreet($result['Customer']['Billing']['street']);
            $billingAddress->setCompany(isset($result['Customer']['Billing']['company']) ? $result['Customer']['Billing']['company'] : '');
            $billingAddress->setCity($result['Customer']['Billing']['city']);
            $billingAddress->setTelephone($result['Customer']['Billing']['phone']);
            $billingAddress->setCountryId($result['Customer']['Billing']['country'])
                ->setPostcode($result['Customer']['Billing']['zip']);
            if (!isset($result['Customer']['Shipping']) ||(isset($result['Customer']['Shipping']) && count($result['Customer']['Shipping']) == 0)) {
                $result['Customer']['Shipping'] = $result['Customer']['Billing'];
            }
            $shippingAddress = $cart->getQuote()->getShippingAddress();
            $shippingAddress->setFirstname($result['Customer']['Shipping']['firstname']);
            $shippingAddress->setLastname($result['Customer']['Shipping']['lastname']);
            $shippingAddress->setEmail(isset($result['Customer']['Shipping']['email']) ? $result['Customer']['Shipping']['email'] : $result['Customer']['Billing']['email']);

            $shippingAddress->setStreet($result['Customer']['Shipping']['street']);
            $shippingAddress->setCompany(isset($result['Customer']['Shipping']['company']) ? $result['Customer']['Shipping']['company'] : '');
            $shippingAddress->setCity($result['Customer']['Shipping']['city']);
            $shippingAddress->setTelephone($result['Customer']['Shipping']['phone']);
            $shippingAddress->setCountryId(isset($result['Customer']['Shipping']['country']) ? $result['Customer']['Shipping']['country'] : $result['Customer']['Billing']['country'])
                ->setPostcode($result['Customer']['Shipping']['zip'])
                ->setCollectShippingrates(true);
            $billingAddress->save();
            $shippingAddress->save();
            $cart->save();

            // Find if our shipping has been included.
            $rates = $shippingAddress->collectShippingRates()
                ->getGroupedAllShippingRates();
            $shippingRates = array();
            foreach ($rates as $carrier) {
                foreach ($carrier as $rate) {
                    //print_r($rate->getData());
                    $shippingRates[$carrier][] = $rate->getData();
                }
            }


            $this->getResponse()->setBody($this->getLayout()->createBlock('checkout/cart_shipping', 'checkout.cart.shipping')->setTemplate('billmatecheckout/shipping.phtml')->toHtml());
        }
    }

    public function updatetotalsAction()
    {
        $checkout = Mage::getModel('billmatecommon/checkout');
        $checkout->updateCheckout();
        $this->getResponse()->setBody($this->getLayout()->createBlock('checkout/cart_totals', 'checkout.cart.totals')->setTemplate('billmatecheckout/cart/totals.phtml')->toHtml());

    }

    public function _getQuote()
    {
        $cart = Mage::getSingleton('checkout/cart');

        return $cart->getQuote();
    }

    protected function _getCart()
    {
        return Mage::getSingleton('checkout/cart');
    }
    
    public function setdiscountAction()
    {
        /**
         * No reason continue with empty shopping cart
         */
        $response = array();
        if (!$this->_getCart()->getQuote()->getItemsCount()) {
            $response['success'] = false;
            
        }

        $couponCode = (string) $this->getRequest()->getParam('coupon_code');
        if ($this->getRequest()->getParam('remove') == 1) {
            $couponCode = '';
        }
        $oldCouponCode = $this->_getQuote()->getCouponCode();

        if (!strlen($couponCode) && !strlen($oldCouponCode)) {
            $response['success'] = false;
        }

        try {
            $codeLength = strlen($couponCode);
            $isCodeLengthValid = $codeLength && $codeLength <= Mage_Checkout_Helper_Cart::COUPON_CODE_MAX_LENGTH;

            $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
            $this->_getQuote()->setCouponCode($isCodeLengthValid ? $couponCode : '')
                ->collectTotals()
                ->save();

            if ($codeLength) {
                if ($isCodeLengthValid && $couponCode == $this->_getQuote()->getCouponCode()) {
                    $response['success'] = true;
                } else {
                    $response['success'] = false;
                }
            } else {
                $response['success'] = true;
            }

        } catch (Mage_Core_Exception $e) {
            $response['success'] = false;
        } catch (Exception $e) {
            $response['success'] = false;
            Mage::logException($e);
        }

        $this->getResponse()->setBody(json_encode($response));
    }
    
    public function updateshippingmethodAction()
    {
        $checkout = Mage::getModel('billmatecommon/checkout');

        $code = (string) $this->getRequest()->getParam('estimate_method');
        if (!empty($code)) {

            $freeshipping = false;
            if($code == 'freeshipping_freeshipping')
                $freeshipping = true;
            $this->_getQuote()->getShippingAddress()->setShippingMethod($code)->save();
            $this->_getCart()->save();
        }
        
        $result = $checkout->updateCheckout();
        if(!isset($result['code'])){
            $response['success'] = true;
            $response['update_checkout'] = ($result['update_checkout']) ? true : false;
            $response['data'] = $result['data'];

        } else {
            $response['success'] = false;
            
        }
        $this->getResponse()->setBody(json_encode($response));
    }


    public function updatepaymentmethodAction()
    {
        $checkout = Mage::getModel('billmatecommon/checkout');
        $post = $this->getRequest()->getParams();

        $methodtoModuleMap = array(
            1 => 'billmateinvoice',
            4 => 'billmatepartpayment',
            8 => 'billmatecardpay',
            16 => 'billmatebankpay'
        );
        $method = $methodtoModuleMap[$post['method']];
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = $this->_getQuote();
        $quote->getPayment()->importData(array('method' => $method));
        $quote->save();
        $result =  $checkout->updateCheckout();

        //$result = $this->updatePayment();
        if(!isset($result['code'])){
            $response['success'] = true;
            $response['update_checkout'] = ($result['update_checkout']) ? true : false;
            $response['data'] = $result['data'];

        } else {
            $response['success'] = false;
        }
        $this->getResponse()->setBody(json_encode($response));

    }

    public function createorderAction()
    {
        $checkout = Mage::getModel('billmatecommon/checkout');
        $quote = $this->_getQuote();
        $post = $this->getRequest()->getParams();
        $result = Mage::helper('billmatecommon')->getBillmate()->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));
        if(!isset($result['code'])){
            Mage::register('billmate_checkout_complete',true);
        }
        $codeToMethod = array(
            1 => 'billmateinvoice',
            4 => 'billmatepartpayment',
            8 => 'billmatecardpay',
            16 => 'billmatebankpay'
        );
        $method = $codeToMethod[$result['PaymentData']['method']];

        $quote->getPayment()->importData(array('method' => $method));

        $url = '';
        switch(strtolower($result['PaymentData']['order']['status']))
        {
            case 'pending':
                $order = $this->place($quote);
                if($order && $order->getStatus()) {
                    if($order->getStatus() == Mage::getStoreConfig('payment/'.$method.'/order_status')) {
                        $url = Mage::getUrl('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $post['hash']),'_secure' => true));

                        break;

                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $result['PaymentData']['order']['status'] . '<br/>' . 'Transaction ID: ' . $result['PaymentData']['order']['number']));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->save();

                        $url = Mage::getUrl('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $post['hash']),'_secure' => true));

                    }

                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                    $url = Mage::getUrl(Mage::helper('checkout/url')->getCheckoutUrl());

                }
                break;
            case 'created':
            case 'paid':
                $order = $this->place($quote);
                if($order) {
                    if($order->getStatus() != Mage::getStoreConfig('payment/'.$method.'/order_status')) {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $result['PaymentData']['order']['status'] . '<br/>' . 'Transaction ID: ' . $result['PaymentData']['order']['number']));
                        $order->setState('new', Mage::getStoreConfig('payment/'.$method.'/order_status'), '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
                        $order->save();
                        $this->addTransaction($order,$result['PaymentInfo']['number']);
                        $this->sendNewOrderMail($order);
                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $result['PaymentData']['order']['status'] . '<br/>' . 'Transaction ID: ' . $result['PaymentData']['order']['number']));
                        $order->setState('new',Mage::getStoreConfig('payment/'.$method.'/order_status'), '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
                        $order->save();

                        $url = Mage::getUrl('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $post['hash']),'_secure' => true));

                    }

                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                    $url = Mage::getUrl(Mage::helper('checkout/url')->getCheckoutUrl());
                                    }
                break;
            case 'cancelled':
                Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('The bank payment has been canceled. Please try again or choose a different payment method.'));
                $url = Mage::getUrl(Mage::helper('checkout/url')->getCheckoutUrl());

                break;
            case 'failed':
                Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                $url = Mage::getUrl(Mage::helper('checkout/url')->getCheckoutUrl());

                break;

        }
        $response['url'] = $url;
        $this->getResponse()->setBody(json_encode($response));

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
}