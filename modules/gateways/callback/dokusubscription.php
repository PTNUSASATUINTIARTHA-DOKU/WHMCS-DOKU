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
 Version: V1.2
 Released: Sept 18, 2017
 --------------------------
 *
 * For more information, about this modules payment please kindly visit our website at www.doku.com
 *
 */
/*
error_reporting(E_ALL);
ini_set('display_startup_errors', true);
ini_set('display_errors', true);
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
/*
print_r($gatewayParams);
exit;
*/
$PaymentCheck_Enabled = (isset($gatewayParams['PaymentCheck-Enabled']) ? $gatewayParams['PaymentCheck-Enabled'] : '');
$PaymentCheck_Enabled = ((strtolower($PaymentCheck_Enabled) === strtolower('on')) ? TRUE : FALSE);
function redirecting_page_to_invoice($Redirect_Url) {
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: {$Redirect_Url}");
	exit;
}
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}
$Environment = (isset($gatewayParams['Environment']) ? $gatewayParams['Environment'] : 'sandbox'); // Let sandbox as default
if (!is_string($Environment) && ((!is_array($Environment)) || (!is_object($Environment)))) {
	if (strtolower($Environment) === strtolower('live')) {
		// Gateway Configuration Parameters
		$MallId = (isset($gatewayParams['MallId_Live']) ? $gatewayParams['MallId_Live'] : '');
		$ShopName = (isset($gatewayParams['ShopName_Live']) ? $gatewayParams['ShopName_Live'] : '');
		$ChainMerchant = (isset($gatewayParams['ChainMerchant_Live']) ? $gatewayParams['ChainMerchant_Live'] : 'NA');
		$SharedKey = (isset($gatewayParams['SharedKey_Live']) ? $gatewayParams['SharedKey_Live'] : '');
	} else {
		// Gateway Configuration Parameters
		$MallId = (isset($gatewayParams['MallId']) ? $gatewayParams['MallId'] : '');
		$ShopName = (isset($gatewayParams['ShopName']) ? $gatewayParams['ShopName'] : '');
		$ChainMerchant = (isset($gatewayParams['ChainMerchant']) ? $gatewayParams['ChainMerchant'] : 'NA');
		$SharedKey = (isset($gatewayParams['SharedKey']) ? $gatewayParams['SharedKey'] : '');
	}
} else {
	// Gateway Configuration Parameters
	$MallId = (isset($gatewayParams['MallId']) ? $gatewayParams['MallId'] : '');
	$ShopName = (isset($gatewayParams['ShopName']) ? $gatewayParams['ShopName'] : '');
	$ChainMerchant = (isset($gatewayParams['ChainMerchant']) ? $gatewayParams['ChainMerchant'] : 'NA');
	$SharedKey = (isset($gatewayParams['SharedKey']) ? $gatewayParams['SharedKey'] : '');
}
$DokuConfigs = array(
	'isofile'		=> 'dokuhosted/assets/iso3166.json',
	'merchant'		=> array(
		'mallid'			=> $MallId,
		'shopname'			=> $ShopName,
		'chainmerchant'		=> $ChainMerchant,
		'sharedkey'			=> $SharedKey,
	),
	'endpoint'		=> (is_string($Environment) ? strtolower($Environment) : 'sandbox'), // sandbox as default
);
if (class_exists('dokusubscription_DokuPayment')) {
	$DokuPayment = new dokusubscription_DokuPayment($DokuConfigs);
} else {
	require_once($dokulib);
	$DokuPayment = new dokusubscription_DokuPayment($DokuConfigs);
}
// Getting URL Querystring
$doku_get = dokusubscription_DokuPayment::_GET();
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
if ($Log_Enabled) {
	logTransaction($gatewayParams['paymentmethod'], $doku_ipn_params, "({$REQUEST_METHOD}) Incoming Callback Params");
}
# Get Doku Redirect Params from Request-Uri
$doku_redirect_params = dokusubscription_DokuPayment::get_query_string();
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
	'command' 	=> 'GetOrders',
	'data'		=> array(
		'id' 				=> '', // Later updated on review case
		//'invoiceid' 		=> '', # Later updated on review case
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
			$data = array(
				//'id' 				=> $invoiceId,
				'invoiceid' 		=> $invoiceId,
			);
			$InvoiceData = localAPI('GetInvoice', $data, 'whmcsdoku');
			print_r($InvoiceData);
			
			
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
				$REQUEST_METHOD_MSG =  "STOP: BODY Have no content - Identify";
			}
		} else {
			$REQUEST_METHOD_MSG = "STOP: POST Method Required - Identify";
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
		if (!$doku_error) {
			try {
				$transaction_id_part = date_create_from_format('YmdHis', $transaction_id_part);
			} catch (Exception $ex) {
				$doku_error = true;
				$doku_error_msg[] = "Exception error of date-created from formta: {$ex->getMessage()}.";
			}
		}
		if (!$doku_error) {
			if (!strtotime(date_format($transaction_id_part, 'Y-m-d H:i:s'))) {
				$doku_error = true;
				$doku_error_msg[] = "Transaction id part not in Dateformat structured.";
			}
		}
		if (!$doku_error) {
			$transaction_id_part = date_format($transaction_id_part, 'YmdHis');
			$merchant_transaction = explode("{$transaction_id_part}", $dokuparams['TRANSIDMERCHANT']);
			if (!isset($merchant_transaction[1])) {
				$doku_error = true;
				$doku_error_msg[] = "There is no Transaction-id from IPN Callback as expected: #DATETIME#TRANSID.";
			}
		}
		if (!$doku_error) {
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
				$doku_error_msg[] = "invoiceId is not found.";
				if ($Log_Enabled) {
					logTransaction($gatewayParams['paymentmethod'], $doku_error_msg, "InvoiceId Not Found");
				}
			}
		}
		if (!$doku_error) {
			$localApi['data']['id'] = $invoiceId;
			$InvoiceData = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
			$Redirect_Url = "";
			if (isset($InvoiceData['totalresults'])) {
				if (intval($InvoiceData['totalresults']) > 0) {
					$GetConfigurationValue = localAPI('GetConfigurationValue', array('setting' => 'SystemURL'), $localApi['username']);
					$Redirect_Url .= (isset($GetConfigurationValue['value']) ? $GetConfigurationValue['value'] : '');
					$Redirect_Url .= ((substr($systemUrl, -1) == '/') ? '' : '/');
					$Redirect_Url .= "viewinvoice.php?id={$invoiceId}";
					echo "redirect 01 - <br/>\n";
					//redirecting_page_to_invoice($Redirect_Url)
					exit;
				}
			} else {
				$doku_error = true;
				$doku_error_msg[] = "Local API Result not get totalresults of InvoiceData from GetOrders().";
			}
		}
	break;
	case 'notify':
	case 'updatenotify':
	case 'regupdate':
	case 'subscription':
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
					// -- Recurring POST
					'CHAINMERCHANT'				=> (isset($doku_ipn_params['body']['CHAINMERCHANT']) ? $doku_ipn_params['body']['CHAINMERCHANT'] : ''),
					'CUSTOMERID'				=> (isset($doku_ipn_params['body']['CUSTOMERID']) ? $doku_ipn_params['body']['CUSTOMERID'] : ''),
					'BILLNUMBER'				=> (isset($doku_ipn_params['body']['BILLNUMBER']) ? $doku_ipn_params['body']['BILLNUMBER'] : ''),
					'CARDNUMBER'				=> (isset($doku_ipn_params['body']['CARDNUMBER']) ? $doku_ipn_params['body']['CARDNUMBER'] : ''),
					'STATUS'					=> (isset($doku_ipn_params['body']['STATUS']) ? $doku_ipn_params['body']['STATUS'] : ''),
					'ERRORCODE'					=> (isset($doku_ipn_params['body']['ERRORCODE']) ? $doku_ipn_params['body']['ERRORCODE'] : ''),
					'MESSAGE'					=> (isset($doku_ipn_params['body']['MESSAGE']) ? $doku_ipn_params['body']['MESSAGE'] : ''),
					'STATUSTYPE'				=> (isset($doku_ipn_params['body']['STATUSTYPE']) ? $doku_ipn_params['body']['STATUSTYPE'] : ''),
					'TOKENID'					=> (isset($doku_ipn_params['body']['TOKENID']) ? $doku_ipn_params['body']['TOKENID'] : ''),
					// -- Subscription Get Amount
					'CUSTOMERNAME'				=> (isset($doku_ipn_params['body']['CUSTOMERNAME']) ? $doku_ipn_params['body']['CUSTOMERNAME'] : ''),
					'CUSTOMEREMAIL'				=> (isset($doku_ipn_params['body']['CUSTOMEREMAIL']) ? $doku_ipn_params['body']['CUSTOMEREMAIL'] : ''),
					'CUSTOMERHOMEPHONE'			=> (isset($doku_ipn_params['body']['CUSTOMERHOMEPHONE']) ? $doku_ipn_params['body']['CUSTOMERHOMEPHONE'] : ''),
					'CUSTOMERMOBILEPHONE'		=> (isset($doku_ipn_params['body']['CUSTOMERMOBILEPHONE']) ? $doku_ipn_params['body']['CUSTOMERMOBILEPHONE'] : ''),
				);
				$REQUEST_METHOD_MSG = "NOTIFY-REGISTER:Continue";
			} else {
				$doku_error = true;
				$REQUEST_METHOD_MSG = "STOP: BODY Have no content";
				$doku_error_msg[] = $REQUEST_METHOD_MSG;
			}
		} else {
			$doku_error = true;
			$REQUEST_METHOD_MSG = "STOP: POST Method Required";
			$doku_error_msg[] = $REQUEST_METHOD_MSG;
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
			// -- Recurring Params
			'CHAINMERCHANT'				=> (isset($doku_redirect_params['CHAINMERCHANT']) ? $doku_redirect_params['CHAINMERCHANT'] : ''),
			'CUSTOMERID'				=> (isset($doku_redirect_params['CUSTOMERID']) ? $doku_redirect_params['CUSTOMERID'] : ''),
			'BILLNUMBER'				=> (isset($doku_redirect_params['BILLNUMBER']) ? $doku_redirect_params['BILLNUMBER'] : ''),
			'CARDNUMBER'				=> (isset($doku_redirect_params['CARDNUMBER']) ? $doku_redirect_params['CARDNUMBER'] : ''),
			'STATUS'					=> (isset($doku_redirect_params['STATUS']) ? $doku_redirect_params['STATUS'] : ''),
			'ERRORCODE'					=> (isset($doku_redirect_params['ERRORCODE']) ? $doku_redirect_params['ERRORCODE'] : ''),
			'MESSAGE'					=> (isset($doku_redirect_params['MESSAGE']) ? $doku_redirect_params['MESSAGE'] : ''),
			'STATUSTYPE'				=> (isset($doku_redirect_params['STATUSTYPE']) ? $doku_redirect_params['STATUSTYPE'] : ''),
			'TOKENID'					=> (isset($doku_redirect_params['TOKENID']) ? $doku_redirect_params['TOKENID'] : ''),
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
			$REQUEST_METHOD_MSG = "STOP: QUERYSTRING Have no content";
		}
	break;
}
/*
**
* CONTINUE
**
*/
$Notify_Type = 0;
switch (strtolower($CallbackPage)) {
	case 'notify':
	case 'subscripton':
		if (!$doku_error) {
			if (!empty($dokuparams['STATUSTYPE'])) {
				if (!is_string($dokuparams['STATUSTYPE'])) {
					$doku_error = true;
					$doku_error_msg[] = "STATUS TYPE should be in string format.";
				}
			}
		}
		if (!$doku_error) {
			switch (strtoupper($dokuparams['STATUSTYPE'])) {
				case 'G':
					$Notify_Type = 1; // Notify Registration
					break;
				case 'T':
					$Notify_Type = 2; // Notify Update
					break;
			}
		}
	break;
	default:
		// Nothing to do....
	break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'notify':
	case 'updatenotify':
	case 'regupdate':
	default:
		if (!$doku_error) {
			if ($Log_Enabled) {
				switch ($Notify_Type) {
					case 0: logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Notify Process'); break;
					case 1: logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Notify Register'); break;
					case 2: logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Notify Update'); break;
				}
			}
		}
		break;
	case 'redirect':
		if (!$doku_error) {
			if ($Log_Enabled) {
				switch ($Notify_Type) {
					case 0: logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Redirect Process'); break;
					case 1: logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Redirect Register'); break;
					case 2: logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Redirect Update'); break;
				}
			}
		}
		break;
	case 'subscription':
		if (!$doku_error) {
			if ($Log_Enabled) {
				logTransaction($gatewayParams['paymentmethod'], $dokuparams, 'Payment Subscription GetAmount');
			}
		}
		break;
	case 'review':
	case 'identify':
		echo "{$REQUEST_METHOD_MSG}";
		exit;
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'notify':
	case 'updatenotify':
	case 'regupdate':
	case 'redirect':
	case 'subscription':
	default:
		if (!$doku_error) {
			if (is_array($dokuparams['BILLNUMBER']) || is_object($dokuparams['BILLNUMBER'])) {
				$doku_error = true;
				$doku_error_msg[] = "Array or Object return for BILLNUMBER.";
			}
		}
		if (!$doku_error) {
			$transaction_id = $dokuparams['BILLNUMBER'];
			$transaction_id_part = substr($dokuparams['BILLNUMBER'], 0, 12);
			try {
				//$transaction_id_part = DateTime::createFromFormat('YmdHi', $transaction_id_part);
				$transaction_id_part = date_create_from_format('YmdHi', $transaction_id_part);
			} catch (Exception $ex) {
				$doku_error = true;
				$doku_error_msg[] = "Exception error of date-created from format: {$ex->getMessage()}.";
			}
		}
		if (!$doku_error) {
			if (!strtotime(date_format($transaction_id_part, 'Y-m-d H:i:s'))) {
				$doku_error = true;
				$doku_error_msg[] = "Transaction id part from billnumber not in Dateformat structured.";
			}
		}
		if (!$doku_error) {
			$transaction_id_part = date_format($transaction_id_part, 'YmdHi');
			$merchant_transaction = explode("{$transaction_id_part}", $dokuparams['BILLNUMBER']);
			if (!isset($merchant_transaction[1])) {
				$doku_error = true;
				$doku_error_msg[] = "There is no Transaction-id from IPN Callback as expected: #DATETIME#TRANSID.";
			}
		}
		if (!$doku_error) {
			$invoiceId = trim($merchant_transaction[1]);
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
	case 'regupdate':
	case 'redirect':
	default:
		#########################################
		if (!$doku_error) {
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
				$doku_error_msg[] = "invoiceId is not found.";
				if ($Log_Enabled) {
					logTransaction($gatewayParams['paymentmethod'], $doku_error_msg, "InvoiceId Not Found");
				}
			}
		}
		########################################
		break;
	case 'subscription':
		// do nothing..
		break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'notify':
	case 'regupdate':
	case 'redirect':
	case 'subscription':
	default:
		//--------------------------------------------------------------------
		if (!$doku_error) {
			$params_input = array();
			$params_input['mallid'] = (isset($dokuparams['MALLID']) ? $dokuparams['MALLID'] : '');
			$params_input['chainmerchant'] = (isset($dokuparams['CHAINMERCHANT']) ? $dokuparams['CHAINMERCHANT'] : '');
			$params_input['transaction_id'] = (isset($dokuparams['TRANSIDMERCHANT']) ? $dokuparams['TRANSIDMERCHANT'] : '');
			$params_input['transaction_customer'] = (isset($dokuparams['CUSTOMERID']) ? $dokuparams['CUSTOMERID'] : '');
			$params_input['transaction_card'] = (isset($dokuparams['CARDNUMBER']) ? $dokuparams['CARDNUMBER'] : '');
			$params_input['transaction_status'] = (isset($dokuparams['STATUS']) ? $dokuparams['STATUS'] : '');
			$params_input['transaction_error'] = (isset($dokuparams['ERRORCODE']) ? $dokuparams['ERRORCODE'] : '');
			$params_input['transaction_message'] = (isset($dokuparams['MESSAGE']) ? $dokuparams['MESSAGE'] : '');
			$params_input['transaction_type'] = (isset($dokuparams['STATUSTYPE']) ? $dokuparams['STATUSTYPE'] : ''); // G: Notify Registration T: Notify Update
			$params_input['transaction_code'] = (isset($dokuparams['RESPONSECODE']) ? $dokuparams['RESPONSECODE'] : '');
			$params_input['transaction_currency'] = (isset($dokuparams['CURRENCY']) ? $dokuparams['CURRENCY'] : '');
			$params_input['transaction_approval'] = (isset($dokuparams['APPROVALCODE']) ? $dokuparams['APPROVALCODE'] : '');
			$params_input['transaction_bank'] = (isset($dokuparams['BANK']) ? $dokuparams['BANK'] : '');
			$params_input['transaction_datetime'] = (isset($dokuparams['PAYMENTDATETIME']) ? $dokuparams['PAYMENTDATETIME'] : '');
			$params_input['words'] = (isset($dokuparams['WORDS']) ? $dokuparams['WORDS'] : '');
			//---
			$params_input['subscription_amount'] = (isset($dokuparams['AMOUNT']) ? $dokuparams['AMOUNT'] : '0.00');
			$params_input['subscription_chainmerchant'] = (isset($dokuparams['CHAINMERCHANT']) ? $dokuparams['CHAINMERCHANT'] : 'NA');
			$params_input['subscription_transaction_id'] = (isset($dokuparams['TRANSIDMERCHANT']) ? $dokuparams['TRANSIDMERCHANT'] : 0);
			$params_input['subscription_transaction_bill'] = (isset($dokuparams['BILLNUMBER']) ? $dokuparams['BILLNUMBER'] : 0);
			$params_input['subscription_transaction_session'] = (isset($dokuparams['SESSIONID']) ? $dokuparams['SESSIONID'] : '');
			$params_input['subscription_transaction_currency'] = (isset($dokuparams['CURRENCY']) ? $dokuparams['CURRENCY'] : 'IDR');
			$params_input['subscription_transaction_currency'] = substr($params_input['subscription_transaction_currency'], 0, 2);
			$params_input['subscription_transaction_status'] = (isset($dokuparams['STATUS']) ? $dokuparams['STATUS'] : '');
			$params_input['subscription_transaction_error'] = (isset($dokuparams['ERRORCODE']) ? $dokuparams['ERRORCODE'] : '');
			$params_input['subscription_transaction_message'] = (isset($dokuparams['MESSAGE']) ? $dokuparams['MESSAGE'] : '');
			$params_input['subscription_transaction_card'] = (isset($dokuparams['CARDNUMBER']) ? $dokuparams['CARDNUMBER'] : '');
			$params_input['subscription_transaction_type'] = (isset($dokuparams['STATUSTYPE']) ? $dokuparams['STATUSTYPE'] : '');
			$params_input['subscription_result_msg'] = (isset($dokuparams['RESULTMSG']) ? $dokuparams['RESULTMSG'] : ''); // SUCCESS | FAILED
			$params_input['subscription_transaction_customer'] = (isset($dokuparams['CUSTOMERID']) ? $dokuparams['CUSTOMERID'] : '');
			$params_input['subscription_transaction_token'] = (isset($dokuparams['TOKENID']) ? $dokuparams['TOKENID'] : '');
			$params_input['subscription_transaction_channel'] = (isset($dokuparams['PAYMENTCHANNEL']) ? $dokuparams['PAYMENTCHANNEL'] : '');
			$params_input['subscription_verify_id'] = (isset($dokuparams['VERIFYID']) ? $dokuparams['VERIFYID'] : '');
			$params_input['subscription_verify_score'] = (isset($dokuparams['VERIFYSCORE']) ? $dokuparams['VERIFYSCORE'] : '');
			$params_input['subscription_verify_status'] = (isset($dokuparams['VERIFYSTATUS']) ? $dokuparams['VERIFYSTATUS'] : ''); // NA
			$params_input['subscription_words'] = (isset($dokuparams['WORDS']) ? $dokuparams['WORDS'] : '');
			//--
			$params_input['subscription_transaction_customer_name'] = (isset($dokuparams['CUSTOMERNAME']) ? $dokuparams['CUSTOMERNAME'] : '');
			$params_input['subscription_transaction_customer_email'] = (isset($dokuparams['CUSTOMEREMAIL']) ? $dokuparams['CUSTOMEREMAIL'] : '');
			$params_input['subscription_transaction_customer_phone'] = (isset($dokuparams['CUSTOMERHOMEPHONE']) ? $dokuparams['CUSTOMERHOMEPHONE'] : '');
			$params_input['subscription_transaction_customer_mobile'] = (isset($dokuparams['CUSTOMERMOBILEPHONE']) ? $dokuparams['CUSTOMERMOBILEPHONE'] : '');
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
	case 'regupdate':
		if (!$doku_error) {
			// Make subscription-notify structured
			switch ($Notify_Type) {
				case 0:
					$CheckNotifyPaymentStructure = $DokuPayment->create_payment_structure('subscription-process', 0, $params_input, $dokuparams);
					if ($Log_Enabled) {
						logTransaction($gatewayParams['paymentmethod'], $CheckNotifyPaymentStructure, 'Subscription Process Structured');
					}
				break;
				case 1:
					$CheckNotifyPaymentStructure = $DokuPayment->create_payment_structure('subscription-notify', 0, $params_input, $dokuparams);
					if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $CheckNotifyPaymentStructure, 'Subscription Register Structured'); }
				break;
				case 2:
					$CheckNotifyPaymentStructure = $DokuPayment->create_payment_structure('subscription-update', 0, $params_input, $dokuparams);
					if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $CheckNotifyPaymentStructure, 'Subscription Update Structured'); }
				break;
			}
			if (!isset($CheckNotifyPaymentStructure['WORDS'])) {
				$doku_error = true;
				$doku_error_msg[] = "Not get WORDS string from created notify structured.";
			}	
			
		}
	break;
	case 'redirect':
		if (!$doku_error) {
			// Make subscription-redirect structured
			$CheckNotifyPaymentStructure = $DokuPayment->create_payment_structure('redirect', 0, $params_input, $dokuparams);
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $CheckNotifyPaymentStructure, 'Subscription Redirect Structured'); }
			if (!isset($CheckNotifyPaymentStructure['WORDS'])) {
				$doku_error = true;
				$doku_error_msg[] = "Not get WORDS string from created notify structured.";
			}	
		}
		if (!$doku_error) {
			if (!is_string($params_input['words'])) {
				$doku_error = true;
				$doku_error_msg[] = "Words should be a string.";
			}
		}
	break;
	case 'subscription':
		if (!$doku_error) {
			try {
				$SubscriptionDataStructure = $DokuPayment->create_payment_structure('regsubscription', 0, $params_input, $dokuparams);
			} catch (Exception $ex) {
				$doku_error = true;
				$doku_error_msg[] = "Exception error while get system structure for get subscription amount: {$ex->getMessage()}.";
			}
		}
		if (!$doku_error) {
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $SubscriptionDataStructure, 'Get Subscription Amount'); }
			if (!isset($SubscriptionDataStructure['WORDS'])) {
				$doku_error = true;
				$doku_error_msg[] = "Not get WORDS string from created get subscription amount.";
			}	
		}
		if (!$doku_error) {
			if (!is_string($params_input['words'])) {
				$doku_error = true;
				$doku_error_msg[] = "Words should be a string.";
			}
		}
		if (!$doku_error) {
			if (strtolower($params_input['words']) !== strtolower($SubscriptionDataStructure['WORDS'])) {
				$doku_error = true;
				$doku_error_msg[] = "Words should be match to get subscription data.";
			}
		}
	break;
}


