<?php
$installer = $this;
$installer->startSetup();
$installer->run("alter table `{$this->getTable('billmate_custompay_pclasses')}` add `maxamount` decimal(50,2) NOT NULL");
$installer->endSetup(); 
