<?php

class Billmate_BillmateInvoice_Model_Gateway extends Billmate_PaymentCore_Model_GatewayCore
{
    const METHOD_CODE = 1;

    /**
     * @var bool
     */
    public $isMatched = true;

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $quote = null;

    public function makePayment()
    {
        $orderValues = array();
        $quote       = $this->getQuote();
        $shippingAddress = $this->getShippingAddress();

        $payment = Mage::app()->getRequest()->getPost( 'payment' );

        $methodname = $payment['method'] == 'billmateinvoice' ? 'billmateinvoice' : 'billmatepartpayment';

        $billmateConnection = $this->getBMConnection();

        $orderValues['PaymentData'] = $this->getPaymentData();
        $orderValues['PaymentInfo'] = $this->getPaymentInfo();

        $orderValues['Customer']['nr'] = $this->getCustomerId();
        $orderValues['Customer']['pno'] =
            ( empty( $payment[ $methodname . '_pno' ] ) ) ? $payment['person_number'] : $payment[ $methodname . '_pno' ];

        $orderValues['Customer']['Billing'] = $this->getBillingData();
        $orderValues['Customer']['Shipping'] = $this->getShippingData();


        $preparedArticle = Mage::helper('billmatecommon')->prepareArticles($quote);
        $discounts = $preparedArticle['discounts'];
        $totalTax = $preparedArticle['totalTax'];
        $totalValue = $preparedArticle['totalValue'];
        $orderValues['Articles'] = $preparedArticle['articles'];

        $totals = $this->getQuote()->getTotals();

        if (isset( $totals['discount'] )) {
            $totalDiscountInclTax = $totals['discount']->getValue();
            $subtotal             = $totalValue;
            foreach ( $discounts as $percent => $amount )
            {
                $discountPercent           = $amount / $subtotal;
                $floor                     = 1 + ( $percent / 100 );
                $marginal                  = 1 / $floor;
                $discountAmount            = $discountPercent * $totalDiscountInclTax;
                $orderValues['Articles'][] = array(
                    'quantity'   => (int) 1,
                    'artnr'      => 'discount',
                    'title'      => Mage::helper( 'payment' )
                                        ->__( 'Discount' ) . ' ' . Mage::helper( 'billmateinvoice' )
                                                                       ->__( '%s Vat', $percent ),
                    'aprice'     => round( ( $discountAmount * $marginal ) * 100 ),
                    'taxrate'    => (float) $percent,
                    'discount'   => 0.0,
                    'withouttax' => round( ( $discountAmount * $marginal ) * 100 ),

                );
                $totalValue += ( 1 * round( $discountAmount * $marginal * 100 ) );
                $totalTax += ( 1 * round( ( $discountAmount * $marginal ) * 100 ) * ( $percent / 100 ) );
            }
        }


        $rates = $quote->getShippingAddress()->getShippingRatesCollection();
        if (!empty( $rates )) {
            if ($shippingAddress->getBaseShippingTaxAmount() > 0) {

                $shippingExclTax = $shippingAddress->getShippingAmount();
                $shippingIncTax = $shippingAddress->getShippingInclTax();
                $rate = $shippingExclTax > 0 ? (($shippingIncTax / $shippingExclTax) - 1) * 100 : 0;
            } else {
                $rate = 0;
            }
            if ($shippingAddress->getShippingAmount() > 0) {
                $orderValues['Cart']['Shipping'] = array(
                    'withouttax' => $shippingAddress->getShippingAmount() * 100,
                    'taxrate' => (int)$rate
                );
                $totalValue += $shippingAddress->getShippingAmount() * 100;
                $totalTax += ($shippingAddress->getShippingAmount() * 100) * ($rate / 100);
            }
        }


        if ($methodname == 'billmateinvoice') {
            $invoiceFee = Mage::getStoreConfig( 'payment/billmateinvoice/billmate_fee' );
            $invoiceFee = Mage::helper( 'billmateinvoice' )->replaceSeparator( $invoiceFee );

            $feeinfo = Mage::helper( 'billmateinvoice' )
                           ->getInvoiceFeeArray( $invoiceFee, $shippingAddress, $quote->getCustomerTaxClassId() );
            if ( ! empty( $invoiceFee ) && $invoiceFee > 0 ) {
                $orderValues['Cart']['Handling'] = array(
                    'withouttax' => round($shippingAddress->getFeeAmount() * 100),
                    'taxrate'    => $feeinfo['rate']
                );
                $totalValue += $shippingAddress->getFeeAmount() * 100;
                $totalTax += ( $shippingAddress->getFeeAmount() * 100 ) * ( $feeinfo['rate'] / 100 );
            }
        }

        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);
        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax'        => round($totalTax),
            'rounding'   => round($round),
            'withtax'    => round($totalValue +  $totalTax +  $round)
        );

        $result = $billmateConnection->addPayment($orderValues);

        if (isset( $result['code'] )) {
            switch ($result['code']) {
                case 2401:
                case 2402:
                case 2403:
                case 2404:
                case 2405:
                    $this->init();
                    echo Mage::app()->getLayout()->createBlock('billmateinvoice/changeaddress')->toHtml();
                    die();
                    break;
                default:
                    Mage::throwException( utf8_encode( $result['message'] ) );
            }
        } else {
            $session = Mage::getSingleton( 'core/session', array( 'name' => 'frontend' ) );
            $session->setData( 'billmateinvoice_id', $result['number'] );
            $session->setData( 'billmateorder_id', $result['orderid'] );
            $session->setData('billmate_status',$result['status']);
            return $result['number'];
        }
    }

    public function init( $update = false )
    {
        $payment = Mage::app()->getRequest()->getPost( 'payment' );

        $methodname = $payment['method'];
        $billmateConnection = Mage::helper('billmateinvoice')->getBillmate( true, false );
        $billingAddress    = $this->getBillingAddress();
        $shippingAddress   = $this->getShippingAddress();

        $pno = ( empty( $payment[ $methodname . '_pno' ] ) ) ? $payment['person_number'] : $payment[ $methodname . '_pno' ];

        try {
            $addr = $billmateConnection->getAddress( array( 'pno' => $pno ) );

            if (!is_array( $addr ) ) {
                Mage::throwException( Mage::helper( 'payment' )->__( utf8_encode( $addr ) ) );
            }

            if (isset( $addr['code'] )) {
                Mage::throwException( $addr['message'] );

            }

            foreach ($addr as $key => $col) {
                $addr[ $key ] = mb_convert_encoding( $col, 'UTF-8', 'auto' );
            }

            if (empty( $addr['firstname'] )) {
                $this->firstname = $billingAddress->getFirstname();
                $this->lastname  = $billingAddress->getLastname();
                $this->company   = $addr['company'];
            } else {
                $this->firstname = $addr['firstname'];
                $this->lastname  = $addr['lastname'];
                $this->company   = '';
            }
            $this->street       = $addr['street'];
            $this->postcode     = $addr['zip'];
            $this->city         = $addr['city'];

            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $this->telephone = $billingAddress->getTelephone();
            }

            $this->country      = $addr['country'];
            $this->country_name = Mage::getModel( 'directory/country' )->loadByCode( $this->country )->getName();

        }
        catch ( Exception $ex ) {
            Mage::logException( $ex );
            die( 'alert("' . utf8_encode( $ex->getMessage() )/*mb_convert_encoding(strip_tags( str_replace("<br> ",'\n\n',preg_replace_callback('/[\\\\]([a-f0-9]{2})/i','hex2dec',$ex->getMessage())) ),'UTF-8','auto')*/ . '");' );
        }

        $fullname = $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname() . ' ' . $billingAddress->getCompany();
        if ( empty( $addr['firstname'] ) ) {
            $apiName = $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname() . ' ' . $billingAddress->getCompany();
        } else {
            $apiName = $addr['firstname'] . ' ' . $addr['lastname'];
        }
        $billingStreet = $billingAddress->getStreet();
        $addressNotMatched = ! isEqual( $addr['street'], $billingStreet[0] ) ||
                             ! isEqual( $addr['zip'], $billingAddress->getPostcode() ) ||
                             ! isEqual( $addr['city'], $billingAddress->getCity() ) ||
                             ! isEqual( strtolower( $addr['country'] ), $billingAddress->getCountryId() );


        $shippingStreet = $shippingAddress->getStreet();

        $shippingAndBilling = ! match_usernamevp( $fullname, $apiName ) ||
                              ! isEqual( $shippingStreet[0], $billingStreet[0] ) ||
                              ! isEqual( $shippingAddress->getPostcode(), $billingAddress->getPostcode() ) ||
                              ! isEqual( $shippingAddress->getCity(), $billingAddress->getCity() ) ||
                              ! isEqual( $shippingAddress->getCountryId(), $billingAddress->getCountryId() );
        if ($addressNotMatched || $shippingAndBilling) {
            $this->isMatched = false;
        }

        if ($update) {
            $this->isMatched = true;
            $data            = array(
                'firstname'  => $this->firstname,
                'lastname'   => $this->lastname,
                'street'     => $this->street,
                'company'    => $this->company,
                'postcode'   => $this->postcode,
                'city'       => $this->city,
                'country_id' => strtoupper( $this->country ),
                'telephone' => $billingAddress->getTelephone()
            );

            if (Mage::getStoreConfig( 'firecheckout/general/enabled' )) {
                $data['person_number'] = $pno;
            }

            $billingAddress->addData( $data )->save();
            $shippingAddress->addData( $data )->save();

            Mage::getModel( 'checkout/session' )->loadCustomerQuote();
        }

    }
}