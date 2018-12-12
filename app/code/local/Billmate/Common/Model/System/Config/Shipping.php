<?php

class Billmate_Common_Model_System_Config_Shipping
{
    protected static $_carriers;

    protected static $_methods = null;

    /**
     * Return array of carriers.
     * If $isActiveOnlyFlag is set to true, will return only active carriers
     *
     * @param bool $isActiveOnlyFlag
     * @return array
     */
    public function toOptionArray(/*$isActiveOnlyFlag=false*/)
    {
        if (null === self::$_methods) {
            //TM fix to work with multiselect
            $isActiveOnlyFlag = false;

            $methods = array(array('value'=>'', 'label'=>''));
            // TM fix
            //$carriers = Mage::getSingleton('shipping/config')->getAllCarriers();
            $carriers = $this->getAllCarriers();
            foreach ($carriers as $carrierCode => $carrierModel) {
                if (!$carrierModel->isActive() && (bool)$isActiveOnlyFlag === true) {
                    continue;
                }
                try {
                    $carrierMethods = $carrierModel->getAllowedMethods();
                } catch (Exception $e) { // Magento 1.7 dhl bugfix http://www.magentocommerce.com/bug-tracking/issue/?issue=13411
                    continue;
                }
                if (!$carrierMethods) {
                    continue;
                }
                $carrierTitle = Mage::getStoreConfig('carriers/'.$carrierCode.'/title');
                $methods[$carrierCode] = array(
                    'label'   => $carrierTitle,
                    'value' => array(),
                );
                foreach ($carrierMethods as $methodCode=>$methodTitle) {
                    $methods[$carrierCode]['value'][] = array(
                        'value' => $carrierCode.'_'.$methodCode,
                        'label' => '['.$carrierCode.'] '.$methodTitle,
                    );
                }
            }
            self::$_methods = $methods;
        }
        return self::$_methods;
    }

    /*****************/
    // copied from /app/code/core/Mage/Shipping/Model/Config.php
    public function getAllCarriers($store=null)
    {
        $carriers = array();
        $config = Mage::getStoreConfig('carriers', $store);
        foreach ($config as $code => $carrierConfig) {
            if (empty($carrierConfig['model'])) {
                continue;
            }
            $carrier = $this->_getCarrier($code, $carrierConfig, $store);
            if ($carrier) {
                $carriers[$code] = $carrier;
            }
        }
        return $carriers;
    }

    protected function _getCarrier($code, $config, $store=null)
    {
        if (!isset($config['model'])) {
            throw Mage::exception('Mage_Shipping', 'Invalid model for shipping method: '.$code);
        }
        $modelName = $config['model'];

        /**
         * Added protection from not existing models usage.
         * Related with module uninstall process
         */
        try {
            $carrier = Mage::getModel($modelName);
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
        if (!$carrier) {
            return false;
        }
        $carrier->setId($code)->setStore($store);
        self::$_carriers[$code] = $carrier;
        return self::$_carriers[$code];
    }
}