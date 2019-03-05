<?php
echo 'Running This Upgrade: '.get_class($this)."\n <br /> \n";
$installer = $this;
/* @var $installer Mage_Sales_Model_Resource_Setup */
$installer->startSetup();
$installer->addAttribute('order','fee_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('order','base_fee_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->endSetup();
