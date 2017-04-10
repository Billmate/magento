<?php
/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-04-10
 * Time: 11:05
 */
/** @var $installer Mage_Sales_Model_Entity_Setup */
$installer = $this;
$installer->startSetup();
$installer->addAttribute('quote','billmate_hash',array('type' => Varien_Db_Ddl_Table::TYPE_VARCHAR));
$installer->endSetup();