<?php

class Billmate_Common_Block_Confirmation extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    public function _toHtml()
    {
        return $this->getIframe();
    }

    /**
     * @return string
     */
    public function getIframe()
    {
        $url = Mage::registry('billmate_confirmation_url');

        $html = '<iframe style="width: 100%; min-height: 800px; border:none;" src="'.$url.'"></iframe>';
        return $html;
    }

}