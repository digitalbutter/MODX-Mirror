<?php
$properties = array(
	array(
		'name' => 'cacheEnabled',
		'type' => 'combo-boolean',
		'options' => '',
		'value' => true,
	),
	array(
		'name' => 'processComments',
		'type' => 'combo-boolean',
		'options' => '',
		'value' => true,
	),
	array(
		'name' => 'verboseLogging',
		'type' => 'combo-boolean',
		'options' => '',
		'value' => true,
	),
	array(
		'name' => 'logicalDelete',
		'type' => 'combo-boolean',
		'options' => '',
		'value' => true,
	),
	array(
		'name' => 'flushParameter',
		'type' => 'textfield',
		'options' => '',
		'value' => 'flush',
	),
);
foreach ($properties as $i => $property) {
	$properties[$i]['desc'] = 'mirror.prop.' . $property['name'];
	$properties[$i]['lexicon'] = 'mirror:properties';
}
return $properties;
