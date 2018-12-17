<?php

class Billmate_BillmateInvoice_Model_Gateway extends Billmate_Common_Model_Payment_GatewayCore
{
    const METHOD_CODE = 1;

    const METHOD_NAME = 'billmateinvoice';

    /**
     * @var bool
     */
    public $isMatched = true;

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $quote = null;

    /**
     * @return string
     */
    public function makePayment()
    {
        $orderValues = array();
        $quote = $this->getQuote();
        $payment = Mage::app()->getRequest()->getPost( 'payment' );

        $methodName = $payment['method'] == 'billmateinvoice' ? 'billmateinvoice' : 'billmatepartpayment';

        $orderValues['PaymentData'] = $this->getPaymentData();
        $orderValues['PaymentInfo'] = $this->getPaymentInfo();

        $orderValues['Customer']['nr'] = $this->getCustomerId();
        $orderValues['Customer']['pno'] =
            ( empty( $payment[ $methodName . '_pno' ] ) ) ? $payment['person_number'] : $payment[ $methodName . '_pno' ];

        $orderValues['Customer']['Billing'] = $this->getBillingData();
        $orderValues['Customer']['Shipping'] = $this->getShippingData();


        $preparedArticle = $this->calculateArticlesToQuote();
        $totalTax = $preparedArticle['totalTax'];
        $totalValue = $preparedArticle['totalValue'];
        $orderValues['Articles'] = $preparedArticle['articles'];

        $shippingCostData = $this->getShippingCostData();
        if ($shippingCostData) {
            $orderValues['Cart']['Shipping'] = $shippingCostData;
            $totalValue += $shippingCostData['withouttax'];
            $totalTax += ($shippingCostData['withouttax']) * ($shippingCostData['taxrate'] / 100);
        }

        if ($methodName == self::METHOD_NAME) {
            $shippingHandData = $this->getShippingHandData();
            if ($shippingHandData) {
                $orderValues['Cart']['Handling'] = $shippingHandData;
                $totalValue += $shippingHandData['withouttax'];
                $totalTax += ($shippingHandData['withouttax']) * ($shippingHandData['taxrate'] / 100);
            }
        }

        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);
        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax'        => round($totalTax),
            'rounding'   => round($round),
            'withtax'    => round($totalValue +  $totalTax +  $round)
        );

        $billmateConnection = $this->getBMConnection();
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

        $methodName = $payment['method'];
        $billmateConnection = $this->getBMConnection();
        $billingAddress    = $this->getBillingAddress();
        $shippingAddress   = $this->getShippingAddress();

        $pno = ( empty( $payment[ $methodName . '_pno' ] ) ) ? $payment['person_number'] : $payment[ $methodName . '_pno' ];

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