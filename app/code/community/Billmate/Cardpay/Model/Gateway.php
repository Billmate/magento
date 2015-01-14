<?php
class Billmate_Cardpay_Model_Gateway extends Varien_Object{
    public $isMatched = true;
    function makePayment(){
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $_customer  = Mage::getSingleton('customer/session')->isLoggedIn() ? Mage::getSingleton('customer/session')->getCustomer()->getData() : null;
        $Customer = (object)$_customer;

        if(empty($_POST)) $_POST = $_GET;
        $country_to_currency = array(
            'NO' => 'NOK',
            'SE' => 'SEK',
            'FI' => 'EUR',
            'DK' => 'DKK',
            'DE' => 'EUR',
            'NL' => 'EUR',
        );
        $methodname = 'billmatecardpay';
        $k = Mage::helper($methodname)->getBillmate(true, false);

        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();


        //  $quote = Mage::getSingleton('checkout/session')->getQuote();
        $Billing= $quote->getBillingAddress();
        $Shipping= $quote->getShippingAddress();

        $billingiso3 = Mage::getModel('directory/country')->load($Billing->getCountryId())->getIso3Code();
        $billing_country = Mage::getModel('directory/country')->load($Billing->getCountryId())->getName() ;

        $shippingiso3 = Mage::getModel('directory/country')->load($Shipping->getCountryId())->getIso3Code();
        $shipping_country = Mage::getModel('directory/country')->load($Shipping->getCountryId())->getName() ;
        $shipping_country = $shipping_country == 'SWE' ? 209 : $shipping_country;

        // Get Store Country
        $countryCode = Mage::getStoreConfig('general/country/default',Mage::app()->getStore());
        $storeCountryIso2 = Mage::getModel('directory/country')->loadByCode($countryCode)->getIso2Code();

        // Get Store language
        $storeLanguage = Mage::app()->getLocale()->getLocaleCode();

        $language = 138;
        $encoding = 2;
        $currency = 0;

        switch ($billingiso3) {
            // Sweden
            case 'SWE':
                $country = 209;
                $language = 138;
                $encoding = 2;
                $currency = 0;
                $billing_country = 209;
                break;
        }
        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        $ship_address = $bill_address = array();
        $shipp = $Shipping->getStreet();
        $bill = $Billing->getStreet();
        $orderValues['PaymentData'] = array(
            'method' => 8,
            'currency' => $currentCurrencyCode,
            'country' => $storeCountryIso2,
            'orderid' => (string)time(),
            'autoactivate' => Mage::getStoreConfig('payment/billmatecardpay/payment_action') == 'sale' ? 1 : 0,
            'language' => BillmateCountry::fromLocale($storeLanguage)

        );
        $orderValues['PaymentInfo'] = array(
            'paymentdate' => (string)date('Y-m-d'),
            'paymentterms' => 14,
            'yourreference' => $Billing->getFirstname(). ' ' . $Billing->getLastname(),
            'delivery' => $Shipping->getShippingDescription(),

        );
        $prompt_name = Mage::getStoreConfig('payment/billmatecardpay/prompt_name') == 1 ? '1' : '0';
        $do3dsecure = Mage::getStoreConfig('payment/billmatecardpay/do_3d_secure') == 0 ? '0' : '1';

        $orderValues['Card'] = array(
            '3dsecure' => $do3dsecure,
            'promptname' => $prompt_name,
            'accepturl' => Mage::getUrl('cardpay/cardpay/success'),
            'cancelurl' => Mage::getUrl('cardpay/cardpay/cancel'),
            'callbackurl' => Mage::getUrl('cardpay/cardpay/notify'),
            'returnmethod' => 'POST'
        );

        $orderValues['Customer'] = array(
            'nr' => $customerId,
        );
        $orderValues['Customer']['Billing'] = array(
            'firstname' => $Billing->getFirstname(),
            'lastname' => $Billing->getLastname(),
            'company' => $Billing->getCompany(),
            'street' => $bill[0],
            'street2' => isset($bill[1]) ? $bill[1] : '',
            'zip' => $Billing->getPostcode(),
            'city' => $Billing->getCity(),
            'country' => BillmateCountry::fromCode($Billing->getCountry()),
            'phone' => $Billing->getTelephone(),
            'email' => $Billing->email
        );

        $orderValues['Customer']['Shipping'] = array(
            'firstname' => $Shipping->getFirstname(),
            'lastname' => $Shipping->getLastname(),
            'company' => $Shipping->getCompany(),
            'street' => $shipp[0],
            'street2' => isset($shipp[1]) ? $shipp[1] : '',
            'zip' => $Shipping->getPostcode(),
            'city' => $Shipping->getCity(),
            'country' => BillmateCountry::fromCode($Shipping->getCountry()),
            'phone' => $Shipping->getTelephone()
        );
        foreach($bill_address as $key => $col ){
            $bill_address[$key] = utf8_decode($col);
        }
        foreach($ship_address as $key => $col ){
            $ship_address[$key] = utf8_decode($col);
        }
        $goods_list = array();

        $_directory = Mage::helper('directory');
        $_taxHelper  = Mage::helper('tax');
        $_weeeHelper = Mage::helper('weee');
        $percent = 0;
        $store = Mage::app()->getStore();
        $discountAmount = 0;
        $_simplePricesTax = ($_taxHelper->displayPriceIncludingTax() || $_taxHelper->displayBothPrices());
        // Create Array to save ParentId when bundle is fixed prised
        $bundleArr = array();
        $totalValue = 0;
        $totalTax = 0;
        $discountAdded = false;
        $discountValue = 0;
        $discountTax = 0;
        $discounts = array();
        $configSku = false;
        foreach( $quote->getAllItems() as $_item){
            /**
             * @var $_item Mage_Sales_Model_Quote_Item
             */
            // Continue if bundleArr contains item parent id, no need for get price then.
            if( in_array($_item->getParentItemId(),$bundleArr)){
                continue;
            }
            $request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
            $taxclassid = $_item->getProduct()->getData('tax_class_id');
            // If Product type == bunde and if bundle price type == fixed
            if($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 1){
                // Set bundle id to $bundleArr
                $bundleArr[] = $_item->getId();

            }
            if($_item->getProductType() == 'configurable'){
                $configSku = $_item->getSku();
                $cp = $_item->getProduct();
                $sp = Mage::getModel('catalog/product')->loadByAttribute('sku',$_item->getSku());

                $price = $_directory->currencyConvert($_item->getCalculationPrice(),$baseCurrencyCode,$currentCurrencyCode);
                $percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
                $discount = 0.0;

                if($_item->getBaseDiscountAmount() != 0){
                    $discountAdded = true;
                    $discount = 100 *($_item->getBaseDiscountAmount() / $price);
                    $marginal = ($percent/100)/ (1+($percent/100));
                    $discountTax = ($_item->getQty() * $_item->getBaseDiscountAmount()) * $marginal;
                }
                $orderValues['Articles'][] = array(
                    'quantity'   => (int)$_item->getQty(),
                    'artnr'    => $_item->getProduct()->getSKU(),
                    'title'    => $cp->getName().' - '.$sp->getName(),
                    // Dynamic pricing set price to zero
                    'aprice'    => (int)round($price*100,0),
                    'taxrate'      => (float)$percent,
                    'discount' => $discount,
                    'withouttax' => (int)round($price*100) * $_item->getQty()

                );
                Mage::log('disctax'. $discountTax. 'price'. $price. 'discB'. $_item->getBaseDiscountAmount().'disc'. $_item->getDiscountAmount());
                $discounts[(int)$percent] += $_item->getBaseDiscountAmount() * $_item->getQty() - $discountTax;

                //$discountValue += $_item->getBaseDiscountAmount() * $_item->getQty();
                $temp = $_item->getQty() * (int)round($price*100,0);
                $totalValue += $temp;
                $totalTax += $temp * ($percent/100);

            }
            if($_item->getSku() == $configSku){


                continue;
            }
            // If Product type == bunde and if bundle price type == dynamic
            if($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 0){

                $percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
                $orderValues['Articles'][] = array(
                    'quantity'   => (int)$_item->getQty(),
                    'artnr'    => $_item->getProduct()->getSKU(),
                    'title'    => $_item->getName(),
                    // Dynamic pricing set price to zero
                    'aprice'    => (int)0,
                    'taxrate'      => (float)$percent,
                    'discount' => 0.0,
                    'withouttax' => (int)0

                );


                // Else the item is not bundle and dynamic priced
            } else {
                $temp = 0;
                $percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));


                // For tierPrices to work, we need to get calculation price not the price on the product.
                // If a customer buys many of a kind and get a discounted price, the price will bee on the quote item.
                $price = $_directory->currencyConvert($_item->getCalculationPrice(),$baseCurrencyCode,$currentCurrencyCode);

                //Mage::throwException( 'error '.$_regularPrice.'1-'. $_finalPrice .'2-'.$_finalPriceInclTax.'3-'.$_price);
                $discount = 0.0;

                if($_item->getBaseDiscountAmount() != 0){
                    $discountAdded = true;
                    $discount = 100 *($_item->getBaseDiscountAmount() / $price);
                    $marginal = ($percent/100)/ (1+($percent/100));
                    $discountTax = ($_item->getQty() * $_item->getBaseDiscountAmount()) * $marginal;
                }
                $orderValues['Articles'][] = array(
                    'quantity'   => (int)$_item->getQty(),
                    'artnr'    => $_item->getProduct()->getSKU(),
                    'title'    => $_item->getName(),
                    'aprice'    => (int)round($price*100,0),
                    'taxrate'      => (float)$percent,
                    'discount' => $discount,
                    'withouttax' => $_item->getQty() * (int)round($price*100,0)

                );
                $discountValue += $_item->getBaseDiscountAmount() * $_item->getQty();
                $discounts[(int)$percent] += $_item->getBaseDiscountAmount() * $_item->getQty() - $discountTax;
                $temp = $_item->getQty() * (int)round($price*100,0);
                $totalValue += $temp;
                $totalTax += $temp * ($percent/100);
            }
        }
        $totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();

        //print_r($quote1['subtotal']->getData());

        if(isset($totals['discount']) && !$discountAdded) {
            $orderValues['Articles'][] = array(
                'quantity'   => (int)1,
                'artnr'    => 'discount',
                'title'    => Mage::helper('payment')->__('Discount'),
                'aprice'    => round($totals['discount']->getValue()*0.8)*100,
                'taxrate'      => (float)$percent,
                'discount' => 0.0,
                'withouttax'    => round($totals['discount']->getValue()*0.8)*100,

            );
            $totalValue += (1 * round($totals['discount']->getValue()*0.8))*100;
            $totalTax += ((1 * round($totals['discount']->getValue()*0.8))*100) * ($percent/100);
        }

        if(isset($totals['discount']) && $discountAdded) {

            foreach ($discounts as $percent => $value){
                $orderValues['Articles'][] = array(
                    'quantity' => (int)1,
                    'artnr' => 'discount',
                    'title' => Mage::helper('payment')->__('Discount').' - '. Mage::helper('payment')->__('Vat'). $percent.'%',
                    'aprice' => -round((abs($value)) * 100),
                    'taxrate' => (float)$percent,
                    'discount' => 0.0,
                    'withouttax' => -round(abs($value) * 100),

                );
                $totalValue -= round((abs($value)) * 100);
                $totalTax -= round((abs($value)) * 100) * ($percent / 100);
            }
        }

        $rates = $quote->getShippingAddress()->getShippingRatesCollection();
        if(!empty($rates)){
            if( $Shipping->getBaseShippingTaxAmount() > 0 )
                $rate = round( $Shipping->getBaseShippingTaxAmount() / $Shipping->getBaseShippingAmount() * 100);
            else
                $rate = 0;

            $orderValues['Cart']['Shipping'] = array(
                'withouttax' => $Shipping->getShippingAmount()*100,
                'taxrate' => (int)$rate
            );
            $totalValue += $Shipping->getShippingAmount()*100;
            $totalTax += ($Shipping->getShippingAmount()*100) * ($rate/100);
        }

        $orderValues['Cart']['Total'] = array(
            'withouttax' => $totalValue,
            'tax' => $totalTax,
            'withtax' => $totalValue + $totalTax
        );
        $result = $k->addPayment($orderValues);

        if( isset($result['code'])){
            Mage::throwException( utf8_encode( $result['message']));
        }else{
            $session = Mage::getSingleton('core/session', array('name' => 'frontend'));
            $session->setData('billmateinvoice_id', $result['number']);
            $session->setData('billmateorder_id', $result['orderid']);
        }
        return $result['url'];
    }
}
