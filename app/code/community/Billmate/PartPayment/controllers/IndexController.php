<?php


class Billmate_Partpayment_IndexController extends Mage_Core_Controller_Front_Action{

    function IndexAction(){
        $quote =  Mage::getSingleton('checkout/session')->getQuote();
                
        if ($this->getRequest()->isPost()){
            $gateway = Mage::getSingleton('partpayment/gateway');
            $gateway->init();
            
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->renderLayout();
        }
    
    }
    function updateAddressAction(){
        if ($this->getRequest()->isPost()){
            $gateway = Mage::getSingleton('partpayment/gateway');
            $gateway->init(true);
            
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->renderLayout();
        }
    }

    public function pclassAction(){
        $billmate = Mage::helper('partpayment')->getBillmate(true,false);

        $values['PaymentData'] = array('currency' => 'SEK', 'country' => 'se', 'language' => 'sv');
        $result = $billmate->getPaymentplans($values);
        print_r($result);
        die();
    }
}
