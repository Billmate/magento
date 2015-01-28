<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-28
 * Time: 23:09
 */ 
/* @var $this Mage_Customer_Model_Entity_Setup */
$installer = $this;

$installer->startSetup();

$this->addAttribute('customer_address','person_number',array(
    'type' => 'varchar',
    'input' => 'text',
    'label' => 'SSN',
    'global' => 1,
    'required' => 0,
    'user_defined' => 1,
    'visible_on_front' => 1
));
Mage::getSingleton('eav/config')
    ->getAttribute('customer_address','person_number')
    ->setData('used_in_forms',array('customer_register_address','customer_address_edit'))
    ->save();

$installer->endSetup();