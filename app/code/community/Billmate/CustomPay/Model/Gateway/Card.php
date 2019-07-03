<?php
class Billmate_CustomPay_Model_Gateway_Card extends Billmate_CustomPay_Model_Gateway
{
    const METHOD_CODE = 8;

    /**
     * @return array
     */
    public function makePayment()
    {
        $quote = $this->getQuote();


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
            'accepturl' => Mage::getUrl('custompay/card/accept',array('billmate_quote_id' => $quote->getId(),'_secure' => true)),
            'cancelurl' => Mage::getUrl('custompay/card/cancel',array('_secure' => true)),
            'callbackurl' => Mage::getUrl('custompay/card/callback',array('billmate_quote_id' => $quote->getId(),'_secure' => true)),
            'returnmethod' => (Mage::app()->getStore()->isCurrentlySecure()) ? 'POST' : 'GET'
        ];
    }
}