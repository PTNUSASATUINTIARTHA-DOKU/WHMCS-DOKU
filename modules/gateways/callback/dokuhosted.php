<?php
/**
 * WHMCS DOKU Payment Gateway Module
 *
 * DOKU Hosted Payment Gateway Module allow you to integrate DOKU Hosted with the
 * WHMCS platform.
 *
 * This Module Information:
 --------------------------
 PT Nusa Satu Inti Artha (DOKU)
 Released: Sept 18, 2017
 --------------------------
 *
 * For more information, about this modules payment please kindly visit our website at www.doku.com
 *
 */
// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Require DokuPayment Lib
$dokulib = (dirname(dirname(__FILE__)) . "/dokuhosted/dokuhosted-whmcs.php");
if (!file_exists($dokulib)) {
	exit("DokuPayment lib file not exists.");
}
// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');
// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);
$EDU_Enabled = (isset($gatewayParams['EDU-Enabled']) ? $gatewayParams['EDU-Enabled'] : '');
$EDU_Enabled = (is_string($EDU_Enabled) ? strtolower($EDU_Enabled) : '');
$EDU_Enabled = ((strtolower($EDU_Enabled) === strtolower('on')) ? 1 : 0);
$Identify_Enabled = (isset($gatewayParams['Identify-Enabled']) ? $gatewayParams['Identify-Enabled'] : '');
$Identify_Enabled = (is_string($Identify_Enabled) ? strtolower($Identify_Enabled) : '');
$Identify_Enabled = (($Identify_Enabled === 'on') ? TRUE : FALSE);
//------------------------------------------------------------------------------------------------
// Void have operational hours (08.00 - 21.00 GMT+7)
$Datezone = new DateTime(date('Y-m-d H:i:s', time()));
$Datezone->setTimezone(new DateTimeZone("Asia/Bangkok"));
if (((int)$Datezone->format('His') >= 85959) && ((int)$Datezone->format('His') <= 225959)) {
	$Void_Enabled = (isset($gatewayParams['Void-Enabled']) ? $gatewayParams['Void-Enabled'] : '');
	$Void_Enabled = (!is_array($Void_Enabled) ? trim($Void_Enabled) : '');
	$Void_Enabled = ((strtolower($Void_Enabled) === strtolower('on')) ? TRUE : FALSE);
} else {
	$Void_Enabled = FALSE;
}
// Constant disabled VOID
$Void_Enabled = FALSE;
//------------------------------------------------------------------------------------------------
$LocalApiAdminUsername = (isset($gatewayParams['Local-Api-Admin-Username']) ? $gatewayParams['Local-Api-Admin-Username'] : '');
$Log_Enabled = FALSE;
if (isset($gatewayParams['Log-Enabled'])) {
	$Log_Enabled = ((strtolower($gatewayParams['Log-Enabled']) == 'on') ? TRUE : FALSE);
}
$PaymentCheck_Enabled = (isset($gatewayParams['PaymentCheck-Enabled']) ? $gatewayParams['PaymentCheck-Enabled'] : '');
$PaymentCheck_Enabled = ((strtolower($PaymentCheck_Enabled) === strtolower('on')) ? TRUE : FALSE);
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}
$Environment = (isset($gatewayParams['Environment']) ? $gatewayParams['Environment'] : 'sandbox'); // Let sandbox as default
if (is_string($Environment)) {
	if (strtolower($Environment) === strtolower('live')) {
		// Gateway Configuration Parameters
		$Whmcs_Merchant = array(
			'mallid'			=> (isset($gatewayParams['MallId_Live']) ? $gatewayParams['MallId_Live'] : ''),
			'shopname'			=> (isset($gatewayParams['ShopName_Live']) ? $gatewayParams['ShopName_Live'] : ''),
			'chainmerchant'		=> (isset($gatewayParams['ChainMerchant_Live']) ? $gatewayParams['ChainMerchant_Live'] : ''),
			'sharedkey'			=> (isset($gatewayParams['SharedKey_Live']) ? $gatewayParams['SharedKey_Live'] : ''),
		);
	} else {
		// Gateway Configuration Parameters
		$Whmcs_Merchant = array(
			'mallid'			=> (isset($gatewayParams['MallId']) ? $gatewayParams['MallId'] : ''),
			'shopname'			=> (isset($gatewayParams['ShopName']) ? $gatewayParams['ShopName'] : ''),
			'chainmerchant'		=> (isset($gatewayParams['ChainMerchant']) ? $gatewayParams['ChainMerchant'] : ''),
			'sharedkey'			=> (isset($gatewayParams['SharedKey']) ? $gatewayParams['SharedKey'] : ''),
		);
	}
} else {
	// Gateway Configuration Parameters
	$Whmcs_Merchant = array(
		'mallid'			=> (isset($gatewayParams['MallId']) ? $gatewayParams['MallId'] : ''),
		'shopname'			=> (isset($gatewayParams['ShopName']) ? $gatewayParams['ShopName'] : ''),
		'chainmerchant'		=> (isset($gatewayParams['ChainMerchant']) ? $gatewayParams['ChainMerchant'] : ''),
		'sharedkey'			=> (isset($gatewayParams['SharedKey']) ? $gatewayParams['SharedKey'] : ''),
	);
}
$DokuConfigs = array(
	'isofile'		=> 'dokuhosted/assets/iso3166.json',
	'merchant'		=> $Whmcs_Merchant,
	'endpoint'		=> (is_string($Environment) ? strtolower($Environment) : 'sandbox'), // sandbox as default
);
if (class_exists('dokuhosted_DokuPayment')) {
	$DokuPayment = new dokuhosted_DokuPayment($DokuConfigs);
} else {
	require_once($dokulib);
	$DokuPayment = new dokuhosted_DokuPayment($DokuConfigs);
}
// Getting URL Querystring
$doku_get = dokuhosted_DokuPayment::_GET();
// Make Global @CallbackPage
$CallbackPage = (isset($doku_get['page']) ? $doku_get['page'] : 'notify'); // notify as default-callback-page
$CallbackPage = (is_string($CallbackPage) ? strtolower($CallbackPage) : 'notify');
/*
***************************************************************************
*
* IF IPN : $doku_ipn_params
* IF Redirect : $doku_redirect_params
*
***************************************************************************
*/
$REQUEST_METHOD = (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'HEAD');
$REQUEST_METHOD_MSG = "";
# Get Doku IPN Params:
$doku_ipn_params = $DokuPayment->doku_incoming_callback();
if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $doku_ipn_params, "({$REQUEST_METHOD}) Incoming Callback Params"); }
# Get Doku Redirect Params from Request-Uri
$doku_redirect_params = dokuhosted_DokuPayment::get_query_string();
################################
//------------------------------
// Global Validation
$doku_error = FALSE;
$doku_error_msg = array();
// Make GLOBAL @success
$success = FALSE;
//------------------------------
// Make GLOBAL @checkCbTransID
$checkCbTransID = FALSE;
// Previously get 500 Internal Error for this transaction: checkCbTransID($transactionId);
// Make GLOBAL @XMLPaymentStatus
$XMLPaymentStatus = array();
if ($EDU_Enabled > 0) {
	$XMLPaymentStatus['edu-enabled'] = $EDU_Enabled;
} else {
	$XMLPaymentStatus['edu-enabled'] = 0;
}
// Make GLOBAL @StringVoidStatus
$StringVoidStatus = "";
/*
// Use localAPI to handle and redirect user
*/
$localApi = array(
	'command' 	=> 'GetInvoice', //'GetOrders',
	'data'		=> array(
		//'id' 				=> '', // Later updated on review case
		'invoiceid' 		=> '', # Later updated on review case
	),
	'username'	=> $LocalApiAdminUsername, // Optional for WHMCS 7.2 and later
);
//------------------------------
################################
switch (strtolower($CallbackPage)) {
	//------------------
	// Debug Invoice
	//------------------
	case 'debug':
		$invoiceId = (isset($doku_redirect_params['id']) ? $doku_redirect_params['id'] : '');
		$invoiceId = (int)$invoiceId;
		$cbInvoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['paymentmethod']);
		if ($invoiceId) {
			echo "<pre>";
			echo "{$invoiceId}";
			echo "<hr/>";
			echo $cbInvoiceId;
			echo "<hr/>";
			if (isset($localApi['data']['id'])) {
				unset($localApi['data']['id']);
			}
			$data = array(
				//'id' 				=> $invoiceId,
				'invoiceid' 		=> $invoiceId,
			);
			$InvoiceData = localAPI('GetInvoice', $data, $localApi['username']);
			print_r($InvoiceData);
			$data['id'] = (isset($InvoiceData['userid']) ? $InvoiceData['userid'] : '');
			//(isset($InvoiceData['userid']) ? $InvoiceData['userid'] : '');
			$data['customtype'] = 'general';
			$data['customsubject'] = 'Payment With Doku Merchant';
			$data['custommessage'] = '<table class="table table-info" style="border: 1px solid #cccccc; width: 100%;" border="0" cellspacing="2" cellpadding="4"><thead><tr><th colspan="2">Detail Virtual Account untuk Pembayaran Pesanan Anda</th></tr></thead><tbody><tr><th style="font-weight: bold; border-bottom: 1px solid #ccc;">{$transaction_va_channel_name}</th></tr></tbody><tbody><tr><td>Nomor Referensi</td><td>{$transaction_va_reference}</td></tr><tr><td>ID Transaksi</td><td>{$transaction_va_reference}</td></tr><tr><td>Nomor Virtual Account</td><td>{$transaction_va_code}</td></tr><tr><td>Jumlah</td><td>{$transaction_va_amount}</td></tr><tr><td>Tanggal Expired</td><td>{$transaction_va_duedate}</td></tr></tbody><tfoot><tr><td colspan="2"><div class="links"><a href="{$transaction_va_link}">{$transaction_va_link}</a></div></td></tr></tfoot></table>';
			$data['customvars'] = base64_encode(serialize($params_input));
			$sendEmail = localAPI('SendEmail', $data, $localApi['username']);
			print_r($sendEmail);
			
			
			$data['clientid'] = (isset($InvoiceData['userid']) ? $InvoiceData['userid'] : '');
			$data['stats'] = true;
			$userData = localAPI('GetClientsDetails', $data, $localApi['username']);
			//$orderData = mysql_fetch_assoc(select_query('tblorders', 'userid,id,paymentmethod,orderdata', ["invoiceid" => $invoiceId]));
			print_r($userData);
		}
		exit;
	break;
	case 'identify':
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			if (count($doku_ipn_params['body']) > 0) {
				# DOKU Identify
				$dokuparams = array(
					'TRANSIDMERCHANT'			=> (isset($doku_ipn_params['body']['TRANSIDMERCHANT']) ? $doku_ipn_params['body']['TRANSIDMERCHANT'] : ''),
					'PURCHASECURRENCY'			=> (isset($doku_ipn_params['body']['PURCHASECURRENCY']) ? $doku_ipn_params['body']['PURCHASECURRENCY'] : ''),
					'CURRENCY'					=> (isset($doku_ipn_params['body']['CURRENCY']) ? $doku_ipn_params['body']['CURRENCY'] : ''),
					'PAYMENTCHANNEL'			=> (isset($doku_ipn_params['body']['PAYMENTCHANNEL']) ? $doku_ipn_params['body']['PAYMENTCHANNEL'] : ''),
					'PAYMENTCODE'				=> (isset($doku_ipn_params['body']['PAYMENTCODE']) ? $doku_ipn_params['body']['PAYMENTCODE'] : ''),
					'AMOUNT'					=> (isset($doku_ipn_params['body']['AMOUNT']) ? $doku_ipn_params['body']['AMOUNT'] : ''),
					'SESSIONID'					=> (isset($doku_ipn_params['body']['SESSIONID']) ? $doku_ipn_params['body']['SESSIONID'] : ''),
				);
				if ($Identify_Enabled) {
					if ($Log_Enabled) {
						logTransaction($gatewayParams['paymentmethod'], $dokuparams, "Payment Identify");
					}
				}
				$REQUEST_METHOD_MSG = "IDENTIFY:Continue";
			} else {
				$REQUEST_METHOD_MSG =  "STOP : BODY Have no content - Identify";
			}
		} else {
			$REQUEST_METHOD_MSG = "STOP : POST Method Required - Identify";
		}
	break;
	case 'review':
		$dokuparams = array();
		$REQUEST_METHOD_MSG = "Review Page - Redirect to main-page";
		// Get from Querystring
		$dokuparams['TRANSIDMERCHANT'] = (isset($doku_redirect_params['TRANSIDMERCHANT']) ? $doku_redirect_params['TRANSIDMERCHANT'] : '');
		// Get from Postbody
		if (count($doku_ipn_params['body']) > 0) {
			$dokuparams['TRANSIDMERCHANT'] = (isset($doku_ipn_params['body']['TRANSIDMERCHANT']) ? $doku_ipn_params['body']['TRANSIDMERCHANT'] : $dokuparams['TRANSIDMERCHANT']);
		}
		$transaction_id_part = substr($dokuparams['TRANSIDMERCHANT'], 0, 14);
		/*
		if (!$doku_error) {
			try {
				$transaction_id_part = date_create_from_format('YmdHis', $transaction_id_part);
			} catch (Exception $ex) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Exception error of date-created from form: {$ex->getMessage()}.";
			}
		}
		if (!$doku_error) {
			if (!strtotime(date_format($transaction_id_part, 'Y-m-d H:i:s'))) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Transaction id part not in Dateformat structured.";
			}
		}
		if (!$doku_error) {
			$transaction_id_part = date_format($transaction_id_part, 'YmdHis');
			$merchant_transaction = explode("{$transaction_id_part}", $dokuparams['TRANSIDMERCHANT']);
			if (!isset($merchant_transaction[1])) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : There is no Transaction-id from IPN Callback as expected: #DATETIME#TRANSID.";
			}
		}
		*/
		if (!$doku_error) {
			$merchant_transaction = array(
				0 => FALSE,
				1 => $dokuparams['TRANSIDMERCHANT'],
			);
			$invoiceId = trim($merchant_transaction[1]);
			/**
			 * Validate Callback Invoice ID.
			 *
			 * Checks invoice ID is a valid invoice number. Note it will count an
			 * invoice in any status as valid.
			 *
			 * Performs a die upon encountering an invalid Invoice ID.
			 *
			 * Returns a normalised invoice ID.
			 *
			 * @param int $invoiceId Invoice ID
			 * @param string $gatewayName Gateway Name
			**/
			$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['paymentmethod']);
			if (!$invoiceId) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Invoice Id is not found.";
				if ($Log_Enabled) {
					logTransaction($gatewayParams['paymentmethod'], $doku_error_msg, "InvoiceId Not Found");
				}
			}
		}
		if (!$doku_error) {
			$localApi['command'] = 'GetInvoice';
			if (isset($localApi['data']['id'])) {
				unset($localApi['data']['id']);
			}
			$localApi['data']['invoiceid'] = $invoiceId;
			$InvoiceData = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
			$Redirect_Url = "";
			if (isset($InvoiceData['invoiceid'])) {
				if (intval($InvoiceData['invoiceid']) > 0) {
					$GetConfigurationValue = localAPI('GetConfigurationValue', array('setting' => 'SystemURL'), $localApi['username']);
					$Redirect_Url .= (isset($GetConfigurationValue['value']) ? $GetConfigurationValue['value'] : '');
					$Redirect_Url .= ((substr($systemUrl, -1) == '/') ? '' : '/');
					$Redirect_Url .= "viewinvoice.php?id={$invoiceId}";
					header("HTTP/1.1 301 Moved Permanently");
					header("Location: {$Redirect_Url}");
					exit;
				}
			} else {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Local API Result not get invoiceid while review of InvoiceData from GetInvoice().";
			}
		}
	break;
	case 'notify':
	default:
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			if (count($doku_ipn_params['body']) > 0) {
				# DOKU Notify
				$dokuparams = array(
					'PAYMENTDATETIME'			=> (isset($doku_ipn_params['body']['PAYMENTDATETIME']) ? $doku_ipn_params['body']['PAYMENTDATETIME'] : ''),
					'PURCHASECURRENCY' 			=> (isset($doku_ipn_params['body']['PURCHASECURRENCY']) ? $doku_ipn_params['body']['PURCHASECURRENCY'] : ''),
					'LIABILITY'					=> (isset($doku_ipn_params['body']['LIABILITY']) ? $doku_ipn_params['body']['LIABILITY'] : ''),
					'PAYMENTCHANNEL'			=> (isset($doku_ipn_params['body']['PAYMENTCHANNEL']) ? $doku_ipn_params['body']['PAYMENTCHANNEL'] : ''),
					'AMOUNT'					=> (isset($doku_ipn_params['body']['AMOUNT']) ? $doku_ipn_params['body']['AMOUNT'] : ''),
					'PAYMENTCODE'				=> (isset($doku_ipn_params['body']['PAYMENTCODE']) ? $doku_ipn_params['body']['PAYMENTCODE'] : ''),
					'MCN'						=> (isset($doku_ipn_params['body']['MCN']) ? $doku_ipn_params['body']['MCN'] : ''),
					'WORDS'						=> (isset($doku_ipn_params['body']['WORDS']) ? $doku_ipn_params['body']['WORDS'] : ''),
					'RESULTMSG'					=> (isset($doku_ipn_params['body']['RESULTMSG']) ? $doku_ipn_params['body']['RESULTMSG'] : ''),
					'VERIFYID'					=> (isset($doku_ipn_params['body']['VERIFYID']) ? $doku_ipn_params['body']['VERIFYID'] : ''),
					'TRANSIDMERCHANT'			=> (isset($doku_ipn_params['body']['TRANSIDMERCHANT']) ? $doku_ipn_params['body']['TRANSIDMERCHANT'] : ''),
					'BANK'						=> (isset($doku_ipn_params['body']['BANK']) ? $doku_ipn_params['body']['BANK'] : ''),
					'STATUSTYPE'				=> (isset($doku_ipn_params['body']['STATUSTYPE']) ? $doku_ipn_params['body']['STATUSTYPE'] : ''),
					'APPROVALCODE'				=> (isset($doku_ipn_params['body']['APPROVALCODE']) ? $doku_ipn_params['body']['APPROVALCODE'] : ''),
					'EDUSTATUS'					=> (isset($doku_ipn_params['body']['EDUSTATUS']) ? $doku_ipn_params['body']['EDUSTATUS'] : ''),
					'THREEDSECURESTATUS'		=> (isset($doku_ipn_params['body']['THREEDSECURESTATUS']) ? $doku_ipn_params['body']['THREEDSECURESTATUS'] : ''),
					'VERIFYSCORE'				=> (isset($doku_ipn_params['body']['VERIFYSCORE']) ? $doku_ipn_params['body']['VERIFYSCORE'] : ''),
					'CURRENCY'					=> (isset($doku_ipn_params['body']['CURRENCY']) ? $doku_ipn_params['body']['CURRENCY'] : ''),
					'RESPONSECODE'				=> (isset($doku_ipn_params['body']['RESPONSECODE']) ? $doku_ipn_params['body']['RESPONSECODE'] : ''),
					'CHNAME'					=> (isset($doku_ipn_params['body']['CHNAME']) ? $doku_ipn_params['body']['CHNAME'] : ''),
					'BRAND'						=> (isset($doku_ipn_params['body']['BRAND']) ? $doku_ipn_params['body']['BRAND'] : ''),
					'VERIFYSTATUS'				=> (isset($doku_ipn_params['body']['VERIFYSTATUS']) ? $doku_ipn_params['body']['VERIFYSTATUS'] : ''),
					'SESSIONID'					=> (isset($doku_ipn_params['body']['SESSIONID']) ? $doku_ipn_params['body']['SESSIONID'] : ''),
				);
				$REQUEST_METHOD_MSG = "NOTIFY:Continue";
			} else {
				$REQUEST_METHOD_MSG = "STOP : BODY Have no content";
			}
		} else {
			$REQUEST_METHOD_MSG = "STOP : POST Method Required";
		}
	break;
	case 'redirect':
		# DOKU Params (GET)
		$dokuparams = array(
			'PAYMENTDATETIME'			=> (isset($doku_redirect_params['PAYMENTDATETIME']) ? $doku_redirect_params['PAYMENTDATETIME'] : ''),
			'PURCHASECURRENCY' 			=> (isset($doku_redirect_params['PURCHASECURRENCY']) ? $doku_redirect_params['PURCHASECURRENCY'] : ''),
			'LIABILITY'					=> (isset($doku_redirect_params['LIABILITY']) ? $doku_redirect_params['LIABILITY'] : ''),
			'PAYMENTCHANNEL'			=> (isset($doku_redirect_params['PAYMENTCHANNEL']) ? $doku_redirect_params['PAYMENTCHANNEL'] : ''),
			'AMOUNT'					=> (isset($doku_redirect_params['AMOUNT']) ? $doku_redirect_params['AMOUNT'] : ''),
			'PAYMENTCODE'				=> (isset($doku_redirect_params['PAYMENTCODE']) ? $doku_redirect_params['PAYMENTCODE'] : ''),
			'MCN'						=> (isset($doku_redirect_params['MCN']) ? $doku_redirect_params['MCN'] : ''),
			'WORDS'						=> (isset($doku_redirect_params['WORDS']) ? $doku_redirect_params['WORDS'] : ''),
			'RESULTMSG'					=> (isset($doku_redirect_params['RESULTMSG']) ? $doku_redirect_params['RESULTMSG'] : ''),
			'VERIFYID'					=> (isset($doku_redirect_params['VERIFYID']) ? $doku_redirect_params['VERIFYID'] : ''),
			'TRANSIDMERCHANT'			=> (isset($doku_redirect_params['TRANSIDMERCHANT']) ? $doku_redirect_params['TRANSIDMERCHANT'] : ''),
			'BANK'						=> (isset($doku_redirect_params['BANK']) ? $doku_redirect_params['BANK'] : ''),
			'STATUSTYPE'				=> (isset($doku_redirect_params['STATUSTYPE']) ? $doku_redirect_params['STATUSTYPE'] : ''),
			'APPROVALCODE'				=> (isset($doku_redirect_params['APPROVALCODE']) ? $doku_redirect_params['APPROVALCODE'] : ''),
			'EDUSTATUS'					=> (isset($doku_redirect_params['EDUSTATUS']) ? $doku_redirect_params['EDUSTATUS'] : ''),
			'THREEDSECURESTATUS'		=> (isset($doku_redirect_params['THREEDSECURESTATUS']) ? $doku_redirect_params['THREEDSECURESTATUS'] : ''),
			'VERIFYSCORE'				=> (isset($doku_redirect_params['VERIFYSCORE']) ? $doku_redirect_params['VERIFYSCORE'] : ''),
			'CURRENCY'					=> (isset($doku_redirect_params['CURRENCY']) ? $doku_redirect_params['CURRENCY'] : ''),
			'RESPONSECODE'				=> (isset($doku_redirect_params['RESPONSECODE']) ? $doku_redirect_params['RESPONSECODE'] : ''),
			'CHNAME'					=> (isset($doku_redirect_params['CHNAME']) ? $doku_redirect_params['CHNAME'] : ''),
			'BRAND'						=> (isset($doku_redirect_params['BRAND']) ? $doku_redirect_params['BRAND'] : ''),
			'VERIFYSTATUS'				=> (isset($doku_redirect_params['VERIFYSTATUS']) ? $doku_redirect_params['VERIFYSTATUS'] : ''),
			'SESSIONID'					=> (isset($doku_redirect_params['SESSIONID']) ? $doku_redirect_params['SESSIONID'] : ''),
			'STATUSCODE'				=> (isset($doku_redirect_params['STATUSCODE']) ? $doku_redirect_params['STATUSCODE'] : ''),
		);
		# DOKU Params (POST)
		if (count($doku_ipn_params['body']) > 0) {
			foreach ($doku_ipn_params['body'] as $key => $val) {
				if (!isset($dokuparams[$key])) {
					$dokuparams[$key] = $val;
				} else {
					if ($dokuparams[$key] != $val) {
						$dokuparams[$key] = $val;
					}
				}
			}
		}
		if (count($doku_redirect_params) > 0) {
			$REQUEST_METHOD_MSG = "REDIRECT:Continue";
		} else {
			$REQUEST_METHOD_MSG = "STOP : QUERYSTRING Have no content";
		}
	break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'notify':
	default:
		if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Notify From Doku'); }
	break;
	case 'redirect':
		if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Redirect From Doku'); }
	break;
	case 'review':
	case 'identify':
		// Nothing to do
	break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'review':
	case 'identify':
		echo "{$REQUEST_METHOD_MSG}";
	break;
	case 'notify':
	case 'redirect':
	default:
		//--------------------------------------------------------------------
		if (!$doku_error) {
			if (is_array($dokuparams['TRANSIDMERCHANT']) || is_object($dokuparams['TRANSIDMERCHANT'])) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Array or Object return for TRANSIDMERCHANT.";
			}
		}
		/*
		if (!$doku_error) {
			$transaction_id = $dokuparams['TRANSIDMERCHANT'];
			$transaction_id_part = substr($dokuparams['TRANSIDMERCHANT'], 0, 14);
			try {
				$transaction_id_part = date_create_from_format('YmdHis', $transaction_id_part);
			} catch (Exception $ex) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Exception error of date-created from form: {$ex->getMessage()}.";
			}
		}
		if (!$doku_error) {
			if (!strtotime(date_format($transaction_id_part, 'Y-m-d H:i:s'))) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Transaction id part not in Dateformat structured.";
			}
		}
		if (!$doku_error) {
			$transaction_id_part = date_format($transaction_id_part, 'YmdHis');
			$merchant_transaction = explode("{$transaction_id_part}", $dokuparams['TRANSIDMERCHANT']);
			if (!isset($merchant_transaction[1])) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : There is no Transaction-id from IPN Callback as expected: #DATETIME#TRANSID.";
			}
		}
		*/
		#########################################
		if (!$doku_error) {
			$merchant_transaction = array(
				0 => FALSE,
				1 => $dokuparams['TRANSIDMERCHANT'],
			);
			$invoiceId = trim($merchant_transaction[1]);
			/**
			 * Validate Callback Invoice ID.
			 *
			 * Checks invoice ID is a valid invoice number. Note it will count an
			 * invoice in any status as valid.
			 *
			 * Performs a die upon encountering an invalid Invoice ID.
			 *
			 * Returns a normalised invoice ID.
			 *
			 * @param int $invoiceId Invoice ID
			 * @param string $gatewayName Gateway Name
			**/
			$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['paymentmethod']);
			if (!$invoiceId) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Invoice Id is not found.";
				if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $doku_error_msg, "InvoiceId Not Found"); }
			}
		}
		########################################
		//--------------------------------------------------------------------
		if (!$doku_error) {
			$params_input = array();
			$params_input['amount'] = (isset($dokuparams['AMOUNT']) ? $dokuparams['AMOUNT'] : '0.00');
			$params_input['transaction_id'] = (isset($dokuparams['TRANSIDMERCHANT']) ? $dokuparams['TRANSIDMERCHANT'] : 0);
			$params_input['transaction_session'] = (isset($dokuparams['SESSIONID']) ? $dokuparams['SESSIONID'] : '');
			$params_input['transaction_currency'] = (isset($dokuparams['CURRENCY']) ? $dokuparams['CURRENCY'] : '360');
			$params_input['result_msg'] = (isset($dokuparams['RESULTMSG']) ? $dokuparams['RESULTMSG'] : ''); // SUCCESS | FAILED
			$params_input['verify_status'] = (isset($dokuparams['VERIFYSTATUS']) ? $dokuparams['VERIFYSTATUS'] : ''); // NA
			$params_input['words'] = (isset($dokuparams['WORDS']) ? $dokuparams['WORDS'] : '');
			// Make notify structured
			$CheckNotifyPaymentStructure = $DokuPayment->create_payment_structure('notify', 0, $params_input, $dokuparams);
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $CheckNotifyPaymentStructure, 'Payment Notify Structured: ' . (__LINE__)); }
			if (!isset($CheckNotifyPaymentStructure['WORDS'])) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Not get WORDS string from created notify structured.";
			}
		}
		if (!$doku_error) {
			if (!is_string($params_input['words'])) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Words should be a string.";
			}
		}
		//---------------------------------------------------------------------------------
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'review':
	case 'identify':
	case 'notify':
	case 'redirect':
	default:	
		// Make more @XMLPaymentStatus params
		if (count($dokuparams) > 0) {
			foreach ($dokuparams as $key => $val) {
				$key = preg_replace("/[^a-zA-Z0-9]+/", "", $key);
				if (!empty($key) || ($key != '')) {
					$XMLPaymentStatus[$key] = $val;
				}
			}
		}
		//------------------------------------
		$Redirect_Url = "/";
		if (!$doku_error) {
			If (isset($localApi['data']['id'])) {
				unset($localApi['data']['id']);
			}
			$localApi['data']['invoiceid'] = $invoiceId;
			//unset($localApi['data']['invoiceid']);
			//$localApi['data']['id'] = $invoiceId;
			//$InvoiceData = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
			// query localApi from invoiceid
			$InvoiceData = localAPI('GetInvoice', $localApi['data'], $localApi['username']);
			if (isset($InvoiceData['invoiceid']) && isset($InvoiceData['userid'])) {
				if (intval($InvoiceData['invoiceid']) > 0) {
					$GetConfigurationValue = localAPI('GetConfigurationValue', array('setting' => 'SystemURL'), $localApi['username']);
					$Redirect_Url = (isset($GetConfigurationValue['value']) ? $GetConfigurationValue['value'] : '');
					$Redirect_Url .= ((substr($systemUrl, -1) == '/') ? '' : '/');
					$Redirect_Url .= "viewinvoice.php?id={$invoiceId}";
				}
			} else {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Local API Result not get invoiceid of InvoiceData from GetInvoice().";
			}
		}
		//=========================
		// Send details to customer
		//=========================
		if (!$doku_error) {
			//----- Additional for Virtual Account Payment
			$params_input['transaction_va_channels'] = $DokuPayment->get_virtual_channels();
			$params_input['transaction_va_channel'] = (isset($doku_ipn_params['body']['PAYMENTCHANNEL']) ? $doku_ipn_params['body']['PAYMENTCHANNEL'] : '');
			$params_input['transaction_va_channel_code'] = (is_string($params_input['transaction_va_channel']) ? sprintf('%02s', $params_input['transaction_va_channel']) : '00');
			$params_input['transaction_va_channel_name'] = $DokuPayment->get_paymentchannel_name($params_input['transaction_va_channel_code']);
			$params_input['transaction_va_code'] = (isset($doku_ipn_params['body']['PAYMENTCODE']) ? $doku_ipn_params['body']['PAYMENTCODE'] : '');
			$params_input['transaction_va_reference'] = (isset($doku_ipn_params['body']['TRANSIDMERCHANT']) ? $doku_ipn_params['body']['TRANSIDMERCHANT'] : '');
			$params_input['transaction_va_amount'] = (isset($doku_ipn_params['body']['AMOUNT']) ? $doku_ipn_params['body']['AMOUNT'] : '');
			$params_input['transaction_va_amount'] = number_format($params_input['transaction_va_amount'], 2);
			$params_input['transaction_va_amount'] = "Rp. {$params_input['transaction_va_amount']}";
			$params_input['transaction_va_duedate'] = isset($InvoiceData['duedate']) ? $InvoiceData['duedate'] : '';
			$params_input['transaction_va_duedate'] .= " " . date('H:i:s', (strtotime($Datezone->format('Y-m-d H:i:s')) + 21600));
			$params_input['transaction_va_link'] = $Redirect_Url;
			// Trigger
			if (in_array($params_input['transaction_va_channel_code'], $params_input['transaction_va_channels'])) {
				switch (strtolower($params_input['transaction_va_channel_code'])) {
					case '31':
					case '35':
						$params_input['transaction_va_name'] = 'Nomor Kode Bayar';
					break;
					default:
						$params_input['transaction_va_name'] = 'Nomor Virtual Account';
					break;
				}
				try {
					//$data['messagename'] = 'DOKU_Payment';
					$data['id'] = (isset($InvoiceData['userid']) ? $InvoiceData['userid'] : '');
					$data['customtype'] = 'general';
					$data['customsubject'] = "Payment With Doku Merchant - {$params_input['transaction_va_channel_name']}";
					$data['custommessage'] = '<table class="table table-info" style="border: 1px solid #cccccc; width: 100%;" border="0" cellspacing="2" cellpadding="4"><thead><tr><th colspan="2">Detail Virtual Account untuk Pembayaran Pesanan Anda</th></tr></thead><tbody><tr><th style="font-weight: bold; border-bottom: 1px solid #ccc;">{$transaction_va_channel_name}</th></tr></tbody><tbody><tr><td>Nomor Referensi</td><td>{$transaction_va_reference}</td></tr><tr><td>ID Transaksi</td><td>{$transaction_va_reference}</td></tr><tr><td>' . $params_input['transaction_va_name'] . '</td><td>{$transaction_va_code}</td></tr><tr><td>Jumlah</td><td>{$transaction_va_amount}</td></tr><tr><td>Tanggal Expired</td><td>{$transaction_va_duedate}</td></tr></tbody><tfoot><tr><td colspan="2"><div class="links"><a href="{$transaction_va_link}">{$transaction_va_link}</a></div></td></tr></tfoot></table>';
					$data['customvars'] = base64_encode(serialize($params_input));
					$sendEmail = localAPI('SendEmail', $data, $localApi['username']);
				} catch (Exception $ex) {
					$doku_error = true;
					$doku_error_msg[] = "STOP : Send email details is going error with exception: {$ex->getMessage()}";
				}
			}
		}
		if (!$error) {
			/*
			$data['clientid'] = (isset($InvoiceData['userid']) ? $InvoiceData['userid'] : '');
			$data['stats'] = true;
			$userData = localAPI('GetClientsDetails', $data, $localApi['username']);
			if (!isset($userData['email'])) {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Cannot get userData Details from GetClientsDetails().";
			}
			*/
		}
		
		$orders_debug = array(
			'order_id'				=> 0,
			'order_num'				=> 0,
			'order_status'			=> '',
		);
		$order_id = 0;
		$order_num = 0;
		$order_status = '';
		if (!$doku_error) {
			if (isset($InvoiceData['invoiceid']) && isset($InvoiceData['invoicenum']) && isset($InvoiceData['status'])) {
				if (intval($InvoiceData['invoiceid']) > 0) {
					$order_id = $InvoiceData['invoiceid'];
					$orders_debug['order_id'] = $order_id;
				}
				if (intval($InvoiceData['invoicenum']) > 0) {
					$order_num = $InvoiceData['invoicenum'];
					$orders_debug['order_num'] = $order_num;
				}
				$order_status = (is_string($InvoiceData['status']) ? strtolower($InvoiceData['status']) : '');
				$orders_debug['order_status'] = $order_status;
			} else {
				$doku_error = true;
				$doku_error_msg[] = "STOP : Local API Result for GetInvoice() not get proper data as expected.";
			}
		}
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'review':
	case 'identify':
		die();
		break;
	case 'notify':
	default:
		// Only for Notify
		if (strtolower($CallbackPage) === strtolower('notify')) {
			if (!$doku_error) {
				//-----------------------------------------
				// check if WORDS is match or not
				//-----------------------------------------
				if (strtolower($CheckNotifyPaymentStructure['WORDS']) !== strtolower($params_input['words'])) {
					$doku_error = true;
					$doku_error_msg[] = "STOP : Words from Notify-Structured not same with Dokuparams";
				}
			}
		}
		//------------------------------------------------------------------------------------------
		if (!$doku_error) {
			//---------------------------------
			// IMPORTANT GLOBAL VARS
			//---------------------------------
			$invoiceId = (isset($invoiceId) ? $invoiceId : '');
			//$transactionId = (isset($dokuparams['APPROVALCODE']) ? $dokuparams['APPROVALCODE'] : '');
			$transactionId = $invoiceId;
			/**
			 * Adding Unique generated Invoice-Id to Transaction-Id
			 * Got bugs: DOKU always send:
			 * Constant APPROVALCODE for first time Notify (DOKU Always send same Approval-code for each invoice-id)
			 * Using @$transaction_id_part
			*/
			$transactionId .= "_";
			if (isset($dokuparams['TRANSIDMERCHANT'])) {
				$explode_transid = explode("{$invoiceId}_", $dokuparams['TRANSIDMERCHANT']);
				if (isset($explode_transid[1])) {
					$transactionId .= sprintf("%s", $explode_transid[1]);
				}
			}
			//$transactionId .= (isset($transaction_id_part) ? $transaction_id_part : '');
			//$transactionId .= (isset($dokuparams['APPROVALCODE']) ? $dokuparams['APPROVALCODE'] : '');
			$paymentAmount = (isset($dokuparams['AMOUNT']) ? $dokuparams['AMOUNT'] : 0);
			$paymentFee = (isset($dokuparams['FEE']) ? $dokuparams['FEE'] : 0);
			$paymentEDU = (isset($dokuparams['EDUSTATUS']) ? $dokuparams['EDUSTATUS'] : 'NA'); // NA (Default)
			#
			$paymentResponseStatus = (isset($dokuparams['RESULTMSG']) ? $dokuparams['RESULTMSG'] : ''); // SUCCESS, VOIDED, FAILED
			$paymentResponseCode = (isset($dokuparams['RESPONSECODE']) ? $dokuparams['RESPONSECODE'] : ''); // 0000 (Success), Other is failed
			switch (strtoupper($paymentResponseStatus)) {
				case 'SUCCESS':
					$success = TRUE;
					if ($paymentResponseCode == '0000') {
						$success = TRUE;
					} else {
						$success = FALSE;
						$doku_error = true;
						$doku_error_msg[] = "Payment Response Code is Not : 0000 from Payment-Notify.";
					}
				break;
				case 'VOIDED':
					if ($paymentResponseCode == '0000') {
						$success = TRUE;
					} else {
						$success = FALSE;
						$doku_error = true;
						$doku_error_msg[] = "STOP : Payment Response Code is Not : 0000.";
					}
				break;
				case 'FAILED':
					$success = FALSE;
					$doku_error = true;
					$doku_error_msg[] = "STOP : Payment Response Status is not Success: {$paymentResponseStatus}.";
				break;
				default:
					$success = FALSE;
					$doku_error = true;
					$doku_error_msg[] = "STOP : Un-Expected Payment-Response-Status: (" . (__LINE__) . ") {$paymentResponseStatus}";
				break;
			}
		}
		break;
	case 'redirect':
		//-------------------------------------------------------------------------------------------------------------
		/**
		 * Check Status Payment: Call DOKU
		 * Applied only for Redirect-Page not for First Time Notify
		 * ********************************************************
		*/
		//-------------------------------------------------------------------------------------------------------------
		if ($PaymentCheck_Enabled) {
			if (strtolower($order_status) !== strtolower('paid')) {
				// Should call to redirect-page
				//***********************************************************************
				if (!$doku_error) {
					// Make checking payment structured
					# add puchase-currency
					$params_input['transaction_currency_purchase'] = (isset($dokuparams['PURCHASECURRENCY']) ? $dokuparams['PURCHASECURRENCY'] : '360');
					$CheckStatusPaymentStructure = $DokuPayment->create_payment_structure('check', 0, $params_input, $dokuparams);
					$headers = $DokuPayment->create_curl_headers($DokuPayment->dokupayment_headers);
					if (isset($CheckStatusPaymentStructure['WORDS_STRING'])) {
						unset($CheckStatusPaymentStructure['WORDS_STRING']);
					}
					try {
						$payment_status = $DokuPayment->create_curl_request('POST', $DokuPayment->endpoint['checkstatus'], 'API.Context (http://whmcs.alumniparhata.org)', $headers, $CheckStatusPaymentStructure, 30);
					} catch (Exception $ex) {
						$doku_error = true;
						$doku_error_msg[] = "STOP : Exception Error: {$ex->getMessage()}.";
						throw $ex;
					}
				}
				if (!$doku_error) {
					if (!isset($payment_status['response']['body'])) {
						$doku_error = true;
						$doku_error_msg[] = "STOP : There is no body from Doku check -payment-status.";
					}
				}
				// Format XML to Array
				if (!$doku_error) {
					# Log payment-status Body
					//logTransaction($gatewayParams['paymentmethod'], $payment_status['response']['body'], 'Payment Status XML:' . (__LINE__));
					try {
						$payment_status = $DokuPayment->xmltoarray($payment_status['response']['body']);
					} catch (Exception $ex) {
						$doku_error = true;
						$doku_error_msg[] = "STOP : Exception Error while render xml format to array: {$ex->getMessage()}.";
						throw $ex;
					}
				}
				if (!$doku_error) {
					if (!isset($payment_status['PAYMENT_STATUS'])) {
						$doku_error = true;
						$doku_error_msg[] = "STOP : XML Format is not-expected.";
					}
				}
				if (!$doku_error) {
					if (count($payment_status['PAYMENT_STATUS']) > 0) {
						foreach ($payment_status['PAYMENT_STATUS'] as $key => $val) {
							$XMLPaymentStatus[$key] = (isset($val['value']) ? $val['value'] : '');
						}
					} else {
						$doku_error = true;
						$doku_error_msg[] = "STOP : Count payment-status not have an array.";
					}
				}
				if (!$doku_error) {
					// Make checking redirect-payment structured
					# add transaction-words, transaction-status, transaction-code
					$params_input['transaction_words'] = (isset($dokuparams['WORDS']) ? $dokuparams['WORDS'] : '');
					$params_input['transaction_status'] = (isset($dokuparams['STATUSCODE']) ? strval($dokuparams['STATUSCODE']) : ''); // 0000 = Success, Other = Failed
					$params_input['transaction_channel'] = (isset($dokuparams['PAYMENTCHANNEL']) ? $dokuparams['PAYMENTCHANNEL'] : '');
					$CheckRedirectPaymentStructure = $DokuPayment->create_payment_structure('redirect', 0, $params_input, $dokuparams);
					if (strtolower($CallbackPage) === strtolower('redirect')) {
						//-----------------------------------------
						// check if WORDS is match or not (On 'redirect' stage)
						//-----------------------------------------
						if (strtolower($params_input['transaction_words']) !== strtolower($CheckRedirectPaymentStructure['WORDS'])) {
							$doku_error = true;
							$doku_error_msg[] = "STOP : Words from Redirect-Structured not same with Dokuparams.<br/>\n";
							$doku_error_msg[] .= "Input words: {$params_input['transaction_words']}<br/>\n";
							$doku_error_msg[] .= "Structured words: {$CheckRedirectPaymentStructure['WORDS']}<br/>\n";
							/*
							$doku_error_msg['structured'] = $CheckRedirectPaymentStructure;
							$doku_error_msg['input'] = $params_input;
							$doku_error_msg['dokuparams'] = $dokuparams;
							*/
						}
					}
				}
				if (!$doku_error) {
					//---------------------------------
					// IMPORTANT GLOBAL VARS
					//---------------------------------
					$invoiceId = (isset($invoiceId) ? $invoiceId : '');
					#$transactionId = (isset($XMLPaymentStatus['APPROVALCODE']) ? $XMLPaymentStatus['APPROVALCODE'] : '');
					$transactionId = $invoiceId;
					//
					// Adding Unique generated Invoice-Id to Transaction-Id
					// Got bugs: DOKU always send:
					// Constant APPROVALCODE for first time Notify (DOKU Always send same Approval-code foe each invoice-id)
					// Using @$merchant_transaction[0]
					//
					$transactionId .= "_";
					if (isset($dokuparams['TRANSIDMERCHANT'])) {
						$explode_transid = explode("{$invoiceId}_", $dokuparams['TRANSIDMERCHANT']);
						if (isset($explode_transid[1])) {
							$transactionId .= sprintf("%s", $explode_transid[1]);
						}
					}
					//$transactionId .= (isset($transaction_id_part) ? $transaction_id_part : '');
					//$transactionId .= (isset($XMLPaymentStatus['APPROVALCODE']) ? $XMLPaymentStatus['APPROVALCODE'] : '');
					$paymentAmount = (isset($XMLPaymentStatus['AMOUNT']) ? $XMLPaymentStatus['AMOUNT'] : 0);
					$paymentFee = (isset($XMLPaymentStatus['FEE']) ? $XMLPaymentStatus['FEE'] : 0);
					$paymentEDU = (isset($XMLPaymentStatus['EDUSTATUS']) ? $XMLPaymentStatus['EDUSTATUS'] : 'NA'); // NA (Default)
					#
					$paymentResponseStatus = (isset($XMLPaymentStatus['RESULTMSG']) ? $XMLPaymentStatus['RESULTMSG'] : ''); // SUCCESS, VOIDED, FAILED
					$paymentResponseCode = (isset($XMLPaymentStatus['RESPONSECODE']) ? $XMLPaymentStatus['RESPONSECODE'] : ''); // 0000 (Success), Other is failed
				}
				if (!$doku_error) {
					// Log payment-status
					if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $XMLPaymentStatus, "(#{$invoiceId}) " . 'Payment Status Array:' . (__LINE__)); }
					/*
					if ($checkCbTransID) {
						$doku_error = true;
						$doku_error_msg[] = "checkCbTransID() Return True: Maybe duplicated transaction.";
					}
					*/
				}
			//***********************************************************************
			} else {
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: {$Redirect_Url}");
				exit; 
			}
		} else {
			if (!$doku_error) {
				// Make checking redirect-payment structured
				# add transaction-words, transaction-status, transaction-code
				$params_input['transaction_words'] = (isset($dokuparams['WORDS']) ? $dokuparams['WORDS'] : '');
				$params_input['transaction_status'] = (isset($dokuparams['STATUSCODE']) ? strval($dokuparams['STATUSCODE']) : ''); // 0000 = Success, Other = Failed
				$params_input['transaction_channel'] = (isset($dokuparams['PAYMENTCHANNEL']) ? $dokuparams['PAYMENTCHANNEL'] : '');
				$CheckRedirectPaymentStructure = $DokuPayment->create_payment_structure('redirect', 0, $params_input, $dokuparams);
				if (strtolower($CallbackPage) === strtolower('redirect')) {
					//-----------------------------------------
					// check if WORDS is match or not (On 'redirect' stage)
					//-----------------------------------------
					if (strtolower($params_input['transaction_words']) !== strtolower($CheckRedirectPaymentStructure['WORDS'])) {
						$doku_error = true;
						$doku_error_msg[] = "STOP : Words from Redirect-Structured not same with Dokuparams. (" . (__LINE__) . ")";
					}
				}
			}
			if (!$doku_error) {
				if (strtolower($order_status) !== strtolower('paid')) {
					//---------------------------------
					// IMPORTANT GLOBAL VARS
					//---------------------------------
					$invoiceId = (isset($invoiceId) ? $invoiceId : '');
					$transactionId = $invoiceId;
					//
					// Adding Unique generated Invoice-Id to Transaction-Id
					// Got bugs: DOKU always send:
					// Constant APPROVALCODE for first time Notify (DOKU Always send same Approval-code foe each invoice-id)
					// Using @$merchant_transaction[0]
					//
					$transactionId .= "_";
					if (isset($dokuparams['TRANSIDMERCHANT'])) {
						$explode_transid = explode("{$invoiceId}_", $dokuparams['TRANSIDMERCHANT']);
						if (isset($explode_transid[1])) {
							$transactionId .= sprintf("%s", $explode_transid[1]);
						}
					}
					//$transactionId .= (isset($transaction_id_part) ? $transaction_id_part : '');
					//$transactionId .= (isset($dokuparams['APPROVALCODE']) ? $dokuparams['APPROVALCODE'] : '');
					$paymentAmount = (isset($dokuparams['AMOUNT']) ? $dokuparams['AMOUNT'] : 0);
					$paymentFee = (isset($dokuparams['FEE']) ? $dokuparams['FEE'] : 0);
					$paymentEDU = (isset($dokuparams['EDUSTATUS']) ? $dokuparams['EDUSTATUS'] : 'NA'); // NA (Default)
					#
					$paymentResponseStatus = (isset($dokuparams['RESULTMSG']) ? $dokuparams['RESULTMSG'] : ''); // SUCCESS, VOIDED, FAILED
					$paymentResponseCode = (isset($dokuparams['RESPONSECODE']) ? $XMLPaymentStatus['RESPONSECODE'] : $params_input['transaction_status']); // 0000 (Success), Other is failed
				} else {
					header("HTTP/1.1 301 Moved Permanently");
					header("Location: {$Redirect_Url}");
					exit;
				}
			}
			if (!$doku_error) {
				// Log payment-status
				if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $dokuparams, "(#{$invoiceId}) " . 'Payment Status From Redirected:' . (__LINE__)); }
				if (strtolower($order_status) !== strtolower('paid')) {
					// Make Error if Redirected from Un-Success Payment
					$doku_error = true;
					$doku_error_msg[] = "STOP : Redirected from failed payment: Maybe WHMCS Payment-Gateway-Module not activate payment-check status.";
				}
			}
		}
		//-------------------------------------------------------------------------------------------------------------
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'review':
	case 'identify':
		die();
		break;
	case 'notify':
	case 'redirect':
	default:
		if (!$doku_error) {
			$transactionStatus = ($success ? 'Success' : 'Failure');
			//-------------------------------------------------------------------------------------------------------------------
			// EDU Enabled?
			//-------------------------------------------------------------------------------------------------------------------
			$transactionEdu = "EDU:APPROVE"; // DEFAULT: For not-enabled EDU
			if ($success) {
				if ($EDU_Enabled > 0) {
					switch (strtoupper($paymentEDU)) {
						case 'APPROVE':
							$success = TRUE;
							$transactionEdu = 'EDU:APPROVE';
							// Status: Success
						break;
						case 'REJECT':
							$success = FALSE;
							$transactionEdu = 'EDU:REJECT';
							// Status: Failed
						break;
						case 'NA':
							$success = FALSE;
							$transactionEdu = "EDU:NA";
							// Status: Pending/Waiting
						break;
						default:
							$success = FALSE;
							$transactionEdu = "EDU:{$paymentEDU}";
							// Status: Un-Expected
						break;
					}
				}
				if ($transactionEdu == 'EDU:APPROVE') {
					$transactionStatus = 'Success';
				} else {
					$transactionStatus = 'Pending';
				}
			}
		}
		#############################################################################################################
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'review':
	case 'identify':
		die();
		break;
	case 'redirect':
	case 'notify':
	default:
		if (!$doku_error) {
			if (strtolower($order_status) !== strtolower('paid')) {
				//---------------------------------
				/**
				 * Check Callback Transaction ID.
				 *
				 * Performs a check for any existing transactions with the same given
				 * transaction number.
				 *
				 * Performs a die upon encountering a duplicate.
				 *
				 * @param string $transactionId Unique Transaction ID
				*/
				checkCbTransID($transactionId);
			}
		}
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'review':
	case 'identify':
		die();
		break;
	case 'redirect':
		#############################################################################################################
		# CALL VOID?
		#############################################################################################################
		//-------------------------------------------------------------------------------------------------------------------
		if ($Void_Enabled) {
			if (!$doku_error) {
				$VoidParams = array(
					'transaction_id'		=> (isset($XMLPaymentStatus['TRANSIDMERCHANT']) ? $XMLPaymentStatus['TRANSIDMERCHANT'] : ''),
					'transaction_session'	=> (isset($XMLPaymentStatus['SESSIONID']) ? $XMLPaymentStatus['SESSIONID'] : ''),
					'transaction_currency'	=> (isset($XMLPaymentStatus['CURRENCY']) ? $XMLPaymentStatus['CURRENCY'] : '360'),
					'transaction_channel'	=> (isset($XMLPaymentStatus['PAYMENTCHANNEL']) ? $XMLPaymentStatus['PAYMENTCHANNEL'] : ''),
				);
				$createVoidStructure = $DokuPayment->create_payment_structure('void', 0, $VoidParams, $dokuparams);
				if (isset($createVoidStructure['WORDS_STRING'])) {
					unset($createVoidStructure['WORDS_STRING']);
				}
				$headers = $DokuPayment->create_curl_headers($DokuPayment->dokupayment_headers);
				try {
					$create_void = $DokuPayment->create_curl_request('POST', $DokuPayment->endpoint['void'], 'API.Context (http://whmcs.alumniparhata.org) - Change this as you wish.', $headers, $createVoidStructure, 30);
				} catch (Exception $ex) {
					$doku_error = true;
					$doku_error_msg[] = "STOP : Error exception for create void: {$ex->getMessage()}.";
					throw $ex;
				}
			}
			if (!$doku_error) {
				if (!isset($create_void['response']['body'])) {
					$doku_error = true;
					$doku_error_msg[] = "STOP : There is no body response from Void response.";
				}
			}
			if (!$doku_error) {
				$StringVoidStatus = trim($create_void['response']['body']);
				switch (strtoupper($StringVoidStatus)) {
					case 'FAILED':
						// Do what to do if Failed VOID
						if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $create_void, "Payment Void: ". strtoupper($StringVoidStatus)); }
					break;
					case 'SUCCESS':
						// Do what to do if Success VOID
						if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $create_void, "Payment Void: ". strtoupper($StringVoidStatus)); }
					break;
					default:
						// DO what to do if Un-expected VOID
						if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $create_void, "Payment Void: UN-EXPECTED ({$StringVoidStatus})"); }
					break;
				}
			}
		}
		#############################################################################################################
		//-------------------------------------------------------------------------------------------------------------------
		if (!$doku_error) {
			switch (strtoupper($paymentResponseStatus)) {
				case 'SUCCESS':
					$success = TRUE;
				break;
				case 'VOIDED':
					if ($paymentResponseCode == '0000') {
						$success = TRUE;
					} else {
						$success = FALSE;
						$doku_error = true;
						$doku_error_msg[] = "STOP : Payment Response Code is Not : 0000.";
					}
				break;
				case 'FAILED':
					$success = FALSE;
					$doku_error = true;
					$doku_error_msg[] = "STOP : Payment Response Status is not Success: {$paymentResponseStatus}.";
				break;
				default:
					$success = FALSE;
					$doku_error = true;
					$doku_error_msg[] = "STOP : Un-Expected Payment-Response-Status on Line (" . (__LINE__) . "): {$paymentResponseStatus}";
					$doku_error_msg['XMLPaymentStatus'] = $XMLPaymentStatus;
				break;
			}
		}
		if (!$doku_error) {
			if (strtolower($order_status) !== strtolower('paid')) {
				$REQUEST_METHOD_MSG = "REDIRECT:Continue";
			} else {
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: {$Redirect_Url}");
				exit;
			}
		}
		break;
	case 'notify':
	default:
		$REQUEST_METHOD_MSG = "CONTINUE";
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'redirect':
	case 'notify':
	default:
		if (!$doku_error) {
			/**
			 * Log Transaction.
			 *
			 * Add an entry to the Gateway Log for debugging purposes.
			 *
			 * The debug data can be a string or an array. In the case of an
			 * array it will be
			 *
			 * @param string $gatewayName        Display label
			 * @param string|array $debugData    Data to log
			 * @param string $transactionStatus  Status
			*/
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $XMLPaymentStatus, $transactionStatus); }
			if ($success) {
				/**
				 * Add Invoice Payment.
				 *
				 * Applies a payment transaction entry to the given invoice ID.
				 *
				 * @param int $invoiceId         Invoice ID
				 * @param string $transactionId  Transaction ID
				 * @param float $paymentAmount   Amount paid (defaults to full balance)
				 * @param float $paymentFee      Payment fee (optional)
				 * @param string $gatewayModule  Gateway module name
				 */
				addInvoicePayment(
					$invoiceId,
					$transactionId,
					$paymentAmount,
					$paymentFee,
					$gatewayModuleName
				);
			}
		} else {
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], array('STOP' => $doku_error_msg), "(doku_error): Failed"); }
		}
		break;
	case 'review':
	case 'identify':
		die();
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'review':
	case 'identify':
		if (!$doku_error) {
			echo $REQUEST_METHOD_MSG;
		} else {
			print_r($doku_error_msg);
		}
	case 'notify':
	default:	
		if (!$doku_error) {
			echo $REQUEST_METHOD_MSG;
		} else {
			echo "STOP\r\n";
			if (count($doku_error_msg) > 0) {
				foreach ($doku_error_msg as $keval) {
					echo $keval . "\n";
				}
			}
		}
		break;
	case 'redirect':
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: {$Redirect_Url}");
		exit;
		break;
}	
		
