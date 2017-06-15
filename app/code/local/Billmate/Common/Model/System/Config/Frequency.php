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
                    'label' => Mage::helper('cron')->__('20 Minutes'),
                    'value' => '20/* * * * *',
                ),
                array(
                    'label' => Mage::helper('cron')->__('1 Hour'),
                    'value' => '0 * * * *',
                ),
                array('label' => Mage::helper('cron')->__('3 Hour'),
                    'value' => '* 3/* * * *'
                ),
                array('label' => Mage::helper('cron')->__('6 Hour'),
                    'value' => '* 6/* * * *'
                ),
                array('label' => Mage::helper('cron')->__('12 Hour'),
                    'value' => '* 12/* * * *'
                )

            );
        }
        return self::$_options;
    }
}