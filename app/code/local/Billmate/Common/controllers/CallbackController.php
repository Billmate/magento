<?php
require_once ('BillmatecheckoutController.php');
class Billmate_Common_CallbackController extends Billmate_Common_BillmatecheckoutController
{

    public function callbackAction()
    {
        $_POST = file_get_contents('php://input');

        /** @var  $quote Mage_Sales_Model_Quote */
        $quote = $this->_getQuote();
        $billmateConnection = $this->getHelper()->getBillmate();

        if(empty($_POST)) $_POST = $_GET;
        $data = $billmateConnection->verify_hash($_POST);
        if(isset($data['code'])){
            Mage::getSingleton('core/session')
                ->addError(
                    Mage::helper('billmatecommon')
                        ->__('Unfortunately your payment was not processed correctly. Please try again or choose another payment method.')
                );
            $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
            return;
        }
        $result = $this->getHelper()->getBillmate()
            ->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));

        Mage::register('billmate_checkout_complete',true);
        $method =  $this->getHelper()->getPaymentMethodCode();
        $quote->getPayment()->importData(array('method' => $method));
        $quote->getPayment()->setAdditionalInformation(
            Billmate_BillmateCheckout_Model_Billmatecheckout::BM_ADDITIONAL_INFO_CODE,
            $data['PaymentData']['method_name']
        );
        $quote->save();

        $checkoutOrderModel = $this->getCheckoutOrderModel();
        $status = $this->getHelper()->getAdaptedStatus($data['status']);
        $paymentMethodStatus = Mage::getStoreConfig('payment/'.$method.'/order_status');
        switch($status)
        {
            case 'pending':
                $order = $checkoutOrderModel->place($quote);
                if($order && $order->getStatus()) {
                    if($order->getStatus() == $paymentMethodStatus) {
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        break;
                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $data['status'] . '<br/>' . 'Transaction ID: ' . $data['number']));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->save();
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        return;
                    }
                } else {
                    Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                    $this->_redirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
                    return;
                }
                break;
            case 'created':
            case 'paid':
                $order = $checkoutOrderModel->place($quote);
                if ($order) {
                    $redirectSuccess = $checkoutOrderModel->updateOrder($paymentMethodStatus, $data);
                    if ($redirectSuccess) {
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        return;
                    }
                } else {
                    Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                    $this->_redirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
                    return;
                }
                break;
            case 'cancelled':
                Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('The bank payment has been canceled. Please try again or choose a different payment method.'));
                $this->_redirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
                return;
                break;
            case 'failed':
                Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                $this->_redirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
                return;
                break;
        }
        $this->_redirect('checkout/onepage/success',array('_secure' => true));
        return;
    }

    public function acceptAction()
    {
     try{
        $bmRequestData = $this->getBmRequestData();
        $verifiedData = $this->getBmPaymentData($bmRequestData);
        if (isset($verifiedData['code'])) {
            $codeMessage = $this->getHelper()->__('Unfortunately your payment was not processed correctly.
             Please try again or choose another payment method.');
            throw new Exception($codeMessage);
        }
        $checkoutOrderModel = $this->getCheckoutOrderModel()
            ->setQuote($this->_getQuote())
            ->setBmRequestData($verifiedData);

        $status = $this->getHelper()->getAdaptedStatus($bmRequestData['data']['status']);
        $paymentMethodStatus = $this->getHelper()->getBillmateCheckoutOrderStatus();

            switch($status) {
                case 'pending':
                    $order = $checkoutOrderModel->place();
                    if (!$order || !$order->getStatus()) {
                        throw new Exception(
                            $this->getHelper()->
                            __('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.')
                        );
                    }

                    if ($order->getStatus() != $paymentMethodStatus) {
                        $order->addStatusHistoryComment(
                            $this->getHelper()->__('Order processing completed' .
                                '<br/>Billmate status: %s  
                                <br/>' . 'Transaction ID: %s',[
                                $verifiedData['data']['status'],
                                $verifiedData['data']['number']
                            ]));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->save();
                    }
                    break;
                case 'created':
                case 'paid':
                    $order = $checkoutOrderModel->place();
                    if (!$order) {
                        throw new Exception(
                            $this->getHelper()->
                            __('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.')
                        );
                    }
                    $checkoutOrderModel->updateOrder($paymentMethodStatus, $bmRequestData['data']);
                    break;
                case 'cancelled':
                    throw new Exception(
                        Mage::helper('billmatecommon')->
                        __('The bank payment has been canceled. Please try again or choose a different payment method.')
                    );
                    break;
                case 'failed':
                    throw new Exception(
                        Mage::helper('billmatecommon')->
                        __('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.')
                    );
                    break;
            }
        } catch (Exception $e) {
             Mage::getSingleton('core/session')->addError($e->getMessage());
             $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
             return;
        }

        Mage::register('billmate_checkout_complete',true);
        $this->_redirect('checkout/onepage/success',array('_secure' => true));
    }

    public function cancelAction()
    {
        $billmateConnection = $this->getHelper()->getBillmate();

        if(empty($_POST)) $_POST = $_GET;
        $data = $billmateConnection->verify_hash($_POST);


        if(isset($data['code'])){
            Mage::getSingleton('core/session')->addError(Mage::helper('billmatecommon')->__('Unfortunately your payment was not processed correctly. Please try again or choose another payment method.'));
            $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
            return;
        }
        if(isset($data['status'])){
            switch(strtolower($data['status'])){
                case 'cancelled':
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatecommon')->__('Thepayment has been canceled. Please try again or choose a different payment method.'));
                    $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
                    return;
                    break;
                case 'failed':
                    Mage::getSingleton('core/session')->addError(Mage::helper('billmatecommon')->__('Unfortunately your payment was not processed correctly. Please try again or choose another payment method.'));
                    $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
                    return;
                    break;
            }
        }
        $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
        return;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function _getQuote()
    {
        $quoteId = $this->getRequest()->getParam('billmate_quote_id');
        /** @var  $quote Mage_Sales_Model_Quote */
        return Mage::getModel('sales/quote')->load($quoteId);
    }
}
