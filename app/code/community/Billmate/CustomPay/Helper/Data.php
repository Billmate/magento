<?php
class Billmate_CustomPay_Helper_Data extends Mage_Core_Helper_Abstract
{
    const SV_PATH_CODE = 'sv';

    const DEFAULT_PATH_CODE = 'en';

    const SHOW_PNO_CONFIG_PATH = 'payment/bm_connnection/show_pno_form';

    const BM_PAYMENT_LOG_FILE = 'bm_custom_payment.log';

    /**
     * @var array
     */
    protected $_svLocales = [
        'sv_SE'
    ];

    /**
     * @var Billmate_Connection_Helper_Data
     */
    protected $connectionHelper;

    public function __construct()
    {
        $this->connectionHelper = Mage::helper('bmconnection');
    }

    /**
     * @param bool $ssl
     * @param bool $debug
     *
     * @return BillMate
     */
    public function getBillmate()
    {
        return $this->connectionHelper->getBmProvider();
    }

    /**
     * @return bool
     */
    public function isShowPnoForm()
    {
        return  (bool)$this->getConfigValue(self::SHOW_PNO_CONFIG_PATH);
    }

    /**
     * @param $paymentCode
     *
     * @return bool
     */
    public function isActivePayment($paymentCode)
    {
        return  (bool)$this->getConfigValue('payment/' . $paymentCode . '/active') ;
    }

    /**
     * @param $methodCode
     *
     * @return string
     */
    public function getMethodLogo($methodCode)
    {
        $langPath = $this->getLogoLangPath();
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) .
            'bmcustompay/images/' . $langPath . DIRECTORY_SEPARATOR . $methodCode . '.png';
    }

    /**
     * @return bool
     */
    public function useEmailQueue()
    {
        $magentoVersion = Mage::getVersion();
        $isEE = Mage::helper('core')->isModuleEnabled('Enterprise_Enterprise');
        return version_compare($magentoVersion, '1.9.1', '>=') && !$isEE;
    }

    /**
     * @return string
     */
    protected function getLogoLangPath()
    {
        $localeCode = Mage::app()->getLocale()->getLocaleCode();
        if (in_array($localeCode, $this->getSvLocales())) {
           return self::SV_PATH_CODE;
        }
        return self::DEFAULT_PATH_CODE;
    }

    /**
     * @return array
     */
    protected function getSvLocales()
    {
        return $this->_svLocales;
    }

    /**
     * @param $message
     */
    public function addLog($message)
    {
        $this->connectionHelper->addLog($message, self::BM_PAYMENT_LOG_FILE);
    }

    /**
     * @return int
     */
    public function getStoreIdForConfig()
    {
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) {
            $store_id = Mage::getModel('core/store')->load($code)->getId();
        } elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) {
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        } else {
            $store_id = 0;
        }

        return $store_id;
    }

    /**
     * @param $path
     *
     * @return mixed
     */
    protected function getConfigValue($path)
    {
        return Mage::getStoreConfig($path);
    }
}