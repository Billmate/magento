<?php
require_once ('BillmatecheckoutController.php');
class Billmate_Common_CallbackController extends Billmate_Common_BillmatecheckoutController
{

    public function callbackAction()
    {
        /** @var  $quote Mage_Sales_Model_Quote */
        $bmRequestData = $this->getBmRequestData();
        try {
            $this->runCallbackProcess($bmRequestData);
            $response['has_error'] = false;
            $response['message'] = $this->getHelper()->__('The order was successfully updated');
            $statusCode = 200;
        } catch (Exception $e) {
            $response['has_error'] = true;
            $response['message'] = $e->getMessage();
            $statusCode = 500;
            Mage::log("Callback Order Creation Error: " . $e->getMessage(),0,'billmate.log',true);
            Mage::log($e->getTraceAsString(),0,'billmate.log',true);
        }
        $this->getResponse()->clearHeaders()->setHeader('HTTP/1.0', $statusCode, true)->setHeader('Content-Type', 'text/json')->setBody(json_encode($response));
    }

    public function acceptAction()
    {
        $bmRequestData = $this->getBmRequestData();
        try{
            $this->runCallbackProcess($bmRequestData);
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
            Mage::log("Accept URL Order Creation Error: " . $e->getMessage(),0,'billmate.log',true);
            Mage::log($e->getTraceAsString(),0,'billmate.log',true);
            return;
        }
        $this->_redirect('checkout/onepage/success',array('_secure' => true));
    }

    public function cancelAction()
    {
        $bmRequestData = $this->getBmRequestData();
        $billmateConnection = $this->getHelper()->getBillmate();
        $verifiedData = $billmateConnection->verify_hash($bmRequestData);
        try {
            if (isset($verifiedData['code'])) {
                throw new Exception(
                    $this->getHelper()->
                    __('Unfortunately your payment was not processed correctly. Please try again or choose another payment method.')
                );
            }

            if (isset($verifiedData['status'])) {
                switch (strtolower($verifiedData['status'])) {
                    case 'cancelled':
                        throw new Exception(
                            $this->getHelper()->
                            __('The payment has been canceled. Please try again or choose a different payment method.')
                        );
                        break;
                    case 'failed':
                        throw new Exception(
                            $this->getHelper()->
                            __('Unfortunately your payment was not processed correctly. Please try again or choose another payment method.')
                        );
                        break;
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
            return;
        }
        $this->getResponse()->setRedirect(Mage::helper('billmatecommon/url')->getCheckoutUrl());
    }


    /**
     * @return Mage_Sales_Model_Quote
     */
    public function _getQuote()
    {
        $quoteId = $this->getRequest()->getParam('billmate_quote_id');
        /** @var  $quote Mage_Sales_Model_Quote */
        return Mage::getModel('sales/quote')->load($quoteId);
    }
}
