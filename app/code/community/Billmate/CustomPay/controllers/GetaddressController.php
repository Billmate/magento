<?php

class Billmate_CustomPay_GetaddressController extends Billmate_CustomPay_Controller_Methods
{

    public function indexAction()
    {
        $pno = $this->getRequest()->getParam('billmate_pno');
        Mage::getSingleton('checkout/session')->setBillmatePno($pno);

        $bmConnection = $this->getBmConnection();
        $bmResponse = $bmConnection->getAddress(
            ['pno' => $pno]
        );

        $status = (!isset($bmResponse['code'])) ? true : false;
        $response['success'] = $status;
        $response['message'] = (isset($bmResponse['code'])) ? utf8_encode($bmResponse['message']) : '';
        $response['data'] = $bmResponse;
        $response['data']['message'] = $response['message'];

        $this->getResponse()->setBody(Zend_Json::encode($response));
    }
}