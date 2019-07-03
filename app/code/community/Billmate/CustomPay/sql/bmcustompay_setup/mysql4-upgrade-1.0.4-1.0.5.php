<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2014-12-13
 * Time: 00:04
 */
$installer = $this;
$installer->startSetup();
$installer->run("alter table `{$this->getTable('billmate_custompay_pclasses')}` ADD `language` VARCHAR(10)");
$installer->endSetup();