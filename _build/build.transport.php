<?php
$startTime = explode(' ', microtime());
$startTime = $startTime[1] + $startTime[0];
set_time_limit(0);

define('PKG_NAME', 'Mirror');
define('PKG_NAME_LOWER', strtolower(PKG_NAME));
define('PKG_VERSION', '1.0');
define('PKG_RELEASE', 'beta3');

$root = dirname(dirname(__FILE__)) . '/';
$sources = array(
	'root' => $root,
	'build' => $root . '_build/',
	'data' => $root . '_build/data/',
	'resolvers' => $root . '_build/resolvers/',
	'chunks' => $root . 'core/components/' . PKG_NAME_LOWER . '/chunks/',
	'lexicon' => $root . 'core/components/' . PKG_NAME_LOWER . '/lexicon/',
	'docs' => $root . 'core/components/' . PKG_NAME_LOWER . '/docs/',
	'elements' => $root . 'core/components/' . PKG_NAME_LOWER . '/elements/',
	'source_assets' => $root . 'assets/components/' . PKG_NAME_LOWER,
	'source_core' => $root . 'core/components/' . PKG_NAME_LOWER,
);
unset($root);

require_once $sources['build'] . 'build.config.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX;
$modx->initialize('mgr');
echo '<pre>';
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
$modx->loadClass('transport.modPackageBuilder', '', false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME_LOWER, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(PKG_NAME_LOWER, false, true, '{core_path}components/' . PKG_NAME_LOWER . '/');
$modx->log(modX::LOG_LEVEL_INFO, 'Packaging in plugins...');
$plugins = include $sources['data'] . 'transport.plugin.php';
if (empty($plugins)) {
	$modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in plugins.');
} else {
	$vehicle = $builder->createVehicle($plugins[0], array(
		xPDOTransport::UNIQUE_KEY => 'name',
		xPDOTransport::PRESERVE_KEYS => false,
		xPDOTransport::UPDATE_OBJECT => true,
		xPDOTransport::RELATED_OBJECTS => true,
		xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
			'PluginEvents' => array(
				xPDOTransport::PRESERVE_KEYS => true,
				xPDOTransport::UPDATE_OBJECT => false,
				xPDOTransport::UNIQUE_KEY => array(
					'pluginid',
					'event',
				),
			),
		),
	));
	$modx->log(modX::LOG_LEVEL_INFO, 'Adding file resolvers to plugin...');
	$vehicle->resolve('file', array(
		'source' => $sources['source_core'],
		'target' => "return MODX_CORE_PATH . 'components/';",
	));
	$builder->putVehicle($vehicle);
}
$modx->log(modX::LOG_LEVEL_INFO, 'Adding package attributes and setup options...');
$builder->setPackageAttributes(array(
	'readme' => file_get_contents($sources['docs'] . 'readme.txt'),
	'changelog' => file_get_contents($sources['docs'] . 'changelog.txt'),
	'setup-options' => array(
		'source' => $sources['build'] . 'setup.options.php',
	),
));
$modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
$builder->pack();
$endTime = explode(' ', microtime());
$endTime = $endTime[1] + $endTime[0];
$totalTime = sprintf('%2.4f s', ($endTime - $startTime));
$modx->log(modX::LOG_LEVEL_INFO, "Package Built.<br />\nExecution time: {$totalTime}\n");
exit ();
