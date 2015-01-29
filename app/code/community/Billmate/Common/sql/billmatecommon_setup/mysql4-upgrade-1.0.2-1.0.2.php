<?php

$installer = $this;

$installer->startSetup();

$this->removeAttribute('customer_address','person_number');


$installer->endSetup();