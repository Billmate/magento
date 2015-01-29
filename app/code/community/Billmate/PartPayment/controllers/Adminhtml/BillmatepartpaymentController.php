<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-28
 * Time: 19:32
 */

class Billmate_PartPayment_Adminhtml_BillmatepartpaymentController extends Mage_Adminhtml_Controller_Action
{

    public function updateplansAction()
    {
        $collection = Mage::getModel('partpayment/pclass')->getCollection();
        foreach( $collection as $item ){
            $item->delete();
        }

        $countries = explode(',',Mage::getStoreConfig('payment/partpayment/countries'));

        $eid = Mage::getStoreConfig('billmate/credentials/eid');
        $secret = Mage::getStoreConfig('billmate/credentials/secret');
        $testmode = Mage::getStoreConfig('payment/partpayment/test_mode');


        $gateway = Mage::helper("partpayment");

        foreach( $countries as $country ){
            $gateway->savePclasses($eid, $secret, $country, $testmode);
        }

        $response['success'] = true;
        $this->getResponse()->setBody(Zend_Json::encode($response));
    }
}