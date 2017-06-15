<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 2015-01-28
 * Time: 17:48
 */
require_once Mage::getBaseDir('lib').'/Billmate/Billmate.php';
require_once Mage::getBaseDir('lib').'/Billmate/utf8.php';

class  Billmate_Common_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $bundleArr = array();
    protected $totalValue = 0;
    protected $totalTax = 0;
    protected $discounts = array(); 
    public function getBillmate()
    {
        if(!defined('BILLMATE_CLIENT')) define('BILLMATE_CLIENT','MAGENTO:3.0.6');
        if(!defined('BILLMATE_SERVER')) define('BILLMATE_SERVER','2.1.7');

        $lang = explode('_',Mage::getStoreConfig('general/locale/code'));
        if(!defined('BILLMATE_LANGUAGE'))define('BILLMATE_LANGUAGE',$lang[0]);
        $eid = Mage::getStoreConfig('billmate/credentials/eid');
        $secret = Mage::getStoreConfig('billmate/credentials/secret');
        $testmode = Mage::getStoreConfig('billmate/checkout/testmode');
        return new BillMate($eid, $secret, true, $testmode,false);
    }

    public function verifyCredentials($eid,$secret)
    {

        $billmate = new BillMate($eid, $secret, true, false,false);

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

    public function getAddress($pno)
    {
        $billmate = $this->getBillmate();

        $values = array(
            'pno' => $pno
        );

        return $billmate->getAddress($values);


    }


    public function prepareArticles($quote)
    {
        $bundleArr     = array();
        $totalValue    = 0;
        $totalTax      = 0;
        $discountAdded = false;
        $discountValue = 0;
        $configSku     = false;
        $discounts     = array();
        foreach ($quote->getAllItems() as $_item) {
            // Continue if bundleArr contains item parent id, no need for get price then.
            if (in_array($_item->getParentItemId(), $bundleArr)) {
                continue;
            }
            $request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
            $taxclassid = $_item->getProduct()->getData('tax_class_id');
            // If Product type == bunde and if bundle price type == fixed
            if ($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 1) {
                // Set bundle id to $bundleArr
                $bundleArr[] = $_item->getId();

            }
            if ($_item->getProductType() == 'configurable') {
                $configSku = $_item->getSku();
                $cp = $_item->getProduct();
                $sp = Mage::getModel('catalog/product')->loadByAttribute('sku', $_item->getSku());

                $price = $_item->getCalculationPrice();
                //$percent        = Mage::getSingleton( 'tax/calculation' )->getRate( $request->setProductClassId( $taxclassid ) );
                $percent = $_item->getTaxPercent();


                $discount = 0.0;
                $discountAmount = 0;
                if ($_item->getDiscountPercent() != 0) {
                    $discountAdded = true;
                    $discount = $_item->getDiscountPercent();
                    $marginal = ($percent / 100) / (1 + ($percent / 100));

                    $discountAmount = $_item->getDiscountAmount();
                    // $discountPerArticle without VAT
                    $discountAmount = $discountAmount - ($discountAmount * $marginal);

                }
                $total = ($discountAdded) ? (int)round((($price * $_item->getQty() - $discountAmount) * 100)) : (int)round($price * 100) * $_item->getQty();
                $article[] = array(
                    'quantity' => (int)$_item->getQty(),
                    'artnr' => $_item->getProduct()->getSKU(),
                    'title' => addslashes($cp->getName() . ' - ' . $sp->getName()),
                    // Dynamic pricing set price to zero
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

            // If Product type == bunde and if bundle price type == dynamic
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


                // Else the item is not bundle and dynamic priced
            } else {
                $temp = 0;
                //$percent = Mage::getSingleton( 'tax/calculation' )->getRate( $request->setProductClassId( $taxclassid ) );
                $percent = $_item->getTaxPercent();


                // For tierPrices to work, we need to get calculation price not the price on the product.
                // If a customer buys many of a kind and get a discounted price, the price will bee on the quote item.

                $price = $_item->getCalculationPrice();

                //Mage::throwException( 'error '.$_regularPrice.'1-'. $_finalPrice .'2-'.$_finalPriceInclTax.'3-'.$_price);
                $discount = 0.0;
                $discountAmount = 0;
                if ($_item->getDiscountPercent() != 0) {
                    $discountAdded = true;
                    $discount = $_item->getDiscountPercent();
                    $marginal = ($percent / 100) / (1 + ($percent / 100));

                    $discountAmount = $_item->getDiscountAmount();
                    // $discountPerArticle without VAT
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
        return array(
            'articles' => $article,
            'totalValue' => $totalValue,
            'totalTax' => $totalTax,
            'discounts' => $discounts
        );
    }
    
}
