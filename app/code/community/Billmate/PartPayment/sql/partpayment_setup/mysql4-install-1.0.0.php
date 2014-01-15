<?php
$installer = $this;
$installer->startSetup();
$installer->run("CREATE TABLE IF NOT EXISTS `{$this->getTable('billmate_payment_pclasses')}` (
  `eid` int(10) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(4) NOT NULL,
  `description` varchar(255) NOT NULL,
  `months` int(11) NOT NULL,
  `interestrate` decimal(11,2) NOT NULL,
  `invoicefee` decimal(11,2) NOT NULL,
  `startfee` decimal(11,2) NOT NULL,
  `minamount` decimal(11,2) NOT NULL,
  `country` int(11) NOT NULL,
  `expire` int(11) NOT NULL,
  `country_code` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `pclassid` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$installer->endSetup(); 
