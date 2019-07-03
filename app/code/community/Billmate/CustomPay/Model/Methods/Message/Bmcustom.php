<?php
abstract class Billmate_CustomPay_Model_Methods_Message_Bmcustom extends Varien_Object
{
    /**
     * @return string
     */
    abstract public function getFailedMessage();

    /**
     * @return string
     */
    abstract public function getCancelMessage();
}