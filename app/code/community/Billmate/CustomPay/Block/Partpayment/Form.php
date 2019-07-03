<?php

class Billmate_CustomPay_Block_Partpayment_Form extends Billmate_CustomPay_Block_Form
{
    const PNO_INPUT_CODE = 'bmcustom_partpayment_pno';

    const PHONE_INPUT_CODE = 'bmcustom_partpayment_phone';

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('billmatecustompay/method/partpayment.phtml');
    }

    /**
     * @return mixed
     */
    public function termsx()
    {
        $total = Mage::getSingleTon('checkout/session')->getQuote()->getShippingAddress()->getGrandTotal();

        return Mage::helper('billmatecustompay/methods')->getPlclass($total);
    }
}
