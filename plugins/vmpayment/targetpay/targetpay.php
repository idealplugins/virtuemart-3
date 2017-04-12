<?php
/**
 * @file Provides support for TargetPay iDEAL, Bancontact and Sofort Banking
 *
 * @author TargetPay.
 * @url http://www.idealplugins.nl
 * @release 22-11-2016
 * @ver 3.1
 * Changes: Fix bug & refactor code
 */
use targetpay\helpers\TargetPayCore;

defined('_JEXEC') or die('Restricted access');
if (! class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

if (! class_exists('TargetPayCore')) {
    require(JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'targetpay' . DS . 'targetpay' . DS . 'helpers' . DS . 'targetpay.class.php');
}

class plgVmpaymentTargetpay extends vmPSPlugin
{
    const TARGETPAY_CURRENCY = 'EUR';
    
    public $listMethods = array(
        "IDE" => 'iDEAL',
        "MRC" => 'Bancontact',
        "DEB" => 'Sofort Banking',
        'WAL' => 'Paysafecard',
        'CC'  => 'Creditcard'
    );

    public static $_this = false;

    public $appId = 'bbb31e3f01d6291c12d29dbb86bc9f77';

    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        // unique filelanguage for all targetpay methods
        $jlang = JFactory::getLanguage();
        $jlang->load('plg_vmpayment_targetpay', JPATH_ADMINISTRATOR, null, true);
        $this->_loggable = true;
        $this->_debug = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id'; // virtuemart_targetpay_id';
        $this->_tableId = 'id'; // 'virtuemart_targetpay_id';

        $varsToPush = array(
            'targetpay_rtlo' => array(
                '',
                'char'
            ),
            'payment_currency' => array(
                '',
                'char'
            ),
            'targetpay_test_mode' => array(
                '',
                'int'
            ),
            'countries' => array(
                '',
                'char'
            ),
            'status_pending' => array(
                '',
                'char'
            ),
            'status_success' => array(
                '',
                'char'
            ),
            'status_canceled' => array(
                '',
                'char'
            )
        );
        foreach ($this->listMethods as $id => $name) {
            $varName = 'targetpay_enable_' . strtolower($id);
            $varsToPush[$varName] = array('', 'int');
        }
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => ' char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3) ',
            'tp_rtlo' => 'int(11) NOT NULL',
            'tp_user_session' => 'varchar(255)',
            'tp_method' => 'varchar(8) NOT NULL DEFAULT \'IDE\'',
            'tp_bank' => 'varchar(8)',
            'tp_country' => 'varchar(8)',
            'tp_trxid' => 'varchar(255) NOT NULL',
            'tp_status' => 'varchar(3)',
            'tp_message' => 'varchar(255)',
            'tp_meta_data' => 'varchar(1000)'
        );
        return $SQLfields;
    }

    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Targetpay Table');
    }

    /**
     * Hook to confirm page
     *
     * @param unknown $cart
     * @param unknown $order
     * @return NULL|boolean
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        $application = JFactory::getApplication();
        if (! ($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (! $this->selectedThisElement($method->payment_element)) {
            return false;
        }
        // update status order
        $this->_updateOrderStatus($order['details']['BT']->virtuemart_order_id, $method->status_pending);
        // get session id
        $session = JFactory::getSession();
        $return_context = $session->getId();
        
        $this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
        
        if (! class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        if (! class_exists('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }
        
        if (! class_exists('TableVendors')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
        }
        // convert to EUR before send to targetpay
        $currencyID = ShopFunctions::getCurrencyIDByName(self::TARGETPAY_CURRENCY);
        $paymentCurrency = CurrencyDisplay::getInstance();
        $totalInPaymentCurrency = $paymentCurrency->convertCurrencyTo($currencyID, $order['details']['BT']->order_total, false);
        if ($totalInPaymentCurrency <= 0) {
            vmInfo(JText::_('VMPAYMENT_TARGETPAY_PAYMENT_AMOUNT_INCORRECT'));
            return false;
        }
        $lang = JFactory::getLanguage();
        $tag = substr($lang->get('tag'), 0, 2);
        
        $order_number = $order['details']['BT']->order_number;
        $bankID = (! empty($_POST['payment_option_select'][$_POST['targetpay_method']]) ? $_POST['payment_option_select'][$_POST['targetpay_method']] : 'error');
        $description = 'Order id: ' . $order_number;
        // start payment
        $targetpayObj = new TargetPayCore("AUTO", $method->targetpay_rtlo, $this->appId, 'nl', $method->targetpay_test_mode);
        $targetpayObj->setBankId($bankID);
        $targetpayObj->setAmount(($totalInPaymentCurrency * 100));
        $targetpayObj->setDescription($description);
        $returnUrl = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);
        $reportUrl = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&on=' . $order_number);
        $targetpayObj->setReturnUrl($returnUrl);
        $targetpayObj->setReportUrl($reportUrl);
        $result = @$targetpayObj->startPayment();
        if (! $result) {
            $this->sendEmailToVendorAndAdmins("Error with Targetpay: ", $targetpayObj->getErrorMessage());
            $this->logInfo('Process IPN ' . $targetpayObj->getErrorMessage());
            vmError(JText::_('VMPAYMENT_TARGETPAY_DISPLAY_GWERROR') . " " . $targetpayObj->getErrorMessage());
            $application->redirect('index.php?option=com_virtuemart&view=cart');
            die;
        }
        // Prepare data that should be stored in Payment Targetpay Table
        $dbValues = [];
        $dbValues['order_number'] = $order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['payment_order_total'] = $order['details']['BT']->order_total;
        $dbValues['payment_currency'] = $order['details']['BT']->order_currency;
        $dbValues['tp_rtlo'] = $method->targetpay_rtlo;
        $dbValues['tp_user_session'] = $return_context;
        $dbValues['tp_method'] = $targetpayObj->getPayMethod();
        $dbValues['tp_bank'] = $targetpayObj->getBankId();
        $dbValues['tp_country'] = $targetpayObj->getCountryId();
        $dbValues['tp_trxid'] = $targetpayObj->getTransactionId();
        $dbValues['tp_status'] = $method->status_pending;
        $this->storePSPluginInternalData($dbValues);
        $this->logInfo('Transaction id: ' . $targetpayObj->getTransactionId() . 'Payment Url:' . $targetpayObj->getBankUrl(), 'message');
        
        $cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();
        $application->redirect($targetpayObj->getBankUrl(), "");
    }

    /**
     * Check payment if not checked & show payment result
     *
     * @param unknown $html
     * @return NULL|string|boolean
     */
    public function plgVmOnPaymentResponseReceived(&$html)
    {
        $jinput = JFactory::getApplication()->input;
        
        if (! class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        if (! class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (! class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        
        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = $jinput->getInt('pm', 0);
        $order_number = $jinput->getString('on', 0);
        
        if (! ($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null;
        } // Another method was selected, do nothing

        if (! $this->selectedThisElement($method->payment_element)) {
            return null;
        }

        if (! ($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return null;
        }
        
        if (! ($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return '';
        }
        $payResult = array(
            'state' => 'ERROR_ORDER_NUMBER_NOT_FOUND'
        );
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($virtuemart_order_id);
        
        if ($paymentTable->tp_status == $method->status_success) {
            // empty cart
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();
        } else {
            $this->plgVmOnUserPaymentCancel();
        }
        $html = $this->renderByLayout('response', [
            'paymentTable' => $paymentTable,
            'order' => $order,
        ]);
        return true;
    }

    /**
     * plgVmOnUserPaymentCancel
     * @return NULL|boolean
     */
    public function plgVmOnUserPaymentCancel()
    {
        $jinput = JFactory::getApplication()->input;
        
        if (! class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        
        $order_number = $jinput->getString('on', '');
        $virtuemart_paymentmethod_id = $jinput->getInt('pm', '');
        if (empty($order_number) || empty($virtuemart_paymentmethod_id) || ! $this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
            return null;
        }
        
        if (! ($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return null;
        }
        
        if (! ($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;
        }
        
        vmError(Jtext::_('VMPAYMENT_TARGETPAY_PAYMENT_CANCELLED'));
        $session = JFactory::getSession();
        $return_context = $session->getId();
        if (strcmp($paymentTable->tp_user_session, $return_context) === 0) {
            $this->handlePaymentUserCancel($virtuemart_order_id);
        }
        return true;
    }

    /**
     * get param from targetpay via POST method & check payment & update order & Payment Targetpay Table
     *
     * @return void|boolean
     */
    public function plgVmOnPaymentNotification()
    {
        $jinput = JFactory::getApplication()->input;
        if (! class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        // ToDo: Enable post, for now request is enough
        $post_data = $_POST;
        $order_number = $jinput->getString('on', 0);
        if (empty($post_data['trxid']) || empty($post_data['status'])) { // Trxid not set
            die('error');
        }
        
        if (! ($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            $this->logInfo(__FUNCTION__ . ' Can\'t get VirtueMart order id', 'message');
            return false;
        }
        if (! ($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            $this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
            return;
        }
        $method = $this->getVmPluginMethod($paymentTable->virtuemart_paymentmethod_id);
        if (! $this->selectedThisElement($method->payment_element)) {
            $this->logInfo(__FUNCTION__ . ' payment method not selected', 'message');
            return false;
        }
        $this->_updatePaymentInfo($paymentTable, $method, $post_data);
        // empty cart
        $this->emptyCart($paymentTable->tp_user_session, $paymentTable->order_number);
        die('done version 1.x');
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart
     *            Cart object
     * @param integer $selected
     *            ID of the method selected
     * @return boolean True on success, false on failures, null when this plugin was not selected.
     *         On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     * @author Harry
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        $html = $this->_getTargetPayPluginHtml($cart, $selected);
        if (! empty($html)) {
            $htmlIn[] = [
                $html
            ];
        }
        return true;
    }

    /**
     * hook to order detail in BE to shown addition information
     *
     * @param unknown $virtuemart_order_id
     * @param unknown $payment_method_id
     * @return NULL|string
     */
    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
    {
        if (! $this->selectedThisByMethodId($payment_method_id)) {
            return null;
        } // Another method was selected, do nothing

        if (! ($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;
        }
        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('TARGETPAY_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('VMPAYMENT_TARGETPAY_PAYMENT_RTLO', $paymentTable->tp_rtlo);
        $html .= $this->getHtmlRowBE('VMPAYMENT_TARGETPAY_PAYMENT_METHOD', $paymentTable->tp_method);
        $html .= $this->getHtmlRowBE('VMPAYMENT_TARGETPAY_PAYMENT_TRXID', $paymentTable->tp_trxid);
        $html .= $this->getHtmlRowBE('VMPAYMENT_TARGETPAY_PAYMENT_STATUS', shopFunctionsF::getOrderStatusName($paymentTable->tp_status));
        $html .= $this->getHtmlRowBE('VMPAYMENT_TARGETPAY_PAYMENT_RESULT', $paymentTable->tp_message);
        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * set payment method & payment option to session
     *
     * {@inheritDoc}
     *
     * @see vmPSPlugin::checkConditions()
     */
    public function checkConditions($cart, $method, $cart_prices)
    {
        if (! empty($_POST)) {
            $session = JFactory::getSession();
            // set payment method to session
            $targetpay_method['payment_method'] = $_POST['targetpay_method'];
            $targetpay_method['payment_method_option'] = $_POST['payment_option_select'][$_POST['targetpay_method']];
            $session->set('targetpay_method', $targetpay_method);
        }
        return true;
    }

    /**
     * Build html for targetpay plugin
     *
     * @param object $cart
     *            Cart object
     * @param int $selectedPlugin
     *
     * @return string
     */
    private function _getTargetPayPluginHtml($cart, $selectedPlugin)
    {
        $html = '';
        if (! empty($this->methods)) {
            $session = JFactory::getSession();
            $targetpay_method = $session->get('targetpay_method');
            $pluginmethod_id = $this->_idName;
            $pluginName = $this->_psType . '_name';
            
            foreach ($this->methods as $plugin) {
                $bankArrByPaymentOption = array();
                /* remove unwanted paymethods */
                foreach ($this->listMethods as $id => $name) {
                    $varName = 'targetpay_enable_' . strtolower($id);
                    if ($plugin->$varName == 1) {
                        $bankArrByPaymentOption[$id] = $this->paymentArraySelection($id, $plugin->targetpay_rtlo);
                    }
                }
                if (! empty($bankArrByPaymentOption)) {
                    $html .= $this->renderByLayout('method_form', [
                        'selectedPlugin' => $selectedPlugin,
                        'bankArrByPaymentOption' => $bankArrByPaymentOption,
                        'pluginName' => $pluginName,
                        'plugin' => $plugin,
                        'pluginmethod_id' => $pluginmethod_id,
                        'targetpay_method' => $targetpay_method,
                        '_psType' => $this->_psType,
                        '_type' => $this->_type
                    ]);
                }
            }
        }
        return $html;
    }
    
    /**
     * Get array option of method
     *
     * @param string $method
     * @param string $rtlo
     * @return array
     */
    public function paymentArraySelection($method, $rtlo)
    {
        switch ($method) {
            case "IDE":
                $idealOBJ = new TargetPayCore($method, $rtlo);
                return $this->setPaymethodInKey($method, $idealOBJ->getBankList());
                break;
            case "DEB":
                $directEBankingOBJ = new TargetPayCore($method, $rtlo);
                return $directEBankingOBJ->getBankList();
                break;
            case "MRC":
            case "WAL":
            case "CC":
                return array($method => $method);
                break;
            default:
        }
    }
    
    /**
     * Create array options of method
     *
     * @param unknown $paymethod
     * @param unknown $BankListArray
     * @return []
     */
    public function setPaymethodInKey($paymethod, $BankListArray)
    {
        $newArr = array();
        foreach ($BankListArray as $key => $value) {
            $newArr[strtoupper($paymethod) . $key] = $value;
        }
        return $newArr;
    }
    
    /**
     * Update data in Payment Targetpay Table base on virtuemart_order_id column
     *
     * @param unknown $method
     * @param unknown $paymentTable
     * @param array $post_data
     * @return none
     */
    public function _storeInternalData($method, $paymentTable, $post_data = array())
    {
        // set old value to arr
        $response_fields['virtuemart_order_id'] = $paymentTable->virtuemart_order_id;
        $response_fields['order_number'] = $paymentTable->order_number;
        $response_fields['virtuemart_paymentmethod_id'] = $paymentTable->virtuemart_paymentmethod_id;
        $response_fields['payment_name'] = $paymentTable->payment_name;
        $response_fields['payment_order_total'] = $paymentTable->payment_order_total;
        $response_fields['payment_currency'] = $paymentTable->payment_currency;
        $response_fields['created_by'] = $paymentTable->created_by;
        $response_fields['modified_by'] = $paymentTable->modified_by;
        
        // added column
        $response_fields['tp_rtlo'] = $paymentTable->tp_rtlo;
        $response_fields['tp_user_session'] = $paymentTable->tp_user_session;
        $response_fields['tp_method'] = $paymentTable->tp_method;
        $response_fields['tp_bank'] = @$post_data['cbank'];
        $response_fields['tp_country'] = @$paymentTable->tp_country;
        $response_fields['tp_trxid'] = $paymentTable->tp_trxid;
        $response_fields['tp_status'] = $paymentTable->tp_status;
        $response_fields['tp_message'] = $paymentTable->tp_message;
        $response_fields['tp_meta_data'] = $paymentTable->tp_meta_data;
        
        if (! empty($post_data)) {
            $response_fields['tp_meta_data'] = json_encode($post_data);
        }
        $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);
    }

    /**
     * update Payment Targetpay Table & it's order
     *
     * @param Object $paymentTable
     * @param object $method
     * @param array $post_data
     */
    public function _updatePaymentInfo($paymentTable, $method, $post_data = array())
    {
        if ($post_data['status'] == '000000 OK') { //success
            $paymentTable->tp_status = $method->status_success;
            $paymentTable->tp_message = Jtext::_('VMPAYMENT_TARGETPAY_PAYMENT_CHECK_SUCCESS');
            $comments = JText::sprintf('VMPAYMENT_TARGETPAY_PAYMENT_STATUS_CONFIRMED', $paymentTable->order_number);
        } else {
            $paymentTable->tp_status = $method->status_canceled;
            $paymentTable->tp_message = $post_data['status'];
            $comments = JText::sprintf('VMPAYMENT_TARGETPAY_PAYMENT_CANCELLED', $paymentTable->order_number);
        }
        
        $this->_storeInternalData($method, $paymentTable, $post_data);
        // update orders
        $this->_updateOrderStatus($paymentTable->virtuemart_order_id, $paymentTable->tp_status, $comments);
    }

    /**
     * Update status of order
     *
     * @param unknown $virtuemart_order_id
     * @param unknown $status
     * @return none
     */
    public function _updateOrderStatus($virtuemart_order_id, $status, $comments = null)
    {
        $modelOrder = VmModel::getModel('orders');
        $vmorder = $modelOrder->getOrder($virtuemart_order_id);
        $order = array();
        $order['customer_notified'] = 1;
        if ($comments) {
            $order['comments'] = $comments;
        }
        $order['order_status'] = $status;
        $this->logInfo('plgVmOnPaymentNotification return new_status:' . $status, 'message');
        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @author Valérie Isaksen
     *
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected.
     * It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart:
     *            the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented.
     * If not reimplemented, then the default values from this function are taken.
     *
     * @author Valerie Isaksen
     *         @cart: VirtueMartCart the current cart
     *         @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available.
     * If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @author Valerie Isaksen
     * @param
     *            VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found, virtuemart_xxx_id if only one plugin is found
     *
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        $virtuemart_pluginmethod_id = 0;
        $nbMethod = $this->getSelectable($cart, $virtuemart_pluginmethod_id, $cart_prices);
        if ($nbMethod == NULL) {
            return NULL;
        } else {
            return 0;
        }
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id
     *            The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id
     *            The order ID
     * @param integer $method_id
     *            method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    public function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
} // end of class plgVmpaymentTargetpay
