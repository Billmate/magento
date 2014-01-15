<?php


class Billmate_Partpayment_IndexController extends Mage_Core_Controller_Front_Action{

    function IndexAction(){
        $quote =  Mage::getSingleton('checkout/session')->getQuote();
                
        if ($this->getRequest()->isPost()){
            $gateway = Mage::getSingleton('billmateinvoice/gateway');
            $gateway->init();
            
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->renderLayout();
        }
    
    }
    function updateAddressAction(){
        if ($this->getRequest()->isPost()){
            $gateway = Mage::getSingleton('billmateinvoice/gateway');
            $gateway->init(true);
            
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->renderLayout();
        }
    }
}
