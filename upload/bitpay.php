<?php

function bplog($contents)
{
	error_log($contents);
}


defined('_JEXEC') or die('Restricted access');

/**
 * @version $Id: standard.php,v 1.4 2005/05/27 19:33:57 ei
 *
 * a special type of 'cash on delivey':
 * @author Max Milbers, Valérie Isaksen
 * @version $Id: standard.php 5122 2011-12-18 22:24:49Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentBitPay extends vmPSPlugin
{
	function __construct(& $subject, $config) {

		parent::__construct($subject, $config);
		// 		vmdebug('Plugin stuff',$subject, $config);
		$this->_loggable   = true;
		$this->tableFields = array_keys($this->getTableSQLFields());

		$varsToPush = $this->getVarsToPush();
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * @author Valérie Isaksen
	 */
	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Payment BitPay Table');
	}

	/**
	 * Fields to create the payment table
	 * @return string SQL Fileds
	 */
	function getTableSQLFields() {
		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)'
		);

		return $SQLfields;
	}

	
	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
		if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
			return NULL;
		}

		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('BITPAY_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('BITPAY_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		$html .= '</table>' . "\n";
		return $html;
	}

	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
		if (preg_match('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions($cart, $method, $cart_prices) {
		$this->convert($method);
		// 		$params = new JParameter($payment->payment_params);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$amount      = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return false;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array($address)) {
			$address                          = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			return true;
		}

		return false;
	}

	function convert($method) {

		$method->min_amount = (float)$method->min_amount;
		$method->max_amount = (float)$method->max_amount;
	}

	/*
* We must reimplement this triggers for joomla 1.7
*/

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
	}




	/*
* plgVmonSelectedCalculatePricePayment
* Calculate the price (value, tax_id) of the selected method
* It is called by the calculator
* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
* @author Valerie Isaksen
* @cart: VirtueMartCart the current cart
* @cart_prices: array the new cart prices
* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
*
*
*/

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);

		$paymentCurrencyId = $method->payment_currency;
		return;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
	}


	/*
	*   plgVmOnPaymentNotification() - This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
	*/
	function plgVmOnPaymentNotification () {	
		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$bitpay_data = file_get_contents("php://input");
		$bitpay_data = json_decode($bitpay_data, true);	
		$bitpay_data['posData'] = json_decode($bitpay_data['posData'], true);
		
		if (!isset($bitpay_data['posData'])) {
			bplog('no invoice in data');
			return NULL;
		}
		$order_number = $bitpay_data['posData']['id_order'];
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			bplog('order not found '.$order_number);
			return NULL;
		}

		$modelOrder = VmModel::getModel ('orders');
		$order = $modelOrder->getOrder($virtuemart_order_id);
		if (!$order) {
			bplog('order could not be loaded '.$virtuemart_order_id);
			return NULL;
		}

		$method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
		if ($bitpay_data['posData']['hash'] != crypt($order_number, $method->merchant_apikey))
		{
			bplog('api key invalid for order '.$order_number);
			return NULL;
		}
		
		if ($bitpay_data['status'] != 'confirmed' and $bitpay_data['status'] != 'complete')
		{
			return NULL; // not the status we're looking for 
		}
		
		$order['order_status'] = 'C'; // move to admin method option?
		$modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
	}

	/**
	 * @param $html
	 * @return bool|null|string
	 */
	function plgVmOnPaymentResponseReceived (&$html) {

		if (!class_exists ('VirtueMartCart')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists ('shopFunctionsF')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = JRequest::getInt ('pm', 0);
		$order_number = JRequest::getString ('on', 0);
		$vendorId = 0;
		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return NULL;
		}
		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		$payment_name = $this->renderPluginName ($method);
		$html = $this->_getPaymentResponseHtml ($paymentTable, $payment_name);

		//We delete the old stuff
		// get the correct cart / session
		$cart = VirtueMartCart::getCart ();
		$cart->emptyCart ();
		return TRUE;
	}

	/**
	 * This shows the plugin for choosing in the payment list of the checkout process.
	 *
	 * @author Valerie Cartan Isaksen
	 */
	function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		$session = JFactory::getSession ();
		$errors = $session->get ('errorMessages', 0, 'vm');
		if($errors != "") {
			$errors = unserialize($errors);
			$session->set ('errorMessages', "", 'vm');
		}
		else {
			$errors = array();
		}

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

	/**
	 * getGMTTimeStamp:
	 * 
	 * this function creates a timestamp formatted as per requirement in the
	 * documentation
	 *
	 * @return string The formatted timestamp
	 */
	public function getGMTTimeStamp()
	{
		/* Format: YYYYDDMMHHNNSSKKK000sOOO
			YYYY is a 4-digit year
			DD is a 2-digit zero-padded day of month
			MM is a 2-digit zero-padded month of year (January = 01)
			HH is a 2-digit zero-padded hour of day in 24-hour clock format (midnight =0)
			NN is a 2-digit zero-padded minute of hour
			SS is a 2-digit zero-padded second of minute
			KKK is a 3-digit zero-padded millisecond of second
			000 is a Static 0 characters, as BitPay does not store nanoseconds
			sOOO is a Time zone offset, where s is + or -, and OOO = minutes, from GMT.
		*/
		$tz_minutes = date('Z') / 60;
		
		if ($tz_minutes >= 0)
		{
			$tz_minutes = '+' . sprintf("%03d",$tz_minutes); //Zero padding in-case $tz_minutes is 0
		}
		
		$stamp = date('YdmHis000000') . $tz_minutes; //In some locales, in some situations (i.e. Magento 1.4.0.1) some digits are missing. Added 5 zeroes and truncating to the required length. Terrible terrible hack.
		
		return $stamp;
	}

	private function makeXMLTree ($data, &$outputArray = array()) {
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		$result = xml_parse_into_struct($parser, $data, $values, $tags);
		xml_parser_free($parser);
		if ($result == 0) {
			return false;
		}

		$hash_stack = array();
		foreach ($values as $key => $val) {
			switch ($val['type']) {
				case 'open':
					array_push($hash_stack, $val['tag']);
					break;
				case 'close':
					array_pop($hash_stack);
					break;
				case 'complete':
					array_push($hash_stack, $val['tag']);
					eval("\$outputArray['" . implode($hash_stack, "']['") . "'] = \"{$val['value']}\";");
					array_pop($hash_stack);
					break;
			}
		}
		return true;
	}



	/**
	 *
	 *
	 * @author Kris Henriksen
	 */
	function plgVmConfirmedOrder($cart, $order) {

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		// 		$params = new JParameter($payment->payment_params);
		$lang     = JFactory::getLanguage();
		$filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);
		$vendorId = 0;

		$html = "";

		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$this->getPaymentCurrency($method, true);

		// END printing out HTML Form code (Payment Extra Info)
		$q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3        = $db->loadResult();
		$paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$cd                     = CurrencyDisplay::getInstance($cart->pricesCurrency);
		$usrBT = $order['details']['BT'];
		$usrST = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);


		$options['transactionSpeed'] = 'high';
		$options['currency'] = $currency_code_3;
		$options['notificationURL'] = (JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component'));
		$options['redirectURL'] = (JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt ('Itemid')));
		$options['posData'] = '{"id_order": "' . $order['details']['BT']->order_number . '"';
		$options['posData'].= ', "hash": "' . crypt($order['details']['BT']->order_number, $method->merchant_apikey) . '"';
		$options['posData'].= '}';
		$options['orderID'] = $order['details']['BT']->order_number;
		$options['price'] = $order['details']['BT']->order_total;

		
		$postOptions = array('orderID', 'itemDesc', 'itemCode', 'notificationEmail', 'notificationURL', 'redirectURL', 
			'posData', 'price', 'currency', 'physical', 'fullNotifications', 'transactionSpeed', 'buyerName', 
			'buyerAddress1', 'buyerAddress2', 'buyerCity', 'buyerState', 'buyerZip', 'buyerEmail', 'buyerPhone');

		foreach($postOptions as $o)
		{
			if (array_key_exists($o, $options))
			{
				$post[$o] = $options[$o];
			}
		}

		$post = json_encode($post);
		
		// Call BitPay
		$curl = curl_init('https://bitpay.com/api/invoice/');
		$length = 0;
		if ($post)
		{	
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
			$length = strlen($post);
		}

		$uname = base64_encode($method->merchant_apikey);
		$header = array(
			'Content-Type: application/json',
			"Content-Length: $length",
			"Authorization: Basic $uname",
			'X-BitPay-Plugin-Info: virtuemart033114',
			);

		curl_setopt($curl, CURLOPT_PORT, 443);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1); // verify certificate
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // check existence of CN and verify that it matches hostname
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
			
		$responseString = curl_exec($curl);
		
		if($responseString == false) {
			$response = curl_error($curl);
		} else {
			$response = json_decode($responseString, true);
		}
		curl_close($curl);
		
		$this->logInfo ('invoice ' . var_export($response, true), 'message');

		if (isset($response['url'])) {
			header('Location: ' . $response['url']);
			exit;
		} else {
			bplog(var_export($response, true));

			$mainframe = JFactory::getApplication();
	
			if (is_array($response) && array_key_exists('error', $response)) {
				$this->_handlePaymentCancel($order['details']['BT']->virtuemart_order_id, $response['error']['message']);
			} else if (!is_array($response)) {
				$this->_handlePaymentCancel($order['details']['BT']->virtuemart_order_id, "Invalid response returned from gateway");
			} else {
				$this->_handlePaymentCancel($order['details']['BT']->virtuemart_order_id, "Unknown error or response");
			}
		}	
	}

	function _handlePaymentCancel ($virtuemart_order_id, $html) {
		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$modelOrder = VmModel::getModel ('orders');
		$modelOrder->remove (array('virtuemart_order_id' => $virtuemart_order_id));
		// error while processing the payment
		$mainframe = JFactory::getApplication ();
		$mainframe->redirect (JRoute::_ ('index.php?option=com_virtuemart&view=cart&task=editpayment'), $html);
	}

	/**
	 * takes a string and returns an array of characters
	 *
	 * @param string $input string of characters
	 * @return array
	 */
	function toCharArray($input) {
		$len = strlen ( $input );
		for($j = 0; $j < $len; $j ++) {
			$char [$j] = substr ( $input, $j, 1 );
		}
		return ($char);
	}
}

defined('_JEXEC') or die('Restricted access');

/**
 *
 * @package	VirtueMart
 * @subpackage Plugins  - Elements
 * @author Valérie Isaksen
 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2011 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: $
 */
/*
 * This class is used by VirtueMart Payment  Plugins
 * which uses JParameter
 * So It should be an extension of JElement
 * Those plugins cannot be configured througth the Plugin Manager anyway.
 */
if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart'.DS.'helpers'.DS.'config.php');
if (!class_exists('ShopFunctions'))
    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');

/**
 * @copyright	Copyright (C) 2009 Open Source Matters. All rights reserved.
 * @license	GNU/GPL
 */
// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();
