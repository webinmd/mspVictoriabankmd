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
			'paymentUrl' => $paymentUrl
			,'checkoutUrl' => $this->modx->getOption('ms2_payment_vcbmd_url', null, 'https://ecomt.victoriabank.md/cgi-bin/cgi_link', true)
			,'terminal_id' => $this->modx->getOption('ms2_payment_vcbmd_terminal_id')
			,'merchant_id' => $this->modx->getOption('ms2_payment_vcbmd_merchant_id')
			,'assets_url' => $assetsUrl
			,'merch_url' => $this->modx->getOption('site_url')
			,'merch_name' => $this->modx->getOption('ms2_payment_vcbmd_merch_name', '', true)
			,'merch_address' => $this->modx->getOption('ms2_payment_vcbmd_merch_address', '', true)
			,'currency' => $this->modx->getOption('ms2_payment_vcbmd_currency', '', true)
			,'language' => $this->modx->getOption('ms2_payment_vcbmd_language', 'ru', true)
			,'success_id' => $this->modx->getOption('ms2_payment_vcbmd_success_id', '1', true)
			,'failure_id' => $this->modx->getOption('ms2_payment_vcbmd_failure_id', '1', true)
			,'email' => $this->modx->getOption('ms2_payment_vcbmd_email', $this->modx->getOption('emailsender'), true)
		), $config);
	}


	public function send(msOrder $order) { 
		$link = $this->getPaymentLink($order);		
		return $this->success('', array('redirect' => $link));  
	}

 
	public function getPaymentLink(msOrder $order) 
	{

		$id 			= $order->get('id');
		$amount 		= number_format($order->get('cost'), 2, '.', '');
		$timestamp 		= date("YmdHis", strtotime($order->get('createdon')));
		$trtType 		= 0;
		$sign 			= $this->P_SIGN_ENCRYPT('00000'.$id, $timestamp, $trtType, $amount); 	 
		$success_url	= $this->modx->makeUrl($this->config['success_id'], '', array('msorder'=>$id), 'full');
		//$success_url 	= $this->modx->makeUrl($this->config['success_id'],'','',$this->config['protocol']);

		$product_list = '';

		$product_q = $this->modx->newQuery('msOrderProduct', array('order_id' => $id));
		$product_q->limit(1000);

		$product_q->prepare();
		$product_q->stmt->execute();
		$product_res = $product_q->stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($product_res as $product) {
			$product_list .= ' '.$product['msOrderProduct_name'].'.'; 
		}

		$request = array(
			 'checkoutUrl' 	=> $this->config['checkoutUrl']
			,'AMOUNT' 		=> $amount
			,'CURRENCY' 	=> $this->config['currency']
			,'ORDER' 		=> '00000'.$id
			,'DESC' 		=> 'Payment #'.$id.': '.$product_list
			,'MERCH_NAME' 	=> $this->config['merch_name']
			,'MERCH_URL'	=> $this->config['merch_url']
			,'MERCHANT' 	=> $this->config['merchant_id']		
			,'MERCH_ADDRESS'=> 	$this->config['merch_address']	
			,'TERMINAL' 	=> $this->config['terminal_id']	
			,'EMAIL' 		=> $order->getOne('UserProfile')->get('email')
			,'TRTYPE' 		=> $trtType
			,'TIMESTAMP' 	=> $timestamp
			,'COUNTRY' 		=> 'md'
			,'BACKREF'		=> $success_url
			,'NONCE'		=> '11111111000000011111'
			,'P_SIGN'		=> $sign
			,'MERCH_GMT'	=> 2
			,'LANG'			=> $this->config['language']
		); 

		$link = $this->config['paymentUrl'] .'?'. http_build_query($request);
		return $link;
	} 
 
 
	public function receive(msOrder $order, $params = array()) 
	{

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
			$this->sendCheck($order, $params);
			exit('OK');
		}
		else {
			$this->paymentError('Err: wrong response.', $params);
		}
	}


	public function paymentError($text, $request = array()) 
	{
		$this->modx->log(modX::LOG_LEVEL_ERROR,'[miniShop2:Victoriabankmd] ' . $text . ', request: '.print_r($request,1));
		header("HTTP/1.0 400 Bad Request");

		die('ERR: ' . $text);
	}


	public function payCompletion($params, $trtype = 21)
	{ 

		$sign = $this->P_SIGN_ENCRYPT($params['ORDER'], $params['TIMESTAMP'], $trtype, $params['AMOUNT']); 

		$ch = curl_init(); 
		$fields = array( 
			'ORDER' 	=> $params['ORDER'], 
			'AMOUNT'	=> $params['AMOUNT'],
			'RRN'		=> $params['RRN'],
			'INT_REF'	=> $params['INT_REF'],
			'TRTYPE'	=> $trtype,
			'TERMINAL'	=> $params['TERMINAL'],
			'CURRENCY'	=> $params['CURRENCY'],
			'TIMESTAMP'	=> $params['TIMESTAMP'],
			'NONCE'		=> '11111111000000011111',
			'P_SIGN'	=> $sign
		); 

		$file_tmp = $_SERVER['DOCUMENT_ROOT']. '/card_tmp.txt';
		file_put_contents($file_tmp, $params['CARD']);

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



	/*
	* send email to customer 
	*/
	public function sendCheck($order, $params = array()) 
	{
 
		$fields = array();

		$this->pdoTools = $this->modx->getService('pdoFetch');

		$file_tmp = $_SERVER['DOCUMENT_ROOT']. '/card_tmp.txt';
		$fields['card'] = file_get_contents($file_tmp);
		unlink($file_tmp);

		$fields['merch_name'] 	= $this->config['merch_name'];
		$fields['merch_url'] 	= $this->config['merch_url']; 
		$fields['date'] 		= date("Y.m.d H:i", strtotime($order->get('createdon')));
		$fields['amount'] 		= $params['AMOUNT'];
		$fields['currency'] 	= $params['CURRENCY']; 
		$fields['order_id'] 	= '00000'.$order->get('id');  
		$fields['rrn'] 			= $params['RRN']; 
		$fields['auth_code'] 	= $params['APPROVAL'];  
		$fields['type'] 		= 'Payment by '.$this->checkCardType($fields['card']);
 

		$userId 	= $order->get('user_id');
		$objUser 	= $this->modx->getObject('modUser', $userId);
        $objProfile = $this->modx->getObject('modUserProfile', $userId);

        if ($objUser && $objProfile) {
            $fields['username'] = $objProfile->get('fullname');
            $email 				= $objProfile->get('email');
        }

        $products = $this->modx->runSnippet('msGetOrder', array(
	    	'tpl' => 'customerOrderProducts',
	    	'id' => $order->get('id')
	    ));

        $fields['products']  		= $products;
        $fields['link_return']  	=  $this->modx->makeUrl(752, '', '', 'full'); // условия возврата
        $fields['link_delivery']  	=  $this->modx->makeUrl(753, '', '', 'full'); // условия доставки

		$subject = 'Payment check on  '.$this->config['merch_name']; 
		$body = $this->pdoTools->getChunk('customerOrderPaymentEmail', $fields); 

		$mail = $this->modx->getService('mail', 'mail.modPHPMailer');
        $mail->setHTML(true);
        $mail->address('to', trim($email));
        $mail->set(modMail::MAIL_SUBJECT,  $subject);
        $mail->set(modMail::MAIL_BODY, $body);
        $mail->set(modMail::MAIL_FROM, $this->modx->getOption('emailsender'));
        $mail->set(modMail::MAIL_FROM_NAME, $this->modx->getOption('site_name'));
        
        if (!$mail->send()) {

            $this->modx->log(modX::LOG_LEVEL_ERROR,

                'An error occurred while trying to send the email for pay check: ' . $mail->mailer->ErrorInfo

            );

        }

        $mail->reset();
    
	}


	/*
	* check card Visa or MasterCard
	*/
	public function checkCardType($cardNumber)
	{
		if(!$cardNumber) return;

		if($cardNumber[0] == 4){
			$cardtype = 'Visa';
		}else{
			$cardtype = 'MasterCard';
		}

		return $cardtype;

	}



	public function P_SIGN_ENCRYPT($OrderId, $Timestamp,$trtType,$Amount)
	{
		$MAC  = '';
		$RSA_KeyPath = MODX_BASE_PATH.'core/components/minishop2/custom/payment/lib/victoriabankmd/key.pem';
		$RSA_Key = file_get_contents ($RSA_KeyPath);
		$Data = array (
				'ORDER' => $OrderId,
				'NONCE' => '11111111000000011111',
				'TIMESTAMP' => $Timestamp,
				'TRTYPE' => $trtType,
				'AMOUNT' => $Amount
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



	public function P_SIGN_DECRYPT($P_SIGN, $ACTION, $RC, $RRN, $ORDER, $AMOUNT)
	{
		$InData = array (
			'ACTION' => $ACTION,
			'RC' => $RC,
			'RRN' => $RRN,
			'ORDER' => $ORDER ,
			'AMOUNT' => $AMOUNT
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
