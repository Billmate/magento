<?php

$stores = Mage::app()->getStores();
$settings = array();
$methods = array('billmateinvoice','partpayment','billmatecardpay','billmatebankpay');
$credentials = array();

foreach($stores as $storeId => $val){

    $store_id = Mage::app()->getStore($storeId)->getId();
    $x = 0;
    foreach($methods as $method){
        if(Mage::getStoreConfig('payment/'.$method.'/active',$store_id)) {

            $settings[$store_id][$x]['eid'] = Mage::getStoreConfig('payment/' . $method . '/eid', $store_id);
            $settings[$store_id][$x]['secret'] = Mage::getStoreConfig('payment/' . $method . '/secret', $store_id);
            $x++;
        }
    }
    $credentials[$store_id] = @array_unique($settings[$store_id], SORT_STRING);

    Mage::getConfig()->saveConfig('billmate/credentials/eid',$credentials[$store_id][0]['eid'],(count($stores) > 1) ? 'stores' : 'default',(count($stores) > 1) ? $store_id :0);
    Mage::getConfig()->saveConfig('billmate/credentials/secret',$credentials[$store_id][0]['secret'],(count($stores) > 1) ? 'stores' : 'default',(count($stores) > 1) ? $store_id :0);

}

