<?php

class Billmate_CustomPay_MethodController extends Mage_Core_Controller_Front_Action
{
    public function updateaddressAction()
    {
        if ($this->getRequest()->isPost()) {
            $gateway = Mage::getSingleton('billmatecustompay/gateway_invoice');
            $gateway->init(true);

            $layout = $this->getLayout();
            $update = $layout->getUpdate();
            $update->load('billmate_complete_checkout');
            $layout->generateXml();
            $layout->generateBlocks();
            $output = $layout->getOutput();
            $this->getResponse()->setBody($output);
        }
    }
}