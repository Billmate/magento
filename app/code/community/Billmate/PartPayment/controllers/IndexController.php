<?php


class Billmate_Partpayment_IndexController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $gateway = Mage::getSingleton('partpayment/gateway');
            $gateway->init();
            
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->renderLayout();
        }
    
    }

    public function updateAddressAction()
    {
        if ($this->getRequest()->isPost()) {
            $gateway = Mage::getSingleton('partpayment/gateway');
            $gateway->init(true);
            
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->renderLayout();
        }
    }

    public function checkpclassAction()
    {
        $billmate = Mage::helper('partpayment')->checkPclasses();
    }
}
