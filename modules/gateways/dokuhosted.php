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
 
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

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
function dokuhosted_MetaData() {
    return array(
        'DisplayName' => 'DOKU Merchant',
        'APIVersion' => '1.2', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

//---------------
// URL show to admin page
Class dokuhosted_DokuAdmin {
	public static $notify = false;
	public static $redirect = false;
	public static $review = false;
	public static $identify = false;
	public static $DOKUCONFIGS;
	public function __construct($configs = array()) {
		$this->set_protocol(self::getDokuPaymentProtocol());
		$this->protocol = (isset($this->protocol) ? $this->protocol : $this->get_protocol());
		$this->set_hostname(self::getDokuPaymentHost());
		$this->hostname = (isset($this->hostname) ? $this->hostname : $this->get_hostname());
		$this->set_url('notify', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokuhosted.php?page=notify");
		$this->set_url('redirect', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokuhosted.php?page=redirect");
		$this->set_url('review', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokuhosted.php?page=review");
		$this->set_url('identify', "{$this->protocol}{$this->hostname}modules/gateways/callback/dokuhosted.php?page=identify");
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
		}
	}
}
// Include @DOKUCONFIGS
$configfile = (dirname(__FILE__). '/dokuhosted/dokuhosted-config.php');
if (!file_exists($configfile)) {
	Exit("Required configs file does not exists.");
}
require($configfile);
if (!isset($DOKUCONFIGS)) {
	Exit("There is no DOKUCONFIGS of included config file.");
}
$DokuAdmin = new dokuhosted_DokuAdmin($DOKUCONFIGS);
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
require('dokuhosted/dokuhosted-whmcs.php');
function dokuhosted_config() {
    $configs = array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'DOKU Merchant',
			'Description' => 'DOKU is an online payment platform that processes payments through many different methods, including Credit Card, ATM Transfer and DOKU Wallet. Check us out on http://www.doku.com',
        ),
		'Pembatas-Description-Payment-Gateway' => array(
			'FriendlyName' => '',
			'Type' => 'hidden',
			'Size' => '72',
            'Default' => '',
			'Description' => '<img src="https://doku.com/themes/default/images/logo-doku-merchant.png" align="left" style="padding-right:12px;" /> DOKU is an online payment platform that processes payments through many different methods, including Credit Card, ATM Transfer and DOKU Wallet. Check us out on <span style="color:red;"><a href="http://www.doku.com">http://www.doku.com</a></span>',
		),
		'Description-Payment-Gateway' => array(
			'FriendlyName' => 'Description',
			'Type' => 'textarea',
			'Rows' => '8',
            'Cols' => '72',
			'Default' => 'DOKU is an online payment platform that processes payments through many different methods, including Credit Card, ATM Transfer and DOKU Wallet. Check us out on http://www.doku.com',
			'Description' => '',
		),
        // a text field type allows for single line text input
		# Development
		//-------------
		'Pembatas-Sandbox' => array(
			"FriendlyName" => "(*)", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => '',
			"Description" => '<span style="color:green;font-weight:bold;">Development Params</span>',
		),
		//-------------
        'MallId' => array(
            'FriendlyName' => 'Development: Mall ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Mall ID here',
        ),
		'ChainMerchant' => array(
            'FriendlyName' => 'Development: Chain Merchant (Default is: NA)',
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'NA',
            'Description' => 'Enter Chain Merchant (Default is: NA)',
        ),
		'SharedKey' => array(
			'FriendlyName' => 'Development: Shared Key',
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
			"Default" => '',
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
			"Default" => '',
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
                'sandbox' => 'Development',
                'live' => 'Production',
            ),
            'Description' => 'Choose environment development or production',
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
		
		"PaymentCheck-Enabled" => array(
			'FriendlyName' => "<span style='font-weight:bold;'>Enable Payment Check During Notify and Redirect</span>", 
			'Type' => "yesno",
			'Description' => "Tick this box to enable Payment Check (<span style='text-decoration:italic;color:green;'>We recomended you enabled payment-check to verified payment</span>)", 
		),
		'URL-Notify' => array(
			"FriendlyName" => "Enter your URL Notify to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokuhosted_DokuAdmin::$notify,
			"Description" => dokuhosted_DokuAdmin::$notify,
		),
		'URL-Redirect' => array(
			"FriendlyName" => "Enter your URL Redirect to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokuhosted_DokuAdmin::$redirect,
			"Description" => dokuhosted_DokuAdmin::$redirect,
		),
		'URL-Review' => array(
			"FriendlyName" => "Enter your URL Review to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokuhosted_DokuAdmin::$review,
			"Description" => dokuhosted_DokuAdmin::$review,
		),
		'URL-Identify' => array(
			"FriendlyName" => "Enter your URL Identify to DOKU Dasboard", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => dokuhosted_DokuAdmin::$identify,
			"Description" => dokuhosted_DokuAdmin::$identify,
		),
		//
		'Local-Api-Admin-Username' => array(
			'FriendlyName'	=> '(*) WHMCS Admin Username for using WHMCS::localAPI().',
			'Type'			=> 'text',
			'Size'			=> '22',
			'Default'		=> '',
			'Description'	=> 'Enter your WHMCS Admin Username for using WHMCS::localAPI().',
		),
		//----------------------------------------------------------------------------
		'Pembatas-Payment-Channel' => array(
			"FriendlyName" => "<span style='color:black;font-weight:bold;'>(*) Select Enabled Payment Channel</span>", 
			"Type" => 'hidden',
			"Size" => "64",
			"Default" => '',
			"Description" => '<div style="width:100%;border-bottom:2px solid #ff7200;padding-bottom:4px;"><span style="color:#ff7200;font-weight:bold;text-decoration:none;">Please tick to enable payment channels</span></div>',
		),
		//-----------------------------------------------------------------------------
	
    );
	// Add payment-channels
	$getDokuPaymentChannels = dokuhosted_DokuAdmin::getDokuPaymentConfigs('channels');
	if (count($getDokuPaymentChannels) > 0) {
		foreach ($getDokuPaymentChannels as $val) {
			$payment_channel_key = "Payment-Channel-";
			$payment_channel_key .= (isset($val[0]) ? $val[0] : '00');
			$configs[$payment_channel_key] = array(
				'FriendlyName'		=> '',
				'Type'				=> 'yesno',
				'Default'				=> (isset($val[0]) ? $val[0] : '00'),
				'Description'		=> (isset($val[1]) ? $val[1] : 'All'),
			);
		}
	}
	//------------------------------------------------------------------------------------
	// INSTALLMENT PAYMENT (Credit Card)
	//------------------------------------------------------------------------------------
	$configs["Installment-Enabled"] = array(
		'FriendlyName'	=> 'Use Installment',
		'Type'			=> 'dropdown',
		'Options'		=> array(
			'SALE'					=> 'SALE',
			'ONUS'					=> 'ONUS INSTALLMENT',
			'OFFUS'					=> 'OFFUS INSTALLEMENT',
		),
	);
	//------------------------------------------------------
	$configs['Pembatas-Payment-Acquirer-Bank'] = array(
		"FriendlyName" => "<span style='color:black;font-weight:bold;'>::</span>", 
		"Type" => 'hidden',
		"Size" => "64",
		"Default" => '',
		"Description" => '<div style="width:100%;border-bottom:2px solid #ff7200;padding-bottom:4px;"><span style="color:#778899;font-weight:bold;text-decoration:none;">ACQUIRER BANK FOR INSTALLMENT</span></div>',
	);
	//--------------
	// GLOBAL OBJECT FOR PROMO OF EACH BANK TENORS
	$GLOBAL_ACQUIRER_BANKS = array(
		'onus'		=> [],
		'offus'		=> [],
	);
	# Get Banks
	$getDokuPaymentAcquirers = dokuhosted_DokuAdmin::getDokuPaymentConfigs('acquirers');
	//-----------------------
	// ON US INSTALLMENT
	//-----------------------
	$configs['Pembatas-Payment-Acquirer-Bank-Onus'] = array(
		"FriendlyName" => "<span style='color:black;font-weight:bold;'>(*) Payment Acquirer Banks (ON US)</span>", 
		"Type" => 'hidden',
		"Size" => "64",
		"Default" => '',
		"Description" => '<div style="width:100%;border-bottom:2px solid #ff7200;padding-bottom:4px;"><span style="color:#ff7200;font-weight:bold;text-decoration:none;">Please tick to enable payment acquirers bank</span></div>',
	);
	if (isset($getDokuPaymentAcquirers['onus'])) {
		if (count($getDokuPaymentAcquirers['onus']) > 0) {
			foreach ($getDokuPaymentAcquirers['onus'] as $keval) {
				$bank_acquirer_code = (isset($keval[0]) ? $keval[0] : '100'); // 100 (BNI as Default Bank)
				$bank_acquirer_name = (isset($keval[1]) ? $keval[1] : 'Bank BNI'); // 100 (BNI as Default Bank)
				$GLOBAL_ACQUIRER_BANKS['onus'][] = array(
					'code'			=> $bank_acquirer_code,
					'name'			=> $bank_acquirer_name,
					'tenor'			=> array(),
				);
			}
		}
	}
	if (count($GLOBAL_ACQUIRER_BANKS['onus']) > 0) {
		foreach ($GLOBAL_ACQUIRER_BANKS['onus'] as $k => $val) {
			# Get Tenors
			$getDokuPaymentTenors = dokuhosted_DokuAdmin::getDokuPaymentConfigs('tenors');
			if (count($getDokuPaymentTenors) > 0) {
				foreach ($getDokuPaymentTenors as $keval) {
					$tenor_acquirer_code = (isset($keval[0]) ? $keval[0] : '03'); // (03 Months as Default Tenor)
					$tenor_acquirer_name = (isset($keval[1]) ? $keval[1] : '03 Bulan'); // (03 Months as Default Tenor)
					$val_tenor = array(
						'code'		=> $tenor_acquirer_code,
						'name'		=> $tenor_acquirer_name,
						'promo'		=> '',
					);
					$GLOBAL_ACQUIRER_BANKS['onus'][$k]['tenor'][] = $val_tenor;
				}
			}
		}
	}
	# Build into @configs
	if (count($GLOBAL_ACQUIRER_BANKS['onus']) > 0) {
		foreach ($GLOBAL_ACQUIRER_BANKS['onus'] as $key => $val) {
			// Bank installment key
			$bank_installment_key = "Bank-Installment-Acquirer-Onus-";
			$bank_installment_key .= (isset($val['code']) ? $val['code'] : '100'); // 100 = BNI
			# add to configs
			$configs[$bank_installment_key] = array(
				'FriendlyName'		=> '',
				'Type'				=> 'yesno',
				'Default'				=> (isset($val['code']) ? $val['code'] : '100'), // 100 = BNI
				'Description'		=> "<span style='font-weight:bold;'>" . (isset($val['name']) ? $val['name'] : 'Bank BNI..?') . "</span>", // BNI as Default
			);
			$bank_installment_code = (isset($val['code']) ? (string)$val['code'] : '');
			if (strtolower($bank_installment_code) === strtolower('100')) {
				$configs["{$bank_installment_key}-00"] = array(
					'FriendlyName'		=> 'Installment Promo Code For ' . (isset($val['name']) ? $val['name'] : 'Bank BNI..?'),
					'Type'				=> 'text',
					'Size'				=> '22',
					'Default'			=> '',
					'Description'		=> 'Insert Promo Code For ' . (isset($val['name']) ? $val['name'] : 'Bank BNI..?'),
				);
			}
			// Bank promo for each available tenors
			if (isset($val['tenor'])) {
				if (count($val['tenor']) > 0) {
					foreach ($val['tenor'] as $keval) {
						$bank_installment_tenor = $bank_installment_key;
						$bank_installment_tenor .= "-";
						$bank_installment_tenor .= (isset($keval['code']) ? $keval['code'] : '03'); // 03 Months as default
						# add to configs (tenor)
						$configs[$bank_installment_tenor] = array(
							'FriendlyName'		=> '',
							'Type'				=> 'yesno',
							'Default'			=> (isset($keval['code']) ? $keval['code'] : '03'),
							'Description'		=> (isset($keval['name']) ? $keval['name'] : '03 Bulan'),
						);
						$bank_installment_tenor_promo = "{$bank_installment_tenor}-PromoId";
						# add to configs (promo)
						// except BNI
						if (strtolower($bank_installment_code) !== strtolower('100')) {
							$configs[$bank_installment_tenor_promo] = array(
								'FriendlyName'		=> 'Installment Promo Code For ' . (isset($keval['name']) ? $keval['name'] : '03 Bulan'),
								'Type'				=> 'text',
								'Size'				=> '22',
								'Default'			=> '',
								'Description'		=> 'Insert Promo Code For ' . (isset($keval['name']) ? $keval['name'] : '03 Bulan') . ' On ' . (isset($val['name']) ? $val['name'] : 'Bank BNI..?'),
							);
						} else {
							$configs[$bank_installment_tenor_promo] = array(
								'FriendlyName'		=> '',
								'Type'				=> 'hidden',
								'Size'				=> '22',
								'Default'			=> '001',
								'Description'		=> '',
							);
						}
					}
				}
			}
		}
	}
	//-----------------------
	// OFF US INSTALLMENT
	//-----------------------
	$configs['Pembatas-Payment-Acquirer-Bank-Offus'] = array(
		"FriendlyName" => "<span style='color:black;font-weight:bold;'>(*) Payment Acquirer Banks (OFF US)</span>", 
		"Type" => 'hidden',
		"Size" => "64",
		"Value" => '',
		"Description" => '<div style="width:100%;border-bottom:2px solid #ff7200;padding-bottom:4px;"><span style="color:#ff7200;font-weight:bold;text-decoration:none;">Please tick to enable payment acquirers bank</span></div>',
	);
	if (isset($getDokuPaymentAcquirers['offus'])) {
		if (count($getDokuPaymentAcquirers['offus']) > 0) {
			foreach ($getDokuPaymentAcquirers['offus'] as $keval) {
				$bank_acquirer_code = (isset($keval[0]) ? $keval[0] : '100'); // 100 (BNI as Default Bank)
				$bank_acquirer_name = (isset($keval[1]) ? $keval[1] : 'Bank BNI'); // 100 (BNI as Default Bank)
				$GLOBAL_ACQUIRER_BANKS['offus'][] = array(
					'code'			=> $bank_acquirer_code,
					'name'			=> $bank_acquirer_name,
					'tenor'			=> array(),
				);
			}
		}
	}
	if (count($GLOBAL_ACQUIRER_BANKS['offus']) > 0) {
		foreach ($GLOBAL_ACQUIRER_BANKS['offus'] as $k => $val) {
			# Get Tenors
			$getDokuPaymentTenors = dokuhosted_DokuAdmin::getDokuPaymentConfigs('tenors');
			if (count($getDokuPaymentTenors) > 0) {
				foreach ($getDokuPaymentTenors as $keval) {
					$tenor_acquirer_code = (isset($keval[0]) ? $keval[0] : '03'); // (03 Months as Default Tenor)
					$tenor_acquirer_name = (isset($keval[1]) ? $keval[1] : '03 Bulan'); // (03 Months as Default Tenor)
					$val_tenor = array(
						'code'		=> $tenor_acquirer_code,
						'name'		=> $tenor_acquirer_name,
						'promo'		=> '',
					);
					$GLOBAL_ACQUIRER_BANKS['offus'][$k]['tenor'][] = $val_tenor;
				}
			}
		}
	}
	# Build into @configs
	if (count($GLOBAL_ACQUIRER_BANKS['offus']) > 0) {
		foreach ($GLOBAL_ACQUIRER_BANKS['offus'] as $key => $val) {
			// Bank installment key
			$bank_installment_key = "Bank-Installment-Acquirer-Offus-";
			$bank_installment_key .= (isset($val['code']) ? $val['code'] : '100'); // 100 = BNI
			// For OFFUS because same $val['code'] create unique identify by index loop
			$bank_installment_key .= "-{$key}";
			# add to configs
			$configs[$bank_installment_key] = array(
				'FriendlyName'		=> '',
				'Type'				=> 'yesno',
				'Default'				=> (isset($val['code']) ? $val['code'] : '100'), // 100 = BNI
				'Description'		=> "<span style='font-weight:bold;'>" . (isset($val['name']) ? $val['name'] : 'Bank BNI..?') . "</span>", // BNI as Default
			);
			$bank_installment_code = (isset($val['code']) ? (string)$val['code'] : '');
			if (strtolower($bank_installment_code) === strtolower('000')) {
				$configs["{$bank_installment_key}-00"] = array(
					'FriendlyName'		=> 'Installment Promo Code For ' . (isset($val['name']) ? $val['name'] : 'Bank BNI..?'),
					'Type'				=> 'text',
					'Size'				=> '22',
					'Default'			=> '',
					'Description'		=> 'Insert Promo Code For ' . (isset($val['name']) ? $val['name'] : 'Bank BNI..?'),
				);
			}
			// Bank promo for each available tenors
			if (isset($val['tenor'])) {
				if (count($val['tenor']) > 0) {
					foreach ($val['tenor'] as $keval) {
						$bank_installment_tenor = $bank_installment_key;
						$bank_installment_tenor .= "-";
						$bank_installment_tenor .= (isset($keval['code']) ? $keval['code'] : '03'); // 03 Months as default
						# add to configs (tenor)
						$configs[$bank_installment_tenor] = array(
							'FriendlyName'		=> '',
							'Type'				=> 'yesno',
							'Default'			=> (isset($keval['code']) ? $keval['code'] : '03'),
							'Description'		=> (isset($keval['name']) ? $keval['name'] : '03 Bulan'),
						);
						$bank_installment_tenor_promo = "{$bank_installment_tenor}-PromoId";
						# add to configs (promo)
						// except BNI
						if (strtolower($bank_installment_code) !== strtolower('000')) {
							$configs[$bank_installment_tenor_promo] = array(
								'FriendlyName'		=> 'Installment Promo Code For ' . (isset($keval['name']) ? $keval['name'] : '03 Bulan'),
								'Type'				=> 'text',
								'Size'				=> '22',
								'Default'			=> '',
								'Description'		=> 'Insert Promo Code For ' . (isset($keval['name']) ? $keval['name'] : '03 Bulan') . ' On ' . (isset($val['name']) ? $val['name'] : 'Bank BNI..?'),
							);
						} else {
							$configs[$bank_installment_tenor_promo] = array(
								'FriendlyName'		=> '',
								'Type'				=> 'hidden',
								'Size'				=> '22',
								'Default'			=> '001',
								'Description'		=> '',
							);
						}
					}
				}
			}
		}
	}
	return $configs;
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function dokuhosted_link($params) {
	$Log_Enabled = FALSE;
	if (isset($params['Log-Enabled'])) {
		$Log_Enabled = ((strtolower($params['Log-Enabled']) == 'on') ? TRUE : FALSE);
	}
	/*
	echo "<pre>";
	print_r($params);
	exit;
	*/
	$Environment = (isset($params['Environment']) ? $params['Environment'] : 'sandbox'); // Sandbox as Default-Environment
	if (is_string($Environment)) {
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
	$DokuPayment = new dokuhosted_DokuPayment($DokuConfigs);
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
	// POSTFIELDS
	$postfields = array();
    $postfields['username'] = $email;
    $postfields['invoice_id'] = $invoiceId;
    $postfields['description'] = $description;
    $postfields['amount'] = $amount;
    $postfields['currency'] = $currencyCode;
    $postfields['first_name'] = $firstname;
    $postfields['last_name'] = $lastname;
	$postfields['fullname'] = $fullname;
    $postfields['email'] = $email;
    $postfields['address1'] = $address1;
    $postfields['address2'] = $address2;
    $postfields['city'] = $city;
    $postfields['state'] = $state;
    $postfields['postcode'] = $postcode;
    $postfields['country'] = $country;
    $postfields['phone'] = $phone;
    $postfields['callback_url'] = $systemUrl . ((substr($systemUrl, -1) == '/') ? '' : '/') . 'modules/gateways/callback/' . $moduleName . '.php';
    $postfields['return_url'] = $returnUrl;
	// Create Unique invoice_id
	//===============================
	#$postfields['invoice_id'] = $postfields['invoice_id'] . '_' . (date('YmdHis', time()));
	$postfields['invoice_id'] = "{$postfields['invoice_id']}_{$postfields['invoice_id']}";
	//===============================
	####postfields['invoice_id'] = $postfields['invoice_id'];
	// AVAILABLE PAYMENT CHANNELS
	$PaymentChannels = array();
	$getDokuPaymentChannels = dokuhosted_DokuAdmin::getDokuPaymentConfigs('channels');
	if (count($getDokuPaymentChannels) > 0) {
		foreach ($getDokuPaymentChannels as $keval) {
			if (isset($keval[0])) {
				if (isset($params["Payment-Channel-{$keval[0]}"])) {
					if ($params["Payment-Channel-{$keval[0]}"] == 'on') {
						$channel_code = $keval[0];
						$channel_name = (isset($keval[1]) ? $keval[1] : '--Un-Named Payment Channel--');
						$PaymentChannels[] = array(
							'code'				=> $channel_code,
							'name'				=> $channel_name,
						);
					}
				}
			}
		}
	}
	// AVAILABLE INSTALLMENT BANKS
	//--------------
	// MAKE GLOBAL_ACQUIRER_BANKS
	// GLOBAL OBJECT FOR PROMO OF EACH BANK TENORS
	$GLOBAL_ACQUIRER_BANKS = array(
		'onus'		=> [],
		'offus'		=> [],
	);
	//$GLOBAL_ACQUIRER_BANKS = array();
	# Get Banks
	$getDokuPaymentAcquirers = dokuhosted_DokuAdmin::getDokuPaymentConfigs('acquirers');
	//-----------------------
	// ON US INSTALLMENT
	//-----------------------
	if (isset($getDokuPaymentAcquirers['onus'])) {
		if (count($getDokuPaymentAcquirers['onus']) > 0) {
			foreach ($getDokuPaymentAcquirers['onus'] as $key => $keval) {
				if (isset($keval[0])) {
					if (isset($params["Bank-Installment-Acquirer-Onus-{$keval[0]}"])) {
						if ($params["Bank-Installment-Acquirer-Onus-{$keval[0]}"] == 'on') {
							$bank_code = (isset($keval[0]) ? $keval[0] : '100'); // 100 (BNI as Default Bank)
							$bank_name = (isset($keval[1]) ? $keval[1] : 'Bank BNI'); // 100 (BNI as Default Bank)
							$GLOBAL_ACQUIRER_BANKS['onus'][] = array(
								'index'			=> "Bank-Installment-Acquirer-Onus-{$keval[0]}",
								'code'			=> $bank_code,
								'name'			=> $bank_name,
								'tenor'			=> array(),
							);
						}
					}
				}
			}
		}
	}
	if (count($GLOBAL_ACQUIRER_BANKS['onus']) > 0) {
		foreach ($GLOBAL_ACQUIRER_BANKS['onus'] as $k => $val) {
			# Get Tenors
			$getDokuPaymentTenors = dokuhosted_DokuAdmin::getDokuPaymentConfigs('tenors');
			if ((string)$val['code'] !== strval('100')) { // If NOT BNI
				if (count($getDokuPaymentTenors) > 0) {
					foreach ($getDokuPaymentTenors as $keval) {
						$tenor_code = (isset($keval[0]) ? $keval[0] : '03'); // (03 Months as Default Tenor)
						$tenor_name = (isset($keval[1]) ? $keval[1] : '03 Bulan'); // (03 Months as Default Tenor)
						if (isset($params["{$val['index']}-{$tenor_code}"])) {
							if ($params["{$val['index']}-{$tenor_code}"] == 'on') {
								$val_tenor = array(
									'code'		=> $tenor_code,
									'name'		=> $tenor_name,
									'promo'		=> false,
								);
								if (isset($params["{$val['index']}-{$tenor_code}-PromoId"])) {
									if (!empty($params["{$val['index']}-{$tenor_code}-PromoId"]) && (strlen($params["{$val['index']}-{$tenor_code}-PromoId"]) > 0)) {
										$val_tenor['promo'] = (string)$params["{$val['index']}-{$tenor_code}-PromoId"];
									}
								}
								// Add to GLOBAL_ACQUIRER_BANKS
								if ($val_tenor['promo'] && ($val_tenor['promo'] != '')) {
									$GLOBAL_ACQUIRER_BANKS['onus'][$k]['tenor'][] = $val_tenor;
								}
							}
						}
					}
				}
			} else {
				if (count($getDokuPaymentTenors) > 0) {
					foreach ($getDokuPaymentTenors as $keval) {
						$tenor_code = (isset($keval[0]) ? $keval[0] : '03'); // (03 Months as Default Tenor)
						$tenor_name = (isset($keval[1]) ? $keval[1] : '03 Bulan'); // (03 Months as Default Tenor)
						if (isset($params["{$val['index']}-{$tenor_code}"])) {
							if ($params["{$val['index']}-{$tenor_code}"] == 'on') {
								$val_tenor = array(
									'code'		=> $tenor_code,
									'name'		=> $tenor_name,
									'promo'		=> false,
								);
								if (isset($params["{$val['index']}-00"])) { // Only BNI using 00 as Tenor_Code
									if (!empty($params["{$val['index']}-00"]) && (strlen($params["{$val['index']}-00"]) > 0)) {
										$val_tenor['promo'] = (string)$params["{$val['index']}-00"];
									}
								}
								// Add to GLOBAL_ACQUIRER_BANKS
								if ($val_tenor['promo'] && ($val_tenor['promo'] != '')) {
									$GLOBAL_ACQUIRER_BANKS['onus'][$k]['tenor'][] = $val_tenor;
								}
							}
						}
					}
				}
			}
		}
	}
	//-----------------------
	// OFF US INSTALLMENT
	//-----------------------
	if (isset($getDokuPaymentAcquirers['offus'])) {
		if (count($getDokuPaymentAcquirers['offus']) > 0) {
			foreach ($getDokuPaymentAcquirers['offus'] as $key => $keval) {
				if (isset($keval[0])) {
					if (isset($params["Bank-Installment-Acquirer-Offus-{$keval[0]}-{$key}"])) {
						if ($params["Bank-Installment-Acquirer-Offus-{$keval[0]}-{$key}"] == 'on') {
							$bank_code = (isset($keval[0]) ? $keval[0] : '100'); // 100 (BNI as Default Bank)
							$bank_name = (isset($keval[1]) ? $keval[1] : 'Bank BNI'); // 100 (BNI as Default Bank)
							$GLOBAL_ACQUIRER_BANKS['offus'][] = array(
								'index'			=> "Bank-Installment-Acquirer-Offus-{$keval[0]}-{$key}",
								'code'			=> $bank_code,
								'name'			=> $bank_name,
								'tenor'			=> array(),
							);
						}
					}
				}
			}
		}
	}
	if (count($GLOBAL_ACQUIRER_BANKS['offus']) > 0) {
		foreach ($GLOBAL_ACQUIRER_BANKS['offus'] as $k => $val) {
			# Get Tenors
			$getDokuPaymentTenors = dokuhosted_DokuAdmin::getDokuPaymentConfigs('tenors');
			if ((string)$val['code'] !== strval('000')) { // If NOT BNI
				if (count($getDokuPaymentTenors) > 0) {
					foreach ($getDokuPaymentTenors as $keval) {
						$tenor_code = (isset($keval[0]) ? $keval[0] : '03'); // (03 Months as Default Tenor)
						$tenor_name = (isset($keval[1]) ? $keval[1] : '03 Bulan'); // (03 Months as Default Tenor)
						if (isset($params["{$val['index']}-{$tenor_code}"])) {
							if ($params["{$val['index']}-{$tenor_code}"] == 'on') {
								$val_tenor = array(
									'code'		=> $tenor_code,
									'name'		=> $tenor_name,
									'promo'		=> false,
								);
								if (isset($params["{$val['index']}-{$tenor_code}-PromoId"])) {
									if (!empty($params["{$val['index']}-{$tenor_code}-PromoId"]) && (strlen($params["{$val['index']}-{$tenor_code}-PromoId"]) > 0)) {
										$val_tenor['promo'] = (string)$params["{$val['index']}-{$tenor_code}-PromoId"];
									}
								}
								// Add to GLOBAL_ACQUIRER_BANKS
								if ($val_tenor['promo'] && ($val_tenor['promo'] != '')) {
									$GLOBAL_ACQUIRER_BANKS['offus'][$k]['tenor'][] = $val_tenor;
								}
							}
						}
					}
				}
			} else {
				if (count($getDokuPaymentTenors) > 0) {
					foreach ($getDokuPaymentTenors as $keval) {
						$tenor_code = (isset($keval[0]) ? $keval[0] : '03'); // (03 Months as Default Tenor)
						$tenor_name = (isset($keval[1]) ? $keval[1] : '03 Bulan'); // (03 Months as Default Tenor)
						if (isset($params["{$val['index']}-{$tenor_code}"])) {
							if ($params["{$val['index']}-{$tenor_code}"] == 'on') {
								$val_tenor = array(
									'code'		=> $tenor_code,
									'name'		=> $tenor_name,
									'promo'		=> false,
								);
								if (isset($params["{$val['index']}-00"])) { // Only BNI using 00 as Tenor_Code
									if (!empty($params["{$val['index']}-00"]) && (strlen($params["{$val['index']}-00"]) > 0)) {
										$val_tenor['promo'] = (string)$params["{$val['index']}-00"];
									}
								}
								// Add to GLOBAL_ACQUIRER_BANKS
								if ($val_tenor['promo'] && ($val_tenor['promo'] != '')) {
									$GLOBAL_ACQUIRER_BANKS['offus'][$k]['tenor'][] = $val_tenor;
								}
							}
						}
					}
				}
			}
		}
	}
	//---------------------------------------------
	# Make Front-End of available installment banks
	$BankInstallmentActive = array(
		'onus'				=> array(),
		'offus'				=> array(),
	);
	$InstallmentBanks = array();
	// From On-us
	if (count($GLOBAL_ACQUIRER_BANKS['onus']) > 0) {
		foreach ($GLOBAL_ACQUIRER_BANKS['onus'] as $k => $keval) {
			/**
			 * @string index
			 * @string code
			 * @string name
			 * @array tenor
			 **** @string code
				* @string name
			 **** @string promo
			*/
			if (isset($keval['tenor']) && (isset($keval['code']))) {
				$installment_code = $keval['code'];
				if (count($keval['tenor']) > 0) {
					array_push($BankInstallmentActive['onus'], $keval);
					//$InstallmentBanks[] = $keval;
				}
			}
		}
	}
	// From Off-us
	if (count($GLOBAL_ACQUIRER_BANKS['offus']) > 0) {
		foreach ($GLOBAL_ACQUIRER_BANKS['offus'] as $k => $keval) {
			/**
			 * @string index
			 * @string code
			 * @string name
			 * @array tenor
			 **** @string code
				* @string name
			 **** @string promo
			*/
			if (isset($keval['tenor']) && (isset($keval['code']))) {
				$installment_code = $keval['code'];
				if (count($keval['tenor']) > 0) {
					array_push($BankInstallmentActive['offus'], $keval);
					//$InstallmentBanks[] = $keval;
				}
			}
		}
	}
	//-------------------------
	// Get Active
	//----
	if (strtoupper($params['Installment-Enabled']) === strtoupper('ONUS')) {
		if (count($BankInstallmentActive['onus']) > 0) {
			foreach ($BankInstallmentActive['onus'] as $keval) {
				$InstallmentBanks[] = $keval;
			}
		}
	} else if (strtoupper($params['Installment-Enabled']) === strtoupper('OFFUS')) {
		if (count($BankInstallmentActive['offus']) > 0) {
			foreach ($BankInstallmentActive['offus'] as $keval) {
				$InstallmentBanks[] = $keval;
			}
		}
	}
	
	
	//Log for orders
	if ($Log_Enabled) {
		logTransaction($moduleName, $params, "(WHMCS-params)");
	}
	
	###########################
	# HTML Output
	###########################
	$returnHtml = "";
	# URL Of Payment-Request
	//---------------------------------------------------
	// Start Of Form
	$returnHtml .= '<form name="formRedirect" id="formRedirect" action="' . $DokuPayment->endpoint['request'] . '" method="post">';
	// Create input to DokuPayment Instance
	$params_input = array(
		'transaction_id'			=> $postfields['invoice_id'], // Create unique request_id
		'transaction_currency'		=> strtoupper(substr($postfields['currency'], 0, 2)),
		'transaction_datetime'		=> date('YmdHis', time()),
		'transaction_session'		=> (isset($params['clientdetails']['uuid']) ? $params['clientdetails']['uuid'] : ''),
		'amount_total'				=> 0,
	);
	$params_input['transaction_session'] = md5($params_input['transaction_session']);
	# Temporary for fees items, nex from $logsync['item_lists']
	###########################################################
	$params_input['items'] = Array();
	$item_invoices = array(
		'item_id' 				=> (isset($params['invoicenum']) ? $params['invoicenum'] : 1),
		'order_price' 			=> (isset($params['amount']) ? $params['amount'] : 0),
		'order_unit'			=> 1,
		'order_item_name'		=> $postfields['description'],
	);
	array_push($params_input['items'], $item_invoices);
	foreach ($params_input['items'] as $val) {
		$params_input['amount_total'] += ($val['order_price'] * $val['order_unit']);
	}
	$user_input = array(
		'name' 		=> $postfields['fullname'], // Should be an user input tmn account
		'email' 	=> $postfields['email'], 	// Should be an user input tmn email or myarena email
		'shipping_address'	=> array(							// Input or generate randomly or API to MyArena Account?
			'forename'					=> $postfields['first_name'],
			'surname'					=> $postfields['last_name'],
			'fullname'					=> $postfields['fullname'],
			'email'						=> $postfields['email'],
			'phone'						=> $postfields['phone'],
			'SHIPPING_ADDRESS'			=> '',
			'SHIPPING_CITY'				=> $postfields['city'],
			'SHIPPING_STATE'			=> $postfields['state'],
			'SHIPPING_COUNTRY'			=> $postfields['country'],
			'SHIPPING_ZIPCODE'			=> $postfields['postcode'],
			'ADDITIONALDATA'			=> $postfields['description'],
		),
	);
	$user_input['shipping_address']['SHIPPING_ADDRESS'] .= (isset($postfields['address1']) ? $postfields['address1'] : '');
	$user_input['shipping_address']['SHIPPING_ADDRESS'] .= (isset($postfields['address2']) ? ((strlen($postfields['address2']) > 0) ? " {$postfields['address2']}" : '') : '');
	# Create Verify Payment Structure
	$createPaymentStructure = $DokuPayment->create_payment_structure('create', 0, $params_input, $user_input, $params_input['items'], $user_input['shipping_address']);
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
		// Sanitize URL
		$makepayment_data = $DokuPayment->sanitize_string_parameter($makepayment_data);
		if ($Log_Enabled) {
			logTransaction($moduleName, $makepayment_data, "(WHMCS) REQUEST-PAYMENT");
		}
		//------------------
		// return HTML
		if (count($makepayment_data) > 0) {
			foreach ($makepayment_data as $key => $val) {
				$returnHtml .= "<input type='hidden' id='{$key}' name='{$key}' value='{$val}' />";
			}
		}
	}
	// payment-channel selections
	if (count($PaymentChannels) > 0) {
		$returnHtml .= '<div class="small-text"><span style="font-weight:bold;">Select Payment Channel</span></div>';
		$returnHtml .= '<div class="small-text">';
			$returnHtml .= '<select id="PAYMENTCHANNEL" name="PAYMENTCHANNEL">';
				foreach ($PaymentChannels as $keval) {
					if (isset($keval['code']) && ($keval['code'] != '01')) {
						$returnHtml .= '<option value="' . (isset($keval['code']) ? (($keval['code'] != '00') ? $keval['code'] : '') : '') . '">';
						$returnHtml .= (isset($keval['name']) ? $keval['name'] : '-');
						$returnHtml .= '</option>';
					} else {
						if (count($InstallmentBanks) > 0) {
							$returnHtml .= '<option value="' . (isset($keval['code']) ? (($keval['code'] != '00') ? $keval['code'] : '') : '') . '">';
							$returnHtml .= (isset($keval['name']) ? $keval['name'] : '-');
							$returnHtml .= '</option>';
						}
					}
				}
			$returnHtml .= '</select>';
		$returnHtml .= '</div>';
		// Another params for payment-channel selected
		$add_html = "";
		$add_html .= '<div id="add-html-cc" style="display:none;">';
		$add_html .= '<input type="hidden" id="INSTALLMENT_ACQUIRER" name="INSTALLMENT_ACQUIRER" value="'.(isset($InstallmentBanks[0]['code']) ? $InstallmentBanks[0]['code'] : '').'" />';
		$add_html .= '<input type="hidden" id="TENOR" name="TENOR" value="'.(isset($InstallmentBanks[0]['tenor']['code']) ? $InstallmentBanks[0]['tenor']['code'] : '').'" />';
		$add_html .= '<input type="hidden" id="PROMOID" name="PROMOID" value="'.(isset($InstallmentBanks[0]['tenor']['promo']) ? $InstallmentBanks[0]['tenor']['promo'] : '').'" />';
		$add_html .= '</div>';
		// ADD TO @$returnHtml
		$returnHtml .= $add_html;
    }
	// payment installment acquirer banks, promo, and tenor
	//--------------
	$returnHtml .= '<div id="html-installment-payment" style="display:none;">';
		if (count($InstallmentBanks) > 0) {
			$returnHtml .= '<div class="small-text"><span style="font-weight:bold;">Select Acquirer Bank</span></div>';
			$returnHtml .= '<div class="small-text">';
				$returnHtml .= '<select id="bank_installment_acquirers" name="bank_installment_acquirers">';
					$tenor_printout = array();
					// Default -- Select Acquirer Bank --
					$returnHtml .= '<option value="">-- Select Acquirer Bank --</option>';
					foreach ($InstallmentBanks as $k => $keval) {
						/*
						 * @string index
						 * @string code
						 * @string name
						 * @array tenor
						 **** @string code
							* @string name
						 **** @string promo
						*/
						$bank_code = (isset($keval['code']) ? strval($keval['code']) : '100');
						if (strtoupper($params['Installment-Enabled']) === strtoupper('OFFUS')) {
							$bank_code .= "-{$k}";
						}
						$tenor_printout[$bank_code] = array();
						$returnHtml .= '<option value="' . $bank_code . '">';
						$returnHtml .= (isset($keval['name']) ? $keval['name'] : '-');
						$returnHtml .= '</option>';
						// tenor-printout
						if (isset($keval['tenor'])) {
							if (count($keval['tenor']) > 0) {
								foreach ($keval['tenor'] as $tenorVal) {
									$tenorKey = (isset($tenorVal['code']) ? $tenorVal['code'] : '03');
									$tenorKey = (string)$tenorKey;
									$tenor_printout[$bank_code][$tenorKey] = "";
									$tenor_printout[$bank_code][$tenorKey] .= (isset($tenorVal['code']) ? strval($tenorVal['code']) : '');
									$tenor_printout[$bank_code][$tenorKey] .= "|";
									$tenor_printout[$bank_code][$tenorKey] .= (isset($tenorVal['promo']) ? strval($tenorVal['promo']) : '');
									$tenor_printout[$bank_code][$tenorKey] .= "|";
									$tenor_printout[$bank_code][$tenorKey] .= (isset($tenorVal['name']) ? strval($tenorVal['name']) : '');
								}
							}
						}
					}
				$returnHtml .= '</select>';
			$returnHtml .= '</div>';
		}
		// Tenor placeholder
		$returnHtml .= '<div class="small-text"><span style="font-weight:bold;">Select Tenor</span></div>';
		$returnHtml .= '<div class="small-text">';
			$returnHtml .= '<select id="TENOR_PROMO" name="TENOR_PROMO">';
				$returnHtml .= '<option value=\'00\'>-- Select Tenor --</option>';
			$returnHtml .= '</select>';
		$returnHtml .= '</div>';
	$returnHtml .= '</div>';
	// End of Form
	$returnHtml .= '</form>';
	// Create padding
	$returnHtml .= '<div class="row">';
	$returnHtml .= '<div style="margin-top:12px;">&nbsp;</div>';
	$returnHtml .= '</div>';
	// DOKU Description
	$dokuDesc = (isset($params['Description-Payment-Gateway']) ? $params['Description-Payment-Gateway'] : '');
	$dokuDesc = filter_var($dokuDesc, FILTER_SANITIZE_STRING);
	$dokuDesc = (is_string($dokuDesc) ? $dokuDesc : '');
	//===========================
	// DOKU Description as NULL
	//===========================
	$dokuDesc = "";
	if (strlen($dokuDesc) > 0) {
		$returnHtml .= '<div class="row">';
		$returnHtml .= '<div class="small-text"><img alt="doku" src="http://doku.com/themes/default/images/logo-doku-merchant.png" align="left" width="120" height-"34" style="padding-right:12px;" />' . $dokuDesc . '</div>';
		$returnHtml .= '</div>';
	}
	
	//Javascript for payment-channel-placeholder
	$js = "";
	$js .= '<script type="text/javascript">';
	$js .= 'var form_object = document.getElementById("formRedirect");';
	$js .= 'var form_installment = document.getElementById("html-installment-payment");';
	$js .= 'var payment_channels = document.getElementById("PAYMENTCHANNEL");';
	$js .= 'var bank_installment_acquirers = document.getElementById("bank_installment_acquirers");';
	$js .= 'var add_installment_acquirer = document.getElementById("INSTALLMENT_ACQUIRER"), add_installment_tenor = document.getElementById("TENOR"), add_installment_promoid = document.getElementById("PROMOID");';
	$js .= 'var add_html_cc = document.getElementById("add-html-cc");';
	$js .= 'var form_paymenttype = document.getElementById("PAYMENTTYPE");';
	$js .= 'var form_tenor_promo = document.getElementById("TENOR_PROMO");';
	$js .= 'payment_channels.addEventListener("change", function() {
				if (this.value == "") {
					form_paymenttype.value = "SALE";
					add_html_cc.style.display = "none";
				} else {
					if (this.value == "01") {
						//payment_channels.value = "15";
						form_paymenttype.value = "'.$params['Installment-Enabled'].'";
						//------------------------------------
						add_html_cc.style.display = null;
						var add_html_cc_name = document.createElement("input");
						add_html_cc_name.setAttribute("type", "hidden");
						add_html_cc_name.setAttribute("name", "CC_NAME");
						add_html_cc_name.setAttribute("id", "CC_NAME");
						add_html_cc_name.setAttribute("value", "'.$postfields['fullname'].'");
						form_object.appendChild(add_html_cc_name);
						//--------------------------------------
						form_installment.style.display = null;
					} else if (this.value == "16") {
						form_paymenttype.value = "SALE";
						add_html_cc.style.display = "none";
						form_installment.style.display = "none";
						var add_html_cc_name = document.createElement("input");
						add_html_cc_name.setAttribute("type", "hidden");
						add_html_cc_name.setAttribute("name", "CUSTOMERID");
						add_html_cc_name.setAttribute("id", "CUSTOMERID");
						add_html_cc_name.setAttribute("value", "' . (isset($params['clientdetails']['userid']) ? $params['clientdetails']['userid'] : '') . '");
						form_object.appendChild(add_html_cc_name);
					} else {
						form_paymenttype.value = "SALE";
						add_html_cc.style.display = "none";
						form_installment.style.display = "none";
						// remove not mandatory
						/*
						if (add_installment_acquirer != null) {
							add_installment_acquirer.remove();
						}
						if (add_tenor != null) {
							add_tenor.remove();
						}
						if (add_promoid != null) {
							add_promoid.remove();
						}
						*/
					}
				}
				//alert("form_paymenttype: " + form_paymenttype.value + ", payment_channels: " + this.value);
			}, true);';
	
	/*
	 * @string index
	 * @string code
	 * @string name
	 * @array tenor
	 **** @string code
		* @string name
	 **** @string promo
	*/
	$js .= 'var available_tenor = [];';
	if (isset($tenor_printout) && (count($tenor_printout) > 0)) {
		foreach ($tenor_printout as $k => $tenorval) {
			$av_key = $k;
			if (is_array($tenorval)) {
				/*
				$js_k = explode("-", $k);
				if (isset($js_k[1])) {
					$av_key = ((int)$js_k[0] - (int)$js_k[1]);
				} else {
					$av_key = $k;
				}
				*/
				$js .= 'available_tenor["' . $av_key . '"] = ' . json_encode($tenorval) . ';';
			}
		}
	}
	$tenorJs = '';
	$js .= 'bank_installment_acquirers.addEventListener("change", function() {
			//var placeholder_tenor = document.getElementById("TENOR");
			//var placeholder_promo = document.getElementById("PROMOID");
			var tenor_innerhtml = "";
			var this_select_val = this.value;
			';
			//$tenorJs = "tenor_innerhtml += '<option value=\'00\'>-- Select Tenor --</option>';";
			$tenorJs .= "if ('03' in available_tenor[this.value]) {
				var tenor_code_promo_name_tiga = available_tenor[this.value]['03'].split('|');
				tenor_innerhtml += '<option value=\'' + tenor_code_promo_name_tiga[0] + '|' + tenor_code_promo_name_tiga[1] + '\'>';
				tenor_innerhtml += tenor_code_promo_name_tiga[2];
				tenor_innerhtml += '</option>';
			}";
			$tenorJs .= "if ('06' in available_tenor[this.value]) {
				var tenor_code_promo_name_enam = available_tenor[this.value]['06'].split('|');
				tenor_innerhtml += '<option value=\'' + tenor_code_promo_name_enam[0] + '|' + tenor_code_promo_name_enam[1] + '\'>';
				tenor_innerhtml += tenor_code_promo_name_enam[2];
				tenor_innerhtml += '</option>';
			}";
			$tenorJs .= "if ('12' in available_tenor[this.value]) {
				var tenor_code_promo_name_duabelas = available_tenor[this.value]['12'].split('|');
				tenor_innerhtml += '<option value=\'' + tenor_code_promo_name_duabelas[0] + '|' + tenor_code_promo_name_duabelas[1] + '\'>';
				tenor_innerhtml += tenor_code_promo_name_duabelas[2];
				tenor_innerhtml += '</option>';
			}";
			$tenorJs .= 'form_tenor_promo.innerHTML = tenor_innerhtml;';
		$js .= $tenorJs;	
			
		$js .= '
		
				var acquirer_promo = this.value.split("|");
				add_installment_acquirer.value = acquirer_promo[0];
				//add_installment_promoid.value = acquirer_promo[1];
			}, false);';
	$js .= '</script>';
	/*
			for(var k in result) {
				console.log(k, result[k]);
			}
	--Create an input type dynamically.
    var element = document.createElement("input");
	--Assign different attributes to the element.
    element.setAttribute("type", "hidden");
    element.setAttribute("name", "TENOR");
    element.setAttribute("id", "TENOR");
	element.setAttribute("value", "TENOR-VALUE");
	*/
	// Submit button
	$returnHtml .= '<a href="javascript:;" onclick="javascript:onSubmitToPayment();"><img alt="'.$params['langpaynow'].'" src="/modules/gateways/dokuhosted/assets/doku-button.png" width="120" height="34" title="'.$params['langpaynow'].'" /></a>';
	
	// Javascript for submit
	$returnHtml .= $js;
	$js_submit = '<script type="text/javascript">';
	$js_submit .= 'function onSubmitToPayment() {
			var payment_channels = document.getElementById("PAYMENTCHANNEL");
			var payment_channels_value = payment_channels.value;
			var installment_tenor_promo = document.getElementById("TENOR_PROMO");
			var add_installment_acquirer = document.getElementById("INSTALLMENT_ACQUIRER"), add_installment_tenor = document.getElementById("TENOR"), add_installment_promoid = document.getElementById("PROMOID");
			if (payment_channels_value == "01") {
			 payment_channels.value = "15";
			 var isi_tenor_dan_promo = installment_tenor_promo.value.split("|");
			 add_installment_tenor.value = isi_tenor_dan_promo[0];
			 add_installment_promoid.value = isi_tenor_dan_promo[1];
			 var isi_installment_acquirer = add_installment_acquirer.value.split("-");
			 add_installment_acquirer.value = isi_installment_acquirer[0];
			} else {
			 var add_installment_acquirer = document.getElementById("INSTALLMENT_ACQUIRER");
			 var add_installment_tenor = document.getElementById("TENOR");
			 var add_installment_promoid = document.getElementById("PROMOID");
			 var add_installment_tenorpromo = document.getElementById("TENOR_PROMO");
			 add_installment_acquirer.remove();
			 add_installment_tenor.remove();
			 add_installment_promoid.remove();
			 add_installment_tenorpromo.remove();
			 
			 add_installment_acquirer.removeAttribute("name");
			 add_installment_tenor.removeAttribute("name");
			 add_installment_promoid.removeAttribute("name");
			 add_installment_tenorpromo.removeAttribute("name");
			}
			document.getElementById("formRedirect").submit();
		   }';
	$js_submit .= '</script>';
			
	$returnHtml .= $js_submit;
	
    return $returnHtml;
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
function dokuhosted_refund($params) {
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
function dokuhosted_cancelSubscription($params)
{
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



