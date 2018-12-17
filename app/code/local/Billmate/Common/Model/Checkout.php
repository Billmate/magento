<?php

class Billmate_Common_Model_Checkout extends Billmate_Common_Model_Payment_GatewayCore
{
    const METHOD_CODE = 93;

    public function init()
    {
        $quote = $this->getQuote();
        $shippingAddress= $quote->getShippingAddress();

        $orderValues = array();
        $orderValues['CheckoutData'] = array(
            'windowmode' => 'iframe',
            'sendreciept' => 'yes'
        );

        // Terms page
        $termsPageId = Mage::getStoreConfig('billmate/checkout/terms_page');
        $termsPagePermalink = Mage::helper('cms/page')->getPageUrl($termsPageId);
        if ($termsPagePermalink != "") {
            $orderValues['CheckoutData']['terms'] = $termsPagePermalink;
        }

        // Privacy Policy page
        $privacyPolicyPageId = Mage::getStoreConfig('billmate/checkout/privacy_policy_page');
        $privacyPolicyPermaLink = Mage::helper('cms/page')->getPageUrl($privacyPolicyPageId);
        if ($privacyPolicyPermaLink != '') {
            $orderValues['CheckoutData']['privacyPolicy'] = $privacyPolicyPermaLink;
        }

        if(!$quote->getReservedOrderId())
            $quote->reserveOrderId();

        $orderValues['PaymentData'] = $this->getPaymentData();
        $orderValues['PaymentData']['accepturl'] = Mage::getUrl('billmatecommon/callback/accept', array('_query' => array('billmate_checkout' => true,'billmate_quote_id' => $quote->getId()), '_secure' => true));
        $orderValues['PaymentData']['cancelurl'] = Mage::getUrl('billmatecommon/callback/cancel', array('_secure' => true));
        $orderValues['PaymentData']['callbackurl'] = Mage::getUrl('billmatecommon/callback/callback', array('_query' => array('billmate_quote_id' => $quote->getId(),'billmate_checkout' => true), '_secure' => true));

        $orderValues['PaymentData']['returnmethod'] = (Mage::app()->getStore()->isCurrentlySecure()) ? 'POST' : 'GET';

        $preparedArticle = Mage::helper('billmatecommon')->prepareArticles($quote);
        $totalTax = $preparedArticle['totalTax'];
        $totalValue = $preparedArticle['totalValue'];
        $orderValues['Articles'] = $preparedArticle['articles'];

        $shippingCostData = $this->getShippingCostData();
        if ($shippingCostData) {
            $orderValues['Cart']['Shipping'] = $shippingCostData;
            $totalValue += $shippingCostData['withouttax'];
            $totalTax += ($shippingCostData['withouttax']) * ($shippingCostData['taxrate'] / 100);
        }

        $rates = $quote->getShippingAddress()->getShippingRatesCollection();
        if(!empty($rates)){
            if( $shippingAddress->getBaseShippingTaxAmount() > 0 ){
                $shippingExclTax = $shippingAddress->getShippingAmount();
                $shippingIncTax = $shippingAddress->getShippingInclTax();
                $rate = $shippingExclTax > 0 ? (($shippingIncTax / $shippingExclTax) - 1) * 100 : 0;
            }
            else
                $rate = 0;
            if($shippingAddress->getShippingAmount() > 0 && $shippingAddress->getShippingDiscountAmount() != $shippingAddress->getShippingAmount()) {
                $orderValues['Cart']['Shipping'] = array(
                    'withouttax' => ($shippingAddress->getShippingDiscountAmount() < 0) ? ($shippingAddress->getShippingAmount() - $shippingAddress->getShippingDiscountAmount()) * 100 : $shippingAddress->getShippingAmount() * 100,
                    'taxrate' => (int)$rate
                );
                $totalValue += $shippingAddress->getShippingAmount() * 100;
                $totalTax += ($shippingAddress->getShippingAmount() * 100) * ($rate / 100);
            } else {
                $orderValues['Cart']['Shipping'] = array(
                    'withouttax' => 0,
                    'taxrate' => (int)$rate
                );
            }
        }
        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);

        $invoiceFee = Mage::getStoreConfig( 'payment/billmateinvoice/billmate_fee' );
        $invoiceFee = Mage::helper( 'billmateinvoice' )->replaceSeparator( $invoiceFee );


