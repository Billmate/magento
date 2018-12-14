<?php
class Billmate_Cardpay_Model_Gateway extends Billmate_PaymentCore_Model_GatewayCore
{
    public $isMatched = true;

    /**
     * @return array
     */
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

        if( isset($result['code'])){
            Mage::throwException( utf8_encode($result['message']));
        }else{
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
            'accepturl' => Mage::getUrl('cardpay/cardpay/accept',array('billmate_quote_id' => $quote->getId(),'_secure' => true)),
            'cancelurl' => Mage::getUrl('cardpay/cardpay/cancel',array('_secure' => true)),
            'callbackurl' => Mage::getUrl('cardpay/cardpay/callback',array('billmate_quote_id' => $quote->getId(),'_secure' => true)),
            'returnmethod' => (Mage::app()->getStore()->isCurrentlySecure()) ? 'POST' : 'GET'
        ];
    }
}