/*
**
* CONTINUE
**
*/
$order_id = 0;
$order_num = 0;
$order_status = '';
switch (strtolower($CallbackPage)) {
	case 'notify':
	case 'regupdate':
	case 'redirect':
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
			// query localApi from invoiceid
			$InvoiceData = localAPI('GetInvoice', $localApi['data'], $localApi['username']);
			if (isset($InvoiceData['invoiceid'])) {
				if (intval($InvoiceData['invoiceid']) > 0) {
					$GetConfigurationValue = localAPI('GetConfigurationValue', array('setting' => 'SystemURL'), $localApi['username']);
					$Redirect_Url = (isset($GetConfigurationValue['value']) ? $GetConfigurationValue['value'] : '');
					$Redirect_Url .= ((substr($systemUrl, -1) == '/') ? '' : '/');
					$Redirect_Url .= "viewinvoice.php?id={$invoiceId}";
				}
			} else {
				$doku_error = true;
				$doku_error_msg[] = "Local API Result not get totalresults of InvoiceData from GetInvoice().";
			}
		}
		$orders_debug = array(
			'order_id'				=> 0,
			'order_num'				=> 0,
			'order_status'			=> '',
		);
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
				$doku_error_msg[] = "Local API Result for GetInvoice() not get proper data as expected.";
			}
		}
	break;
	case 'subscription':
		if (!$doku_error) {
			If (isset($localApi['data']['id'])) {
				unset($localApi['data']['id']);
			}
			$localApi['data']['invoiceid'] = $invoiceId;
			$InvoiceData = localAPI('GetInvoice', $localApi['data'], $localApi['username']);
			if (isset($InvoiceData['invoiceid'])) {
				if (intval($InvoiceData['invoiceid']) > 0) {
					$GetConfigurationValue = localAPI('GetConfigurationValue', array('setting' => 'SystemURL'), $localApi['username']);
					$Redirect_Url = (isset($GetConfigurationValue['value']) ? $GetConfigurationValue['value'] : '');
					$Redirect_Url .= ((substr($systemUrl, -1) == '/') ? '' : '/');
					$Redirect_Url .= "viewinvoice.php?id={$invoiceId}";
				}
			} else {
				$doku_error = true;
				$doku_error_msg[] = "Local API Result not get totalresults of InvoiceData from GetOrders().";
			}
		}
		$invoceSubscriptionTotal = "";
		if (!$doku_error) {
			if (isset($InvoiceData['total'])) {
				$invoceSubscriptionTotal = sprintf("%.2f", floatval($InvoiceData['total']));
			}
		}
		if (!$doku_error) {
			echo $invoceSubscriptionTotal;
		} else {
			print_r($doku_error_msg);
		}
		exit;
	break;
}
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'notify':
	case 'regupdate':
		
	break;
}