        $feeinfo = Mage::helper( 'billmateinvoice' )
            ->getInvoiceFeeArray( $invoiceFee, $shippingAddress, $quote->getCustomerTaxClassId() );
        if ( ! empty( $invoiceFee ) && $invoiceFee > 0 )
        {
            $baseCurrencyCode    = Mage::app()->getStore()->getBaseCurrencyCode();
            $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            $_directory          = Mage::helper( 'directory' );
            $invoiceFee = $_directory->currencyConvert($invoiceFee,$baseCurrencyCode,$currentCurrencyCode);

            $orderValues['Cart']['Handling'] = array(
                'withouttax' => round($invoiceFee * 100),
                'taxrate'    => $feeinfo['rate']
            );
            $totalValue += $invoiceFee * 100;
            $totalTax += ( $invoiceFee * 100 ) * ( $feeinfo['rate'] / 100 );
        }

        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );

        $billmateConnection = $this->getBMConnection();
        $result = $billmateConnection->initCheckout($orderValues);

        if(!isset($result['code'])){
            $url = $result['url'];
            $parts = explode('/',$url);
            $sum = count($parts);
            $hash = ($parts[$sum-1] == 'test') ? str_replace('\\','',$parts[$sum-2]) : str_replace('\\','',$parts[$sum-1]);
            $quote->setBillmateHash($hash);
            $quote->save();
            Mage::getSingleton('checkout/session')->setBillmateHash($hash);
        }
        return $result;

    }

    public function updateCheckout()
    {
        $billmateConnection = $this->getBMConnection();
        $quote = $this->getQuote();

        $shippingAddress= $this->getShippingAddress();
        $orderValues = $billmateConnection->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));

        $previousTotal = $orderValues['Cart']['Total']['withtax'];

        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }

        if(!isset($orderValues['PaymentData']) || (isset($orderValues['PaymentData']) && !is_array($orderValues['PaymentData']))) {
            $orderValues['PaymentData'] = array();
        }
        $paymentData = $this->getPaymentData();
        $orderValues['PaymentData']['currency'] = $paymentData['currency'];
        $orderValues['PaymentData']['language'] = $paymentData['language'];
        $orderValues['PaymentData']['country'] = $paymentData['country'];
        $orderValues['PaymentData']['orderid'] = $paymentData['orderid'];

        unset($orderValues['Articles']);
        
        $preparedArticle = Mage::helper('billmatecommon')->prepareArticles($quote);
        $totalTax = $preparedArticle['totalTax'];
        $totalValue = $preparedArticle['totalValue'];
        $orderValues['Articles'] = $preparedArticle['articles'];


        $shippingCostData = $this->getShippingCostData();
        if ($shippingCostData) {
            $orderValues['Cart']['Shipping'] = $shippingCostData;
            $totalValue += $shippingCostData['withouttax'];
            $totalTax += ($shippingCostData['withouttax']) * ($shippingCostData['taxrate'] / 100);
        }

        unset($orderValues['Cart']['Shipping']);
        unset($orderValues['Cart']['Handling']);
        unset($orderValues['Customer']);

        $shippingCostData = $this->getShippingCostData();
        if ($shippingCostData) {
            $orderValues['Cart']['Shipping'] = $shippingCostData;
            $totalValue += $shippingCostData['withouttax'];
            $totalTax += ($shippingCostData['withouttax']) * ($shippingCostData['taxrate'] / 100);
        }

        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);

        $invoiceFee = Mage::getStoreConfig( 'payment/billmateinvoice/billmate_fee' );
        $invoiceFee = Mage::helper( 'billmateinvoice' )->replaceSeparator( $invoiceFee );

        $feeinfo = Mage::helper( 'billmateinvoice' )
            ->getInvoiceFeeArray( $invoiceFee, $shippingAddress, $quote->getCustomerTaxClassId() );

        if ( ! empty( $invoiceFee ) && $invoiceFee > 0 )
        {
            $baseCurrencyCode    = Mage::app()->getStore()->getBaseCurrencyCode();
            $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            $_directory          = Mage::helper( 'directory' );
            $invoiceFee = $_directory->currencyConvert($invoiceFee,$baseCurrencyCode,$currentCurrencyCode);

            $orderValues['Cart']['Handling'] = array(
                'withouttax' => round($invoiceFee * 100),
                'taxrate'    => $feeinfo['rate']
            );
            $totalValue += $invoiceFee * 100;
            $totalTax += ( $invoiceFee * 100 ) * ( $feeinfo['rate'] / 100 );
        }

        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );

        $result = $billmateConnection->updateCheckout($orderValues);
        if($previousTotal != $orderValues['Cart']['Total']['withtax']){
            $result['update_checkout'] = true;
            $result['data'] = $orderValues;
        } else {
            $result['update_checkout'] = false;
            $result['data'] = array();

        }
        return $result;
    }
}