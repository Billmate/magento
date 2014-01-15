<?php

class Billmate_Bankpay_Model_Adminhtml_Comment{
    public function getCommentText(){ //this method must exits. It returns the text for the comment
        return '<img src="'.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'/billmate/images/billmate_bank_s.png?dyn"/>';
    }
}
