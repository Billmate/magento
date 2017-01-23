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

        $billingAddress = $cart->getQuote()->getBillingAddress();
        $billingAddress->setFirstname($post['Customer']['Billing']['firstname']);
        $billingAddress->setLastname($post['Customer']['Billing']['lastname']);
        $billingAddress->setStreet($post['Customer']['Billing']['street']);
        $billingAddress->setCompany(isset($post['Customer']['Billing']['company']) ? $post['Customer']['Billing']['company'] : '');
        $billingAddress->setCity($post['Customer']['Billing']['city']);
        $billingAddress->setCountryId($post['Customer']['Billing']['country'])
            ->setPostcode($post['Customer']['Billing']['zip']);

        $shippingAddress = $cart->getQuote()->getShippingAddress();
        $shippingAddress->setFirstname($post['Custoemer']['Shipping']['firstname']);
        $shippingAddress->setLastname($post['Custoemer']['Shipping']['lastname']);
        $shippingAddress->setStreet($post['Custoemer']['Shipping']['street']);
        $shippingAddress->setCompany(isset($post['Custoemer']['Shipping']['company']) ? $post['Custoemer']['Shipping']['company'] : '');
        $shippingAddress->setCity($post['Custoemer']['Shipping']['city']);
        $shippingAddress->setCountryId($post['Custoemer']['Shipping']['country'])
            ->setPostcode($post['Custoemer']['Shipping']['zip'])
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


        $this->getResponse()->setBody($this->getLayout()->createBlock('checkout/cart_shipping','checkout.cart.shipping')->setTemplate('billmatecheckout/shipping.phtml')->toHtml());
    }

    public function _getQuote()
    {
        $cart = Mage::getSingleton('checkout/cart');

        return $cart->getQuote();
    }

    public function updateshippingmethodAction()
    {
        $code = (string) $this->getRequest()->getParam('estimate_method');
        if (!empty($code)) {

            $this->_getQuote()->getShippingAddress()->setShippingMethod($code)->collectTotals()->save();
        }
        $result = $this->updatePayment();
        if(!isset($result['code'])){
            $response['success'] = true;
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
        $method = $methodtoModuleMap[$post['PaymentData']['method']];
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = $this->_getQuote();
        if($quote->isVirtual()){
            $quote->getBillingAddress()->setPaymentMethod($method);
        } else {
            $quote->getShippingAddress()->setPaymentMethod($method);
        }
        $quote->save();

        $result =  $checkout->updateCheckout();

        $result = $this->updatePayment();
        if(!isset($result['code'])){
            $response['success'] = true;
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
        switch(strtolower($post['PaymentInfo']['status']))
        {
            case 'pending':
                $order = $this->place($quote);
                if($order && $order->getStatus()) {
                    if($order->getStatus() == Mage::getStoreConfig('payment/billmatebankpay/order_status')) {
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));

                        break;

                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $post['PaymentInfo']['status'] . '<br/>' . 'Transaction ID: ' . $post['PaymentInfo']['number']));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->save();

                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        return;
                    }

                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatebankpay')->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                    $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;
                }
                break;
            case 'created':
            case 'paid':
                $order = $this->place($quote);
                if($order) {
                    if($order->getStatus() != Mage::getStoreConfig('payment/billmatebankpay/order_status')) {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $post['PaymentInfo']['status'] . '<br/>' . 'Transaction ID: ' . $post['PaymentInfo']['number']));
                        $order->setState('new', Mage::getStoreConfig('payment/billmatebankpay/order_status'), '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
                        $order->save();
                        $this->addTransaction($order,$post['PaymentInfo']['number']);
                        $this->sendNewOrderMail($order);
                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $post['PaymentInfo']['status'] . '<br/>' . 'Transaction ID: ' . $post['PaymentInfo']['number']));
                        $order->setState('new',Mage::getStoreConfig('payment/billmatebankpay/order_status'), '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
                        $order->save();

                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        return;
                    }

                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatebankpay')->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                    $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;                }
                break;
            case 'cancelled':
                Mage::getSingleton('core/session')->addError(Mage::helper('billmatebankpay')->__('The bank payment has been canceled. Please try again or choose a different payment method.'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                return;
                break;
            case 'failed':
                Mage::getSingleton('core/session')->addError(Mage::helper('billmatebankpay')->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                return;
                break;

        }
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