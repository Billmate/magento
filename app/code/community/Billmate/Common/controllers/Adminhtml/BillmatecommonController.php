<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-27
 * Time: 16:08
 */

class Billmate_Common_Adminhtml_BillmatecommonController extends Mage_Adminhtml_Controller_Action
{
    public function verifyAction()
    {
        $eid = $this->getRequest()->getParam('eid');
        $secret = $this->getRequest()->getParam('secret');


        $result['success'] = Mage::helper('billmatecommon')->verifyCredentials($eid,$secret);

        $this->getResponse()->setBody(Zend_Json::encode($result));
    }

    public function getaddressAction()
    {
        $pno = $this->getRequest()->getParam('billmate_pno');

        $result['success'] = Mage::helper('billmatecommon')->getAddress($pno);
    }
}