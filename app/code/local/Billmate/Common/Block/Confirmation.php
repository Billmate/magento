<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2017-03-22
 * Time: 08:25
 */
class Billmate_Common_Block_Confirmation extends Mage_Core_Block_Template
{


    public function _toHtml()
    {
        return $this->getIframe();
    }

    public function getIframe()
    {
        $url = Mage::registry('billmate_confirmaion_url');

        $html = '<iframe src="'.$url.'"></iframe>';
        return $html;
    }

}