<?php

class Billmate_Common_Model_System_Config_Business
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
                    'label' => Mage::helper('cron')->__('Consumer'),
                    'value' => 'consumer',
                ),
                array(
                    'label' => Mage::helper('cron')->__('Company'),
                    'value' => 'business',
                )
            );
        }
        return self::$_options;
    }
}
