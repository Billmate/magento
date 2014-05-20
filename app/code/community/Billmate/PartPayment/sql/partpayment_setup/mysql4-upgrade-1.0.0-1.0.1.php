<?php
$installer = $this;
$installer->startSetup();
$installer->run("alter table `{$this->getTable('billmate_payment_pclasses')}` add `maxamount` decimal(50,2) NOT NULL");
$installer->endSetup(); 
