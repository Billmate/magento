<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2014-12-08
 * Time: 14:12
 */
$installer = $this;
$installer->startSetup();
$installer->addAttribute('quote_address','fee_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('quote_address','base_fee_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('order','fee_amount_invoiced',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('order','base_fee_amount_invoiced',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('order','fee_amount_refunded',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('order','base_fee_amount_refunded',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->endSetup();