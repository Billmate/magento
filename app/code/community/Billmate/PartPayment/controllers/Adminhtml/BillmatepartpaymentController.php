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
        $store = $this->getRequest()->getParam('store');
        $collection->addFieldToFilter('store_id',$store);
        foreach( $collection as $item ){
            $item->delete();
        }

        $countries = explode(',',Mage::getStoreConfig('payment/billmatepartpayment/countries'));
        $lang = explode('_',Mage::getStoreConfig('general/locale/code',$store));

        $eid = Mage::getStoreConfig('billmate/credentials/eid');
        $secret = Mage::getStoreConfig('billmate/credentials/secret');
        $testmode = Mage::getStoreConfig('payment/billmatepartpayment/test_mode');


        $gateway = Mage::helper("partpayment");

        foreach($countries as $country)
            $gateway->savePclasses($eid, $secret, $country, $testmode, $lang[0],$store);

        $pclass = Mage::getModel('partpayment/pclass')->getCollection();
        $pclass->addFieldToFilter('store_id',Mage::helper('partpayment')->getStoreIdForConfig());

        if( $pclass->count() > 0 ){
            $html = '<div class="grid"><table border="0" class="data"><tr class="headings"><th>PClassid</th><th>Type</th><th>Description</th><th>Months</th><th>Interest Rate</th><th>Invoice Fee</th><th>Start Fee</th><th>Min Amount</th><th>Max Amount</th><th>Expire</th><th>Country</th></tr>';
            $i=0;
            foreach($pclass as $_item ){
                $id = $_item->getPaymentplanid();
                $typ= $_item->getType();
                $des= $_item->getDescription();
                $mont= $_item->getNbrofmonths();
                $int = $_item->getInterestrate();
                $fee = $_item->getHandlingfee();
                $min = $_item->getMinamount();
                $max = $_item->getMaxamount();
                $startfee = $_item->getStartfee();
                $country= $_item->getCountryCode();
                $exp = $_item->getExpirydate();
                $class = $i%2 == 0 ? 'even' :'odd';
                $i++;
                $html.='<tr class="'.$class.' pointer"><td>'.$id.'</td><td>'.$typ.'</td><td>'.$des.'</td><td class="a-center">'.$mont.'</td><td>'.$int.'</td><td>'.$fee.'</td><td>'.$startfee.'</td><td>'.$min.'</td><td>'.$max.'</td><td>'.$exp.'</td><td>'.$country.'</td></tr>';
            }
            $html.='</table></div>';
            $status = true;
        } else {
            $html = '<b>'.$this->__('No Pclasses found').'</b>';
            $status = false;
        }


        $response['content'] = $html;
        $response['success'] = $status;
        $this->getResponse()->setBody(Zend_Json::encode($response));
    }
}