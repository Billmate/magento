<?php

$installer = $this;
$installer->startSetup();
$installer->run("alter table `{$this->getTable('billmate_payment_pclasses')}` ADD `store_id` VARCHAR(10)");
$installer->endSetup();