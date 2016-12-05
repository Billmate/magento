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
		$status = Mage::helper('billmatecommon')->verifyCredentials($eid,$secret);
		$store = $this->getRequest()->getParam('store_id');
	    if($status && Mage::getStoreConfig('payment/partpayment/active',$store) == 1){
		    $collection = Mage::getModel('partpayment/pclass')->getCollection();

		    $collection->addFieldToFilter('store_id',$store);
		    foreach( $collection as $item ){
			    $item->delete();
		    }

		    $countries = explode(',',Mage::getStoreConfig('payment/partpayment/countries'));
		    $lang = explode('_',Mage::getStoreConfig('general/locale/code',$store));

		    $testmode = Mage::getStoreConfig('payment/partpayment/test_mode',$store);


		    $gateway = Mage::helper("partpayment");

		    foreach($countries as $country)
			    $gateway->savePclasses($eid, $secret, $country, $testmode, $lang[0],$store);

		    $pclass = Mage::getModel('partpayment/pclass')->getCollection();
		    $pclass->addFieldToFilter('store_id',$store);

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
	    }

        $result['success'] = $status;

        $this->getResponse()->setBody(Zend_Json::encode($result));
    }
}