<?php
define('MODX_API_MODE', true);
require dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';

$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE'); 


if($_GET['checkoutUrl']){ 
 	 
	$form = '';

	$form .= "<body onload='document.payform.submit()'>";
	$form .=  'Redirecting ...';
	$form .=  "<form method='post' action='".$_GET['checkoutUrl']."' name='payform'>";

	foreach ($_GET as $key => $value) {
		if($key != 'checkoutUrl') $form .= "<input type='hidden' name='".$key."' value='".$value."'>";
	}

	$form .= "</form></body>"; 

	echo $form;
 

}else{

 	//$modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2:Victoriabankmd] $_REQUEST  '.print_r($_REQUEST, true));

	/* @var miniShop2 $miniShop2 */
	$miniShop2 = $modx->getService('minishop2');
	$miniShop2->loadCustomClasses('payment');

	if (!class_exists('Victoriabankmd')) {exit('Error: could not load payment class "Victoriabankmd".');}	 

	$context = '';
	$params = array();

	/* @var msPaymentInterface|Victoriabankmd $handler */
	$handler = new Victoriabankmd($modx->newObject('msOrder'));

	$istest1 = false; // set false to disable test requests and true to enable
	$istest2 = false; // set false to disable test requests and true to enable
	$istest3 = false; // set false to disable test requests and true to enable

	// start test requests 
	// 1. pay(0) - completion(21)
	// 2. pay(0) - reversal request(24)
	// 3. pay(0) - completion(21) - reversal request(24)

	// все данные (RRN транзакции) будут указаны в письме от банка


	// 1
	if($istest1){
		if($_REQUEST['TRTYPE'] == 0 &&  $_REQUEST['TEXT']=='Approved'){		 
			$handler->payCompletion($_REQUEST, 21); //trtype = 21
		} 
	}

	// 2
	if($istest2){
		if($_REQUEST['TRTYPE'] == 0 &&  $_REQUEST['TEXT']=='Approved'){		 
			$handler->payCompletion($_REQUEST, 24); //trtype = 24
		} 
	}


	// 3
	if($istest3){

		if($_REQUEST['TRTYPE'] == 0 &&  $_REQUEST['TEXT']=='Approved'){		 
			$handler->payCompletion($_REQUEST, 21); //trtype = 21
		} 

		if($_REQUEST['TRTYPE'] == 21 &&  $_REQUEST['TEXT']=='Approved'){		 
			$handler->payCompletion($_REQUEST, 24); //trtype = 24
		} 

	}


	if(!$istest1 && !$istest2 && !$istest3){
	    
		// start real shop work 

		if($_REQUEST['TRTYPE'] == 0 &&  $_REQUEST['TEXT']=='Approved'){		
			$handler->payCompletion($_REQUEST);
		}

		if($_REQUEST['TRTYPE'] == 21 && $_REQUEST['TEXT']=='Approved'){	

			$order_id =  substr($_REQUEST['ORDER'], 5);

			if ($order = $modx->getObject('msOrder', $order_id)) {  
				$handler->receive($order, $_REQUEST); 
			}
			else {
				$modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2:Victoriabankmd] Could not retrieve order with id '.$_REQUEST['ORDER']);
			}
		}	
		
		
		
		// redirect  
		
		if($_REQUEST['msorder']) {
		    
		    $params['msorder'] = (int)$_REQUEST['msorder'];
	    
		    if ($order = $modx->getObject('msOrder', $params['msorder'])) {
			$context = $order->get('context');
			$status = $order->get('status');
		    } 

			$success = $cancel = $modx->getOption('site_url');
		    if ($id = $modx->getOption('ms2_payment_vcbmd_success_id', null, 0)) {
			$success = $modx->makeUrl($id, $context, $params, 'full');
		    }
		    if ($id = $modx->getOption('ms2_payment_vcbmd_failure_id', null, 0)) {
			$cancel = $modx->makeUrl($id, $context, $params, 'full');
		    }

		    if($status == 2) {
			$redirect = $success;
		    } else {
			$redirect = $cancel;
		    }

		    $modx->sendRedirect($redirect);
		} 
		     
	}
 
}
