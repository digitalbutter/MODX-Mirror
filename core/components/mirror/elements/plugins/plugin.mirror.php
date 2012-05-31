<?php
/**
 * @var modX $modx
 * @var array $scriptProperties
 */
$flushParameterName = $modx->getOption('mirror.flushParameterName', null, 'flush');
if ($modx->event->name != 'OnWebPageInit' || $modx->context->get('key') == 'mgr' || !isset($_GET[$flushParameterName])) {
	return;
} else {
	$resourceId = $modx->resourceIdentifier;
	if ($modx->resourceMethod == 'alias') {
		$resourceId = $modx->aliasMap[$resourceId];
	}
	if (!is_numeric($resourceId)) {
		return;
	}
}
require_once $modx->getOption('mirror.core_path', null, $modx->getOption('core_path') . 'components/mirror/') . 'model/mirror/mirror.class.php';

$objectsMeta = array(
	'chunks' => array(
		'extension' => 'html',
		'className' => 'modChunk',
		'processComments' => false,
	),
	'plugins' => array(
		'extension' => 'php',
		'className' => 'modPlugin',
		'processComments' => true,
	),
	'snippets' => array(
		'extension' => 'php',
		'className' => 'modSnippet',
		'processComments' => true,
	),
	'templates' => array(
		'extension' => 'html',
		'className' => 'modTemplate',
		'processComments' => false,
	),
);
$mirror = new Mirror($modx, $scriptProperties);
$mirror->process($objectsMeta);
$modx->cacheManager->refresh();
