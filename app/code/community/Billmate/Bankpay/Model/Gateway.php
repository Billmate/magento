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

        $quote->reserveOrderId();
        $orderValues['PaymentData'] = $this->getPaymentData();
        $orderValues['PaymentInfo'] = $this->getPaymentInfo();
        $orderValues['Card'] = $this->getCardUrls();

        $orderValues['Customer']['nr'] = $this->getCustomerId();
        $orderValues['Customer']['Billing'] = $this->getBillingData();
        $orderValues['Customer']['Shipping'] = $this->getShippingData();

		/** @var Mage_Sales_Model_Quote_Item $_item */
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

        $billmateConnection = $this->getBMConnection();
		$result = $billmateConnection->addPayment($orderValues);

        if ( isset($result['code'])) {
            Mage::throwException( utf8_encode( $result['message']));
        } else {
            $session = Mage::getSingleton('core/session', array('name' => 'frontend'));
            $session->setData('billmateinvoice_id', $result['number']);
            $session->setData('billmateorder_id', $result['orderid']);
		}
        return $result;
    }

    /**
     * @return array
     */
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
