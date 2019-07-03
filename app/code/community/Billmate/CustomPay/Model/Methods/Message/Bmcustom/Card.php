<?php
class Billmate_CustomPay_Model_Methods_Message_Bmcustom_Card extends Billmate_CustomPay_Model_Methods_Message_Bmcustom
{
    /**
     * @return string
     */
    public function getFailedMessage()
    {
        return  'Unfortunately your card payment was not processed with the provided card details. ' .
                'Please try again or choose another payment method.';
    }

    /**
     * @return string
     */
    public function getCancelMessage()
    {

        return 'The card payment has been canceled. Please try again or choose a different payment method.';
    }
}