//--------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------
/*
$OrderData = FALSE;
if (isset($localApi['data']['id'])) {
	unset($localApi['data']['id']);
}
$localApi['command'] = 'GetOrders';
$localApi['data']['invoiceid']	= (isset($params['invoiceid']) ? $params['invoiceid'] : '');
$OrdersData = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
if (isset($OrdersData['orders']['order'])) {
	if (count($OrdersData['orders']['order']) > 0) {
		foreach ($OrdersData['orders']['order'] as $keval) {
			if (isset($keval['invoiceid'])) {
				if ((int)$keval['invoiceid'] === (int)$localApi['data']['invoiceid']) {
					$OrderData = $keval;
				}
			}
		}
	}
}
if (!$error) {
	if (!$OrderData) {
		$error = true;
		$error_msg[] = "No Invoice data from localApi: " . (__METHOD__);
	}
}
if ($InvoiceData) {
	if (!$doku_error) {
		echo "<pre>";
		echo "{$invoiceId}";
		echo "\r\n";
		print_r($InvoiceData);
		exit;
		
		
		
		echo "<pre>";
		print_r($localApi);
		print_r($orders_debug);
		print_r($InvoiceData);
		$GetConfigurationValue = localAPI('GetConfigurationValue', array('setting' => 'SystemURL'), $localApi['username']);
		print_r($GetConfigurationValue);
		exit;
	} else {
		echo "<pre>";
		print_r($doku_error);
		exit;
	}
} else {
	exit("NO INVOICE DATA");
}
*/
//--------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------
/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'notify':
	default:
		// Only for Notify
		//**************************************************************************
		//**************************************************************************
		if (strtolower($CallbackPage) === strtolower('notify')) {
			if (!$doku_error) {
				//-----------------------------------------
				// check if WORDS is match or not
				//-----------------------------------------
				if (strtolower($CheckNotifyPaymentStructure['WORDS']) !== strtolower($params_input['words'])) {
					$doku_error = true;
					$doku_error_msg[] = "Words from Notify-Structured not same with Dokuparams";
				}
			}
		}
		//**************************************************************************
		//**************************************************************************
		//------------------------------------------------------------------------------------------
		if (!$doku_error) {
			//---------------------------------
			// IMPORTANT GLOBAL VARS
			//---------------------------------
			$invoiceId = (isset($invoiceId) ? $invoiceId : '');
			$transactionId = False;
			/**
			 * Adding Unique generated Invoice-Id to Transaction-Id
			 * Got bugs: DOKU always send:
			 * Constant $transactionId
			 * Using @$transaction_id_part
			*/
			switch ($Notify_Type) {
				case 0: // Notify process
					$transactionId = (isset($CheckNotifyPaymentStructure['APPROVALCODE']) ? $CheckNotifyPaymentStructure['APPROVALCODE'] : '');
				break;
				case 1: // Notify register
					$transactionId = (isset($CheckNotifyPaymentStructure['BILLNUMBER']) ? $CheckNotifyPaymentStructure['BILLNUMBER'] : '');
				break;
				case 2: // Notify update
					$transactionId = (isset($CheckNotifyPaymentStructure['BILLNUMBER']) ? $CheckNotifyPaymentStructure['BILLNUMBER'] : '');
				break;
			}
			$transactionId .= "_";
			switch ($Notify_Type) {
				case 0: // Notify process
					$transactionId .= (isset($CheckNotifyPaymentStructure['TRANSIDMERCHANT']) ? $CheckNotifyPaymentStructure['TRANSIDMERCHANT'] : '');
					$paymentAmount = (isset($CheckNotifyPaymentStructure['AMOUNT']) ? $CheckNotifyPaymentStructure['AMOUNT'] : 0);
					$paymentFee = (isset($CheckNotifyPaymentStructure['FEE']) ? $CheckNotifyPaymentStructure['FEE'] : 0);
					$paymentEDU = (isset($CheckNotifyPaymentStructure['EDUSTATUS']) ? $CheckNotifyPaymentStructure['EDUSTATUS'] : 'NA'); // NA (Default)
				break;
				case 1: // Notify register
					$transactionId .= (isset($CheckNotifyPaymentStructure['STATUSTYPE']) ? $CheckNotifyPaymentStructure['STATUSTYPE'] : '');
					$paymentAmount = (isset($InvoiceData['total']) ? $InvoiceData['total'] : 0);
					$paymentFee = (isset($CheckNotifyPaymentStructure['FEE']) ? $CheckNotifyPaymentStructure['FEE'] : 0);
					$paymentEDU = (isset($CheckNotifyPaymentStructure['EDUSTATUS']) ? $CheckNotifyPaymentStructure['EDUSTATUS'] : 'NA'); // NA (Default)
				break;
				case 2: // Notify update
					$transactionId .= (isset($CheckNotifyPaymentStructure['STATUSTYPE']) ? $CheckNotifyPaymentStructure['STATUSTYPE'] : '');
					$paymentAmount = (isset($InvoiceData['total']) ? $InvoiceData['total'] : 0);
					$paymentFee = (isset($CheckNotifyPaymentStructure['FEE']) ? $CheckNotifyPaymentStructure['FEE'] : 0);
					$paymentEDU = (isset($CheckNotifyPaymentStructure['EDUSTATUS']) ? $CheckNotifyPaymentStructure['EDUSTATUS'] : 'NA'); // NA (Default)
				break;
			}
			#
			switch ($Notify_Type) {
				case 0:
					$paymentResponseStatus = (isset($dokuparams['RESULTMSG']) ? $dokuparams['RESULTMSG'] : ''); // SUCCESS, FAILED
					$paymentResponseCode = (isset($dokuparams['RESPONSECODE']) ? $dokuparams['RESPONSECODE'] : ''); // 00 (Success), Other is failed
					$paymentResponseType = (isset($dokuparams['VERIFYSTATUS']) ? $dokuparams['VERIFYSTATUS'] : ''); //APPROVE / REJECT / REVIEW / HIGHRISK
				break;
				case 1:
					$paymentResponseStatus = (isset($dokuparams['STATUS']) ? $dokuparams['STATUS'] : ''); // SUCCESS, FAILED
					$paymentResponseCode = (isset($dokuparams['ERRORCODE']) ? $dokuparams['ERRORCODE'] : ''); // 00 (Success), Other is failed
					$paymentResponseType = (isset($dokuparams['STATUSTYPE']) ? $dokuparams['STATUSTYPE'] : ''); // G or T
				break;
				case 2:
					$paymentResponseStatus = (isset($dokuparams['STATUS']) ? $dokuparams['STATUS'] : ''); // SUCCESS, FAILED
					$paymentResponseCode = (isset($dokuparams['ERRORCODE']) ? $dokuparams['ERRORCODE'] : ''); // 00 (Success), Other is failed
					$paymentResponseType = (isset($dokuparams['STATUSTYPE']) ? $dokuparams['STATUSTYPE'] : ''); // G or T
				break;
			}
			switch (strtoupper($paymentResponseStatus)) {
				case 'SUCCESS':
				case 'VOIDED':
					$success = TRUE;
					switch ($Notify_Type) {
						case 0:
							if ((int)$paymentResponseCode > 0) {
								$success = FALSE;
								$doku_error = true;
								$doku_error_msg[] = "RESPONSECODE should be 0";
							}
						break;
						case 1:
							if (strtoupper($paymentResponseType) !== strtoupper('G')) {
								$success = FALSE;
								$doku_error = true;
								$doku_error_msg[] = "Status Type should be G for Notify Registration";
							}
						break;
						case 2:
							if (strtoupper($paymentResponseType) !== strtoupper('T')) {
								$success = FALSE;
								$doku_error = true;
								$doku_error_msg[] = "Status Type should be T for Notify Registration";
							}
						break;
					}
				break;
				case 'FAILED':
					$success = FALSE;
					$doku_error = true;
					$doku_error_msg[] = "Payment Response Status is not Success: {$paymentResponseStatus}.";
				break;
				default:
					$success = FALSE;
					$doku_error = true;
					$doku_error_msg[] = "Un-Expected Payment-Response-Status: (" . (__LINE__) . ") {$paymentResponseStatus}";
				break;
			}
		}
		break;
	case 'redirect':
	case 'regupdate':
		//-------------------------------------------------------------------------------------------------------------
		/**
		 * Check Status Payment: Call DOKU
		 * Applied only for Redirect-Page not for First Time Notify
		 * ********************************************************
		*/
		//-------------------------------------------------------------------------------------------------------------
		if (!$doku_error) {
			// Make checking redirect-payment structured
			# add transaction-words, transaction-status, transaction-code
			$params_input['transaction_words'] = (isset($dokuparams['WORDS']) ? $dokuparams['WORDS'] : '');
			$params_input['transaction_status'] = (isset($dokuparams['STATUSCODE']) ? strval($dokuparams['STATUSCODE']) : ''); // 00 = Success, Other = Failed
			$params_input['transaction_channel'] = (isset($dokuparams['PAYMENTCHANNEL']) ? $dokuparams['PAYMENTCHANNEL'] : '');
			$CheckRedirectPaymentStructure = $DokuPayment->create_payment_structure('redirect', 0, $params_input, $dokuparams);
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $CheckRedirectPaymentStructure, 'Subsciption Chek Redirect Structured'); }
			if (strtolower($CallbackPage) === strtolower('redirect')) {
				//-----------------------------------------
				// check if WORDS is match or not (On 'redirect' stage)
				//-----------------------------------------
				if (strtolower($params_input['transaction_words']) !== strtolower($CheckRedirectPaymentStructure['WORDS'])) {
					$doku_error = true;
					$doku_error_msg[] = "Words from Redirect-Structured not same with Dokuparams. (" . (__LINE__) . ")";
					redirecting_page_to_invoice($Redirect_Url);
					exit;
				}
			}
		}
		if (!$doku_error) {
				/*
				TRANSIDMERCHANT => 2017091216074812
				PAYMENTCHANNEL => 17
				BILLNUMBER => 20170912160712
				AMOUNT => 2469305.00
				WORDS => 6064d5cdc802395df1ffc7bc93c41bb22874322e
				SESSIONID => 00dd18b360d7ed1f8c509a19b19be8d4
				CUSTOMERID => 1
				*/
			if (!in_array(strtolower($order_status), array('paid', 'pending'))) {
				//---------------------------------
				// IMPORTANT GLOBAL VARS
				//---------------------------------
				$invoiceId = (isset($invoiceId) ? $invoiceId : '');
				$transactionId = False;
				//
				// Adding Unique generated Invoice-Id to Transaction-Id
				// Got bugs: DOKU always send:
				// Constant APPROVALCODE for first time Notify (DOKU Always send same Approval-code foe each invoice-id)
				// Using @$merchant_transaction[0]
				//
				switch ($Notify_Type) {
					case 0:
						$transactionId = (isset($CheckNotifyPaymentStructure['BILLNUMBER']) ? $CheckNotifyPaymentStructure['BILLNUMBER'] : '');
						$paymentResponseStatus = (isset($dokuparams['RESULTMSG']) ? $dokuparams['RESULTMSG'] : ''); // SUCCESS, FAILED
						$paymentResponseCode = (isset($dokuparams['RESPONSECODE']) ? $dokuparams['RESPONSECODE'] : ''); // 00 (Success), Other is failed
						$paymentResponseType = (isset($dokuparams['VERIFYSTATUS']) ? $dokuparams['VERIFYSTATUS'] : ''); //APPROVE / REJECT / REVIEW / HIGHRISK
					break;
					case 1:
						$transactionId = (isset($dokuparams['BILLNUMBER']) ? $dokuparams['BILLNUMBER'] : '');
						$paymentResponseStatus = (isset($dokuparams['STATUS']) ? $dokuparams['STATUS'] : ''); // SUCCESS, FAILED
						$paymentResponseCode = (isset($dokuparams['ERRORCODE']) ? $dokuparams['ERRORCODE'] : ''); // 00 (Success), Other is failed
						$paymentResponseType = (isset($dokuparams['STATUSTYPE']) ? $dokuparams['STATUSTYPE'] : ''); // G or T
					break;
					case 2:
						$transactionId = (isset($dokuparams['BILLNUMBER']) ? $dokuparams['BILLNUMBER'] : '');
						$paymentResponseStatus = (isset($dokuparams['STATUS']) ? $dokuparams['STATUS'] : ''); // SUCCESS, FAILED
						$paymentResponseCode = (isset($dokuparams['ERRORCODE']) ? $dokuparams['ERRORCODE'] : ''); // 00 (Success), Other is failed
						$paymentResponseType = (isset($dokuparams['STATUSTYPE']) ? $dokuparams['STATUSTYPE'] : ''); // G or T
					break;
				}
				$transactionId .= "_";
				switch ($Notify_Type) {
					case 0:
						$transactionId .= (isset($CheckNotifyPaymentStructure['TRANSIDMERCHANT']) ? $CheckNotifyPaymentStructure['TRANSIDMERCHANT'] : '');
						$paymentAmount = (isset($dokuparams['AMOUNT']) ? $dokuparams['AMOUNT'] : 0);
						$paymentFee = (isset($dokuparams['FEE']) ? $dokuparams['FEE'] : 0);
						$paymentEDU = (isset($dokuparams['EDUSTATUS']) ? $dokuparams['EDUSTATUS'] : 'NA'); // NA (Default)
					break;
					case 1:
						$transactionId .= (isset($dokuparams['STATUSTYPE']) ? $dokuparams['STATUSTYPE'] : '');
						$paymentAmount = (isset($InvoiceData['total']) ? $InvoiceData['total'] : 0);
						$paymentFee = (isset($CheckRedirectPaymentStructure['FEE']) ? $CheckRedirectPaymentStructure['FEE'] : 0);
						$paymentEDU = (isset($CheckRedirectPaymentStructure['EDUSTATUS']) ? $CheckRedirectPaymentStructure['EDUSTATUS'] : 'NA'); // NA (Default)
					break;
					case 2:
						$transactionId .= (isset($dokuparams['STATUSTYPE']) ? $dokuparams['STATUSTYPE'] : '');
						$paymentAmount = (isset($InvoiceData['total']) ? $InvoiceData['total'] : 0);
						$paymentFee = (isset($CheckRedirectPaymentStructure['FEE']) ? $CheckRedirectPaymentStructure['FEE'] : 0);
						$paymentEDU = (isset($CheckRedirectPaymentStructure['EDUSTATUS']) ? $CheckRedirectPaymentStructure['EDUSTATUS'] : 'NA'); // NA (Default)
					break;
				}
			} else {
				redirecting_page_to_invoice($Redirect_Url);
				exit;
			}
		}
		if (!$doku_error) {
			// Log payment-status
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $dokuparams, "(#{$invoiceId}) " . 'Payment Status From Redirected (Not Paid)'); }
			/*
			if (strtolower($order_status) !== strtolower('paid')) {
				// Make Error if Redirected from Un-Success Payment
				$doku_error = true;
				$doku_error_msg[] = "Redirected from failed payment: Maybe WHMCS Payment-Gateway-Module not activate payment-check status.";
			}
			*/
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
	case 'notify':
	case 'regupdate':
	default:
		if (!$doku_error) {
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
			if (strtolower($order_status) !== strtolower('paid')) {
				switch ($Notify_Type) {
					case 0:
						if (!in_array(strtolower($order_status), array('paid', 'draft'))) {
							checkCbTransID($transactionId);
						}
					break;
					case 1:
						if (!in_array(strtolower($order_status), array('paid', 'draft'))) {
							checkCbTransID($transactionId);
						}
					break;
					case 2:
						if (!in_array(strtolower($order_status), array('paid', 'draft'))) {
							checkCbTransID($transactionId);
						}
					break;
				}
			}
		}
	break;
	case 'redirect':
		if (!$doku_error) {
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
			if (strtolower($order_status) !== strtolower('paid')) {
				switch ($Notify_Type) {
					case 0:
						if (!in_array(strtolower($order_status), array('paid'))) {
							checkCbTransID($transactionId);
						}
					break;
					case 1:
						if (!in_array(strtolower($order_status), array('paid', 'draft'))) {
							checkCbTransID($transactionId);
						}
					break;
					case 2:
						if (!in_array(strtolower($order_status), array('paid', 'draft'))) {
							checkCbTransID($transactionId);
						}
					break;
				}
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
	// allow only Doku IP Address for payment
	case 'notify':
	case 'regupdate':
		if (!$doku_error) {
			$doku_ip_address = '103.10.129.0/24';
			$this_ip_address = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0');
			if (substr($DokuPayment->get_ip_address($this_ip_address), 0, 10) != substr($doku_ip_address, 0, 10)) {
				$doku_error = true;
				$doku_error_msg[] = "Invalid IP Address, Not from doku sandbox env: {$DokuPayment->get_ip_address()}";
				$success = FALSE;
			}
		}
	break;
	default:
		// do nothing
	break;
}

/*
**
* CONTINUE
**
*/
switch (strtolower($CallbackPage)) {
	case 'notify':
	case 'regupdate':
	default:
		if (!$doku_error) {
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
				$localApi['command'] = 'UpdateInvoice';
				$localApi['data'] = array(
					'invoiceid'			=> $invoiceId,
					'status'			=> 'Draft',
				);
				if (strtolower($order_status) !== strtolower('paid')) {
					switch ($Notify_Type) {
						case 0:
							$REQUEST_METHOD_MSG = "CONTINUE";
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $XMLPaymentStatus, 'Success'); }
							addInvoicePayment($invoiceId, $transactionId, $paymentAmount, $paymentFee, $gatewayModuleName);
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], "Notify Process", "(#{$invoiceId}) Logger"); }
						break;
						case 1:
							$REQUEST_METHOD_MSG = "CONTINUE";
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $XMLPaymentStatus, 'Pending: Subscription Register'); }
							/*
							try {
								$InvoiceUpdate = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
								logTransaction($gatewayParams['paymentmethod'], $InvoiceUpdate, 'Pending: InvoiceUpdate - Register');
							} catch (Exception $ex) {
								$doku_error = true;
								$doku_error_msg[] = "Cannot update Invoice Status On Notify: Subscription Register.";
							}
							*/
							addInvoicePayment($invoiceId, $transactionId, $paymentAmount, $paymentFee, $gatewayModuleName);
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], "Notify Register", "(#{$invoiceId}) Logger"); }
						break;
						case 2:
							$REQUEST_METHOD_MSG = "CONTINUE";
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $XMLPaymentStatus, 'Pending: Subscription Update'); }
							/*
							try {
								$InvoiceUpdate = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
								logTransaction($gatewayParams['paymentmethod'], $InvoiceUpdate, 'Pending: InvoiceUpdate - Update');
							} catch (Exception $ex) {
								$doku_error = true;
								$doku_error_msg[] = "Cannot update Invoice Status On Notify: Subscription Update.";
							}
							*/
							addInvoicePayment($invoiceId, $transactionId, $paymentAmount, $paymentFee, $gatewayModuleName);
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], "Notify Update", "(#{$invoiceId}) Logger"); }
						break;
					}
				}
			}
		} else {
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], array('STOP' => $doku_error_msg), "(doku_error): Failed on Notify"); }
		}
	break;
	case 'redirect':
		if (!$doku_error) {
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
				$localApi['command'] = 'UpdateInvoice';
				$localApi['data'] = array(
					'invoiceid'			=> $invoiceId,
					'status'			=> 'Draft',
				);
				if (strtolower($order_status) !== strtolower('paid')) {
					switch ($Notify_Type) {
						case 0:
							$REQUEST_METHOD_MSG = "Subscription Redirect Process";
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $XMLPaymentStatus, 'Success'); }
							addInvoicePayment($invoiceId, $transactionId, $paymentAmount, $paymentFee, $gatewayModuleName);
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], "Redirect Process", "(#{$invoiceId}) Logger"); }
						break;
						case 1:
							$REQUEST_METHOD_MSG = "Subscription Redirect Register";
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $XMLPaymentStatus, 'Pending: Redirect Register'); }
							/*
							try {
								$InvoiceUpdate = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
							} catch (Exception $ex) {
								$doku_error = true;
								$doku_error_msg[] = "Cannot update Invoice Status On Register: Subscription Register.";
							}
							*/
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], "Redirect Register", "(#{$invoiceId}) Logger"); }
						break;
						case 2:
							$REQUEST_METHOD_MSG = "Subscription Redirect Update";
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $XMLPaymentStatus, 'Pending: Redirect Update'); }
							/*
							try {
								$InvoiceUpdate = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
							} catch (Exception $ex) {
								$doku_error = true;
								$doku_error_msg[] = "Cannot update Invoice Status On Register: Subscription Update.";
							}
							*/
							if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], "Redirect Update", "(#{$invoiceId}) Logger"); }
						break;
					}
				}
			}
		} else {
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $doku_error_msg, "(doku_error): Failed on Redirect"); }
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
			print_r($REQUEST_METHOD_MSG);
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], array('STOP' => $REQUEST_METHOD_MSG), 'DOKU ERROR MSG'); }
		}
		break;
	case 'redirect':
		if (!$doku_error) {
			if ($Log_Enabled) { logTransaction($gatewayParams['paymentmethod'], $REQUEST_METHOD_MSG, "(#{$invoiceId}) " . 'Echo From Redirect'); }
			redirecting_page_to_invoice($Redirect_Url);
		} else {
			echo "<pre>";
			print_r($doku_error_msg);
		}
		break;
}	



