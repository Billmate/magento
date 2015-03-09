<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2014-12-12
 * Time: 11:06
 */
$installer = $this;
$installer->startSetup();
$installer->run("alter table `{$this->getTable('billmate_payment_pclasses')}` CHANGE `expire` `expirydate`  DATE");
$installer->run("alter table `{$this->getTable('billmate_payment_pclasses')}` CHANGE `invoicefee` `handlingfee`  INT");
$installer->run("alter table `{$this->getTable('billmate_payment_pclasses')}` CHANGE `months` `nbrofmonths`  INT");
$installer->endSetup();