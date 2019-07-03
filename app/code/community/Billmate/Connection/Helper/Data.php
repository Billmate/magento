<?php
require_once Mage::getBaseDir('lib').'/BillmateConnection/utf8.php';
require_once Mage::getBaseDir('lib').'/BillmateConnection/commonfunctions.php';

class Billmate_Connection_Helper_Data extends Mage_Core_Helper_Abstract
{
    const BM_CREDENTIALS_ID_PATH = 'payment/bm_connnection/eid';

    const BM_CREDENTIALS_SECRET_PATH = 'payment/bm_connnection/secret';

    const BM_CREDENTIALS_TEST_MODE_PATH = 'payment/bm_connnection/testmode';

    const BM_CREDENTIALS_PUSH_EVENTS_PATH = 'payment/bm_connnection/push_events';

    const BM_CREDENTIALS_DEBUG_MODE = false;

    const BM_CONNNECTION_LOG_FILE = 'bm_connection.log';

    /**
     * @var array
     */
    protected $defPaymentData = [
        "currency"=> 'SEK',
        "country"=> 'se',
        "language"=> 'sv',
    ];

    /**
     * @param $path
     *
     * @return mixed
     */
    public function getConfigValue($path)
    {
        return Mage::getStoreConfig($path);
    }

    /**
     * @return BillMate
     */
    public function getBmProvider()
    {
        $eid = $this->getConnectionId();
        $secret = $this->getConnectionSecret();
        $testMode = $this->isTestMode();
        $debugMode = $this->isDebugMode();

        return $this->createProvider($eid, $secret, $testMode, $debugMode);
    }

    /**
     * @param $eid
     * @param $secret
     */
    public function verifyCredentials($eid, $secret)
    {
        $billmate = $this->createProvider($eid, $secret, false, false);
        $additionalInfo['PaymentData'] = $this->getDefPaymentData();

        $response = $billmate->GetPaymentPlans($additionalInfo);
        if (isset($response['code'])) {
            return false;
        }
        return true;
    }

    /**
     * @return int
     */
    public function getConnectionId()
    {
        return $this->getConfigValue(self::BM_CREDENTIALS_ID_PATH);
    }

    /**
     * @return string
     */
    public function getConnectionSecret()
    {
        return $this->getConfigValue(self::BM_CREDENTIALS_SECRET_PATH);
    }

    /**
     * @return bool
     */
    public function isTestMode()
    {
        return (bool)$this->getConfigValue(self::BM_CREDENTIALS_TEST_MODE_PATH);
    }

    /**
     * @return bool
     */
    public function isPushEvents()
    {
        return (bool)$this->getConfigValue(self::BM_CREDENTIALS_PUSH_EVENTS_PATH);
    }

    /**
     * @return bool
     */
    public function isDebugMode()
    {
        return self::BM_CREDENTIALS_DEBUG_MODE;
    }

    /**
     * @param      $eid
     * @param      $secret
     * @param      $testMode
     * @param      $debugMode
     * @param bool $useSsl
     *
     * @return BillmateConnection_Billmate
     */
    protected function createProvider($eid, $secret, $testMode, $debugMode, $useSsl = true)
    {
        return new BillmateConnection_Billmate($eid, $secret, $useSsl, $testMode, $debugMode);
    }


    /**
     * @return array
     */
    protected function getDefPaymentData()
    {
        return $this->defPaymentData;
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        $storeLanguage = Mage::app()->getLocale()->getLocaleCode();
        return BillmateCountry::fromLocale( $storeLanguage );
    }


