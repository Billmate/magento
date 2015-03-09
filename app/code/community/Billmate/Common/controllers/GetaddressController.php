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
        $pno = $this->getRequest()->getParam('billmate_pno');
        $data = Mage::helper('billmatecommon')->getAddress($pno);
        $status = ($data) ? true : false;
        $result['success'] = $status;
        $result['data'] = $data;

        $this->getResponse()->setBody(Zend_Json::encode($result));
    }
}