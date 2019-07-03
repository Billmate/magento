<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2014-12-12
 * Time: 23:37
 */
$installer = $this;
$installer->startSetup();
$installer->run("alter table `{$this->getTable('billmate_custompay_pclasses')}` CHANGE `pclassid` `paymentplanid`  VARCHAR(10)");
$installer->run("alter table `{$this->getTable('billmate_custompay_pclasses')}` ADD `currency` VARCHAR(10)");
$installer->run("alter table `{$this->getTable('billmate_custompay_pclasses')}` CHANGE `country` `country`  VARCHAR(10)");
$installer->endSetup();