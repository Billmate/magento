<?php
require_once Mage::getBaseDir('lib').'/Billmate/Billmate.php';
require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';

class  Billmate_Common_Helper_Data extends Mage_Core_Helper_Abstract
{
    const DEF_POST_CODE = '12345';

    /**
     * @var array
     */
    protected $bundleArr = array();

    /**
     * @var int
     */
    protected $totalValue = 0;

    /**
     * @var int
     */
    protected $totalTax = 0;

    /**
     * @var array
     */
    protected $discounts = array();

    /**
     * @var array
     */
    protected $paymentMethodMap = [
        1 => 'billmateinvoice',
        4 => 'billmatepartpayment',
        8 => 'billmatecardpay',
        16 => 'billmatebankpay'
    ];

    /**
     * @var array
     */
    protected $shippingRatesCodes = [];

    /**
     * @return BillMate
     */
    public function getBillmate()
    {
        $eid = Mage::getStoreConfig('billmate/credentials/eid');
        $secret = Mage::getStoreConfig('billmate/credentials/secret');
        $testmode = Mage::getStoreConfig('billmate/checkout/testmode');
        return new Billmate_Billmate($eid, $secret, true, $testmode,false);
    }

    /**
     * @param $eid
     * @param $secret
     *
     * @return bool
     */
    public function verifyCredentials($eid,$secret)
    {
        $billmate = new Billmate_Billmate($eid, $secret, true, false,false);

        $additionalinfo['PaymentData'] = array(
            "currency"=> 'SEK',//SEK
            "country"=> 'se',//Sweden
            "language"=> 'sv',//Swedish
        );

        $result = $billmate->GetPaymentPlans($additionalinfo);
        if(isset($result['code']) && $result['code'] == '9013'){
            return false;
        }
        return true;

    }

    /**
     * @param $pno
     *
     * @return mixed
     */
    public function getAddress($pno)
    {
        $billmate = $this->getBillmate();

        $values = array(
            'pno' => $pno
        );

        return $billmate->getAddress($values);
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
     * @return string
     */
    public function getTermsUrl()
    {
        $termsPageId = Mage::getStoreConfig('billmate/checkout/terms_page');
        $termPageUrl = Mage::helper('cms/page')->getPageUrl($termsPageId);
        return $termPageUrl;
    }

    /**
     * @return mixed
     */
    public function getPrivacyUrl()
    {
        $privacyPolicyPageId = Mage::getStoreConfig('billmate/checkout/privacy_policy_page');
        $privacyPolicyPageUrl= Mage::helper('cms/page')->getPageUrl($privacyPolicyPageId);
        return $privacyPolicyPageUrl;
    }

    public function getCheckoutMode(){
        return Mage::getStoreConfig('billmate/checkout/billmate_checkout_mode');
    }

    /**
     *
     * @return string
     */
    public function getPaymentMethodCode()
    {
        return Billmate_BillmateCheckout_Model_Billmatecheckout::METHOD_CODE;
    }

    /**
     * @param $billmateStatus
     *
     * @return string
     */
    public function getAdaptedStatus($billmateStatus)
    {
        return strtolower($billmateStatus);
    }

    /**
     * @return string
     */
    public function getDefaultPostcode()
    {
        $postCode = Mage::getStoreConfig('shipping/origin/postcode');
        if ($postCode) {
            return $postCode;
        }
        return self::DEF_POST_CODE;
    }

    /**
     * @return string
     */
    public function getContryId()
    {
        return  Mage::getStoreConfig('general/country/default');
    }

    /**
     * @return string
     */
    public function getDefaultShipping()
    {
        $shippingMethodCode = Mage::getStoreConfig('billmate/checkout/shipping_method');
        $allowedShippingMethods = $this->getAllowedShippingMethods();
        if (!in_array($shippingMethodCode, $allowedShippingMethods)) {
            return current($allowedShippingMethods);
        }
        return $shippingMethodCode;
    }


    /**
     * @return array
     */
    protected function getAllowedShippingMethods()
    {
        $checkoutSession = Mage::getSingleton('checkout/session');
        $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();

        $shippingRates = $shippingAddress
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->getAllShippingRates();

        foreach ($shippingRates as $rate) {
            $this->shippingRatesCodes[] = $rate->getCode();
        }

        return $this->shippingRatesCodes;
    }

    /**
     * @return string
     */
    public function getBillmateCheckoutOrderStatus()
    {
        return Mage::getStoreConfig('payment/billmatecheckout/order_status');
    }

    /**
     * @return bool
     */
    public function isAllowedBackEvents()
    {
        return  (bool)Mage::getStoreConfig('billmate/settings/activation');
    }
}
