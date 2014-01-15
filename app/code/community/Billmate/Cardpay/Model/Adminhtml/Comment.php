<?php

class Billmate_Cardpay_Model_Adminhtml_Comment{
    public function getCommentText(){ //this method must exits. It returns the text for the comment
        return '<img src="'.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."/billmate/images/bm_kort_l.png".'"/>';
    }
}