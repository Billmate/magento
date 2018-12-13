<?php
class Billmate_Bankpay_Model_Gateway extends Billmate_PaymentCore_Model_GatewayCore
{
    const METHOD_CODE = 16;

    /**
     * @var bool
     */
    public $isMatched = true;

    public function makePayment()
    {
        $quote = $this->getQuote();

		if(empty($_POST)) $_POST = $_GET;		

        $k = $this->getBMConnection();
        $Shipping= $quote->getShippingAddress();

        $quote->reserveOrderId();
        $orderValues['PaymentData'] = $this->getPaymentData();
        $orderValues['PaymentInfo'] = $this->getPaymentInfo();
        $orderValues['Card'] = $this->getCardUrls();

        $orderValues['Customer']['nr'] = $this->getCustomerId();
        $orderValues['Customer']['Billing'] = $this->getBillingData();
        $orderValues['Customer']['Shipping'] = $this->getShippingData();

        $discountAdded = false;

		/** @var Mage_Sales_Model_Quote_Item $_item */
	    $preparedArticle = Mage::helper('billmatecommon')->prepareArticles($quote);
	    $discounts = $preparedArticle['discounts'];
	    $totalTax = $preparedArticle['totalTax'];
	    $totalValue = $preparedArticle['totalValue'];
	    $orderValues['Articles'] = $preparedArticle['articles'];


	    $totals = $this->getQuote()->getTotals();

        if(isset($totals['discount']) && !$discountAdded) {
	        $totalDiscountInclTax = $totals['discount']->getValue();
	        $subtotal = $totalValue;
	        foreach($discounts as $percent => $amount) {
		        $discountPercent = $amount / $subtotal;
		        $floor    = 1 + ( $percent / 100 );
		        $marginal = 1 / $floor;
		        $discountAmount = $discountPercent * $totalDiscountInclTax;
		        $orderValues['Articles'][] = array(
			        'quantity'   => (int) 1,
			        'artnr'      => 'discount',
			        'title'      => Mage::helper( 'payment' )->__( 'Discount' ).' '. Mage::helper('billmatebankpay')->__('%s Vat',$percent),
			        'aprice'     => round( ($discountAmount * $marginal ) * 100 ),
			        'taxrate'    => (float) $percent,
			        'discount'   => 0.0,
			        'withouttax' => round( ($discountAmount * $marginal ) * 100 ),

		        );
		        $totalValue += ( 1 * round( $discountAmount * $marginal * 100 ) );
		        $totalTax += ( 1 * round( ( $discountAmount * $marginal ) * 100 ) * ( $percent / 100 ) );
	        }
        }

        $rates = $quote->getShippingAddress()->getShippingRatesCollection();
        if(!empty($rates)){
            if( $Shipping->getBaseShippingTaxAmount() > 0 ){

	            $shippingExclTax = $Shipping->getShippingAmount();
	            $shippingIncTax = $Shipping->getShippingInclTax();
	            $rate = $shippingExclTax > 0 ? (($shippingIncTax / $shippingExclTax) - 1) * 100 : 0;
            }
            else
                $rate = 0;

            if($Shipping->getShippingAmount() > 0) {
                $orderValues['Cart']['Shipping'] = array(
                    'withouttax' => $Shipping->getShippingAmount() * 100,
                    'taxrate' => (int)$rate
                );
                $totalValue += $Shipping->getShippingAmount() * 100;
                $totalTax += ($Shipping->getShippingAmount() * 100) * ($rate / 100);
            }
        }

        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);


        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );
		$result = $k->addPayment($orderValues);

        if( isset($result['code'])){
            Mage::throwException( utf8_encode( $result['message']));
        }else{
            $session = Mage::getSingleton('core/session', array('name' => 'frontend'));
            $session->setData('billmateinvoice_id', $result['number']);
            $session->setData('billmateorder_id', $result['orderid']);
		}
        return $result;
    }

    protected function getCardUrls()
    {
        $quote = $this->getQuote();
        return [
            'accepturl' => Mage::getUrl('bankpay/bankpay/accept',array('billmate_quote_id' => $quote->getId(),'_secure' => true)),
            'cancelurl' => Mage::getUrl('bankpay/bankpay/cancel',array('_secure' => true)),
            'callbackurl' => Mage::getUrl('bankpay/bankpay/callback',array('billmate_quote_id' => $quote->getId(),'_secure' => true)),
            'returnmethod' => (Mage::app()->getStore()->isCurrentlySecure()) ? 'POST' : 'GET'
        ];
    }
}
