<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-12-06
 * Time: 10:21
 */
class Billmate_Common_Model_System_Config_Frequency 
{

    protected static $_options;

    const CRON_MINUTELY = 'm';
    const CRON_HOURLY   = 'H';
    const CRON_DAILY    = 'D';
    const CRON_WEEKLY   = 'W';
    const CRON_MONTHLY  = 'M';

    public function toOptionArray()
    {
        if (!self::$_options) {
            self::$_options = array(
                array(
                    'label' => Mage::helper('cron')->__('Minutely'),
                    'value' => self::CRON_MINUTELY,
                ),
                array(
                    'label' => Mage::helper('cron')->__('Hourly'),
                    'value' => self::CRON_HOURLY,
                )
            );
        }
        return self::$_options;
    }
}