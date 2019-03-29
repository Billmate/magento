<?php

class Billmate_Cardpay_Model_PaymentAction
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'authorize',
                'label' => Mage::helper('billmatecardpay')->__('Authorize Only')
            ),
            array(
                'value' => 'sale',
                'label' => Mage::helper('billmatecardpay')->__('Sale')
            ),
        );
    }
}
