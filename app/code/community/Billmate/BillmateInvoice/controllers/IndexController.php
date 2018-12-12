<?php


class Billmate_BillmateInvoice_IndexController extends Mage_Core_Controller_Front_Action
{
    public function IndexAction()
    {
        $quote =  Mage::getSingleton('checkout/session')->getQuote();
                
        if ($this->getRequest()->isPost()){

			$payment = $_POST['payment'];
            $pophide = $this->getRequest()->getPost('pophide',false);
            if($pophide === false) {
                if (!in_array($payment['method'], array('billmateinvoice', 'billmatepartpayment'))) {
	                if(Mage::getStoreConfig('streamcheckout/general/enabled'))
		                echo '$(streamcheckout.container).removeClassName("placing-order");streamcheckout.placeUrl=oldurl;streamcheckout.place()';
	                else
		                echo 'payment.saveUrl=oldurl;payment.save();payment.onComplete=function(){checkout.setLoadWaiting(false);payment.saveUrl = billmateindexurl;payment.onComplete = function(res){ checkout.setLoadWaiting(false); eval(res.responseText);}}';
	                return;
                }
                if(Mage::getStoreConfig('firecheckout/general/enabled') || Mage::getStoreConfig('streamcheckout/general/enabled')) {
                    if (empty($payment['person_number']) && empty($payment[$payment['method'].'_pno'])) {
                        $stream = Mage::getStoreConfig('streamcheckout/general/enabled') ? '$(streamcheckout.container).removeClassName("placing-order");' : '';

                        $js = 'if($("'.$payment['method'].'_pno").offsetHeight > 0){'."\r".
                            '$("'.$payment['method'].'_pno").addClassName("validation-failed");'."\r".
                            '$("'.$payment['method'].'_pno").insert({after: "<div class=\"validation-advice\" id=\"getaddress_failure\">'.Mage::helper('billmatecommon')->__('Missing Social Security Number / Corporate Registration Number').'</div>"})'."\r".
                            $stream."\r".
                            '} else {'."\r".
                            '$("person_number").addClassName("validation-failed")'."\r".
                            '$("billmategetaddress").insert({after: "<div class=\"validation-advice\" id=\"getaddress_failure\">'.Mage::helper('billmatecommon')->__('Missing Social Security Number / Corporate Registration Number').'</div>"})'."\r".
                            $stream."\r".
                            '}';
                        die($js);
                        //die('alert("' . Mage::helper('payment')->__('Missing Social Security Number / Corporate Registration Number') . '")');
                    }
                }
                else {
                    if (empty($payment[$payment['method'] . '_pno'])) {
                        $js = 'if($("'.$payment['method'].'_pno").offsetHeight > 0){'."\r".
                            '$("'.$payment['method'].'_pno").addClassName("validation-failed");'."\r".
                            '$("'.$payment['method'].'_pno").insert({after: "<div class=\"validation-advice\" id=\"getaddress_failure\">'.Mage::helper('billmatecommon')->__('Missing Social Security Number / Corporate Registration Number').'</div>"})'."\r".
                            '}';
                        die($js);
                        //die('alert("' . Mage::helper('payment')->__('Missing Social Security Number / Corporate Registration Number') . '")');
                    }
                }
                if (empty($payment[$payment['method'] . '_phone'])) {
                    die('alert("' . Mage::helper('payment')->__('Please confirm email address is accurate and can be used for billing') . '")');
                }
            }
		    
			$gateway = Mage::getSingleton('billmateinvoice/gateway');
            $gateway->init();
            $this->loadLayout();
            $this->getResponse()->setBody($this->getLayout()->createBlock('billmateinvoice/changeaddress')->toHtml());
        }
    
    }

	public function getInfoAction()
    {
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
	}

    public function updateAddressAction()
    {
        if ($this->getRequest()->isPost()){
            $gateway = Mage::getSingleton('billmateinvoice/gateway');
            $gateway->init(true);
            $this->loadLayout();
            $this->getResponse()->setBody($this->getLayout()->createBlock('billmateinvoice/changeaddress')->toHtml());
        }
    }
}
