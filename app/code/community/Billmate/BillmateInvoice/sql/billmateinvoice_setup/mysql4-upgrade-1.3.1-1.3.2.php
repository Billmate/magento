<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2014-12-08
 * Time: 13:08
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();
$installer->addAttribute('invoice','fee_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('invoice','base_fee_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('creditmemo','fee_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->addAttribute('creditmemo','base_fee_amount',array('type' => Varien_Db_Ddl_Table::TYPE_DECIMAL));
$installer->endSetup();
