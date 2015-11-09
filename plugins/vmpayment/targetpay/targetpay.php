<?php

/**
 * @file	 Provides support for TargetPay iDEAL, Mister Cash and Sofort Banking
 * @author	 Yellow Melon B.V.
 * @url		 http://www.idealplugins.nl
 * @release	 20-08-2013
 * @ver		 2.1
 *
 * Changes:
 *
 * v1.1 	Bug fix for SEF URLs
 */

defined ('_JEXEC') or die('Restricted access');
if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}


if (!class_exists('TargetPayCore')) {
    require(JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'targetpay' . DS . 'targetpay.class.php');
}

class plgVmpaymentTargetpay extends vmPSPlugin {

	public static $_this = FALSE;
	public $appId = 'bbb31e3f01d6291c12d29dbb86bc9f77';
	
	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);
		// unique filelanguage for all targetpay methods
		$jlang = JFactory::getLanguage ();
		$jlang->load ('plg_vmpayment_targetpay', JPATH_ADMINISTRATOR, NULL, TRUE);
		$this->_loggable = TRUE;
		$this->_debug = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id'; //virtuemart_targetpay_id';
		$this->_tableId = 'id'; //'virtuemart_targetpay_id';

		$varsToPush = array('targetpay_rtlo'        => array('', 'char'),
		                    'payment_currency'    	=> array('', 'char'),
		                    'countries'           	=> array('', 'char'),
		                    'cost_per_transaction' 	=> array('', 'int'),
							'cost_percent_total' 	=> array('', 'int'),
							
							'targetpay_enable_ide'  => array('', 'int'),
		                    'targetpay_enable_mrc'  => array('', 'int'),
		                    'targetpay_enable_deb'  => array('', 'int'),
							
		                    'min_amount'          => array('', 'int'),
		                    'max_amount'          => array('', 'int'),
		                    'tax_id'              => array(0, 'int'),
		                    'countries'           => array('', 'char'),
		                    'status_pending'      => array('', 'char'),
		                    'status_success'      => array('', 'char'),
		                    'status_canceled'     => array('', 'char'));

		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
	}

	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}
	
	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Payment Targetpay Table');
	}

	function _getTargetPayWebsite ($method) {

		$url = 'www.targetpay.com';
		return $url;
	}

	function __makeHiddenFields(){
		$html = '';
		if(isset($_POST)) {
			foreach($_POST AS $key => $value) {
				$html .= '<input type="hidden" value="'.htmlspecialchars($value).'" name="'.htmlspecialchars($key).'" />';
			}
		}
		return $html;
	}

	function _getPaymentResponseHtml ($paymentTable, $payment_name,$state) {
		$html .= '<table>' . "\n";
		$html .= $this->getHtmlRow ('TARGETPAY_PAYMENT_NAME', $payment_name);
		if (!empty($paymentTable)) {
			$html .= $this->getHtmlRow ('TARGETPAY_ORDER_NUMBER', $paymentTable->order_number);
		}
		$html .= $this->getHtmlRow (Jtext::_ ('TARGETPAY_PAYMENT_CHECK_RESULT'),Jtext::_ ('VMPAYMENT_TARGETPAY_PAYMENT_CHECK_'.$state));
		$html .= '</table>' . "\n";

		return $html;
	}

	function _getInternalData ($virtuemart_order_id, $order_number = '') {

		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE ';
		if ($order_number) {
			$q .= " `order_number` = '" . $order_number . "'";
		} else {
			$q .= ' `virtuemart_order_id` = ' . $virtuemart_order_id;
		}

		$db->setQuery ($q);
		if (!($paymentTable = $db->loadObject ())) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		return $paymentTable;
	}

	function _storeInternalData ($method, $mb_data, $virtuemart_order_id) {

		// get all know columns of the table
		$db = JFactory::getDBO ();
		$query = 'SHOW COLUMNS FROM `' . $this->_tablename . '` ';
		$db->setQuery ($query);
		$columns = $db->loadResultArray (0);

		$post_msg = '';
		foreach ($mb_data as $key => $value) {
			$post_msg .= $key . "=" . $value . "<br />";
			$table_key = 'mb_' . $key;
			if (in_array ($table_key, $columns)) {
				$response_fields[$table_key] = $value;
			}
		}

		$response_fields['payment_name'] = $this->renderPluginName ($method);
		$response_fields['mbresponse_raw'] = $post_msg;
		$response_fields['order_number'] = $mb_data['transaction_id'];
		$response_fields['mb_transaction_id'] = $mb_data['trxid'];
		$response_fields['mb_account_name'] = $mb_data['cname'];
		$response_fields['mb_account_number'] = $mb_data['cbank'];
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		$this->storePSPluginInternalData ($response_fields, 'virtuemart_order_id', TRUE);
	}

	function __storeTargetpayRequestData($data) {

		// Get a db connection.
		$db = JFactory::getDbo();
		 
		// Create a new query object.
		$query = $db->getQuery(true);
		
		foreach($data AS $key => $value) {
			$columns[] = $key;
			$values[] = $db->quote($value);
		}
		
		// Prepare the insert query.
		$query
			->insert($db->quoteName('#__targetpay_ideal'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		 
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$db->execute();
		return $db->insertid();
	}


	

	function getTableSQLFields () {

		$SQLfields = array('id'                     => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
		                   'virtuemart_order_id'    => 'int(1) UNSIGNED',
		                   'order_number'           => ' char(64)',
		                   'virtuemart_paymentmethod_id'
		                                             => 'mediumint(1) UNSIGNED',
		                   'payment_name'            => 'varchar(5000)',
		                   'payment_order_total'     => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
		                   'payment_currency'        => 'char(3) ',
		                   'cost_per_transaction'    => 'decimal(10,2)',
		                   'cost_percent_total'      => 'decimal(10,2)',
		                   'tax_id'                  => 'smallint(1)',
		                   'user_session'            => 'varchar(255)',
			// status report data returned by Moneybookers to the merchant
		                   'mb_pay_to_email'         => 'varchar(50)',
		                   'mb_pay_from_email'       => 'varchar(50)',
		                   'mb_account_name'		 => 'varchar(75)',
		                   'mb_account_number'		 => 'varchar(75)',
		                   'mb_merchant_id'          => 'int(10) UNSIGNED',
		                   'mb_transaction_id'       => 'varchar(25)',
		                   'mb_rec_payment_id'       => 'int(10) UNSIGNED',
		                   'mb_rec_payment_type'     => 'varchar(16)',
		                   'mb_amount'               => 'decimal(19, 2)',
		                   'mb_currency'             => 'char(3)',
		                   'mb_status'               => 'tinyint',
		                   'mbresponse_raw'          => 'varchar(512)');

		return $SQLfields;
	}




function plgVmConfirmedOrder ($cart, $order, $payment_method = '') {
	$html = '';
		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if($_POST) {
			/* Bank selection */
			if(!isset($_POST["payment_ide"]) && !isset($_POST["payment_mrc"]) && !isset($_POST["payment_deb"])){
				$targetpayCore = new TargetPayCore("AUTO",$method->targetpay_rtlo);
				$bankList = $targetpayCore->getBankList();
				$bankArrByPaymentOption = array();
				foreach($bankList AS $key => $value) {
					$arrKey = (substr($key,3,strlen($key))) ? substr($key,3,strlen($key)) : substr($key,0,3);
					$bankArrByPaymentOption[substr($key,0,3)][$arrKey] = $value;
				}
				/* remove unwanted paymethods */
				if($method->targetpay_enable_ide == 0) {
					unset($bankArrByPaymentOption['IDE']);
				}
				if($method->targetpay_enable_mrc == 0) {
					unset($bankArrByPaymentOption['MRC']);
				}
				if($method->targetpay_enable_deb == 0) {
					unset($bankArrByPaymentOption['DEB']);
				}
				$paymentOptions = array();
				foreach($bankArrByPaymentOption AS $paymentOption => $bankCodesArr) {

				$paymentOptions[$paymentOption] = '
						<form method="post" id="checkoutForm'.strtolower($paymentOption).'" name="checkoutForm'.strtolower($paymentOption).'" action="'.JROUTE::_(JURI::root().'index.php?option=com_virtuemart&view=cart&task=confirm').'">
							<div>'.$this->__makeHiddenFields().'
								<h2>'.JText::_ ('VMPAYMENT_TARGETPAY_PAYMENT_OPTION_'.$paymentOption).'</h2>
								<img src="'.JROUTE::_('media/plg_vmpayment_targetpay/images/method-'.strtolower($paymentOption).'.png').'" />
								<br />';

								$bankListCount = count($bankCodesArr);
								if($bankListCount == 0) {
									$paymentOptions[$paymentOption] .= JText::_ ('VMPAYMENT_TARGETPAY_PAYMENT_OPTION_NOT_FOUNT');
								} else if ($bankListCount == 1) {
									list($bankValue) = array_keys($bankCodesArr);
									$paymentOptions[$paymentOption] .= '<input type="hidden" name="payment_'.strtolower($paymentOption).'" value="'.$bankValue.'" />';
								} else {
									$paymentOptions[$paymentOption] .= '<select name="payment_'.strtolower($paymentOption).'">';
									foreach($bankCodesArr AS $key => $value) {
										$value = str_replace('iDEAL: ','',$value);
										$value = str_replace('Mister Cash: ','',$value);
										$value = str_replace('Sofort Banking: ','',$value);

										$paymentOptions[$paymentOption] .= '<option value="'.$paymentOption.$key.'">'.$value.'</option>';
									}
									$paymentOptions[$paymentOption] .= '</select>';
								}


								$paymentOptions[$paymentOption] .= '<br /><br />
								<a class="vm-button-correct" href="javascript:document.checkoutForm'.strtolower($paymentOption).'.submit();" ><span>'.JText::_ ('VMPAYMENT_TARGETPAY_PAY_WITH').' '.JText::_ ('VMPAYMENT_TARGETPAY_PAYMENT_OPTION_'.$paymentOption).'</span></a>
							</div>
							</form>
							<br />
							<hr />
						';

				}

				foreach($paymentOptions AS $paymentOption => $div) {
					$html .= $div;
				}
				JRequest::setVar ('html', $html);
				return false;
			}
		}

		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		$session = JFactory::getSession ();
		$return_context = $session->getId ();
		$this->logInfo ('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}

		$usrBT = $order['details']['BT'];
		$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

		if (!class_exists ('TableVendors')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
		}
		$vendorModel = VmModel::getModel ('Vendor');
		$vendorModel->setId (1);
		$vendor = $vendorModel->getVendor ();
		$vendorModel->addImages ($vendor, 1);
		$this->getPaymentCurrency ($method);

		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .
			$method->payment_currency . '" ';
		$db = JFactory::getDBO ();
		$db->setQuery ($q);
		$currency_code_3 = $db->loadResult ();

		$paymentCurrency = CurrencyDisplay::getInstance ($method->payment_currency);
		$totalInPaymentCurrency = round ($paymentCurrency->convertCurrencyTo ($method->payment_currency,
			$order['details']['BT']->order_total,
			FALSE), 2);
			
		if ($totalInPaymentCurrency <= 0) {
			vmInfo (JText::_ ('VMPAYMENT_TARGETPAY_PAYMENT_AMOUNT_INCORRECT'));
			return FALSE;
		}

		
		$lang = JFactory::getLanguage ();
		$tag = substr ($lang->get ('tag'), 0, 2);
		
		// Prepare data that should be stored in the database
		$dbValues['user_session'] = $return_context;
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName ($method, $order);
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $method->payment_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData ($dbValues);


		$bankID = ((isset($_POST["payment_ide"])) ? $_POST["payment_ide"] : ((isset($_POST["payment_mrc"])) ? $_POST["payment_mrc"] : ((isset($_POST["payment_deb"])) ? $_POST["payment_deb"] : 'error')));
		$description = 'Order id: '.$order['details']['BT']->order_number;
		
		$targetpayObj = new TargetPayCore("AUTO",$method->targetpay_rtlo,$this->appId);
		$targetpayObj->setBankId($bankID);

		$targetpayObj->setAmount(($order['details']['BT']->order_total*100));
		$targetpayObj->setDescription($description);
		

		$returnUrl = JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' .
													$order['details']['BT']->order_number .
													'&pm=' .
													$order['details']['BT']->virtuemart_paymentmethod_id .
													'&Itemid=' . JRequest::getInt ('Itemid'));
		$targetpayObj->setReturnUrl($returnUrl);
		$reportUrl = JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component');
		$targetpayObj->setReportUrl($reportUrl);
		$result = @$targetpayObj->startPayment();


		if (!$result) {
			$this->sendEmailToVendorAndAdmins ("Error with Targetpay: ",$targetpayObj->getErrorMessage());
			$this->logInfo ('Process IPN ' . $targetpayObj->getErrorMessage());

			vmInfo (JText::_ ('VMPAYMENT_TARGETPAY_DISPLAY_GWERROR'). " ". $targetpayObj->getErrorMessage());
			return NULL;
		} else {
			$data["cart_id"]			= $order['details']['BT']->order_number;
			$data["rtlo"]				= $method->targetpay_rtlo;
			$data["paymethod"]			= $targetpayObj->getPayMethod();
			$data["transaction_id"]		= $targetpayObj->getTransactionId();
			$data["bank_id"]			= $bankId;
			$data["description"]		= $description;
			$data["amount"]				= $order['details']['BT']->order_total;
			$data["bankaccount"]		= 'NULL';
			$data["name"]				= 'NULL';
			$data["city"]				= 'NULL';
			$data["status"]				= '0';
			$data["via"]				= 'NULL';
			$this->__storeTargetpayRequestData($data);

			$this->logInfo ('Transaction id: '. $targetpayObj->getTransactionId() . 'Payment Url:'. $targetpayObj->getBankUrl(), 'message');
			
		}
		
		$html = '<html><head><title>Targetpay Redirect</title></head><body><div style="margin: auto; text-align: center;">';
		$html .= '<form action="' . $targetpayObj->getBankUrl() . '" method="post" name="vm_targetpay_form" >';
		$html .= '<input type="submit"  value="' . JText::_ ('VMPAYMENT_PAYPAL_REDIRECT_MESSAGE') . '" />';
		$html .= '</form></div>';
		$html .= ' <script type="text/javascript">';
		$html .= ' document.vm_targetpay_form.submit();';
		$html .= ' </script></body></html>';

		$cart->_confirmDone = FALSE;
		$cart->_dataValidated = FALSE;
		$cart->setCartIntoSession ();
		
		$application = JFactory::getApplication();
		$application->redirect($targetpayObj->getBankUrl(), "");
		
		JRequest::setVar ('html', $html);
	}
	
	
	function __retrieveTargetpayInformation($id, $by='cart'){
		// Get a db connection.
		$db = JFactory::getDbo();
		 
		// Create a new query object.
		$query = $db->getQuery(true);
		 
		// Select all records from the user profile table where key begins with "custom.".
		// Order it by the ordering field.
		$query->select(array('id','cart_id','rtlo','paymethod','transaction_id','bank_id','description','amount','bankaccount','name','city','status','via'));
		
		$query->from('#__targetpay_ideal');
		if($by == 'cart') {
			$query->where("cart_id = '".$id."'");
		} else {
			$query->where("transaction_id = '".$id."'");
		}
		 
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$db->execute();
		// Load the results as a list of stdClass objects.
		return $db->loadObjectList();
	}
	
	
	function __isPaidViaTargetpay($payMethod,$rtlo,$transactionId){
		$targetpayObj = new TargetPayCore($payMethod,$rtlo,$this->appId);
		$targetpayObj->checkPayment($transactionId);
		
		$result = array('paid' => false,'state' => 'ERROR');
		if(!$targetpayObj->getPaidStatus()) {
			list($errorCode) = explode(" ",$targetpayObj->getErrorMessage());
			switch ($errorCode) {
				case 'TP0010':
					$state = 'ERROR_NOT_COMPLETED';
					break;
				case 'TP0011':
					$state = 'ERROR_CANCELLED';
					break;
				case 'TP0012':
					$state = 'ERROR_EXPIRED';
					break;
				case 'TP0013':
					$state = 'ERROR_NOT_PROCESSED';
					break;
				case 'TP0014':
					$state = 'ERROR_ALREADY_CHECKED';
					break;
			}
			$result["state"] = $state;
		} else {
			$result["paid"] = true;
			$result["state"] = 'SUCCESS';
		}
		return $result;
	}
	
	function _parse_response ($response) {

		$matches = array();
		$rlines = explode ("\r\n", $response);

		foreach ($rlines as $line) {
			if (preg_match ('/([^:]+): (.*)/im', $line, $matches)) {
				continue;
			}

			if (preg_match ('/([0-9a-f]{32})/im', $line, $matches)) {
				return $matches;
			}
		}

		return $matches;
	}

	

	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		$this->getPaymentCurrency ($method);
		$paymentCurrencyId = $method->payment_currency;
	}

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

		$mb_data = JRequest::get ('post');


		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = JRequest::getInt ('pm', 0);
		$order_number = JRequest::getString ('on', 0);
		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

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
		vmdebug ('TARGETPAY plgVmOnPaymentResponseReceived', $mb_data);
		$cart = VirtueMartCart::getCart ();
		
		$payResult = array('state' => 'ERROR_ORDER_NUMBER_NOT_FOUND');
		
		
		
		/* because of the old functions of targetpay, we're at the moment
		 * not able to set a cancellation url, so we have to check the 
		 * state here
		 * */
		 
		$order_number = (!empty($cart->order_number) ? $cart->order_number : $paymentTable->order_number);
		
		$targetpayLocalInformation = $this->__retrieveTargetpayInformation($order_number,'cart');

		$payResult = $this->__isPaidViaTargetpay($targetpayLocalInformation[0]->paymethod,$targetpayLocalInformation[0]->rtlo,$targetpayLocalInformation[0]->transaction_id);
		if($payResult['state'] == 'ERROR_CANCELLED') {
			$this->plgVmOnUserPaymentCancel();
		} else {
			vmInfo (Jtext::_ ('VMPAYMENT_TARGETPAY_PAYMENT_CHECK_'.$payResult['state']));
			//Only when the payment was succesfull empty the cart
			if($payResult["state"] == 'SUCCESS') {
				$cart->emptyCart ();
			}
		}
		$payment_name = $this->renderPluginName ($method);
		
		$html = $this->_getPaymentResponseHtml ($paymentTable, $payment_name,$payResult['state']);
		return TRUE;
	}

	function plgVmOnUserPaymentCancel () {

		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		$order_number = JRequest::getString ('on', '');
		$virtuemart_paymentmethod_id = JRequest::getInt ('pm', '');
		if (empty($order_number) or
			empty($virtuemart_paymentmethod_id) or
			!$this->selectedThisByMethodId ($virtuemart_paymentmethod_id)
		) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return NULL;
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}

		vmError (Jtext::_ ('VMPAYMENT_TARGETPAY_PAYMENT_CANCELLED'));
		$session = JFactory::getSession ();
		$return_context = $session->getId ();
		if (strcmp ($paymentTable->user_session, $return_context) === 0) {
			$this->handlePaymentUserCancel ($virtuemart_order_id);
		}

		return TRUE;
	}

	function plgVmOnPaymentNotification () {

		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		//ToDo: Enable post, for now request is enough
		$mb_data = JRequest::get ('post');
		$mb_data["account_name"] = $mb_data["cname"];
		$mb_data["account_number"] = $mb_data["cbank"];

		if (!isset($mb_data['trxid'])) { //Trxid not set
			die('error');
		}


		/* retrieve order number (cart_id) from the tables */
		$targetpayLocalInformation = $this->__retrieveTargetpayInformation($mb_data['trxid'],'transaction_id');
		$order_number = $targetpayLocalInformation[0]->cart_id;
		$mb_data['transaction_id'] = $order_number;
		
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			$this->logInfo (__FUNCTION__ . ' Can\'t get VirtueMart order id', 'message');
			return;
		}

		if (!($payment = $this->getDataByOrderId ($virtuemart_order_id))) {
			$this->logInfo (__FUNCTION__ . ' Can\'t get payment type', 'message');
			return;
		}

		$method = $this->getVmPluginMethod ($payment->virtuemart_paymentmethod_id);
		if (!$this->selectedThisElement ($method->payment_element)) {
			$this->logInfo (__FUNCTION__ . ' payment method not selected', 'message');
			return FALSE;
		}

		if (!$payment) {
			$this->logInfo ('getDataByOrderId payment not found: exit ', 'ERROR');
			return NULL;
		}
		
		$payResult = $this->__isPaidViaTargetpay($targetpayLocalInformation[0]->paymethod,$targetpayLocalInformation[0]->rtlo,$targetpayLocalInformation[0]->transaction_id);
		switch ($payResult["state"]) {
			case 'ERROR_ALREADY_CHECKED' :
			case 'SUCCESS' :
				$mb_data['payment_status'] = 'Completed';
				break;
			case 'ERROR_NOT_COMPLETED' :
				$mb_data['payment_status'] = 'Pending';
				break;
			case 'ERROR_CANCELLED' :
				$mb_data['payment_status'] = 'Cancelled';
				break;
			case 'ERROR_EXPIRED' :
			case 'ERROR' :
			case 'ERROR_NOT_PROCESSED':
			default:
				$mb_data['payment_status'] = 'Failed';
				break;
		}
		
		$this->_storeInternalData ($method, $mb_data, $virtuemart_order_id);

		$modelOrder = VmModel::getModel ('orders');
		$vmorder = $modelOrder->getOrder ($virtuemart_order_id);
		$order = array();
		
		
		$order['customer_notified'] = 1;

		if (strcmp ($mb_data['payment_status'], 'Completed') == 0) {
			$order['order_status'] = $method->status_success;
			$order['comments'] = JText::sprintf ('VMPAYMENT_TARGETPAY_PAYMENT_STATUS_CONFIRMED', $order_number);
		} elseif (strcmp ($mb_data['payment_status'], 'Pending') == 0) {
			$order['comments'] = JText::sprintf ('VMPAYMENT_TARGETPAY_PAYMENT_STATUS_PENDING', $order_number);
			$order['order_status'] = $method->status_pending;
		}
		else {
			$order['order_status'] = $method->status_canceled;
		}
		$this->logInfo ('plgVmOnPaymentNotification return new_status:' . $order['order_status'], 'message');
		$modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
		$this->emptyCart ($payment->user_session, $mb_data['transaction_id']);
	}
	
	
	
	
	
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $payment_method_id) {

		if (!$this->selectedThisByMethodId ($payment_method_id)) {
			return NULL;
		} // Another method was selected, do nothing

		if (!($paymentTable = $this->_getInternalData ($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}

		$this->getPaymentCurrency ($paymentTable);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .
			$paymentTable->payment_currency . '" ';
		$db = JFactory::getDBO ();
		$db->setQuery ($q);
		$currency_code_3 = $db->loadResult ();
		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('TARGETPAY_PAYMENT_NAME', $paymentTable->payment_name);

		$code = "mb_";
		foreach ($paymentTable as $key => $value) {
			if (substr ($key, 0, strlen ($code)) == $code) {
				$html .= $this->getHtmlRowBE ($key, $value);
			}
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

		if (preg_match ('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr ($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}

		return ($method->cost_per_transaction +
			($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}

	protected function checkConditions ($cart, $method, $cart_prices) {

		$this->convert ($method);

		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$amount = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));

		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (in_array ($address['virtuemart_country_id'], $countries) || count ($countries) == 0) {
			if ($amount_cond) {
				return TRUE;
			}
		}

		return FALSE;
	}

	function convert ($method) {

		$method->min_amount = (float)$method->min_amount;
		$method->max_amount = (float)$method->max_amount;
	}

	/**
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart,  &$msg) {
		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
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

	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
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
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment($psType, VirtueMartCart $cart) {
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
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not activated.

	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}
	 */
	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.

	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}
	 */
	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise

	public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise

	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}
	 */
	function plgVmDeclarePluginParamsPayment ($name, $id, &$data) {

		return $this->declarePluginParams ('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

		return $this->setOnTablePluginParams ($name, $id, $table);
	}

} // end of class plgVmpaymentTargetpay

// No closing tag
