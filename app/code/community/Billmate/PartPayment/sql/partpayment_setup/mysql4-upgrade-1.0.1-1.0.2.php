<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2014-12-12
 * Time: 10:11
 */
$installer = $this;
$installer->startSetup();
$installer->run("alter table `{$this->getTable('billmate_payment_pclasses')}` add `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
$installer->endSetup();
