<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-29
 * Time: 14:19
 */

class Billmate_Common_GetaddressController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        $response = [];
        $pno = $this->getRequest()->getParam('billmate_pno');
        Mage::getSingleton('checkout/session')->setBillmatePno($pno);
        $data = Mage::helper('billmatecommon')->getAddress($pno);

        $response['success'] = (!isset($data['code'])) ? true : false;
        $response['message'] = (isset($data['code'])) ? utf8_encode($data['message']) : '';
        $response['data'] = $data;
        $response['data']['message'] = $response['message'];
        $this->getResponse()->setBody(json_encode($response));
    }
}