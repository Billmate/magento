<?php

class Billmate_BillmateInvoice_Helper_Total extends Mage_Core_Helper_Abstract
{


    public function addToBlock($block)
    {
        $order = $block->getOrder();
        $info = $order->getPayment()->getMethodInstance()->getInfoInstance();
        $storeId = Mage::app()->getStore()->getId();
        $vatOption = Mage::getStoreConfig("tax/sales_display/price", $storeId);

        $invoiceFee = $info->getAdditionalInformation('billmateinvoice_fee');

		$extra = '';
		
        $fee = new Varien_Object();
        $fee->setCode('billmateinvoice_fee');
        $label = Mage::helper('billmateinvoice')->__('Billmate Invoice Fee (Incl. Vat)');

        $fee->setLabel($label);
        $fee->setBaseValue($invoiceFee);
        $fee->setValue($invoiceFee);
        $block->addTotalBefore($fee, 'shipping');

        return $block;
    }

}
