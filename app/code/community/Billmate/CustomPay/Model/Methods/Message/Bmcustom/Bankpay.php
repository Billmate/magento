<?php
class Billmate_CustomPay_Model_Methods_Message_Bmcustom_Bankpay extends Billmate_CustomPay_Model_Methods_Message_Bmcustom
{
    /**
     * @return string
     */
    public function getFailedMessage()
    {
        return  'Unfortunately your bank payment was not processed with the provided bank details. ' .
                'Please try again or choose another payment method.';
    }

    /**
     * @return string
     */
    public function getCancelMessage()
    {

        return 'The bank payment has been canceled. Please try again or choose a different payment method.';
    }
}