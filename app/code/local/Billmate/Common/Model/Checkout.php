<?php

class Billmate_Common_Model_Checkout extends Billmate_Common_Model_Payment_GatewayCore
{
    const METHOD_CODE = 93;

    /**
     * @return array
     */
    public function init()
    {
        $quote = $this->getQuote();

        $orderValues = array();
        $orderValues['CheckoutData'] = array(
            'windowmode' => 'iframe',
            'sendreciept' => 'yes'
        );

        $termsUrl = $this->helper->getTermsUrl();
        if ($termsUrl) {
            $orderValues['CheckoutData']['terms'] = $termsUrl;
        }

        $privacyPolicyUrl = $this->helper->getPrivacyUrl();
        if ($privacyPolicyUrl) {
            $orderValues['CheckoutData']['privacyPolicy'] = $privacyPolicyUrl;
        }

        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }

        $orderValues['PaymentData'] = $this->getPaymentData();
        $callbackUrls = $this->getCallbackUrls();
        $orderValues['PaymentData'] = array_merge($orderValues['PaymentData'],$callbackUrls);
        $orderValues['PaymentData']['returnmethod'] = (Mage::app()->getStore()->isCurrentlySecure()) ? 'POST' : 'GET';

        $preparedArticle = $this->helper->prepareArticles($quote);

        $totalTax = $preparedArticle['totalTax'];
        $totalValue = $preparedArticle['totalValue'];
        $orderValues['Articles'] = $preparedArticle['articles'];

        $shippingCostData = $this->getShippingCostData();
        if ($shippingCostData) {
            $orderValues['Cart']['Shipping'] = $shippingCostData;
            $totalValue += $shippingCostData['withouttax'];
            $totalTax += ($shippingCostData['withouttax']) * ($shippingCostData['taxrate'] / 100);
        }

        $shippingHandData = $this->getShippingHandData();
        $feetotal = 0;
        if ($shippingHandData) {
            $orderValues['Cart']['Handling'] = $shippingHandData;
            $totalValue += $shippingHandData['withouttax'];
            $totalTax += ($shippingHandData['withouttax']) * ($shippingHandData['taxrate'] / 100);
            $feetotal = $shippingHandData['withouttax'] * (1+$shippingHandData['taxrate']/100);
        }

        $round = round($quote->getGrandTotal() * 100+$feetotal) - round($totalValue +  $totalTax);

        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );

        $billmateConnection = $this->getBMConnection();
        $result = $billmateConnection->initCheckout($orderValues);

        if (!isset($result['code'])) {
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

    /**
     * @return array
     */
    public function updateCheckout()
    {
        $billmateConnection = $this->getBMConnection();
        $quote = $this->getQuote();

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

        unset($orderValues['Cart']['Shipping']);
        unset($orderValues['Cart']['Handling']);
        unset($orderValues['Customer']);

        $shippingCostData = $this->getShippingCostData();
        if ($shippingCostData) {
            $orderValues['Cart']['Shipping'] = $shippingCostData;
            $totalValue += $shippingCostData['withouttax'];
            $totalTax += ($shippingCostData['withouttax']) * ($shippingCostData['taxrate'] / 100);
        }

        $shippingHandData = $this->getShippingHandData();
        if ($shippingHandData) {
            $orderValues['Cart']['Handling'] = $shippingHandData;
            $totalValue += $shippingHandData['withouttax'];
            $totalTax += ($shippingHandData['withouttax']) * ($shippingHandData['taxrate'] / 100);
        }

        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);

        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );

        $result = $billmateConnection->updateCheckout($orderValues);
        if ($previousTotal != $orderValues['Cart']['Total']['withtax']) {
            $result['update_checkout'] = true;
            $result['data'] = $orderValues;
        } else {
            $result['update_checkout'] = false;
            $result['data'] = array();

        }
        return $result;
    }

    /**
     * @return array
     */
    protected function getCallbackUrls()
    {
        $quote = $this->getQuote();
        $urls = [
            'accepturl' => Mage::getUrl('billmatecommon/callback/accept',[
                '_query' => ['billmate_checkout' => true,'billmate_quote_id' => $quote->getId()],
                '_secure' => true
            ]),
            'cancelurl' => Mage::getUrl('billmatecommon/callback/cancel', array('_secure' => true)),
            'callbackurl' => Mage::getUrl('billmatecommon/callback/callback',[
                '_query' => ['billmate_quote_id' => $quote->getId(),'billmate_checkout' => true],
                '_secure' => true
            ])
        ];
        return $urls;
    }

    /**
     * @return array
     */
    protected function getShippingHandData()
    {
        $shippingCostData = [];
        $invoiceFee = Mage::getStoreConfig( 'payment/billmatecheckout/billmate_fee' );
        $invoiceFee = Mage::helper( 'billmateinvoice' )->replaceSeparator( $invoiceFee );
        $shippingAddress = $this->getShippingAddress();

        $feeinfo = Mage::helper( 'billmateinvoice' )
            ->getInvoiceFeeArray( $invoiceFee, $shippingAddress, $this->getQuote()->getCustomerTaxClassId() );
        if ((!empty( $invoiceFee ) && $invoiceFee > 0)) {
            $shippingCostData = array(
                'withouttax' => round($invoiceFee * 100),
                'taxrate'    => $feeinfo['rate']
            );
        }
        return $shippingCostData;
    }
}