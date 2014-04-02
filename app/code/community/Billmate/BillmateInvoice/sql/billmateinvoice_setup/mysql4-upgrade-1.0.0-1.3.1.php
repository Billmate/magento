<?php
echo 'Running This Upgrade: '.get_class($this)."\n <br /> \n";
$installer = $this;
/* @var $installer Mage_Catalog_Model_Entity_Setup */
$installer->startSetup();
$installer->run("ALTER TABLE  `".$this->getTable('sales/order')."` ADD  `fee_amount` DECIMAL( 10, 2 ) NOT NULL;");
$installer->run("ALTER TABLE  `".$this->getTable('sales/order')."` ADD  `base_fee_amount` DECIMAL( 10, 2 ) NOT NULL;");
$installer->endSetup();
die("Exit for now");
