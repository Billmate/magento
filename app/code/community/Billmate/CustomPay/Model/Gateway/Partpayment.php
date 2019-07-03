<?php
class Billmate_CustomPay_Model_Gateway_Partpayment extends Billmate_CustomPay_Model_Gateway
{
    const METHOD_CODE = 4;

    const METHOD_NAME = 'bmcustom_partpayment';

    public $isMatched = true;

    public function makePayment()
    {
        $orderValues = [];

        $quote = $this->getQuote();
        $payment = Mage::app()->getRequest()->getPost('payment');

        $methodname = $payment['method'] == 'bmcustom_invoice'? 'bmcustom_invoice': 'bmcustom_partpayment';

        $billmateConnection = $this->getBMConnection();

        $orderValues['PaymentData'] = $this->getPaymentData();
        $orderValues['PaymentInfo'] = $this->getPaymentInfo();
        $orderValues['Card'] = $this->getCardUrls();

        $orderValues['Customer']['nr'] = $this->getCustomerId();
        $orderValues['Customer']['pno'] =
            (empty($payment[$methodname.'_pno'])) ? $payment['person_number'] : $payment[$methodname.'_pno'];

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

        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);
        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );
        $result  = $billmateConnection->addPayment($orderValues);

        if(isset($result['code'])){
            switch($result['code']){
                case 2401:
                case 2402:
                case 2403:
                case 2404:
                case 2405:
                    $this->init();
                    echo Mage::app()->getLayout()->createBlock('partpayment/changeaddress')->toHtml();
                    die();
                    break;
                default:
                    Mage::throwException( utf8_encode( $result['message'] ) );
            }
        } else {
            $session = Mage::getSingleton('core/session', array('name' => 'frontend'));
            $session->setData('invoiceid', $result['number']);
            $session->setData('billmateorder_id', $result['orderid']);
            $session->setData('billmate_status',$result['status']);

            return $result['number'];
        }
    }

    /**
     * @param bool $update
     */
    public function init($update = false)
    {
        $payment = Mage::app()->getRequest()->getPost('payment');

        $methodname = $payment['method'];

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $Billing= $quote->getBillingAddress();
        $Shipping= $quote->getShippingAddress();

        try{
            $pno = (empty($payment[$methodname.'_pno'])) ? $payment['person_number'] : $payment[$methodname.'_pno'];

            $billmateConnection = $this->getBMConnection();
            $addr = $billmateConnection->getAddress(array('pno' =>$pno));

            if(!is_array($addr)){
                Mage::throwException( Mage::helper('payment')->__(utf8_encode($addr)));
            }

            if (isset($addr['code'])) {
                Mage::throwException(utf8_encode($addr['message']));
            }

            foreach ($addr as $key => $col) {
                $addr[$key] = mb_convert_encoding($col,'UTF-8','auto');
            }
            if (empty($addr['firstname'])) {
                $this->firstname = $Billing->getFirstname();
                $this->lastname = $Billing->getLastname();
                $this->company  = $addr['lastname'];
            } else {
                $this->firstname = $addr['firstname'];
                $this->lastname = $addr['lastname'];
                $this->company  = '';
            }
            $this->street = $addr['street'];
            $this->postcode = $addr['zip'];
            $this->city = $addr['city'];
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $this->telephone = $Billing->getTelephone();
            }
            $this->country = $addr['country'];
            $this->country_name = Mage::getModel('directory/country')->loadByCode($this->country)->getName();

        } catch( Exception $ex ) {
            Mage::logException( $ex );
            die('alert("'.utf8_encode($ex->getMessage()).'");');
        }

        $fullname = $Billing->getFirstname().' '.$Billing->getLastname().' '.$Billing->getCompany();
        if( empty($addr['firstname']) ){
            $apiName = $Billing->getFirstname().' '.$Billing->getLastname().' '.$Billing->getCompany();
        } else {
            $apiName  = $addr['firstname'].' '.$addr['lastname'];
        }
        $billingStreet = $Billing->getStreet();

        $addressNotMatched = !isEqual($addr['street'], $billingStreet[0] ) ||
            !isEqual($addr['zip'], $Billing->getPostcode()) ||
            !isEqual($addr['city'], $Billing->getCity()) ||
            !isEqual(strtolower($addr['country']), $Billing->getCountryId());


        $shippingStreet = $Shipping->getStreet();

        $shippingAndBilling =  !match_usernamevp( $fullname , $apiName) ||
            !isEqual($shippingStreet[0], $billingStreet[0] ) ||
            !isEqual($Shipping->getPostcode(), $Billing->getPostcode()) ||
            !isEqual($Shipping->getCity(), $Billing->getCity()) ||
            !isEqual($Shipping->getCountryId(), $Billing->getCountryId()) ;

        if( $addressNotMatched || $shippingAndBilling ){
            $this->isMatched = false;
        }
        if( $update) {
            $this->isMatched = true;
            $data = array(
                'firstname' => $this->firstname,
                'lastname'  => $this->lastname,
                'street'    => $this->street,
                'company'   => $this->company,
                'postcode'  => $this->postcode,
                'city'      => $this->city,
                'country_id'   => strtoupper($this->country),
            );

            $Billing->addData( $data )->save();
            $Shipping->addData($data)->save();
            Mage::getModel('checkout/session')->loadCustomerQuote();
        }
    }
}
