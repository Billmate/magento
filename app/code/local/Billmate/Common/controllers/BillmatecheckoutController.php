<?php

class Billmate_Common_BillmatecheckoutController extends Mage_Core_Controller_Front_Action
{
    const BM_ADDITIONAL_INFO_CODE = 'bm_payment_method';

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
        if (!$this->isAvailableToProcess()) {
            $this->getResponse()->setRedirect($this->getRedirectUrl());
            return;
        }

        $quote = $this->_getQuote();
        if (!$quote->isVirtual() && (!$quote->getShippingAddress()->getCountry()
                || !$quote->getShippingAddress()->getShippingMethod())) {

            $quote->getShippingAddress()->addData([
                'postcode' => $this->getHelper()->getDefaultPostcode(),
                'country_id' => $this->getHelper()->getContryId(),
            ]);

            $method = $this->getHelper()->getDefaultShipping();
            Mage::log('assign country'.print_r($quote->getShippingAddress()->getData(),true),1,'billmate.log');

            $quote->getShippingAddress()
                ->setCollectShippingRates(true)
                ->setShippingMethod($method)
                ->collectTotals()
                ->save();
            
            $quote->save();
        }
        
        $this->loadLayout();
        $this->renderLayout();
    }

    public function confirmationAction()
    {
        $runRedirect = true;
        $billmate = $this->getHelper()->getBillmate();
        $hash = $this->getRequest()->getParam('hash');

        $checkout = $billmate->getCheckout(array('PaymentData' => array('hash' => $hash)));
        if (isset($checkout['PaymentData']['order']['status'])) {
            $status = $this->getHelper()->getAdaptedStatus($checkout['PaymentData']['order']['status']);
            if ($status && in_array($status, $this->_allowedStates)) {
                $this->_getQuote()->setIsActive(false)->save();
                Mage::getSingleton('checkout/session')->clear();
                $this->loadLayout();
                Mage::register('billmate_confirmation_url',$checkout['PaymentData']['url']);
                $this->renderLayout();
                $runRedirect = false;
            }
        }

        if ($runRedirect) {
            $this->getResponse()->setRedirect(Mage::helper('checkout/url')->getCartUrl());
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
                    $shippingRates[$rate->getCarrier()][] = $rate->getData();
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
        $method = $this->getHelper()->getPaymentMethodCode();
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
        if (!$this->isAvailableToProcess()) {
            $response['url'] = $this->getRedirectUrl();
            $this->getResponse()->setBody(json_encode($response));
        }

        $quote = $this->_getQuote();
        $hash = $this->getRequest()->getParam('hash');
        $result = $this->getHelper()->getBillmate()
            ->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));
        if(!isset($result['code'])){
            Mage::register('billmate_checkout_complete',true);
        }

        $method = $this->getHelper()->getPaymentMethodCode();
        $quote->getPayment()->importData(array('method' => $method));


        $quote->getPayment()->setAdditionalInformation(
            self::BM_ADDITIONAL_INFO_CODE, $result['PaymentData']['method_name']
        );

        $checkoutOrderModel = $this->getCheckoutOrderModel();
        $url = '';
        $status = $this->getHelper()->getAdaptedStatus($result['PaymentData']['order']['status']);
        $paymentMethodStatus = Mage::getStoreConfig('payment/'.$method.'/order_status');
        switch($status)
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
                    $redirectSuccess = $checkoutOrderModel->updateOrder($paymentMethodStatus, $result['PaymentData']['order']);
                    if ($redirectSuccess) {
                        $url = Mage::getUrl('billmatecommon/billmatecheckout/confirmation',
                            array('_query' => array('hash' => $hash),'_secure' => true)
                        );
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

    /**
     * @return bool
     */
    protected function isAvailableToProcess()
    {
        return (bool)$this->_getQuote()->hasItems();
    }

    /**
     * @return mixed
     */
    protected function getRedirectUrl()
    {
        return Mage::helper('checkout/url')->getCartUrl();
    }
}