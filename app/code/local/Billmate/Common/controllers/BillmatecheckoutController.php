<?php

class Billmate_Common_BillmatecheckoutController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var array
     */
    protected $allowedStates = [
        'paid',
        'created',
        'pending'
    ];

    public function indexAction()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if(!$quote->isVirtual() && strlen($quote->getShippingAddress()->getCountry() < 2)){
            $quote->getShippingAddress()->addData(array('postcode' => (strlen(Mage::getStoreConfig('shipping/origin/postcode')) > 0) ? Mage::getStoreConfig('shipping/origin/postcode') : '12345' ,'country_id' => Mage::getStoreConfig('general/country/default')));
            $method = Mage::getStoreConfig('billmate/checkout/shipping_method');

            Mage::log('assign country'.print_r($quote->getShippingAddress()->getData(),true),1,'billmate.log');
            $quote->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod($method)->collectTotals()->save();
            
            $quote->save();
        }
        
        $this->loadLayout();
        $this->renderLayout();
    }

    public function confirmationAction()
    {
        $billmate = $this->getHelper()->getBillmate();
        $hash = $this->getRequest()->getParam('hash');

        $checkout = $billmate->getCheckout(array('PaymentData' => array('hash' => $hash)));
        if (isset($checkout['PaymentData']['order']['status'])) {
            $status = $this->getHelper()->getAdaptedStatus($checkout['PaymentData']['order']['status']);

            if ($status && in_array($status, $this->_allowedStates)) {
                $quote = Mage::getSingleton('checkout/session')->getQuote();
                $quote->setIsActive(false)->save();
                Mage::getSingleton('checkout/session')->clear();
                $this->loadLayout();

                Mage::register('billmate_confirmation_url',$checkout['PaymentData']['url']);
                $this->renderLayout();
            } else {
                $this->getResponse()->setRedirect(Mage::getUrl('checkout/url'));
            }
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
        $cart = $this->_getCart();

        $connection = $this->getHelper()->getBillmate();
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
                    $shippingRates[$carrier][] = $rate->getData();
                }
            }

            $respHtml = $this->getLayout()
                ->createBlock('billmatecommon/checkout_cart_shipping', 'checkout.cart.shipping')
                ->setTemplate('billmatecheckout/shipping.phtml')
                ->toHtml();
            $this->getResponse()->setBody($respHtml);
        }
    }

    public function updatetotalsAction()
    {
        $checkout = Mage::getModel('billmatecommon/checkout');
        $checkout->updateCheckout();
        $this->getResponse()->setBody($this->getLayout()->createBlock('checkout/cart_totals', 'checkout.cart.totals')->setTemplate('billmatecheckout/cart/totals.phtml')->toHtml());

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
                    $response['message'] = $this->__('Coupon code "%s" was applied.', Mage::helper('core')->escapeHtml($couponCode));
                } else {
                    $response['success'] = false;
                    $response['message'] = $this->__('Coupon code "%s" is not valid.', Mage::helper('core')->escapeHtml($couponCode));
                }
            } else {
                $response['success'] = true;
                $response['message'] = $this->_getSession()->addSuccess($this->__('Coupon code was canceled.'));
            }

        } catch (Mage_Core_Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $this->__('Cannot apply the coupon code.');
            Mage::logException($e);
        }

        $this->getResponse()->setBody(json_encode($response));
    }
    
    public function updateshippingmethodAction()
    {
        $checkout = Mage::getModel('billmatecommon/checkout');

        $code = (string) $this->getRequest()->getParam('estimate_method');
        if (!empty($code)) {
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
        $methodId = $this->getRequest()->getParams('method');
        $method = $this->getHelper()->getPaymentMethodCode($methodId);
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = $this->_getQuote();
        $quote->getPayment()->importData(array('method' => $method));
        $quote->save();
        $result =  $checkout->updateCheckout();

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
        $quote = $this->_getQuote();
        $hash = $this->getRequest()->getParam('hash');
        $result = $this->getHelper()->getBillmate()
            ->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));
        if(!isset($result['code'])){
            Mage::register('billmate_checkout_complete',true);
        }

        $method = $this->getHelper()->getPaymentMethodCode($result['PaymentData']['method']);
        $quote->getPayment()->importData(array('method' => $method));

        $checkoutOrderModel = $this->getCheckoutOrderModel();
        $url = '';
        $status = $this->getHelper()->getAdaptedStatus($result['PaymentData']['order']['status']);
        $paymentMethodStatus = Mage::getStoreConfig('payment/'.$method.'/order_status');
        switch(strtolower($status))
        {
            case 'pending':
                $order = $checkoutOrderModel->place($quote);
                if ($order && $order->getStatus()) {
                    if($order->getStatus() == $paymentMethodStatus) {
                        $url = Mage::getUrl('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $hash),'_secure' => true));
                        break;
                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $result['PaymentData']['order']['status'] . '<br/>' . 'Transaction ID: ' . $result['PaymentData']['order']['number']));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->save();
                        $url = Mage::getUrl('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $hash),'_secure' => true));
                    }
                } else {
                    Mage::getSingleton('core/session')->addError(
                        Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.')
                    );
                    $url = Mage::getUrl(Mage::helper('checkout/url')->getCheckoutUrl());
                }
                break;
            case 'created':
            case 'paid':
                $order = $checkoutOrderModel->place($quote);
                if ($order) {
                    if($order->getStatus() != $paymentMethodStatus) {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $result['PaymentData']['order']['status'] . '<br/>' . 'Transaction ID: ' . $result['PaymentData']['order']['number']));
                        $order->setState('new', $paymentMethodStatus, '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
                        $order->save();
                        $this->addTransaction($order,$result['PaymentInfo']['number']);
                        $this->sendNewOrderMail($order);
                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $result['PaymentData']['order']['status'] . '<br/>' . 'Transaction ID: ' . $result['PaymentData']['order']['number']));
                        $order->setState('new',$paymentMethodStatus, '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
                        $order->save();

                        $url = Mage::getUrl('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => $hash),'_secure' => true));
                    }
                } else {
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
     * @return Billmate_Common_Model_Checkout_Order
     */
    protected function getCheckoutOrderModel()
    {
        return Mage::getModel('billmatecommon/checkout_order');
    }

    /**
     * @return Billmate_Common_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('billmatecommon');
    }


    /**
     * @return Mage_Sales_Model_Quote
     */
    public function _getQuote()
    {
        return $this->_getCart()->getQuote();
    }

    /**
     * @return Mage_Checkout_Model_Cart
     */
    protected function _getCart()
    {
        return Mage::getSingleton('checkout/cart');
    }
}