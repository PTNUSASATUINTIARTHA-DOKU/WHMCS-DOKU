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
/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function dokusubscription_MetaData() {
    return array(
        'DisplayName' => 'DOKU Account Billing',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

//---------------
// URL show to admin page
Class dokusubscription_DokuAdmin {
	public static $notify = false;
	public static $redirect = false;
	public static $review = false;
	public static $identify = false;
	public static $regsubscription = false;
	public static $regupdate = false;
	public static $regupdatenotify = false;
	public static $DOKUCONFIGS;
	public function __construct($configs = array()) {
		$this->set_protocol(self::getDokuPaymentProtocol());
		$this->protocol = (isset($this->protocol) ? $this->protocol : $this->get_protocol());
		$this->set_hostname(self::getDokuPaymentHost());
		$this->hostname = (isset($this->hostname) ? $this->hostname : $this->get_hostname());
		$this->set_url('notify', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokusubscription.php?page=notify");
		$this->set_url('redirect', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokusubscription.php?page=redirect");
		$this->set_url('review', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokusubscription.php?page=review");
		$this->set_url('identify', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokusubscription.php?page=identify");
		$this->set_url('regsubscription', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokusubscription.php?page=subscription");
		$this->set_url('regupdate', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokusubscription.php?page=regupdate");
		$this->set_url('regupdatenotify', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokusubscription.php?page=notify");
		// Set GLOBAL $DOKUCONFIGS
		self::$DOKUCONFIGS = $configs;
	}
	public static function getDokuPaymentConfigs($Vars) {
		$configs = array();
		if (!isset(self::$DOKUCONFIGS)) {
			return false;
		}
		switch (strtolower($Vars)) {
			case 'channels':
				$configs = (isset(self::$DOKUCONFIGS['paymentchannels']) ? self::$DOKUCONFIGS['paymentchannels'] : NULL);
				break;
			case 'acquirers':
				$configs = (isset(self::$DOKUCONFIGS['paymentacquirers']) ? self::$DOKUCONFIGS['paymentacquirers'] : NULL);
				break;
			case 'tenors':
				$configs = (isset(self::$DOKUCONFIGS['paymenttenors']) ? self::$DOKUCONFIGS['paymenttenors'] : NULL);
				break;
		}
		return $configs;
	}
	public static function getDokuPaymentProtocol() {
		if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
			if ( $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) {
				$_SERVER['HTTPS']       = 'on';
				$_SERVER['SERVER_PORT'] = 443;
			}
		}
		$protocol = 'http://';
		if (isset($_SERVER['HTTPS'])) {
			$protocol = (($_SERVER['HTTPS'] == 'on') ? 'https://' : 'http');
		} else {
			$protocol = (isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'http');
			$protocol = ((strtolower(substr($protocol, 0, 5)) =='https') ? 'https://': 'http://');
		}
		return $protocol;
	}
	public static function getDokuPaymentHost() {
		$currentPath = (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
		$pathInfo = pathinfo(dirname($currentPath)); 
		$hostName = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
		$return = $hostName;
		$return .= ((substr($hostName, -1) == '/') ? '' : '/');
		return $return;
	}
	public static function _GET(){
		$__GET = array();
		$request_uri = ((isset($_SERVER['REQUEST_URI']) && (!empty($_SERVER['REQUEST_URI']))) ? $_SERVER['REQUEST_URI'] : '/');
		$_get_str = explode('?', $request_uri);
		if( !isset($_get_str[1]) ) return $__GET;
		$params = explode('&', $_get_str[1]);
		foreach ($params as $p) {
			$parts = explode('=', $p);
			$__GET[$parts[0]] = isset($parts[1]) ? $parts[1] : '';
		}
		return $__GET;
	}
	public function set_hostname($hostname) {
		$this->hostname = $hostname;
		return $this;
	}
	public function get_hostname() {
		return $this->hostname;
	}
	public function set_protocol($protocol) {
		$this->protocol = $protocol;
		return $this;
	}
	public function get_protocol() {
		return $this->protocol;
	}
	public static function set_url($type, $url) {
		switch (strtolower($type)) {
			case 'notify':
			default:
				self::$notify = $url;
			break;
			case 'redirect':
				self::$redirect = $url;
			break;
			case 'review':
				self::$review = $url;
			break;
			case 'identify':
				self::$identify = $url;
			break;
			case 'regsubscription':
				self::$regsubscription = $url;
			break;
			case 'regupdate':
				self::$regupdate = $url;
			break;
			case 'regupdatenotify':
				self::$regupdatenotify = $url;
			break;
		}
	}
}
// Start new DokuAdmin instance
/*
error_reporting(E_ALL);
ini_set('display_startup_errors', true);
ini_set('display_errors', true);
*/
// Include @DOKUCONFIGS
$configfile = (dirname(__FILE__) . '/dokuhosted/dokusubscription-config.php');
if (!file_exists($configfile)) {
	Exit("Required configs file does not exists.");
}
require($configfile);
if (!isset($DOKUCONFIGS)) {
	Exit("There is no DOKUCONFIGS of included config file.");
}
$DokuAdmin = new dokusubscription_DokuAdmin($DOKUCONFIGS);
/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
// Require doku-payment class instance
require('dokuhosted/dokusubscription-whmcs.php');
function dokusubscription_config() {
    $configs = array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'DOKU Account Billing',
        ),
        // a text field type allows for single line text input
		# Sandbox
		//-------------
		'Pembatas-Sandbox' => array(
			"FriendlyName" => "(*)", 
			"Type" => 'hidden',
			"Size" => "64",
			"Value" => '',
			"Description" => '<span style="color:green;font-weight:bold;">Sandbox Params</span>',
		),
		//-------------
        'MallId' => array(
            'FriendlyName' => 'Sandbox: Mall ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Mall ID here',
        ),
		/*
        'ShopName' => array(
            'FriendlyName' => 'Sandbox: Shop Name',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter Shop Name here',
        ),
		*/
		'ChainMerchant' => array(
            'FriendlyName' => 'Development: Chain Merchant Name (Default is: NA)',
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'NA',
            'Description' => 'Enter Chain Merchant Name (Default is: NA)',
        ),
		'SharedKey' => array(
			'FriendlyName' => 'Sandbox: Shared Key',
			'Type' => 'text',
			'Size' => '25',
			'Default' => '',
			'Description' => 'Enter Shared Key you got from DOKU Dashboard',
		),
		//-------------
		'Pembatas-Live' => array(
			"FriendlyName" => "(*)", 
			"Type" => 'hidden',
			"Size" => "64",
			"Value" => '',
			"Description" => '<span style="color:red;font-weight:bold;">Production Params</span>',
		),
		//-------------
		# Live
		'MallId_Live' => array(
            'FriendlyName' => '<span style="color:red;font-weight:bold;">(*)</span>Production: Mall ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Mall ID here',
        ),
		'ChainMerchant_Live' => array(
            'FriendlyName' => '<span style="color:red;font-weight:bold;">(*)</span>Production: Chain Merchant Name (Default is: NA)',
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'NA',
            'Description' => 'Enter Chain Merchant Name (Default is: NA)',
        ),
		'SharedKey_Live' => array(
			'FriendlyName' => '<span style="color:red;font-weight:bold;">(*)</span>Production: Shared Key',
			'Type' => 'text',
			'Size' => '25',
			'Default' => '',
			'Description' => 'Enter Shared Key you got from DOKU Dashboard',
		),
		//-------------
		'Pembatas-Global-Params' => array(
			"FriendlyName" => "", 
			"Type" => 'hidden',
			"Size" => "64",
			"Value" => '',
			"Description" => '<span style="color:blue;font-weight:bold;">Global Params</span>',
		),
		//-------------
		# GLOBAL
		// the dropdown field type renders a select menu of options (LOGGER)
        'Log-Enabled' => array(
            'FriendlyName' => "Use Logger on Transaction Log? (Billing &gt; Gateway Log)", 
			'Type' => "yesno",
			'Description' => "Tick this box to enable Logging", 
        ),
        // the dropdown field type renders a select menu of options
        'Environment' => array(
            'FriendlyName' => 'Environment Mode',
            'Type' => 'dropdown',
            'Options' => array(
                'sandbox' => 'Sandbox',
                'live' => 'Production',
            ),
            'Description' => 'Choose environment, sandbox or production',
        ),
		"EDU-Enabled" => array(
			'FriendlyName' => "Use EDU?", 
			'Type' => "yesno",
			'Description' => "Tick this box to enable EDU", 
		),
		"Identify-Enabled" => array(
			'FriendlyName' => "Use Identify?", 
			'Type' => "yesno",
			'Description' => "Tick this box to enable DOKU Identify", 
		),
		####################
		"PaymentCheck-Enabled" => array(
			'FriendlyName' => "<span style='font-weight:bold;'>Enable Payment Check During Notify and Redirect</span>", 
			'Type' => "yesno",
			'Description' => "Tick this box to enable Payment Check (<span style='text-decoration:italic;color:green;'>We recomended you enabled payment-check to verified payment for security purpose</span>)", 
		),
		'URL-Notify' => array(
			"FriendlyName" => "Enter your URL Notify to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokusubscription_DokuAdmin::$notify,
			"Description" => dokusubscription_DokuAdmin::$notify,
		),
		'URL-Redirect' => array(
			"FriendlyName" => "Enter your URL Redirect to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokusubscription_DokuAdmin::$redirect,
			"Description" => dokusubscription_DokuAdmin::$redirect,
		),
		/*
		'URL-Review' => array(
			"FriendlyName" => "Enter your URL Review to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokusubscription_DokuAdmin::$review,
			"Description" => dokusubscription_DokuAdmin::$review,
		),
		'URL-Identify' => array(
			"FriendlyName" => "Enter your URL Identify to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokusubscription_DokuAdmin::$identify,
			"Description" => dokusubscription_DokuAdmin::$identify,
		),
		'URL-RegSubscription' => array(
			"FriendlyName" => "Enter your URL Subscription Get Amount to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokusubscription_DokuAdmin::$regsubscription,
			"Description" => dokusubscription_DokuAdmin::$regsubscription,
		),
		'URL-RegUpdate' => array(
			"FriendlyName" => "Enter your URL Register Update Redirect to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokusubscription_DokuAdmin::$regupdate,
			"Description" => dokusubscription_DokuAdmin::$regupdate,
		),
		'URL-RegUpdateNotify' => array(
			"FriendlyName" => "Enter your URL Update Notify to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokusubscription_DokuAdmin::$regupdatenotify,
			"Description" => dokusubscription_DokuAdmin::$regupdatenotify,
		),
		*/
		//--------------------
		'Local-Api-Admin-Username' => array(
			'FriendlyName'	=> '(*) WHMCS Admin Username for using WHMCS::localAPI().',
			'Type'			=> 'text',
			'Size'			=> '22',
			'Default'		=> '',
			'Description'	=> 'Enter your WHMCS Admin Username for using WHMCS::localAPI().',
		),
		//----------------------------------------------------------------------------
    );
	//------------------------------------------------------
	return $configs;
}
/**
// Subscription 
// Fill Credit Card
*/
//function dokusubscription_capture($params) {
function dokusubscription_link($params) {
	$Log_Enabled = FALSE;
	if (isset($params['Log-Enabled'])) {
		$Log_Enabled = ((strtolower($params['Log-Enabled']) == 'on') ? TRUE : FALSE);
	}
	$htmlReturn = "";
	$htmlForm = "";
	$htmlFormSubmit = "";
	$rawdata = array();
	$LocalApiAdminUsername = (isset($params['Local-Api-Admin-Username']) ? $params['Local-Api-Admin-Username'] : '');
	$localApi = array(
		'command' 	=> 'GetOrders', //'GetInvoice',
		'data'		=> array(
			//'id' 				=> '', // Later updated on review case
			'invoiceid' 		=> '', # Later updated on review case
		),
		'username'	=> $LocalApiAdminUsername, // Optional for WHMCS 7.2 and later
	);
	// Error GLOBAL
	$error = false;
	$error_msg = array();
	//---------------------------
	$Environment = (isset($params['Environment']) ? $params['Environment'] : 'sandbox'); // Sandbox as Default-Environment
	if (!is_string($Environment) && ((!is_array($Environment)) || (!is_object($Environment)))) {
		if (strtolower($Environment) === strtolower('live')) {
			// Gateway Configuration Parameters
			$MallId = (isset($params['MallId_Live']) ? $params['MallId_Live'] : '');
			$ShopName = (isset($params['ShopName_Live']) ? $params['ShopName_Live'] : '');
			$ChainMerchant = (isset($params['ChainMerchant_Live']) ? $params['ChainMerchant_Live'] : 'NA');
			$SharedKey = (isset($params['SharedKey_Live']) ? $params['SharedKey_Live'] : '');
		} else {
			// Gateway Configuration Parameters
			$MallId = (isset($params['MallId']) ? $params['MallId'] : '');
			$ShopName = (isset($params['ShopName']) ? $params['ShopName'] : '');
			$ChainMerchant = (isset($params['ChainMerchant']) ? $params['ChainMerchant'] : 'NA');
			$SharedKey = (isset($params['SharedKey']) ? $params['SharedKey'] : '');
		}
	} else {
		// Gateway Configuration Parameters
		$MallId = (isset($params['MallId']) ? $params['MallId'] : '');
		$ShopName = (isset($params['ShopName']) ? $params['ShopName'] : '');
		$ChainMerchant = (isset($params['ChainMerchant']) ? $params['ChainMerchant'] : 'NA');
		$SharedKey = (isset($params['SharedKey']) ? $params['SharedKey'] : '');
	}
	$LocalApiAdminUsername = (isset($params['Local-Api-Admin-Username']) ? $params['Local-Api-Admin-Username'] : '');
    //$ExchangeCurrency = (isset($params['ExchangeCurrency']) ? $params['ExchangeCurrency'] : '');
	//---------
	// Create DokuPayment Instance
	//---------
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
	##
	# DokuPayment Instance
	##
	$DokuPayment = new dokusubscription_DokuPayment($DokuConfigs);
	$rawdata['params'] = $params;
	//-----------------------------------------------------
	// Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];
    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
	$fullname = $params['clientdetails']['fullname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];
    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];
	// Tokenized Creditcard Parameters
	$cc_number 		= (isset($params['cardnum']) ? $params['cardnum'] : '');
	$cc_exp 		= array(
		'month'			=> (isset($params['cardexp']) ? substr($params['cardexp'], 0, 2) : ''),
		'year'			=> (isset($params['cardexp']) ? substr($params['cardexp'], 2, 2) : ''),
	);
	$cc_cvv			= (isset($params['cccvv']) ? $params['cccvv'] : '');
	$cc_type		= (isset($params['cardtype']) ? $params['cardtype'] : '');
	$cc_lastfour	= (isset($params['cardlastfour']) ? $params['cardlastfour'] : '');
	$cc_cardnum		= (isset($params['cardnum']) ? $params['cardnum'] : '');
	# URL Of Payment-Request
	//---------------------------------------------------
	$DateObject = $DokuPayment->get_datetime_object("Asia/Bangkok");
	// Create input to DokuPayment Instance
	$params_input = array(
		'transaction_id'			=> "{$DateObject->format('YmdHis')}{$params['invoiceid']}", // Create unique request_id
		'transaction_currency'		=> strtoupper(substr($params['currency'], 0, 2)),
		'transaction_datetime'		=> $DateObject->format('YmdHis'),
		'transaction_session'		=> (isset($params['clientdetails']['uuid']) ? $params['clientdetails']['uuid'] : ''),
		'amount_total'				=> 0,
	);
	$params_input['transaction_session'] = md5($params_input['transaction_session']);
	$params_input['transaction_customerid'] = (isset($params['clientdetails']['userid']) ? $params['clientdetails']['userid'] : '');
	$params_input['transaction_billnumber'] = $params_input['transaction_id'];
	# Temporary for fees items, nex from $logsync['item_lists']
	###########################################################
	$params_input['items'] = Array();
	$item_invoices = array(
		'item_id' 				=> (isset($params['invoicenum']) ? $params['invoicenum'] : 1),
		'order_price' 			=> (isset($params['amount']) ? $params['amount'] : 0),
		'order_unit'			=> 1,
		'order_item_name'		=> $params['description'],
	);
	array_push($params_input['items'], $item_invoices);
	foreach ($params_input['items'] as $val) {
		$params_input['amount_total'] += ($val['order_price'] * $val['order_unit']);
	}
	$user_input = array(
		'name' 		=> $params['clientdetails']['fullname'], // Should be an user input tmn account
		'email' 	=> $params['clientdetails']['email'], 	// Should be an user input tmn email or myarena email
		'shipping_address'	=> array(							// Input or generate randomly or API to MyArena Account?
			'forename'					=> $params['clientdetails']['lastname'],
			'surname'					=> $params['clientdetails']['firstname'],
			'fullname'					=> $params['clientdetails']['fullname'],
			'email'						=> $params['clientdetails']['email'],
			'phone'						=> $params['clientdetails']['phonenumber'],
			'SHIPPING_ADDRESS'			=> '',
			'SHIPPING_CITY'				=> $params['clientdetails']['city'],
			'SHIPPING_STATE'			=> $params['clientdetails']['state'],
			'SHIPPING_COUNTRY'			=> $params['clientdetails']['country'],
			'SHIPPING_ZIPCODE'			=> $params['clientdetails']['postcode'],
			'ADDITIONALDATA'			=> $params['description'],
		),
	);
	$user_input['shipping_address']['SHIPPING_ADDRESS'] .= (isset($params['clientdetails']['address1']) ? $params['clientdetails']['address1'] : '');
	$user_input['shipping_address']['SHIPPING_ADDRESS'] .= (isset($params['clientdetails']['address2']) ? ((strlen($params['clientdetails']['address2']) > 0) ? " {$params['clientdetails']['address2']}" : '') : '');
	$user_input['tokenization'] = array(
		'CUSTOMERID'				=> $params['clientdetails']['userid'],
		'TOKENID'					=> (isset($params['token']) ? $params['token'] : $cc_number),
	);
	$user_input['credit_card'] = array(
		'CARDNUMBER'				=> $cc_number,
		'EXPIRYDATE'				=> "{$cc_exp['year']}{$cc_exp['month']}",
		'CVV2'						=> $cc_cvv,
		'CC_NAME'					=> $user_input['name'],
	);
	//----------------------------
	// Get Invoices Data
	//----------------------------
	$OrderData = FALSE;
	$InvoiceData = FALSE;
	if (isset($localApi['data']['id'])) {
		unset($localApi['data']['id']);
	}
	#if ((__METHOD__) == 'dokusubscription_capture') {
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
	#}
	$localApi['command'] = 'GetInvoice';
	$localApi['data']['invoiceid']	= (isset($params['invoiceid']) ? $params['invoiceid'] : '');
	$InvoiceData = localAPI($localApi['command'], $localApi['data'], $localApi['username']);
	$Invoice_BillDetail = "";
	if (isset($InvoiceData['items']['item'])) {
		if (count($InvoiceData['items']['item']) > 0) {
			foreach ($InvoiceData['items']['item'] as $keval) {
				$Invoice_BillDetail .= (isset($keval['description']) ? $keval['description'] : '');
			}
		}
	}
	
	/*
	if (!$error) {
		$retDebug = "<pre>";
		$retDebug .= print_r($InvoiceData, true);
		return $retDebug;
	}
	*/
	
	$Invoice_BillCycle = 0;
	if (!$error) {
		if (isset($OrderData['lineitems']['lineitem'][0]['billingcycle'])) {
			switch (strtolower($OrderData['lineitems']['lineitem'][0]['billingcycle'])) {
				case 'monthly':
					$Invoice_BillCycle = 1;
				break;
				case 'quarterly':
					$Invoice_BillCycle = 3;
				break;
				case 'semi-annually':
					$Invoice_BillCycle = 6;
				break;
				case 'annually':
					$Invoice_BillCycle = 12;
				break;
				case 'biennially':
					$Invoice_BillCycle = 24;
				break;
				case 'triennially':
					$Invoice_BillCycle = 36;
				break;
			}
		}
	}
	if (!$error) {
		$params_input['subscription'] = array(
			'CUSTOMERID'			=> (isset($InvoiceData['userid']) ? $InvoiceData['userid'] : ''),
			'BILLNUMBER'			=> '', // Generate later
			'BILLDETAIL'			=> substr($Invoice_BillDetail, 0, 256),
			'BILLTYPE'				=> 'S', // Shopping as default
			'STARTDATE'				=> (isset($InvoiceData['date']) ? $InvoiceData['date'] : ''),
			'ENDDATE'				=> (isset($InvoiceData['enddate']) ? $InvoiceData['enddate'] : 'NA'),
			'EXECUTETYPE'			=> 'DATE', // DAY / DATE / FULLDATE
			'EXECUTEDATE'			=> '',
			'EXECUTEMONTH'			=> '',
			'FLATSTATUS'			=> '',
			'REGISTERAMOUNT'		=> '',
		);
		#### Create Date Time Object ####
		//DateTime::createFromFormat('Y-m-d', date('Y-m-d'));
		$DateObject = $DokuPayment->get_datetime_object("Asia/Bangkok");
		$params_input['subscription']['BILLNUMBER'] = $DateObject->format('YmdHi');
		$params_input['subscription']['BILLNUMBER'] .= (isset($InvoiceData['invoiceid']) ? $InvoiceData['invoiceid'] : '');
		$params_input['subscription']['STARTDATE'] = DateTime::createFromFormat('Y-m-d', $params_input['subscription']['STARTDATE']);
		$params_input['subscription']['STARTDATE'] = $params_input['subscription']['STARTDATE']->format('Ymd');
		// Make ENDDATE
		$DateSubscribe = DateTime::createFromFormat('Y-m-d', date('Y-m-d'));
		switch ($Invoice_BillCycle) {
			case 1:
				$DateSubscribe->add(new DateInterval('P1M'));
			break;
			case 3:
				$DateSubscribe->add(new DateInterval('P3M'));
			break;
			case 6:
				$DateSubscribe->add(new DateInterval('P6M'));
			break;
			case 12:
				$DateSubscribe->add(new DateInterval('P12M'));
			break;
			case 24:
				$DateSubscribe->add(new DateInterval('P2Y'));
			break;
			case 36:
				$DateSubscribe->add(new DateInterval('P3Y'));
				//$DateSubscribe->add(new DateInterval('P3Y0M0DT0H0M0S'));
			break;
		}
		$params_input['subscription']['ENDDATE'] = $DateSubscribe->format('Ymd');
	}
	$Invoice_BillCycle_Months = array();
	if (!$error) {
		// Make lists of EXECUTEMONTH
		if ($Invoice_BillCycle > 0) {
			switch ($Invoice_BillCycle) {
				case 1:
				case 3:
				case 6:
				case 12:
					for ($i_bill = 0; $i_bill <= $Invoice_BillCycle; $i_bill++) {
						$DateSubscribeMonths = new DateTime(date('Y-m-d'));
						$DateSubscribeMonths->add(new DateInterval("P{$i_bill}M"));
						$Invoice_BillCycle_Months[] = $DateSubscribeMonths->format('M');
					}
				break;
				case 24:
				case 36:
					for ($i_bill = 0; $i_bill <= 12; $i_bill++) {
						$DateSubscribeMonths = DateTime::createFromFormat('Y-m-d', date('Y-m-d'));
						$DateSubscribeMonths->add(new DateInterval("P{$i_bill}M"));
						$Invoice_BillCycle_Months[] = $DateSubscribeMonths->format('M');
					}
				break;
			}
		}
		$params_input['subscription']['EXECUTEMONTH'] = implode(",", $Invoice_BillCycle_Months);
	}
	if (!$error) {
		// filter BILLDETAIL String
		$params_input['subscription']['BILLDETAIL'] = filter_var($params_input['subscription']['BILLDETAIL'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
		$params_input['subscription']['BILLDETAIL'] = preg_replace("/[^a-zA-Z0-9]+/", "", $params_input['subscription']['BILLDETAIL']);
		$params_input['subscription']['BILLDETAIL'] = str_replace('(', '', $params_input['subscription']['BILLDETAIL']);
		$params_input['subscription']['BILLDETAIL'] = str_replace(')', '', $params_input['subscription']['BILLDETAIL']);
		$params_input['subscription']['BILLDETAIL'] = str_replace('#', '', $params_input['subscription']['BILLDETAIL']);
		switch (strtoupper($params_input['subscription']['EXECUTETYPE'])) {
			case 'DAY':
				$params_input['subscription']['EXECUTEDATE'] = strtoupper($DateObject->format('D'));
			break;
			case 'DATE':
				$params_input['subscription']['EXECUTEDATE'] = $DateObject->format('j');
				if ((int)$params_input['subscription']['EXECUTEDATE'] > 28) {
					$params_input['subscription']['EXECUTEDATE'] = '28';
				}
			break;
			case 'FULLDATE':
				$params_input['subscription']['EXECUTEDATE'] = $DateObject->format('Ymd');
			break;
		}
				
		$params_input['subscription']['FLATSTATUS'] = 'TRUE'; // Static (Fix) Value
		$params_input['subscription']['REGISTERAMOUNT'] = sprintf('%.2f', $InvoiceData['total']);
	}
	//-------------------------------------------------------
	if (!$error) {
		
		# Create Subscription Payment Structure
		$createPaymentStructure = $DokuPayment->create_payment_structure('subscription', 0, $params_input, $user_input, $params_input['items'], $user_input['shipping_address']);
		$PaymentStructure = array(
			'words_sha1'			=> (isset($createPaymentStructure['WORDS']) ? $createPaymentStructure['WORDS'] : ""),
			'words_string'			=> (isset($createPaymentStructure['WORDS_STRING']) ? $createPaymentStructure['WORDS_STRING'] : ""),
			'session_id'			=> (isset($createPaymentStructure['SESSIONID']) ? $createPaymentStructure['SESSIONID'] : ""),
		);
		// Generate Unique SESSIONID Every single payment
		$PaymentStructure['session_id'] = sha1("{$PaymentStructure['session_id']}{$DokuPayment->generate_transaction_id()}");
		if ((isset($createPaymentStructure['WORDS_STRING'])) && (isset($createPaymentStructure['SESSIONID']))) {
			unset($createPaymentStructure['WORDS_STRING']);
			$PaymentStructureJson = json_encode($createPaymentStructure, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			// do any log ....
			$makepayment_data = json_decode($PaymentStructureJson, true, 512, JSON_BIGINT_AS_STRING);
			ksort($makepayment_data);
			if ($Log_Enabled) {
				logTransaction($moduleName, $makepayment_data, "(WHMCS) REQUEST-PAYMENT");
			}
			$rawdata['payment_form'] = $makepayment_data;
			//--------------------------------------------------------------------------------------------------------------
			if (isset($rawdata['payment_form']['TOKENID'])) {
				unset($rawdata['payment_form']['TOKENID']);
			}
			if (isset($rawdata['payment_form']['PAYMENTTYPE'])) {
				unset($rawdata['payment_form']['PAYMENTTYPE']);
			}
			//--------------------------------------------------------------------------------------------------------------
			$rawdata['payment_input'] = http_build_query($rawdata['payment_form']);
			// CURL AND SAVE LOG
			$headers = $DokuPayment->create_curl_headers($DokuPayment->dokupayment_headers);
			//$curlData = $DokuPayment->create_curl_request('POST', $DokuPayment->endpoint['request'], 'WHMCS Payment Plugin (http://www.doku.com)', $headers, $rawdata['payment_form'], 30);
			//logTransaction($moduleName, $curlData, "(WHMCS) SUBSCRIPTION-REQUEST");
			$htmlForm .= "<p><a id='submit-doku-form' href='javascript:;'><img alt='doku-checkout' src='{$systemUrl}" . ((substr($systemUrl, -1) == '/') ? '' : '/') . "modules/gateways/dokuhosted/assets/doku-button.png' /></a></p>";
			$htmlForm .= '<form name="RegistrationNewCard" id="RegistrationNewCard" class="font-reg" action="'. $DokuPayment->endpoint['request'] . '" method="post">';
			if (count($rawdata['payment_form']) > 0) {
				foreach ($rawdata['payment_form'] as $key => $val) {
					//$htmlForm .= "{$key}: <input type='text' id='{$key}' name='{$key}' value='{$val}' /><br/>";
					$htmlForm .= "<input type='hidden' id='{$key}' name='{$key}' value='{$val}' />";
				}
			}
			$htmlForm .= '</form>';
			// Js submit form
			$htmlFormSubmit .= '<script type="text/javascript">';
			$htmlFormSubmit .= "var submit_button = document.getElementById('submit-doku-form');";
			$htmlFormSubmit .= "submit_button.addEventListener('click', function() {
				var form_object = document.getElementById('RegistrationNewCard');
				form_object.submit();
			}, false)";
			$htmlFormSubmit .= '</script>';
		}
	}
	if (!$error) {
		$htmlReturn = "{$htmlForm}{$htmlFormSubmit}";
		return $htmlReturn;
	} else {
		//$htmlReturn = print_r($error_msg, true);
		return false;
	}
	
}
function dokusubscription_nolocalcc($params) {
	return true;
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function dokusubscription_refund($params) {
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fees' => $feeAmount,
    );
}

/**
 * Cancel subscription.
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/subscription-management/
 *
 * @return array Transaction response status
 */
function dokusubscription_cancelSubscription($params) {
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Subscription Parameters
    $subscriptionIdToCancel = $params['subscriptionID'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to cancel subscription and interpret result

    return array(
        // 'success' if successful, any other value for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
    );
}


if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


