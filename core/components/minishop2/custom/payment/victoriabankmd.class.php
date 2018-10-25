<?php

if (!class_exists('msPaymentInterface')) {
	require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/mspaymenthandler.class.php';
}

class Victoriabankmd extends msPaymentHandler implements msPaymentInterface {
	public $config;
	public $modx;

	function __construct(xPDOObject $object, $config = array()) {
		$this->modx = & $object->xpdo;

		$siteUrl = $this->modx->getOption('site_url');
		$assetsUrl = $this->modx->getOption('minishop2.assets_url', $config, $this->modx->getOption('assets_url').'components/minishop2/');
		$paymentUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/victoriabankmd.php';

		$this->config = array_merge(array(
			'paymentUrl' 		=> $paymentUrl
			,'checkoutUrl' 		=> $this->modx->getOption('ms2_payment_vcbmd_url', null, 'https://egateway.victoriabank.md/cgi-bin/cgi_link', true)
			,'terminal_id' 		=> $this->modx->getOption('ms2_payment_vcbmd_terminal_id')
			,'merchant_id' 		=> $this->modx->getOption('ms2_payment_vcbmd_merchant_id')
			,'assets_url' 		=> $assetsUrl
			,'merch_url' 		=> $this->modx->getOption('site_url')
			,'merch_name' 		=> $this->modx->getOption('ms2_payment_vcbmd_merch_name', '', true)
			,'currency' 		=> $this->modx->getOption('ms2_payment_vcbmd_currency', '', true)
			,'language' 		=> $this->modx->getOption('ms2_payment_vcbmd_language', 'ru', true)
			,'success_id' 		=> $this->modx->getOption('ms2_payment_vcbmd_success_id', '1', true)
			,'merch_gmt' 		=> $this->modx->getOption('ms2_payment_vcbmd_merch_gmt', '2', true)
		), $config);
	}


	public function send(msOrder $order) { 
		$link = $this->getPaymentLink($order);		
		return $this->success('', array('redirect' => $link));  
	}

 
	public function getPaymentLink(msOrder $order) {

		$id 			= $order->get('id');
		$amount 		= number_format($order->get('cost'), 2, '.', '');
		$timestamp 		= date("YmdHis", strtotime($order->get('createdon')));
		$trtType 		= 0;
		$sign 			= $this->P_SIGN_ENCRYPT('00000'.$id, $timestamp, $trtType, $amount); 	 
		$success_url	= $this->modx->makeUrl($this->config['success_id'], '', array('msorder'=>$id), 'full');

		$request = array(
			 'checkoutUrl' 	=> $this->config['checkoutUrl']
			,'AMOUNT' 		=> $amount
			,'CURRENCY' 	=> $this->config['currency']
			,'ORDER' 		=> '00000'.$id
			,'DESC' 		=> 'Payment #'.$id
			,'MERCH_NAME' 	=> $this->config['merch_name']
			,'MERCH_URL'	=> $this->config['merch_url']
			,'MERCHANT' 	=> $this->config['merchant_id']			
			,'TERMINAL' 	=> $this->config['terminal_id']	
			,'EMAIL' 		=> $order->getOne('UserProfile')->get('email')
			,'TRTYPE' 		=> $trtType
			,'TIMESTAMP' 	=> $timestamp
			,'COUNTRY' 		=> 'md'
			,'BACKREF'		=> $success_url
			,'NONCE'		=> '11111111000000011111'
			,'P_SIGN'		=> $sign
			,'MERCH_GMT'	=> $this->config['merch_gmt']	
			,'LANG'			=> $this->config['language']
		); 

		$link = $this->config['paymentUrl'] .'?'. http_build_query($request);
		return $link;
	} 
 
 
	public function receive(msOrder $order, $params = array()) {

		$id 		= $order->get('id');
		$amount 	= number_format($order->get('cost'), 2, '.', '');
		$order_id 	= '00000'.$id;
		$action 	= $params['ACTION'];
		$p_sign		= $params['P_SIGN'];
		$rc 		= $params['RC'];
		$rrn 		= $params['RRN']; 

		$response_result = $this->P_SIGN_DECRYPT($p_sign, $action, $rc, $rrn, $order_id, $amount);

		if ($response_result == 'OK') { 
			$miniShop2 = $this->modx->getService('miniShop2');
			@$this->modx->context->key = 'mgr';
			$miniShop2->changeOrderStatus($order->get('id'), 2);
			exit('OK');
		}
		else {
			$this->paymentError('Err: wrong response.', $params);
		}
	}


	public function paymentError($text, $request = array()) {
		$this->modx->log(modX::LOG_LEVEL_ERROR,'[miniShop2:Victoriabankmd] ' . $text . ', request: '.print_r($request,1));
		header("HTTP/1.0 400 Bad Request");

		die('ERR: ' . $text);
	}