/*
**
* CONTINUE
**
*/
/*
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
					$doku_error_msg[] = "Error exception for create void: {$ex->getMessage()}.";
					throw $ex;
				}
			}
			if (!$doku_error) {
				if (!isset($create_void['response']['body'])) {
					$doku_error = true;
					$doku_error_msg[] = "There is no body response from Void response.";
				}
			}
			if (!$doku_error) {
				$StringVoidStatus = trim($create_void['response']['body']);
				switch (strtoupper($StringVoidStatus)) {
					case 'FAILED':
						// Do what to do if Failed VOID
						logTransaction($gatewayParams['paymentmethod'], $create_void, "Payment Void: ". strtoupper($StringVoidStatus));
					break;
					case 'SUCCESS':
						// Do what to do if Success VOID
						logTransaction($gatewayParams['paymentmethod'], $create_void, "Payment Void: ". strtoupper($StringVoidStatus));
					break;
					default:
						// DO what to do if Un-expected VOID
						logTransaction($gatewayParams['paymentmethod'], $create_void, "Payment Void: UN-EXPECTED ({$StringVoidStatus})");
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
					if ($paymentResponseCode == '00') {
						$success = TRUE;
					} else {
						$success = FALSE;
						$doku_error = true;
						$doku_error_msg[] = "Payment Response Code is Not : 00.";
					}
				break;
				case 'FAILED':
					$success = FALSE;
					$doku_error = true;
					$doku_error_msg[] = "Payment Response Status is not Success: {$paymentResponseStatus}.";
				break;
				default:
					$success = FALSE;
					$doku_error = true;
					$doku_error_msg[] = "Un-Expected Payment-Response-Status on Line (" . (__LINE__) . "): {$paymentResponseStatus}";
					$doku_error_msg['XMLPaymentStatus'] = $XMLPaymentStatus;
				break;
			}
		}
		if (!$doku_error) {
			if (strtolower($order_status) !== strtolower('paid')) {
				$REQUEST_METHOD_MSG = "REDIRECT:Continue";
			} else {
				echo "redirect - 3<br/>\n";
				echo $Redirect_Url;
				// redirecting_page_to_invoice($Redirect_Url);
				exit;
			}
		}
		break;
	case 'notify':
	default:
		$REQUEST_METHOD_MSG = "CONTINUE";
		break;
}
*/		
