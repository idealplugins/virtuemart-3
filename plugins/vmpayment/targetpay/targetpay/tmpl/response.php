<?php
/**
 *
 * TargetPay payment plugin
 *
 * @author Harry
 * @package Targetpay
 */

defined('_JEXEC') or die();
vmJsApi::css('targetpay','plugins/vmpayment/targetpay/targetpay/assets/css/');

$paymentTable = $viewData['paymentTable'];
?>
<br />
<table cellpadding="2" class="ordersummary">
<?php 
    echo $this->getHtmlRow('TARGETPAY_PAYMENT_NAME',  $paymentTable->payment_name);
    echo $this->getHtmlRow('TARGETPAY_ORDER_NUMBER', $paymentTable->order_number);
    echo $this->getHtmlRow(Jtext::_('TARGETPAY_PAYMENT_CHECK_RESULT'), $paymentTable->tp_message);
?>
</table>
<br />
<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$viewData["order"]['details']['BT']->order_number.'&order_pass='.$viewData["order"]['details']['BT']->order_pass, false)?>"><?php echo vmText::_('VMPAYMENT_TARGETPAY_PAYMENT_VIEW_ORDER'); ?></a>