	public function payCompletition($params){

		$sign = $this->P_SIGN_ENCRYPT($params['ORDER'], $params['TIMESTAMP'], 21, $params['AMOUNT']); 

		$ch = curl_init(); 
		$fields = array( 
			 'ORDER' 	=> $params['ORDER'],
			,'AMOUNT'	=> $params['AMOUNT']
			,'RRN'		=> $params['RRN']
			,'INT_REF'	=> $params['INT_REF']
			,'TRTYPE'	=> 21
			,'TERMINAL'	=> $params['TERMINAL']
			,'CURRENCY'	=> $params['CURRENCY']
			,'TIMESTAMP'	=> $params['TIMESTAMP']
			,'NONCE'		=> '11111111000000011111'
			,'P_SIGN'	=> $sign
		);

		$request = http_build_query($fields);
		$url =  $this->config['checkoutUrl'];

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);            
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($ch); 
		curl_close ($ch);

		return $response;
	}
 

	public function P_SIGN_ENCRYPT($OrderId, $Timestamp,$trtType,$Amount){
		$MAC  = '';
		$RSA_KeyPath = MODX_BASE_PATH.'core/components/minishop2/custom/payment/lib/victoriabankmd/key.pem';
		$RSA_Key = file_get_contents ($RSA_KeyPath);
		$Data = array (
				 'ORDER' => $OrderId
				,'NONCE' => '11111111000000011111'
				,'TIMESTAMP' => $Timestamp
				,'TRTYPE' => $trtType
				,'AMOUNT' => $Amount
			);
			
		if (!$RSA_KeyResource = openssl_get_privatekey ($RSA_Key)){
			$this->paymentError('Err: Failed get private key');
		}

		$RSA_KeyDetails = openssl_pkey_get_details ($RSA_KeyResource);
		$RSA_KeyLength = $RSA_KeyDetails['bits']/8;
		
		foreach ($Data as $Id => $Filed) $MAC .= strlen ($Filed).$Filed;
		
		$First = '0001';
		$Prefix = '003020300C06082A864886F70D020505000410';
		$MD5_Hash = md5 ($MAC); 
		$Data = $First;

		$paddingLength = $RSA_KeyLength - strlen ($MD5_Hash)/2 - strlen ($Prefix)/2 - strlen ($First)/2;
		for ($i = 0; $i < $paddingLength; $i++) $Data .= "FF";
		
		$Data .= $Prefix.$MD5_Hash;
		$BIN = pack ("H*", $Data);
		
		if (!openssl_private_encrypt ($BIN, $EncryptedBIN, $RSA_Key, OPENSSL_NO_PADDING)){
			while ($msg = openssl_error_string()) echo $msg . "<br />\n"; 
			$this->paymentError('Err: Failed encrypt '.openssl_error_string());
		}
		
		$P_SIGN = bin2hex ($EncryptedBIN);
		
		return strtoupper($P_SIGN);
	}



	public function P_SIGN_DECRYPT($P_SIGN, $ACTION, $RC, $RRN, $ORDER, $AMOUNT){
		$InData = array (
			 'ACTION' => $ACTION
			,'RC' => $RC
			,'RRN' => $RRN
			,'ORDER' => $ORDER
			,'AMOUNT' => $AMOUNT
		);

		foreach($InData as $Id => $Filed) if ($Filed!= '-'  ) : $MAC .= strlen ($Filed).$Filed; else: $MAC .=$Filed; endif;
		$MD5_Hash_In = strtoupper (md5 ($MAC)); 


		$P_SIGNBIN = hex2bin ($P_SIGN);


		$RSA_KeyPath = MODX_BASE_PATH.'core/components/minishop2/custom/payment/lib/victoriabankmd/victoria_pub.pem';
		$RSA_Key = file_get_contents ($RSA_KeyPath);

		if (!$RSA_KeyResource = openssl_get_publickey($RSA_Key)) {
			$this->paymentError('Err: Failed get private key');
		}

		if (!openssl_public_decrypt ($P_SIGNBIN,$DECRYPTED_BIN,$RSA_Key)){
			while ($msg = openssl_error_string()) echo $msg . "<br />\n"; 
			$this->paymentError('Err: Failed decrypt');
		}

		$DECRYPTED = strtoupper( bin2hex ($DECRYPTED_BIN));
		$Prefix = '3020300C06082A864886F70D020505000410';
		$DECRYPTED_HASH = str_replace($Prefix,'',$DECRYPTED);

		if ($DECRYPTED_HASH==$MD5_Hash_In) {
			$RESULT="OK";
		} else {
			$RESULT="NOK";
		}

		return $RESULT;
	}
}