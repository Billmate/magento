<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-12-06
 * Time: 10:14
 */
class Billmate_Common_Model_System_Config_Cron extends Mage_Core_Model_Config_Data
{
    const CRON_STRING_PATH = 'crontab/jobs/check_billmate_status/schedule/cron_expr';

    protected function _afterSave()
    {
        $time = $this->getData('groups/fraud_check/fields/time/value');
        $frequency = $this->getValue();

        $frequencyHourly = Billmate_Common_Model_System_Config_Frequency::CRON_HOURLY;
        $frequencyMinutely = Billmate_Common_Model_System_Config_Frequency::CRON_MINUTELY;
      
        $cronExprArray = array(
            ($frequency == $frequencyMinutely && strlen($time)) ? '*/'.$time : '*',                                   # Minute
            ($frequency == $frequencyHourly && strlen($time)) ? '*/'.$time : '*',                                    # Hour
            '*',      # Day of the Month
            '*',                                                # Month of the Year
            '*',       # Day of the Week
        );
        $cronExprString = join(' ', $cronExprArray);

        try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath(self::CRON_STRING_PATH)
                ->save();
        }
        catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));

        }
    }
}