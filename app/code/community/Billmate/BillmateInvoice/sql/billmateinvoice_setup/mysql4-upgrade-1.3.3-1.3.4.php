<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 15-03-05
 * Time: 08:11
 */
$installer = $this;
$installer->startSetup();
$installer->addAttribute('order','fee_tax_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('invoice','fee_tax_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('creditmemo','fee_tax_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('quote_address','fee_tax_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));

$installer->addAttribute('order','base_fee_tax_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('invoice','base_fee_tax_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('creditmemo','base_fee_tax_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('quote_address','base_fee_tax_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->endSetup();

