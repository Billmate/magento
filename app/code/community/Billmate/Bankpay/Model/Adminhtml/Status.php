<?php

class Billmate_Bankpay_Model_Adminhtml_Status extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    protected $_stateStatuses = array(
        Mage_Sales_Model_Order::STATE_NEW,
        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
        Mage_Sales_Model_Order::STATE_PROCESSING,
        Mage_Sales_Model_Order::STATE_COMPLETE,
        Mage_Sales_Model_Order::STATE_CLOSED,
        Mage_Sales_Model_Order::STATE_CANCELED,
        Mage_Sales_Model_Order::STATE_HOLDED,
        Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
        'Communication Error',
        'No Response from acquirer',
        'Suspected Fraud',
        'Not Sufficient Funds',
        'Stolen Card',
        'Rejected By Acquirer'
    );
}
