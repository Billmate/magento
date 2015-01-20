<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-19
 * Time: 16:45
 */ 
class Billmate_Common_Model_Observer extends Mage_Core_Model_Abstract
{

    public function adminSystemConfigChangedSectionBillmate()
    {
        $billmateInvoicePath = Mage::getModuleDir('etc','Billmate_BillmateInvoice');
        $modules = Mage::getConfig()->getNode('modules')->children();
        $modulesArray = (array)$modules;
        if($modulesArray['TM_FireCheckout']) {
            if (isset($_POST['groups']['settings']['fields']['firecheckout']['value']) && $_POST['groups']['settings']['fields']['firecheckout']['value'] == 1) {


                if (rename($billmateInvoicePath . '/system.xml', $billmateInvoicePath . '/system._xml.original')) {
                    rename($billmateInvoicePath . '/system._xml.firecheckout', $billmateInvoicePath . '/system.xml');
                }
            } else {
                if (rename($billmateInvoicePath . '/system.xml', $billmateInvoicePath . '/system._xml.firecheckout')) {
                    rename($billmateInvoicePath, 'system._xml.original', $billmateInvoicePath . '/system.xml');
                }
            }
        }


    }

}