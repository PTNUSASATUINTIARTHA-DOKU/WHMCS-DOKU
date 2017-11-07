<?php
class dokuhosted_DokuPayment {
	private static $instance;
	public $endpoint;
	protected $mallid;
	protected $shopname;
	protected $chainmerchant;
	protected $sharedkey;
	public $isofile;
	function __construct($configs = array()) {
		$dokupayment_enpoint = array(
			'sandbox'		=> array(
					'request'			=> 'https://staging.doku.com/Suite/Receive',
					'checkstatus'		=> 'https://staging.doku.com/Suite/CheckStatus',
					'void'				=> 'https://staging.doku.com/Suite/VoidRequest',
			),
			'live'			=> array(
					'request'			=> 'https://pay.doku.com/Suite/Receive',
					'checkstatus'		=> 'https://pay.doku.com/Suite/CheckStatus',
					'void'				=> 'https://pay.doku.com/Suite/VoidRequest',
			),
		);
		$this->isofile = (isset($configs['isofile']) ? $configs['isofile'] : '');
		if (file_exists($this->isofile)) {
			$this->isofile = realpath($this->isofile);
		} else {
			$this->isofile = (dirname(__FILE__) . '/assets/iso3166.json');
		}
		$this->endpoint = (isset($dokupayment_enpoint[$configs['endpoint']]) ? $dokupayment_enpoint[$configs['endpoint']] : false);
		$this->mallid = (isset($configs['merchant']['mallid']) ? $configs['merchant']['mallid'] : '');
		$this->shopname = (isset($configs['merchant']['shopname']) ? $configs['merchant']['shopname'] : '');
		$this->chainmerchant = (isset($configs['merchant']['chainmerchant']) ? $configs['merchant']['chainmerchant'] : '');
		$this->sharedkey = (isset($configs['merchant']['sharedkey']) ? $configs['merchant']['sharedkey'] : '');
		# Headers
		$this->set_dokupayment_headers();
		$this->add_dokupayment_headers('Content-type', 'application/x-www-form-urlencoded');
	}
	public static function init($configs) {
        if (!self::$instance) {
            self::$instance = new dokuhosted_DokuPayment($configs);
        }
        return self::$instance;
    }
	//---------------------
	function get_paymentchannel($code = 4) {
		$code = (int)$code;
		$paymentchannels = array(
			array('00', 'All Available Payment'),
			array('01', 'Credit Card Visa/Master IDR'),
			array('02', 'Mandiri ClickPay'),
			array('03', 'KlikBCA'),
			array('04', 'DOKU Wallet'),
			array('05', 'ATM Permata VA LITE'),
			array('06', 'BRI e-Pay'),
			array('07', 'ATM Permata VA'),
			array('08', 'Mandiri Multipayment LITE'),
			array('09', 'Mandiri Multipayment'),
			array('10', 'ATM Mandiri VA LITE'),
			array('11', 'ATM Mandiri VA'),
			//array('12', 'PayPal'),
			//array('13', 'BNI Debit Online (VCN)'),
			array('14', 'Alfamart'),
			//array('15', 'Credit Card Visa/Master Multi Currency'),
			array('16', 'Tokenization'),
			//array('18', 'KlikPayBCA'),
			array('19', 'CIMB Clicks'),
			array('20', 'PTPOS'),
			array('21', 'Sinarmas VA Full'),
			array('22', 'Sinarmas VA Lite'),
			array('23', 'MOTO'),
			//array('24', 'Klikpay BCA Debit'),
			array('25', 'Bank Muamalat Indonesia'),
			array('26', 'Danamon Online Banking'),
		);
		if (isset($paymentchannels[$code])) {
			return $paymentchannels[$code][0];
		}
		return $paymentchannels[0][0];
	}
	private function read_isofile($file) {
		$contents = '';
		if (!$handle = fopen($file, 'r')) {
			exit("Cannot read file");
		}
		while (!feof($handle)) {
			$contents .= fread($handle, 8192);
		}
		fclose($handle);
		return $contents;
	}
	public function get_countrycode($isoFile, $isoCountry = "ID", $index = 'numeric') {
		$countryData = json_decode($this->read_isofile($isoFile), true);
		if (isset($countryData[$isoCountry][$index])) {
			return $countryData[$isoCountry][$index];
		}
		return false;
	}
	//---------------------------
	function create_payment_structure($method, $paymentcode, $params_input, $user_input = array(), $item_data = array(), $shipping_data = null) {
		$PaymentStructure = array();
		switch(strtolower($method)) {
			case 'create':
			default:
				$items = array(
					'purchase'		=> 0,
					'currency'		=> (isset($params_input['transaction_currency']) ? $params_input['transaction_currency'] : 'ID'),
				);
				$create_words = array(
					'amount'				=> (isset($params_input['amount_total']) ? $params_input['amount_total'] : 0),
					'mallid'				=> $this->mallid,
					'sharedkey'				=> $this->sharedkey,
					'transaction_id'		=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'currency'				=> $items['currency'],
				);
				if (count($item_data) > 0) {
					foreach ($item_data as $val) {
						$items['purchase'] += (isset($val['order_price']) ? $val['order_price'] : 0);
					}
				}
				$PaymentStructure = array(
					'MALLID'				=> $this->mallid,
					'CHAINMERCHANT'			=> $this->chainmerchant,
					'AMOUNT'				=> sprintf("%.2f", floatval($create_words['amount'])),
					'PURCHASEAMOUNT'		=> sprintf("%.2f", floatval($items['purchase'])),
					'TRANSIDMERCHANT'		=> $create_words['transaction_id'],
					'PAYMENTTYPE'			=> 'SALE', // DEFAULT is "SALE"
					'WORDS_STRING'			=> $this->create_words($method, $create_words),
					'WORDS'					=> sha1($this->create_words($method, $create_words)),
					'REQUESTDATETIME' 		=> (isset($params_input['transaction_datetime']) ? $params_input['transaction_datetime'] : date('YmdHis')),
					'CURRENCY'				=> $this->get_countrycode($this->isofile, $create_words['currency']),
					'PURCHASECURRENCY'		=> $this->get_countrycode($this->isofile, $create_words['currency']),
					'SESSIONID'				=> (isset($params_input['transaction_session']) ? $params_input['transaction_session'] : ''),
					'NAME'					=> $user_input['name'],
					'EMAIL'					=> $user_input['email'],
					'BASKET'				=> $this->create_basket($method, $item_data),
					'PAYMENTCHANNEL'		=> $this->get_paymentchannel($paymentcode),
				);
				if (isset($shipping_data)) {
					$PaymentStructure['ADDITIONALDATA'] = (isset($user_input['shipping_address']['ADDITIONALDATA']) ? $user_input['shipping_address']['ADDITIONALDATA']: "");
					$PaymentStructure['SHIPPING_ADDRESS'] = (isset($user_input['shipping_address']['SHIPPING_ADDRESS']) ? $user_input['shipping_address']['SHIPPING_ADDRESS'] : "");
					$PaymentStructure['SHIPPING_CITY'] = (isset($user_input['shipping_address']['SHIPPING_CITY']) ? $user_input['shipping_address']['SHIPPING_CITY'] : "");
					$PaymentStructure['SHIPPING_STATE'] = (isset($user_input['shipping_address']['SHIPPING_STATE']) ? $user_input['shipping_address']['SHIPPING_STATE'] : "");
					$PaymentStructure['SHIPPING_COUNTRY'] = (isset($user_input['shipping_address']['SHIPPING_COUNTRY']) ? $user_input['shipping_address']['SHIPPING_COUNTRY'] : "");
					$PaymentStructure['SHIPPING_ZIPCODE'] = (isset($user_input['shipping_address']['SHIPPING_ZIPCODE']) ? $user_input['shipping_address']['SHIPPING_ZIPCODE'] : "");
					# New:
					$PaymentStructure['ADDRESS'] = $PaymentStructure['SHIPPING_ADDRESS'];
					$PaymentStructure['CITY'] = $PaymentStructure['SHIPPING_CITY'];
					$PaymentStructure['STATE'] = $PaymentStructure['SHIPPING_STATE'];
					$PaymentStructure['COUNTRY'] = $PaymentStructure['SHIPPING_COUNTRY'];
					$PaymentStructure['ZIPCODE'] = $PaymentStructure['SHIPPING_ZIPCODE'];
					#
					$PaymentStructure['HOMEPHONE'] = (isset($user_input['shipping_address']['phone']) ? $user_input['shipping_address']['phone'] : "");
					$PaymentStructure['MOBILEPHONE'] = (isset($user_input['shipping_address']['phone']) ? $user_input['shipping_address']['phone'] : "");
					$PaymentStructure['WORKPHONE'] = (isset($user_input['shipping_address']['phone']) ? $user_input['shipping_address']['phone'] : "");
				}
				## Remove specific payment channel if < 1
				if ((int)$paymentcode < 1) {
					unset($PaymentStructure['PAYMENTCHANNEL']);
				}
				$PaymentStructure['SESSIONID'] = substr($PaymentStructure['SESSIONID'], 0, 48);
				$PaymentStructure['MOBILEPHONE'] = substr($PaymentStructure['MOBILEPHONE'], 0, 12);
				$PaymentStructure['WORKPHONE'] = substr($PaymentStructure['WORKPHONE'], 0, 13);
			break;
			case 'check':
				$create_words = array(
					'mallid'				=> $this->mallid,
					'sharedkey'				=> $this->sharedkey,
					'transaction_id'		=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'session_id'			=> (isset($params_input['transaction_session']) ? $params_input['transaction_session'] : ''),
					'currency'				=> (isset($params_input['transaction_currency']) ? $params_input['transaction_currency'] : '360'),
					'currency_purchase'		=> (isset($params_input['transaction_currency_purchase']) ? $params_input['transaction_currency_purchase'] : '360'),
				);
				$PaymentStructure = array(
					'MALLID'				=> $this->mallid,
					'CHAINMERCHANT'			=> $this->chainmerchant,
					'TRANSIDMERCHANT'		=> $create_words['transaction_id'],
					'SESSIONID'				=> $create_words['session_id'],
					'WORDS_STRING'			=> $this->create_words($method, $create_words),
					'WORDS'					=> sha1($this->create_words($method, $create_words)),
					'PAYMENTCHANNEL'		=> $this->get_paymentchannel($paymentcode),
					'CURRENCY'				=> $create_words['currency'],
					'PURCHASECURRENCY'		=> $create_words['currency_purchase'],
				);
				if ((int)$paymentcode < 1) {
					unset($PaymentStructure['PAYMENTCHANNEL']);
				} else {
					// Not using payment-channel anymore
					unset($PaymentStructure['PAYMENTCHANNEL']);
				}
			break;
			case 'void':
				$create_words = array(
					'mallid'				=> $this->mallid,
					'sharedkey'				=> $this->sharedkey,
					'transaction_id'		=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'session_id'			=> (isset($params_input['transaction_session']) ? $params_input['transaction_session'] : ''),
					'currency'				=> (isset($params_input['transaction_currency']) ? $params_input['transaction_currency'] : '360'),
					'payment_channel'		=> (isset($params_input['transaction_channel']) ? $params_input['transaction_channel'] : ''),
				);
				
				##
				$PaymentStructure = array(
					'MALLID'				=> $this->mallid,
					'CHAINMERCHANT'			=> $this->chainmerchant,
					'TRANSIDMERCHANT'		=> $create_words['transaction_id'],
					'SESSIONID'				=> $create_words['session_id'],
					'WORDS_STRING'			=> $this->create_words($method, $create_words),
					'WORDS'					=> sha1($this->create_words($method, $create_words)),
					'PAYMENTCHANNEL'		=> $create_words['payment_channel'],
				);
			break;
			//-------
			case 'notify':
			case 'notify-checking':
				$create_words = array(
					'amount'				=> (isset($params_input['amount']) ? sprintf("%.2f", floatval($params_input['amount'])) : '0.00'),
					'mallid'				=> $this->mallid,
					'sharedkey'				=> $this->sharedkey,
					'transaction_id'		=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'result_msg'			=> (isset($params_input['result_msg']) ? $params_input['result_msg'] : ''), // SUCCESS
					'verify_status'			=> (isset($params_input['verify_status']) ? $params_input['verify_status'] : ''), // SUCCESS
					'currency'				=> (isset($params_input['transaction_currency']) ? $params_input['transaction_currency'] : '360'),
				);
				##
				$PaymentStructure = array(
					'MALLID'				=> $this->mallid,
					'CHAINMERCHANT'			=> $this->chainmerchant,
					'TRANSIDMERCHANT'		=> $create_words['transaction_id'],
					'SESSIONID'				=> $create_words['session_id'],
					'WORDS_STRING'			=> $this->create_words($method, $create_words),
					'WORDS'					=> sha1($this->create_words($method, $create_words)),
					'RESULTMSG'				=> $create_words['result_msg'],
					'VERIFYSTATUS'			=> $create_words['verify_status'],
				);
			break;
			//------
			case 'redirect':
			case 'redirect-checking':
				$create_words = array(
					'mallid'				=> $this->mallid,
					'sharedkey'				=> $this->sharedkey,
					'amount'				=> (isset($params_input['amount']) ? sprintf("%.2f", floatval($params_input['amount'])) : '0.00'),
					'transaction_id'		=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'transaction_words'		=> (isset($params_input['transaction_words']) ? $params_input['transaction_words'] : ''),
					'trans_status'			=> (isset($params_input['transaction_status']) ? (string)$params_input['transaction_status'] : ''), // 0000
					'payment_channel'		=> (isset($params_input['transaction_channel']) ? $params_input['transaction_channel'] : ''),
					'session_id'			=> (isset($params_input['transaction_session']) ? $params_input['transaction_session'] : ''),
					'payment_code'			=> (isset($params_input['transaction_code']) ? $params_input['transaction_code'] : ''),
					'currency'				=> (isset($params_input['transaction_currency']) ? $params_input['transaction_currency'] : '360'),
					'currency_purchase'		=> (isset($params_input['transaction_currency_purchase']) ? $params_input['transaction_currency_purchase'] : '360'),
				);
				##
				$PaymentStructure = array(
					'AMOUNT'				=> $create_words['amount'],
					'TRANSIDMERCHANT'		=> $create_words['transaction_id'],
					'WORDS_STRING'			=> $this->create_words($method, $create_words),
					'WORDS'					=> sha1($this->create_words($method, $create_words)),
					'WORDS_DOKU'			=> $create_words['transaction_words'],
					'STATUSCODE'			=> $create_words['trans_status'],
					'PAYMENTCHANNEL'		=> $create_words['payment_channel'],
					'SESSIONID'				=> $create_words['session_id'],
					'PAYMENTCODE'			=> $create_words['payment_code'],
					'CURRENCY'				=> $create_words['currency'],
					'PURCHASECURRENCY'		=> $create_words['currency_purchase'],
				);
			break;
			#######################################################
			# RECURRING
			#######################################################
			case 'recurring-create':
				$items = array(
					'purchase'		=> 0,
					'currency'		=> (isset($params_input['transaction_currency']) ? $params_input['transaction_currency'] : 'ID'),
				);
				$create_words = array(
					'amount'			=> (isset($params_input['amount_total']) ? $params_input['amount_total'] : 0),
					'mallid'			=> $this->mallid,
					'sharedkey'			=> $this->sharedkey,
					'transaction_id'	=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'currency'			=> $items['currency'],
				);
				if (count($item_data) > 0) {
					foreach ($item_data as $val) {
						$items['purchase'] += (isset($val['order_price']) ? $val['order_price'] : 0);
					}
				}
				$PaymentStructure = array(
					'MALLID'				=> $this->mallid,
					'CHAINMERCHANT'			=> $this->chainmerchant,
					'AMOUNT'				=> sprintf("%.2f", floatval($create_words['amount'])),
					'PURCHASEAMOUNT'		=> sprintf("%.2f", floatval($items['purchase'])),
					'TRANSIDMERCHANT'		=> $create_words['transaction_id'],
					//'PAYMENTTYPE'			=> 'SALE', // DEFAULT is "SALE"
					'WORDS_STRING'			=> $this->create_words($method, $create_words),
					'WORDS'					=> sha1($this->create_words($method, $create_words)),
					'REQUESTDATETIME' 		=> (isset($params_input['transaction_datetime']) ? $params_input['transaction_datetime'] : date('YmdHis')),
					'CURRENCY'				=> $this->get_countrycode($this->isofile, $create_words['currency']),
					'PURCHASECURRENCY'		=> $this->get_countrycode($this->isofile, $create_words['currency']),
					'SESSIONID'				=> (isset($params_input['transaction_session']) ? $params_input['transaction_session'] : ''),
					'NAME'					=> $user_input['name'],
					'EMAIL'					=> $user_input['email'],
					'ADDITIONALDATA'		=> '',
					'BASKET'				=> $this->create_basket($method, $item_data),
					'CUSTOMERID'			=> '',
					'PAYMENTCHANNEL'		=> '',
				);
				if (isset($shipping_data)) {
					$PaymentStructure['ADDITIONALDATA'] = (isset($user_input['shipping_address']['ADDITIONALDATA']) ? $user_input['shipping_address']['ADDITIONALDATA']: "");
					$PaymentStructure['SHIPPING_ADDRESS'] = (isset($user_input['shipping_address']['SHIPPING_ADDRESS']) ? $user_input['shipping_address']['SHIPPING_ADDRESS'] : "");
					$PaymentStructure['SHIPPING_CITY'] = (isset($user_input['shipping_address']['SHIPPING_CITY']) ? $user_input['shipping_address']['SHIPPING_CITY'] : "");
					$PaymentStructure['SHIPPING_STATE'] = (isset($user_input['shipping_address']['SHIPPING_STATE']) ? $user_input['shipping_address']['SHIPPING_STATE'] : "");
					$PaymentStructure['SHIPPING_COUNTRY'] = (isset($user_input['shipping_address']['SHIPPING_COUNTRY']) ? $user_input['shipping_address']['SHIPPING_COUNTRY'] : "");
					$PaymentStructure['SHIPPING_ZIPCODE'] = (isset($user_input['shipping_address']['SHIPPING_ZIPCODE']) ? $user_input['shipping_address']['SHIPPING_ZIPCODE'] : "");
					# New:
					$PaymentStructure['ADDRESS'] = $PaymentStructure['SHIPPING_ADDRESS'];
					$PaymentStructure['CITY'] = $PaymentStructure['SHIPPING_CITY'];
					$PaymentStructure['STATE'] = $PaymentStructure['SHIPPING_STATE'];
					$PaymentStructure['COUNTRY'] = $PaymentStructure['SHIPPING_COUNTRY'];
					$PaymentStructure['ZIPCODE'] = $PaymentStructure['SHIPPING_ZIPCODE'];
					$PaymentStructure['HOMEPHONE'] = (isset($user_input['shipping_address']['phone']) ? $user_input['shipping_address']['phone'] : "");
					$PaymentStructure['MOBILEPHONE'] = (isset($user_input['shipping_address']['phone']) ? $user_input['shipping_address']['phone'] : "");
					$PaymentStructure['WORKPHONE'] = (isset($user_input['shipping_address']['phone']) ? $user_input['shipping_address']['phone'] : "");
					#
					$PaymentStructure['BIRTHDATE'] = (isset($user_input['shipping_address']['birthdate']) ? $user_input['shipping_address']['birthdate'] : '');
					# Recurring Billing
					$PaymentStructure['BILLNUMBER'] = (isset($params_input['transaction_billnumber']) ? $params_input['transaction_billnumber'] : '');
					$PaymentStructure['BILLDETAIL'] = $PaymentStructure['ADDITIONALDATA']; // Details of payment
					$PaymentStructure['BILLTYPE'] = ''; // S = Shopping, I = Installment, D = Donation, P = Payment, B = Billing
					$PaymentStructure['STARTDATE'] = ''; // Format: YYYYMMDD
					$PaymentStructure['ENDDATE'] = ''; // Format: YYYYMMDD
					$PaymentStructure['EXECUTETYPE'] = ''; # DAY / DATE / FULLDATE
					$PaymentStructure['EXECUTEDATE'] = ''; # If EXECUTETYPE = DAY (SUN / MON / TUE / WED / THU / FRI / SAT), If EXECUTETYPE = DATE (1 / 2 / 3 / ... / 28), If EXECUTETYPE = FULLDATE (Use format: YYYYMMDD)
					$PaymentStructure['EXECUTEMONTH'] = ''; # Please select one (JAN / FEB / MAR / APR / MAY / JUN / JUL / AUG / SEP / OCT / NOV / DEC)
					$PaymentStructure['FLATSTATUS'] = ''; // Dinamyc AMOUNT = FALSE, Static (Fix) AMOUNT = TRUE
					# AMOUNT
					$PaymentStructure['REGISTERAMOUNT'] = '';
					
				}
			break;
		}
		return $PaymentStructure;
	}
	//------------------------------------------------------------
	private function create_words_string($create_words = array()) {
		$string_words = array();
		if (count($create_words) > 0) {
			foreach ($create_words as $keval) {
				$string_words[] = (string)$keval;
			}
		}
		$string = "";
		if (count($string_words) > 0) {
			foreach($string_words as $keval) {
				$string .= (string)$keval;
			}
		}
		//$string = implode("", $string_words);
		$string = trim((string)$string);
		return $string;
	}
	private function create_words_string_alter($create_words = array()) {
		$string_words = array();
		if (count($create_words) > 0) {
			foreach ($create_words as $keval) {
				$string_words[] = strval($keval);
			}
		}
		$string = "";
		if (count($string_words) > 0) {
			foreach($string_words as $keval) {
				$string .= strval($keval);
			}
		}
		$string = trim(strval($string));
		return $string;
	}
	private function create_words($method, $params_input) {
		$return_create_words = "";
		$create_words = array();
		switch(strtolower($method)) {
			case 'create':
			default:
				$create_words = array(
					'amount'			=> (isset($params_input['amount']) ? sprintf("%.2f", floatval($params_input['amount'])) : '1.00'),
					'mallid'			=> (isset($params_input['mallid']) ? $params_input['mallid'] : $this->config['MALLID']),
					'sharedkey'			=> (isset($params_input['sharedkey']) ? $params_input['sharedkey'] : $this->config['SHAREDKEY']),
					'transaction_id'	=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'currency'			=> (isset($params_input['currency']) ? $this->get_countrycode($this->isofile, $params_input['currency']) : '360'),
				);
				if ((int)$create_words['currency'] === 360) { // using IDR (indonesia)
					unset($create_words['currency']);
				}
			break;
			case 'check':
				$create_words = array(
					'mallid'			=> (isset($params_input['mallid']) ? $params_input['mallid'] : $this->mallid),
					'sharedkey'			=> (isset($params_input['sharedkey']) ? $params_input['sharedkey'] : $this->sharedkey),
					'transaction_id'	=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'currency'			=> (isset($params_input['currency']) ? $params_input['currency'] : '360'),
				);
				if ((int)$create_words['currency'] === 360) { // using IDR (indonesia)
					unset($create_words['currency']);
				}
			break;
			case 'void':
				$create_words = array(
					'mallid'			=> (isset($params_input['mallid']) ? $params_input['mallid'] : $this->mallid),
					'sharedkey'			=> (isset($params_input['sharedkey']) ? $params_input['sharedkey'] : $this->sharedkey),
					'transaction_id'	=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'session_id'		=> (isset($params_input['session_id']) ? $params_input['session_id'] : ''),
					'currency'			=> (isset($params_input['currency']) ? $params_input['currency'] : '360'),
				);
				unset($create_words['currency']); // There is no currency on void WORDS
			break;
			//-----------------------
			case 'notify':
			case 'notify-checking':
				$create_words = array(
					'amount'			=> (isset($params_input['amount']) ? sprintf("%.2f", floatval($params_input['amount'])) : '0.00'),
					'mallid'			=> (isset($params_input['mallid']) ? $params_input['mallid'] : $this->mallid),
					'sharedkey'			=> (isset($params_input['sharedkey']) ? $params_input['sharedkey'] : $this->sharedkey),
					'transaction_id'	=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'result_msg'		=> (isset($params_input['result_msg']) ? $params_input['result_msg'] : ''), // SUCCESS
					'verify_status'		=> (isset($params_input['verify_status']) ? $params_input['verify_status'] : ''), // SUCCESS
					'currency'			=> (isset($params_input['currency']) ? $params_input['currency'] : '360'),
				);
				if ((int)$create_words['currency'] === 360) { // using IDR (indonesia)
					unset($create_words['currency']);
				}
			break;
			case 'redirect':
			case 'redirect-checking':
				$create_words = array(
					'amount'			=> (isset($params_input['amount']) ? sprintf("%.2f", floatval($params_input['amount'])) : '0.00'),
					'sharedkey'			=> (isset($params_input['sharedkey']) ? $params_input['sharedkey'] : $this->sharedkey),
					'transaction_id'	=> (isset($params_input['transaction_id']) ? $params_input['transaction_id'] : 0),
					'trans_status'		=> (isset($params_input['trans_status']) ? (string)$params_input['trans_status'] : ''),
					'currency'			=> (isset($params_input['currency']) ? $params_input['currency'] : '360'),
				);
				if ((int)$create_words['currency'] === 360) { // using IDR (indonesia)
					unset($create_words['currency']);
				}
			break;
		}
		return $this->create_words_string($create_words);
	}
	//----------------------------------------------
	public function check_status_payment($method, $params_input) {
		$CheckStructure = array();
		switch (strtolower($method)) {
			case 'check':
				$CheckStructure = $this->create_payment_structure('check', 0, $params_input);
				unset($CheckStructure['WORDS_STRING']);
				ksort($CheckStructure);
			break;
		}
		return $this->create_curl_request('POST', $this->client['checkstatus'], 'TDP/Api.Context', $this->create_curl_headers(), $CheckStructure, 30);
	}
	//----------------------------------------------
	private function create_basket($method, $item_data) {
		$create_item_details = array();
		$string_return = array();
		foreach ($item_data as $val) {
			$create_item_details[] = array(
				'item_name'			=> (isset($val['order_item_name']) ? $val['order_item_name'] : ""),
				'item_price'		=> (isset($val['order_price']) ? $val['order_price'] : 0),
				'item_unit'			=> (isset($val['order_unit']) ? $val['order_unit'] : 1),
				'item_price_total'	=> ((isset($val['order_price']) && isset($val['order_unit'])) ? ($val['order_price'] * $val['order_unit']) : 0),
			);
		}
		if (count($create_item_details) > 0) {
			foreach ($create_item_details as &$val) {
				$val['item_price'] = sprintf("%.2f", floatval($val['item_price']));
				$val['item_price_total'] = sprintf("%.2f", floatval($val['item_price_total']));
				$string_return[] = implode(",", $val);
			}
		}
		if (count($string_return) > 0) {
			return implode(";", $string_return);
		} else {
			return "";
		}
	}
	//-----------------------------------------------------------------------------
	public static function doku_incoming_callback() {
		###############################
		# Request Input
		$RequestInputParams = array();
		$RequestInput = file_get_contents("php://input");
		$incomingHeaders = self::apache_headers();
		if (isset($incomingHeaders['Content-Type'])) {
			if ((!is_array($incomingHeaders['Content-Type'])) && (!is_object($incomingHeaders['Content-Type']))) {
				if (0 === strpos($incomingHeaders['Content-Type'], 'application/json')) {
					if (!$RequestInputJson = json_decode($RequestInput, true)) {
						parse_str($RequestInput, $RequestInputParams);
					} else {
						$RequestInputParams = $RequestInputJson;
					}
				} else if (0 === strpos($incomingHeaders['Content-Type'], 'application/xml')) {
					$RequestInputParams = $RequestInput;
				} else {
					parse_str($RequestInput, $RequestInputParams);
				}
			}
		} else {
			parse_str($RequestInput, $RequestInputParams);
		}
		$params['input'] = $RequestInput;
		$params['header'] = $incomingHeaders;
		$params['body'] = $RequestInputParams;
		return $params;
	}
	public static function _GET(){
		$__GET = (isset($_GET) ? $_GET : array());
		$request_uri = ((isset($_SERVER['REQUEST_URI']) && (!empty($_SERVER['REQUEST_URI']))) ? $_SERVER['REQUEST_URI'] : '');
		$_get_str = explode('?', $request_uri);
		if( !isset($_get_str[1]) ) return $__GET;
		$params = explode('&', $_get_str[1]);
		foreach ($params as $p) {
			$parts = explode('=', $p);
			$parts[0] = (is_string($parts[0]) ? strtolower($parts[0]) : $parts[0]);
			$__GET[$parts[0]] = isset($parts[1]) ? $parts[1] : '';
		}
		return $__GET;
	}
	public static function get_query_string() {
		$__GET = (isset($_GET) ? $_GET : array());
		$request_uri = ((isset($_SERVER['REQUEST_URI']) && (!empty($_SERVER['REQUEST_URI']))) ? $_SERVER['REQUEST_URI'] : '');
		$query_string = (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');
		parse_str(parse_url(html_entity_decode($request_uri), PHP_URL_QUERY), $array);
		if (count($array) > 0) {
			foreach ($array as $key => $val) {
				$__GET[$key] = $val;
			}
		}
		return $__GET;
	}
	//-----------------------------------------------------------------------------
	function set_dokupayment_headers($headers = array()) {
		$this->dokupayment_headers = $headers;
		return $this;
	}
	function reset_dokupayment_headers() {
		$this->dokupayment_headers = null;
		return $this;
	}
	function add_dokupayment_headers($key, $val) {
		if (!isset($this->dokupayment_headers)) {
			$this->dokupayment_headers = $this->get_dokupayment_headers();
		}
		$add_header = array($key => $val);
		$this->dokupayment_headers = array_merge($add_header, $this->dokupayment_headers);
	}
	function get_dokupayment_headers() {
		return $this->dokupayment_headers;
	}
	//------------------------------------------------------------------------------------------------------
	function create_curl_request($action, $url, $UA, $headers = null, $params = array(), $timeout = 30) {
		$cookie_file = (dirname(__FILE__).'/cookies.txt');
		$url = str_replace( "&amp;", "&", urldecode(trim($url)) );
		$ch = curl_init();
		switch (strtolower($action)) {
			case 'get':
				if ((is_array($params)) && (count($params) > 0)) {
					$Querystring = http_build_query($params);
					$url .= "?";
					$url .= $Querystring;
				}
			break;
			case 'post':
			default:
				$url .= "";
			break;
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		if ($headers != null) {
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		} else {
			curl_setopt($ch, CURLOPT_HEADER, false);
		}
		curl_setopt($ch, CURLOPT_USERAGENT, $UA);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		$post_fields = NULL;
		switch (strtolower($action)) {
			case 'get':
				curl_setopt($ch, CURLOPT_POST, false);
				curl_setopt($ch, CURLOPT_POSTFIELDS, null);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
			break;
			case 'post':
			default:
				if ((is_array($params)) && (count($params) > 0) && (is_array($headers) && count($headers) > 0)) {
					foreach ($headers as $heval) {
						$getContentType = explode(":", $heval);
						if (strtolower($getContentType[0]) !== 'content-type') {
							continue;
						}
						switch (strtolower(trim($getContentType[0]))) {
							case 'content-type':
								if (isset($getContentType[1])) {
									switch (strtolower(trim($getContentType[1]))) {
										case 'application/xml':
											$post_fields = $post_fields;
										break;
										case 'application/json':
											$post_fields = json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
										break;
										case 'application/x-www-form-urlencoded':
											$post_fields = http_build_query($params);
										break;
										default:
											$post_fields = http_build_query($params);
										break;
									}
								}
							break;
							default:
								$post_fields = http_build_query($params);
							break;
						}
					}
				} else if ((!empty($params)) || ($params != '')) {
					$post_fields = $params;
				}
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
			break;
		}
		// Get Response
		$response = curl_exec($ch);
		$mixed_info = curl_getinfo($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header_string = substr($response, 0, $header_size);
		$header_content = $this->get_headers_from_curl_response($header_string);
		$header_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (count($header_content) > 1) {
			$header_content = end($header_content);
		}
		$body = substr($response, $header_size);
		curl_close ($ch);
		$return = array(
			'request'		=> array(
				'method'			=> $action,
				'host'				=> $url,
				'header'			=> $headers,
				'body'				=> $post_fields,
			),
			'response'		=> array(),
		);
		if (!empty($response) || $response != '') {
			$return['response']['code'] = (int)$header_code;
			$return['response']['header'] = array(
				'size' => $header_size, 
				'string' => $header_string,
				'content' => $header_content,
			);
			$return['response']['body'] = $body;
			return $return;
		}
		return false;
	}
	private static function get_headers_from_curl_response($headerContent) {
		$headers = array();
		// Split the string on every "double" new line.
		$arrRequests = explode("\r\n\r\n", $headerContent);
		// Loop of response headers. The "count($arrRequests) - 1" is to 
		// avoid an empty row for the extra line break before the body of the response.
		for ($index = 0; $index < (count($arrRequests) - 1); $index++) {
			foreach (explode("\r\n", $arrRequests[$index]) as $i => $line) {
				if ($i === 0) {
					$headers[$index]['http_code'] = $line;
				} else {
					list ($key, $value) = explode(': ', $line);
					$headers[$index][$key] = $value;
				}
			}
		}
		return $headers;
	}
	public function create_curl_headers($headers = array()) {
		$curlheaders = array();
		foreach ($headers as $ke => $val) {
			$curlheaders[] = "{$ke}: {$val}";
		}
		return $curlheaders;
	}
	public static function apache_headers() {
		if (function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
			$out = array();
			foreach ($headers AS $key => $value) {
				$key = str_replace(" ", "-", ucwords(strtolower(str_replace("-", " ", $key))));
				$out[$key] = $value;
			}
		} else {
			$out = array();
			if	(isset($_SERVER['CONTENT_TYPE'])) {
				$out['Content-Type'] = $_SERVER['CONTENT_TYPE'];
			}
			if (isset($_ENV['CONTENT_TYPE'])) {
				$out['Content-Type'] = $_ENV['CONTENT_TYPE'];
				foreach ($_SERVER as $key => $value) {
					if (substr($key, 0, 5) == "HTTP_") {
						$key = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));
						$out[$key] = $value;
					}
				}
			}
		}
		return $out;
	}
	// ---------------------------------
	// Utilities
	// ---------------------------------
	function create_custom_arraytoxml($root, $params = array()) {
		# Create XML (return object)
		$obj_xml = new SimpleXMLElement($root);
		$this->custom_arraytoxml($params, $obj_xml);
		return $obj_xml;
	}
	function render_custom_arraytoxml($obj_xml) {
		$xml_formatted = $obj_xml->asXML();
		$domXML = new DOMDocument('1.0');
		$domXML->preserveWhiteSpace = false;
		$domXML->formatOutput = true;
		$domXML->loadXML($xml_formatted);
		$xml_formatted = $domXML->saveXML();
		return $xml_formatted;
	}
	// Create Unique Billno by Datetime
	function generate_transaction_id($timezone = "Asia/Bangkok") {
		$microtime = microtime(true);
		$micro = sprintf("%06d",($microtime - floor($microtime)) * 1000000);
		$DateObject = new DateTime(date("Y-m-d H:i:s.{$micro}", $microtime));
		$DateObject->setTimezone(new DateTimeZone($timezone));
		return $DateObject->format('YmdHisu');
	}
	//----------------------------------------------
	function custom_arraytoxml($array, &$xml){
		foreach ($array as $key => $value) {
			if(is_array($value)){
				if(is_int($key)) {
					$key = "e";
				}
				$label = $xml->addChild($key);
				$this->custom_arraytoxml($value, $label);
			} else {
				$xml->addChild($key, $value);
			}
		}
	}
	function xmltoarray($contents, $get_attributes=1) {
		if(!$contents) return array();
		if(!function_exists('xml_parser_create')) {
			return array();
			}
		$parser = xml_parser_create();
		xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, 0 );
		xml_parser_set_option( $parser, XML_OPTION_SKIP_WHITE, 1 );
		xml_parse_into_struct( $parser, $contents, $xml_values );
		xml_parser_free( $parser );
		if(!$xml_values) return;
		$xml_array = array();
		$parents = array();
		$opened_tags = array();
		$arr = array();
		$current = &$xml_array;
		foreach($xml_values as $data) {
			unset($attributes,$value);
			extract($data);
			$result = '';
			if($get_attributes) {
				$result = array();
				if(isset($value)) $result['value'] = $value;
				if(isset($attributes)) {
					foreach($attributes as $attr => $val) {
						if($get_attributes == 1) $result['attr'][$attr] = $val;
						}
					}
				} elseif(isset($value)) {
				$result = $value;
				}
			if($type == "open") {
				$parent[$level-1] = &$current;
				if(!is_array($current) or (!in_array($tag, array_keys($current)))) {
					$current[$tag] = $result;
					$current = &$current[$tag];
					} else {
					if(isset($current[$tag][0])) {
						array_push($current[$tag], $result);
						} else {
						$current[$tag] = array($current[$tag],$result);
						}
					$last = count($current[$tag]) - 1;
					$current = &$current[$tag][$last];
					}
				} elseif($type == "complete") {
				if(!isset($current[$tag])) {
					$current[$tag] = $result;
					} else {
					if((is_array($current[$tag]) and $get_attributes == 0)
					or (isset($current[$tag][0]) and is_array($current[$tag][0]) and $get_attributes == 1)) {
						array_push($current[$tag],$result);
						} else {
						$current[$tag] = array($current[$tag],$result);
						}
					}
				} elseif($type == 'close') {
				$current = &$parent[$level-1];
				}
		}
		return($xml_array);
	}
	// Utils
	//------------------------------------
	# Create by Wordpress
	function sanitize_file_name( $filename ) {
		$filename_raw = $filename;
		$special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}");
		foreach ($special_chars as $chr) {
			$filename = str_replace($chr, '', $filename);
		}
		$filename = preg_replace('/[\s-]+/', '-', $filename);
		$filename = trim($filename, '.-_');
		$filename;
	}
	function sanitize_url_parameter($params_input = array()) {
		$sanitized = [];
		if (count($params_input) > 0) {
			foreach ($params_input as $key => $keval) {
				if (!is_array($keval) || (!is_object($keval))) {
					$keval = filter_var($keval, FILTER_SANITIZE_URL);
				}
				$sanitized[$key] = $keval;
			}
		}
		return $sanitized;
	}
	
}