    /**
     * @param $quote
     *
     * @return array
     */
    public function prepareArticles($quote)
    {
        $bundleArr     = array();
        $totalValue    = 0;
        $totalTax      = 0;
        $discountAdded = false;
        $configSku     = false;
        $discounts     = array();
        foreach ($quote->getAllItems() as $_item) {
            if (in_array($_item->getParentItemId(), $bundleArr)) {
                continue;
            }

            if ($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 1) {
                $bundleArr[] = $_item->getId();
            }

            if ($_item->getProductType() == 'configurable') {
                $configSku = $_item->getSku();
                $cp = $_item->getProduct();
                $sp = Mage::getModel('catalog/product')->loadByAttribute('sku', $_item->getSku());

                $price = $_item->getCalculationPrice();
                $percent = $_item->getTaxPercent();

                $discount = 0.0;
                $discountAmount = 0;
                if ($_item->getDiscountPercent() != 0) {
                    $discountAdded = true;
                    $discount = $_item->getDiscountPercent();
                    $marginal = ($percent / 100) / (1 + ($percent / 100));

                    $discountAmount = $_item->getDiscountAmount();
                    $discountAmount = $discountAmount - ($discountAmount * $marginal);

                }
                $total = ($discountAdded) ? (int)round((($price * $_item->getQty() - $discountAmount) * 100)) : (int)round($price * 100) * $_item->getQty();
                $article[] = array(
                    'quantity' => (int)$_item->getQty(),
                    'artnr' => $_item->getProduct()->getSKU(),
                    'title' => addslashes($cp->getName() . ' - ' . $sp->getName()),
                    'aprice' => (int)round($price * 100, 0),
                    'taxrate' => (float)$percent,
                    'discount' => $discount,
                    'withouttax' => $total
                );

                $temp = $total;
                $totalValue += $temp;
                $totalTax += $temp * ($percent / 100);
                if (isset($discounts[$percent])) {
                    $discounts[$percent] += $temp;
                } else {
                    $discounts[$percent] = $temp;
                }

            }
            if ($_item->getSku() == $configSku) {
                continue;
            }

            if ($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 0) {

                $percent = $_item->getTaxPercent();
                $article[] = array(
                    'quantity' => (int)$_item->getQty(),
                    'artnr' => $_item->getProduct()->getSKU(),
                    'title' => addslashes($_item->getName()),
                    // Dynamic pricing set price to zero
                    'aprice' => (int)0,
                    'taxrate' => (float)$percent,
                    'discount' => 0.0,
                    'withouttax' => (int)0

                );
            } else {
                $percent = $_item->getTaxPercent();
                $price = $_item->getCalculationPrice();
                $discount = 0.0;
                $discountAmount = 0;

                if ($_item->getDiscountPercent() != 0) {
                    $discountAdded = true;
                    $discount = $_item->getDiscountPercent();
                    $marginal = ($percent / 100) / (1 + ($percent / 100));

                    $discountAmount = $_item->getDiscountAmount();
                    $discountAmount = $discountAmount - ($discountAmount * $marginal);

                }

                $parentItem = $_item->getParentItem();
                if ($parentItem) {
                    $qty = $parentItem->getQty();
                } else {
                    $qty = $_item->getQty();
                }

                $total = ($discountAdded) ? (int)round((($price * $qty - $discountAmount) * 100)) : (int)round($price * 100) * $qty;
                $article[] = array(
                    'quantity' => (int)$qty,
                    'artnr' => $_item->getProduct()->getSKU(),
                    'title' => addslashes($_item->getName()),
                    'aprice' => (int)round($price * 100, 0),
                    'taxrate' => (float)$percent,
                    'discount' => $discount,
                    'withouttax' => $total

                );
                $temp = $total;
                $totalValue += $temp;
                $totalTax += $temp * ($percent / 100);
                if (isset($discounts[$percent])) {
                    $discounts[$percent] += $temp;
                } else {
                    $discounts[$percent] = $temp;
                }
            }
        }
        $totals = $quote->getTotals();

        if (isset($totals['discount'])) {
            foreach ($discounts as $percent => $amount)
            {
                $discountPercent           = $amount / $totalValue;
                $floor                     = 1 + ($percent / 100);
                $marginal                  = 1 / $floor;
                $discountAmount            = $discountPercent * $totals['discount']->getValue();
                $article[] = array(
                    'quantity'   => (int) 1,
                    'artnr'      => 'discount',
                    'title'      => Mage::helper('payment')->__('Discount') . ' ' . $this->__('%s Vat', $percent),
                    'aprice'     => round(($discountAmount * $marginal) * 100),
                    'taxrate'    => (float) $percent,
                    'discount'   => 0.0,
                    'withouttax' => round(($discountAmount * $marginal) * 100),

                );
                $totalValue                += (1 * round($discountAmount * $marginal * 100));
                $totalTax                  += (1 * round(($discountAmount * $marginal) * 100) * ($percent / 100));
            }
        }

        return array(
            'articles' => $article,
            'totalValue' => $totalValue,
            'totalTax' => $totalTax,
            'discounts' => $discounts
        );
    }

    /**
     * @param        $message
     * @param string $logFile
     */
    public function addLog($message, $logFile = self::BM_CONNNECTION_LOG_FILE)
    {
        Mage::log($message,0,$logFile);
    }
}