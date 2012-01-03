<?php
function getPluginContent($filename)
{
	$o = file_get_contents($filename);
	$o = trim(str_replace(array('<?php', '?>'), '', $o));
	return $o;
}

/**
 * @var modX $modx
 */
$plugins = array();
$plugins[0] = $modx->newObject('modPlugin');
$plugins[0]->fromArray(array(
	'id' => 1,
	'name' => 'Mirror',
	'description' => 'Synchronizes elements from filesystem with database',
	'plugincode' => getPluginContent($sources['elements'] . 'plugins/plugin.mirror.php'),
	'category' => 0,
), '', true, true);
$properties = include $sources['data'] . 'properties/properties.mirror.php';
$plugins[0]->setProperties($properties);
unset($properties);
$events = include $sources['data'] . 'events/events.mirror.php';
if (is_array($events) && !empty($events)) {
	$plugins[0]->addMany($events);
	$modx->log(xPDO::LOG_LEVEL_INFO, 'Packaged in ' . count($events) . ' Plugin Events.');
} else {
	$modx->log(xPDO::LOG_LEVEL_ERROR, 'Could not find plugin events.');
}
return $plugins;
