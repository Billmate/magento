<?php

class Billmate_CustomPay_Block_Bankpay_Redirect extends Mage_Core_Block_Abstract
{
    /**
     * @return string
     */
    protected function _toHtml()
    {
        $standard = Mage::getModel('billmatecustompay/methods_bankpay');

        $form = new Varien_Data_Form();
        $form->setAction($standard->getBillmateUrl())
            ->setId('billmate_bankpay_checkout')
            ->setName('billmate_bankpay_checkout')
            ->setMethod('POST')
            ->setUseContainer(true);
        foreach ($standard->getStandardCheckoutFormFields() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }
        $session = Mage::getSingleton('checkout/session');
        $orderIncrementId = $session->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        $gateway =  Mage::getSingleton('billmatecustompay/gateway_bankpay');
        $gateway->makePayment($order, true);

        $html = '<html><body>';
        $html.= $this->__('You will be redirected to the Billmate website in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementsByName("form_key").item(0).value = "";document.getElementById("billmate_bankpay_checkout").submit();</script>';
        $html.= '</body></html>';

        return $html;
    }
}
