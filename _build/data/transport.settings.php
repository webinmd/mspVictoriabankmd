<?php
/**
 * Loads system settings into build
 *
 * @package msprobokassa
 * @subpackage build
 */
$settings = array();

$tmp = array(
	'url' => array(
		'xtype' => 'textfield',
		'value' => 'https://egateway.victoriabank.md/cgi-bin/cgi_link',
	),
	'currency' => array(
		'xtype' => 'textfield',
		'value' => 'MDL',
	),
	'terminal_id' => array(
		'xtype' => 'numberfield',
		'value' => '',
	),
	'merchant_id' => array(
		'xtype' => 'numberfield',
		'value' => '',
	),
	'merch_name' => array(
		'xtype' => 'textfield',
		'value' => '',
	),
	'language' => array(
		'xtype' => 'textfield',
		'value' => 'ru',
	),
	'success_id' => array(
		'xtype' => 'numberfield',
		'value' => 1,
	),
);

foreach ($tmp as $k => $v) {
	/* @var modSystemSetting $setting */
	$setting = $modx->newObject('modSystemSetting');
	$setting->fromArray(array_merge(
		array(
			'key' => 'ms2_payment_vcbmd_'.$k,
			'namespace' => 'minishop2',
			'area' => 'ms2_payment',
		), $v
	),'',true,true);

	$settings[] = $setting;
}

unset($tmp);
return $settings;