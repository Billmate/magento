<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-09-26
 * Time: 11:52
 */
class Billmate_Common_BillmatecheckoutController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        
        $this->loadLayout();
        $this->renderLayout();
        
        
    }
}