<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Paypal
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * PayPal Standard checkout request API
 */
class Billmate_Bankpay_Model_Api_Standard extends Mage_Paypal_Model_Api_Abstract
{
    /**
     * Global interface map and export filters
     * @var array
     */
    protected $_globalMap = array(
        // commands
        'callback_url'    => 'notify_url',
        'accept_url'    => 'return_url',
        'cancel_url'	=> 'cancel_url',
        'return_method' => 'return_method',
        // payment
        'order_id'      => 'order_id',
        'currency' 		=> 'currency_code',
        'amount'        => 'amount',
    );

    protected $_exportToRequestFilters = array(
        'amount'   => '_filterAmount',
    );
    protected $_lineItemTotalExportMap = array(
        Mage_Paypal_Model_Cart::TOTAL_SUBTOTAL => 'amount',
    );


    /**
     * Interface for common and "aggregated order" specific fields
     * @var array
     */
    protected $_commonRequestFields = array(
        'merchant_id', 'order_id', 'currency', 'return_method', 'accept_url', 'cancel_url','callback_url', 'amount',
    );

   /**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = array('business');

    /**
     * Line items export mapping settings
     * @var array
     */

    protected $_lineItemExportItemsFilters = array(
         'qty'      => '_filterQty'
    );


    /**
     * Generate PayPal Standard checkout request fields
     * Depending on whether there are cart line items set, will aggregate everything or display items specifically
     * Shipping amount in cart line items is implemented as a separate "fake" line item
     */
    public function getStandardCheckoutRequest()
    {
        $request = $this->_exportToRequest($this->_commonRequestFields);

        $isLineItems = $this->_exportLineItems($request);
        
		$eid = (int)Mage::getStoreConfig('payment/billmatebankpay/eid');
		$secret=(float)Mage::getStoreConfig('payment/billmatebankpay/secret');
		
		$request = array_merge($request, array(
			'merchant_id'   => $eid,
			'pay_method'	=> 'BANK',
			'return_method' => 'GET'
		));
	    $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $_directory = Mage::helper('directory');

        $data = $this->_cart->getTotals();

        $request['amount'] = $_directory->currencyConvert(($data['subtotal']+$data['shipping']+$data['tax'])-$data['discount'],$baseCurrencyCode,$currentCurrencyCode);
        
        $request['amount']        = round($request['amount']* 100,0);
		$request['capture_now']   = 'YES';

        $mac_str = $request['accept_url'] . $request['amount']. 
				   $request['callback_url'] . $request['cancel_url'] . $request['capture_now'] . $request['currency'] . 
				   $request['merchant_id'] . $request['order_id'] . $request['pay_method'] . 'GET' . $secret;
        
        $mac = hash ( "sha256", $mac_str );
		$request['mac'] = $mac	;
		
		require_once Mage::getBaseDir('lib').'/Billmate/commonfunctions.php';
		
        // payer address
        return $request;
    }
    protected function _exportLineItems(array &$request, $i = 1)
    {
        if (!$this->_cart) {
            return;
        }
        if ($this->getIsLineItemsEnabled()) {
            $this->_cart->isShippingAsItem(true);
        }
        return parent::_exportLineItems($request, $i);
    }


    /**
     * Adopt specified request array to be compatible with Paypal
     * Puerto Rico should be as state of USA and not as a country
     *
     * @param array $request
     */
    protected function _applyCountryWorkarounds(&$request)
    {
        if (isset($request['country']) && $request['country'] == 'PR') {
            $request['country'] = 'US';
            $request['state']   = 'PR';
        }
    }
}
;