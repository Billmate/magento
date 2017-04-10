<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-04-10
 * Time: 10:40
 */
class Billmate_Common_CallbackController extends Mage_Core_Controller_Front_Action
{

    public function callbackAction()
    {

    }



    public function acceptAction()
    {
        //$quoteId = Mage::getSingleton('checkout/session')->getBillmateQuoteId();
        $quoteId = $this->getRequest()->getParam('billmate_quote_id');

        /** @var  $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel('sales/quote')->load($quoteId);





        $k = Mage::helper('billmatebankpay')->getBillmate(true, false);

        if(empty($_POST)) $_POST = $_GET;
        $data = $k->verify_hash($_POST);
        if(isset($data['code'])){
            Mage::getSingleton('core/session')->addError(Mage::helper('billmatebankpay')->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
            $this->getResponse()->setRedirect(Mage::helper('checkout/url')->getCheckoutUrl());
            return;
        }

        $methodtoModuleMap = array(
            1 => 'billmateinvoice',
            4 => 'billmatepartpayment',
            8 => 'billmatecardpay',
            16 => 'billmatebankpay'
        );
        $payment = $k->getPaymentInfo(array('number' => $data['number']));
        $method = $methodtoModuleMap[$payment['PaymentData']['method']];

        $quote->getPayment()->importData(array('method' => $method));
        $quote->save();
        switch(strtolower($data['status']))
        {
            case 'pending':
                $order = $this->place($quote);
                if($order && $order->getStatus()) {
                    if($order->getStatus() == Mage::getStoreConfig('payment/'.$method.'/order_status')) {
                        if(isset($_GET['billmate_checkout']) && $_GET['billmate_checkout'] ==1){
                            $this->_redirect('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash()),'_secure' => true));
                            return;

                        }
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));

                        break;

                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $data['status'] . '<br/>' . 'Transaction ID: ' . $data['number']));
                        $order->setState('new', 'pending_payment', '', false);
                        $order->save();
                        if(isset($_GET['billmate_checkout']) && $_GET['billmate_checkout'] ==1){
                            $this->_redirect('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash()),'_secure' => true));
                            return;

                        }
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        return;
                    }

                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                    $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;
                }
                break;
            case 'created':
            case 'paid':
                $order = $this->place($quote);
                if($order) {
                    if($order->getStatus() != Mage::getStoreConfig('payment/'.$method.'/order_status')) {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $data['status'] . '<br/>' . 'Transaction ID: ' . $data['number']));
                        $order->setState('new', Mage::getStoreConfig('payment/'.$method.'/order_status'), '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
                        $order->save();
                        $this->addTransaction($order,$data);
                        $this->sendNewOrderMail($order);
                    } else {
                        $order->addStatusHistoryComment(Mage::helper('payment')->__('Order processing completed' . '<br/>Billmate status: ' . $data['status'] . '<br/>' . 'Transaction ID: ' . $data['number']));
                        $order->setState('new',Mage::getStoreConfig('payment/'.$method.'/order_status'), '', false);
                        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
                        $order->save();
                        if(isset($_GET['billmate_checkout']) && $_GET['billmate_checkout'] == 1){
                            $this->_redirect('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash()),'_secure' => true));
                            return;

                        }
                        $this->_redirect('checkout/onepage/success',array('_secure' => true));
                        return;
                    }

                }
                else {
                    Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                    $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                    return;                }
                break;
            case 'cancelled':
                Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('The bank payment has been canceled. Please try again or choose a different payment method.'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                return;
                break;
            case 'failed':
                Mage::getSingleton('core/session')->addError(Mage::helper($method)->__('Unfortunately your bank payment was not processed with the provided bank details. Please try again or choose another payment method.'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
                return;
                break;

        }
        if(isset($_GET['billmate_checkout']) && $_GET['billmate_checkout'] === true){
            $this->_redirect('billmatecommon/billmatecheckout/confirmation',array('_query' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash()),'_secure' => true));
            return;

        }
        $this->_redirect('checkout/onepage/success',array('_secure' => true));
        return;


    }
}