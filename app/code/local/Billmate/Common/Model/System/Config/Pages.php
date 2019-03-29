<?php

class Billmate_Common_Model_System_Config_Pages
{
    protected static $pages = null;

    /**
     * @return array|null
     */
    public function toOptionArray()
    {
        if (null === self::$pages) {
            $cms_pages = Mage::getModel('cms/page')->getCollection();
            $pages = array();

            foreach($cms_pages as $page) {
                $pages[$page->getPageId()] = $page->getTitle();
            }
            self::$pages = $pages;
        }
        return self::$pages;
    }
}
