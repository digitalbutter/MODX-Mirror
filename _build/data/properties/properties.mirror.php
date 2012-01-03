<?php
$properties = array(
	array(
		'name' => 'cacheEnabled',
		'desc' => 'Whether caching is enabled',
		'type' => 'combo-boolean',
		'options' => '',
		'value' => true,
	),
	array(
		'name' => 'processComments',
		'desc' => 'Whether plugin should process comments',
		'type' => 'combo-boolean',
		'options' => '',
		'value' => true,
	),
	array(
		'name' => 'verboseLogging',
		'desc' => 'Whether to use detailed logging',
		'type' => 'combo-boolean',
		'options' => '',
		'value' => true,
	),
	array(
		'name' => 'logicalDelete',
		'desc' => 'Whether to delete files physically',
		'type' => 'combo-boolean',
		'options' => '',
		'value' => true,
	),
);
return $properties;
