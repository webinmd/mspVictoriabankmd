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

	/* @var miniShop2 $miniShop2 */
	$miniShop2 = $modx->getService('minishop2');
	$miniShop2->loadCustomClasses('payment');

	if (!class_exists('Victoriabankmd')) {exit('Error: could not load payment class "Victoriabankmd".');}
 	 

	$context = '';
	$params = array();

	/* @var msPaymentInterface|Victoriabankmd $handler */
	$handler = new Victoriabankmd($modx->newObject('msOrder'));

	if($_REQUEST['TRTYPE'] == 0 &&  $_REQUEST['TEXT']=='Approved'){		
		$handler->payCompletition($_REQUEST);
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
}