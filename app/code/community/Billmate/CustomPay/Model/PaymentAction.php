<?php

class Billmate_CustomPay_Model_PaymentAction
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'authorize',
                'label' => Mage::helper('billmatecustompay')->__('Authorize Only')
            ],
            [
                'value' => 'sale',
                'label' => Mage::helper('billmatecustompay')->__('Sale')
            ],
        ];
    }
}
