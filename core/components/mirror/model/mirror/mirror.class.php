<?php
if (!defined('T_ML_COMMENT')) {
	define('T_ML_COMMENT', T_COMMENT);
} else {
	define('T_DOC_COMMENT', T_ML_COMMENT);
}

/**
 * @property boolean $cacheEnabled
 * @property boolean $processComments
 * @property boolean $supportStatic
 * @property modX $modx
 */
class Mirror
{
	protected $_modx;
	protected $_config;
	protected $_cacheEnabled;
	protected $_processComments;
	protected $_supportStatic;
	/**
	 * @var PDOStatement[] $_cacheCommands
	 */
	protected $_cacheCommands = array();

	public function __construct(modX $modx, array $config = array())
	{
		$this->_modx = $modx;
		$this->_config = array_merge(array(
			'cacheEnabled' => true,
			'processComments' => true,
			'verboseLogging' => true,
			'logicalDelete' => true,
			'assetsPath' => $modx->getOption('base_path') . 'web_assets/',
		), $config);
		$this->_config['assetsPath'] = rtrim($this->_config['assetsPath'], '/\\') . '/';
		$assetsPath = $this->getOption('assetsPath');
		if (!file_exists($assetsPath)) {
			$this->modx->cacheManager->writeTree($assetsPath);
		}
		$this->modx->lexicon->load('mirror:default');
	}

	public function __get($name)
	{
		$getter = 'get' . $name;
		if (method_exists($this, $getter)) {
			return $this->$getter();
		}
		throw new Exception('Property ' . $name . ' is not defined');
	}

	public function process($objectsMeta)
	{
		$assetsPath = $this->getOption('assetsPath');
		if (file_exists($assetsPath)) {
			$lockFileName = $assetsPath . 'mirror.lock';
			if (!file_exists($lockFileName)) {
				$this->modx->cacheManager->writeFile($lockFileName, time());
				foreach ($objectsMeta as $objectName => $objectMeta) {
					$basePath = $assetsPath . $objectName;
					if (!file_exists($basePath)) {
						if (!$this->modx->cacheManager->writeTree($basePath)) {
							$this->_log('directoryNotCreated', array(
								'path' => $basePath,
							));
							continue;
						}
					}
					$nameField = $objectMeta['className'] == 'modTemplate' ? 'templatename' : 'name';
					$processedFiles = array();
					$files = $this->_findFiles($basePath, array($objectMeta['extension']));
					foreach ($files as $file) {
						$name = basename($file, '.' . $objectMeta['extension']);
						if (strlen($name) == 0 || $name[0] == '.') {
							continue;
						}
						$processedFiles[$file] = true;
						$rawContent = file_get_contents($file);
						if (strlen($rawContent) <= ($objectMeta['extension'] == 'php' ? 5 : 0)) {
							continue;
						}
						$categoryTree = str_replace('\\', '/', dirname($file));
                        $categoryTree = trim(str_replace(str_replace('\\', '/', $basePath), '', $categoryTree), '/');
						$metaData = array();
						if ($this->processComments && $objectMeta['processComments']) {
							$tokens = @token_get_all($rawContent);
							$comment = '';
							foreach ($tokens as $token) {
								if (is_array($token) && count($token) > 2) {
									if ($token[0] == T_DOC_COMMENT) {
										$comment = $token[1];
										break;
									}
								}
							}
							if ($comment != '') {
								$commentLines = array_map('trim', explode("\n", $comment));
								foreach ($commentLines as $commentLine) {
									if (preg_match('/\s*\*\s*@(modx(Category|Description|Event|StaticFile))(.*?)$/', $commentLine, $matches) && count($matches) == 4) {
										if ($matches[2] == 'Event') {
											if ($objectMeta['className'] == 'modPlugin') {
												if (!isset($metaData['modxEvent'])) {
													$metaData['modxEvent'] = array();
												}
												$metaData['modxEvent'][] = trim($matches[3]);
											}
										} else {
											$metaData[trim($matches[1])] = trim($matches[3]);
										}
									}
								}
								if (count($metaData) > 0) {
									$rawContent = str_replace($comment, '', $rawContent);
									if (isset($metaData['modxEvent'])) {
										sort($metaData['modxEvent']);
									}
									if (isset($metaData['modxCategory'])) {
										$metaData['modxCategory'] = trim($metaData['modxCategory'], '/');
									}
									if (!$this->supportStatic) {
										unset($metaData['modxStaticFile']);
									}
								}
							}
						}
						if ($objectMeta['extension'] == 'php') {
							$rawContent = substr($rawContent, 5);
						}
						$rawContent = trim($rawContent);
						$hash = $rawContent;
						if ($this->processComments && $objectMeta['processComments']) {
							$hash .= ':' . (isset($metaData['modxDescription']) ? $metaData['modxDescription'] : '');
							$hash .= ':' . (isset($metaData['modxCategory']) ? $metaData['modxCategory'] : '');
							$hash .= ':' . (isset($metaData['modxEvent']) ? implode('|', $metaData['modxEvent']) : '');
							$hash .= ':' . (isset($metaData['modxStaticFile']) ? $metaData['modxStaticFile'] : '');
						}
						$hash = md5($hash);
						if ($this->cacheEnabled) {
							$fileId = 0;
							$this->_cacheCommands['selectFile']->bindParam(':name', $file, PDO::PARAM_STR);
							$this->_cacheCommands['selectFile']->execute();
							if ($row = $this->_cacheCommands['selectFile']->fetch()) {
								if ($hash == $row['hash']) {
									$this->_log('fileNotChanged', array(
										'file' => $file,
									), true);
									continue;
								}
								$fileId = $row['id'];
							}
						}
						$description = isset($metaData['modxDescription']) ? $metaData['modxDescription'] : '';
						if (isset($metaData['modxCategory']) && $metaData['modxCategory'] != $categoryTree) {
							$categoryData = $this->_getCategoryByTree($metaData['modxCategory'], $objectName);
						} else {
							$categoryData = $this->_getCategoryByTree($categoryTree, $objectName);
						}
						$categoryId = $categoryData['id'];
						if ($categoryData['tree'] != $categoryTree) {
							$categoryTree = $categoryData['tree'];
							$fileName = $basePath . '/' . $categoryTree . ($categoryTree != '' ? '/' : '') . $name . '.' . $objectMeta['extension'];
							$meta = array_merge($objectMeta, array(
								'description' => $description,
								'categoryTree' => $categoryTree,
								'events' => isset($metaData['modxEvent']) ? $metaData['modxEvent'] : array(),
								'static' => isset($metaData['modxStaticFile']) ? $metaData['modxStaticFile'] : '',
							));
							if ($this->_createFile($fileName, $rawContent, $meta)) {
								$this->_log('fileMoved', array(
									'sourceFile' => $file,
									'targetFile' => $fileName,
								), true);
								$this->_deleteFile($file);
								$processedFiles[$fileName] = true;
								if ($this->cacheEnabled) {
									$this->_cacheCommands['deleteFile']->bindParam(':name', $file, PDO::PARAM_STR);
									$this->_cacheCommands['deleteFile']->execute();
								}
								$file = $fileName;
							}
						}
						/**
						 * @var modElement $object
						 */
						$object = $this->modx->getObject($objectMeta['className'], array(
							$nameField => $name,
						));
						if ($object == null) {
							$object = $this->modx->newObject($objectMeta['className']);
							$object->set($nameField, $name);
						}
						$object->set('description', $description);
						$object->set('category', intval($categoryId));
						if (!empty($metaData['modxStaticFile'])) {
							$staticInfo = explode('@', $metaData['modxStaticFile'], 2);
							$object->set('source', (count($staticInfo) == 2 && is_numeric($staticInfo[0])) ? $staticInfo[0] : 1);
							$object->set('static', 1);
							$object->set('static_file', $staticInfo[count($staticInfo) - 1]);
							if (!$object->setFileContent($rawContent)) {
								$object->set('source', 0);
								$object->set('static', 0);
								$object->set('static_file', '');
							}
						}
						$object->setContent($rawContent);
						if ($object->save()) {
							if ($objectMeta['className'] == 'modPlugin' && isset($metaData['modxEvent']) && count($metaData['modxEvent']) > 0) {
								$eventNames = array();
								foreach ($metaData['modxEvent'] as $eventName) {
									$parts = explode(':', $eventName, 2);
									$eventNames[] = $eventName = $parts[0];
									$priority = isset($parts[1]) ? intval($parts[1]) : 0;
									if ($priority < 0) {
										$priority = 0;
									}
									/**
									 * @var modPluginEvent $pluginEvent
									 */
									$pluginEvent = $this->modx->getObject('modPluginEvent', array(
										'pluginid' => $object->get('id'),
										'event' => $eventName,
									));
									if (!$pluginEvent) {
										$pluginEvent = $this->modx->newObject('modPluginEvent');
										$pluginEvent->set('pluginid', $object->get('id'));
										$pluginEvent->set('event', $eventName);
									}
									$pluginEvent->set('priority', $priority);
									if (!$pluginEvent->save()) {
										$this->_log('eventNotAttached', array(
											'event' => $eventName,
											'plugin' => $name,
										));
									}
								}
								$this->modx->removeCollection('modPluginEvent', array(
									'pluginid' => $object->get('id'),
									'event:NOT IN' => $eventNames,
								));
							}
							if ($this->cacheEnabled) {
								if ($fileId > 0) {
									$this->_cacheCommands['updateFile']->bindParam(':hash', $hash, PDO::PARAM_STR);
									$this->_cacheCommands['updateFile']->bindParam(':id', $fileId, PDO::PARAM_INT);
									$this->_cacheCommands['updateFile']->execute();
								} else {
									$this->_cacheCommands['insertFile']->bindParam(':name', $file, PDO::PARAM_STR);
									$this->_cacheCommands['insertFile']->bindParam(':hash', $hash, PDO::PARAM_STR);
									$this->_cacheCommands['insertFile']->execute();
								}
							}
						} else {
							$this->_log('objectNotSaved', array(
								'class' => $objectMeta['className'],
								'name' => $name,
							));
						}
					}
					$objects = $this->modx->getCollection($objectMeta['className']);
					foreach ($objects as /** @var modElement $object */ $object) {
						$name = $object->get($nameField);
						$categoryTree = $this->_getCategoryTree($object->get('category'), $objectName);
						$fileName = $basePath . '/' . $categoryTree . ($categoryTree != '' ? '/' : '') . $name . '.' . $objectMeta['extension'];
						$fileName = str_replace('\\', '/', $fileName);
						$processedFiles[$fileName] = true;
						$rawContent = $object->getContent();
						$events = array();
						$hash = $rawContent;
						if ($this->processComments && $objectMeta['processComments']) {
							if ($objectMeta['className'] == 'modPlugin') {
								$criteria = $this->modx->newQuery('modPluginEvent');
								$criteria->sortby('event', 'ASC');
								$pluginEvents = $object->getMany('PluginEvents', $criteria);
								if (is_array($pluginEvents) && count($pluginEvents) > 0) {
									foreach ($pluginEvents as $pluginEvent) {
										$events[] = $pluginEvent->get('event') . ':' . $pluginEvent->get('priority');
									}
								}
							}
							$hash .= ':' . $object->get('description');
							$hash .= ':' . $categoryTree;
							$hash .= ':' . implode('|', $events);
							$hash .= ':' . (($this->supportStatic && $object->isStatic()) ? ($object->get('source') . '@' . $object->get('static_file')) : '');
						}
						$hash = md5($hash);
						if ($this->cacheEnabled) {
							$fileId = 0;
							$this->_cacheCommands['selectFile']->bindParam(':name', $fileName, PDO::PARAM_STR);
							$this->_cacheCommands['selectFile']->execute();
							if ($row = $this->_cacheCommands['selectFile']->fetch()) {
								if ($hash == $row['hash']) {
									$this->_log('fileNotChanged', array(
										'file' => $fileName,
									), true);
									continue;
								}
								$fileId = $row['id'];
							}
						}
						$meta = array_merge($objectMeta, array(
							'description' => trim($object->get('description')),
							'categoryTree' => $categoryTree,
							'events' => $events,
							'static' => ($this->supportStatic && $object->isStatic()) ? ($object->get('source') . '@' . $object->get('static_file')) : '',
						));
						if ($this->_createFile($fileName, $rawContent, $meta)) {
							if ($this->cacheEnabled) {
								if ($fileId > 0) {
									$this->_cacheCommands['updateFile']->bindParam(':hash', $hash, PDO::PARAM_STR);
									$this->_cacheCommands['updateFile']->bindParam(':id', $fileId, PDO::PARAM_INT);
									$this->_cacheCommands['updateFile']->execute();
								} else {
									$this->_cacheCommands['insertFile']->bindParam(':name', $fileName, PDO::PARAM_STR);
									$this->_cacheCommands['insertFile']->bindParam(':hash', $hash, PDO::PARAM_STR);
									$this->_cacheCommands['insertFile']->execute();
								}
							}
						}
					}
					$files = $this->_findFiles($basePath, array($objectMeta['extension']));
					foreach ($files as $file) {
						if (!isset($processedFiles[$file])) {
							if ($this->_deleteFile($file)) {
								$this->_log('fileDeleted', array(
									'file' => $file,
								), true);
							}
						}
					}
				}
				@unlink($lockFileName);
			} else {
				$this->_log('alreadyRunning');
			}
		}
	}

	/**
	 * @return modX
	 */
	public function getModx()
	{
		return $this->_modx;
	}

	/**
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getOption($key, $default = null)
	{
		return $this->modx->getOption($key, $this->_config, $default);
	}

	/**
	 * @return boolean
	 */
	public function getCacheEnabled()
	{
		if ($this->_cacheEnabled === null) {
			$this->_cacheEnabled = $this->getOption('cacheEnabled');
			if ($this->_cacheEnabled && !$this->_checkSqliteSupport()) {
				$this->_cacheEnabled = false;
			}
		}
		return $this->_cacheEnabled;
	}

	/**
	 * @return boolean
	 */
	public function getProcessComments()
	{
		if ($this->_processComments === null) {
			$this->_processComments = $this->getOption('processComments');
			if ($this->_processComments && !function_exists('token_get_all')) {
				$this->_processComments = false;
			}
		}
		return $this->_processComments;
	}

	public function getSupportStatic()
	{
		if ($this->_supportStatic === null) {
			$modxVersion = $this->modx->getVersionData();
			$this->_supportStatic = version_compare($modxVersion['version'] . '.' . $modxVersion['major_version'], '2.2', '>=');
		}
		return $this->_supportStatic;
	}

	protected function _checkSqliteSupport($createTables = true)
	{
		if (class_exists('PDO', false) && in_array('sqlite', PDO::getAvailableDrivers())) {
			if ($createTables) {
				try {
					$db = new PDO('sqlite:' . $this->getOption('assetsPath') . 'cache.db', null, null);
					$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					$result = $db->query('SELECT COUNT(*) FROM `sqlite_master`');
					if ($result->fetchColumn() == 0) {
						$sql = <<<EOD
		CREATE TABLE IF NOT EXISTS `file` (
			`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
			`name` VARCHAR NOT NULL,
			`hash` VARCHAR NOT NULL
		)
EOD;
						$db->exec($sql);
					}
					$this->_cacheCommands = array(
						'selectFile' => 'SELECT `id`, `hash` FROM `file` WHERE `name` = :name',
						'insertFile' => 'INSERT INTO `file` (`name`, `hash`) VALUES (:name, :hash)',
						'updateFile' => 'UPDATE `file` SET `hash` = :hash WHERE `id` = :id',
						'deleteFile' => 'DELETE FROM `file` WHERE `name` = :name',
					);
					foreach ($this->_cacheCommands as $key => $command) {
						$this->_cacheCommands[$key] = $db->prepare($command);
					}
				} catch (PDOException $e) {
					$this->_log($e->getMessage());
					return false;
				}
			}
			return true;
		}
		return false;
	}

	protected function _getCategoryTree($categoryId, $prefix = '', $create = true)
	{
		$prefix = trim(trim($prefix), '\\/');
		$prefix = $prefix != '' ? ($prefix . '/') : $prefix;
		$basePath = $this->getOption('assetsPath') . $prefix;
		if ($create && $prefix != '' && !file_exists($basePath)) {
			$this->modx->cacheManager->writeTree($basePath);
		}
		$tree = array();
		$category = $this->_getCategoryById($categoryId);
		$level = 0;
		while ($category != null) {
			$level++;
			if ($level > 2) {
				$this->_log('categoryTruncated', array(
					'category' => $categoryId,
				), true);
				break;
			}
			$tree[] = $category['name'];
			$category = $this->_getCategoryById($category['parent']);
		}
		$tree = array_reverse($tree);
		if ($create && count($tree) > 0) {
			foreach ($tree as $item) {
				$basePath .= $item . '/';
				if (!is_dir($basePath)) {
					$this->modx->cacheManager->writeTree($basePath);
				}
			}
		}
		return implode('/', $tree);
	}

	protected function _getCategoryByTree($categoryTree, $prefix = '', $create = true)
	{
		$categoryTree = trim(trim($categoryTree), '/');
		if ($categoryTree == '') {
			return array(
				'id' => 0,
				'tree' => '',
			);
		}
		$prefix = trim(trim($prefix), '\\/');
		$prefix = $prefix != '' ? ($prefix . '/') : $prefix;
		$path = $this->getOption('assetsPath') . $prefix;
		if ($create && $prefix != '' && !is_dir($path)) {
			$this->modx->cacheManager->writeTree($path);
		}
		$parts = explode('/', $categoryTree);
		if (count($parts) > 2) {
			$parts = array_slice($parts, 0, 2);
			$this->_log('categoryTreeTruncated', array(
				'category' => $categoryTree,
			), true);
		}
		$categoryId = 0;
		foreach ($parts as $part) {
			$path .= $part . '/';
			if ($create && !is_dir($path)) {
				$this->modx->cacheManager->writeTree($path);
			}
			$category = $this->_getCategoryByName($part, $categoryId);
			if ($category != null) {
				$categoryId = $category['id'];
			} else {
				break;
			}
		}
		return array(
			'id' => $categoryId,
			'tree' => trim(str_replace($this->getOption('assetsPath') . $prefix, '', $path), '/'),
		);
	}

	protected function _getCategoryById($id)
	{
		global $modx, $categoriesCache;
		if (isset($categoriesCache[$id])) {
			return $categoriesCache[$id];
		} else {
			/**
			 * @var modCategory $category
			 */
			$category = $modx->getObject('modCategory', array(
				'id' => $id,
			));
			if ($category != null) {
				$category = array(
					'id' => $category->get('id'),
					'name' => $category->get('category'),
					'parent' => $category->get('parent'),
				);
				$categoriesCache[$id] = $category;
			}
			return $category;
		}
	}

	protected function _getCategoryByName($name, $parentId = 0)
	{
		global $modx, $categoriesCache;
		$key = $name . ':' . $parentId;
		if (isset($categoriesCache[$key])) {
			return $categoriesCache[$key];
		} else {
			/**
			 * @var modCategory $category
			 */
			$category = $modx->getObject('modCategory', array(
				'category' => $name,
				'parent' => $parentId,
			));
			if ($category != null) {
				$category = array(
					'id' => $category->get('id'),
					'name' => $category->get('category'),
					'parent' => $category->get('parent'),
				);
			} else {
				/**
				 * @var modCategory $newCategory
				 */
				$newCategory = $modx->newObject('modCategory');
				$newCategory->set('category', $name);
				$newCategory->set('parent', $parentId);
				if ($newCategory->save()) {
					$category = array(
						'id' => $newCategory->get('id'),
						'name' => $name,
						'parent' => $parentId,
					);
				}
			}
			if ($category != null) {
				$categoriesCache[$key] = $category;
			}
			return $category;
		}
	}

	protected function _createFile($fileName, $rawContent, $metaData)
	{
		$content = $metaData['extension'] == 'php' ? '<?php' : '';
		$events = '';
		$static = '';
		if (is_array($metaData['events'])) {
			$metaData['events'] = array_filter(array_map('trim', $metaData['events']));
		}
		if (count($metaData['events']) > 0) {
			$separator = PHP_EOL . ' * @modxEvent       ';
			$events = $separator . implode($separator, $metaData['events']);
		}
		if (!empty($metaData['static'])) {
			$static = PHP_EOL . ' * @modxStaticFile  ' . $metaData['static'];
		}
		if ($this->processComments && $metaData['processComments']) {
			$content .= <<<EOD

/**
 * @modxDescription {$metaData['description']}
 * @modxCategory    {$metaData['categoryTree']}{$static}{$events}
 */
EOD;
		}
		$content .= PHP_EOL . $rawContent;
		return $this->modx->cacheManager->writeFile($fileName, trim($content), 'w');
	}

	function _deleteFile($fileName)
	{
		$name = basename($fileName);
		if (strlen($name) == 0 || $name[0] == '.') {
			return true;
		}
		return $this->getOption('logicalDelete') ? @rename($fileName, str_replace($name, '.' . $name, $fileName)) : @unlink($fileName);
	}

	protected function _findFiles($dir, $fileTypes = array(), $exclude = array(), $level = -1)
	{
		$list = $this->_findFilesRecursive($dir, '', $fileTypes, $exclude, $level);
		sort($list);
		return $list;
	}

	protected function _validatePath($base, $file, $isFile, $fileTypes, $exclude)
	{
		foreach ($exclude as $e) {
			if ($file === $e || strpos($base . '/' . $file, $e) === 0) {
				return false;
			}
		}
		if (!$isFile || empty($fileTypes)) {
			return true;
		}
		if (($type = pathinfo($file, PATHINFO_EXTENSION)) !== '') {
			return in_array($type, $fileTypes);
		} else {
			return false;
		}
	}

	protected function _findFilesRecursive($dir, $base, $fileTypes, $exclude, $level)
	{
		$list = array();
		$handle = opendir($dir);
		while (($file = readdir($handle)) !== false) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			$isFile = is_file($path);
			if ($this->_validatePath($base, $file, $isFile, $fileTypes, $exclude)) {
				if ($isFile) {
					$list[] = str_replace('\\', '/', $path);
				} else {
					if ($level) {
						$list = array_merge($list, $this->_findFilesRecursive($path, $base . '/' . $file, $fileTypes, $exclude, $level - 1));
					}
				}
			}
		}
		closedir($handle);
		return $list;
	}

	protected function _log($key, $params = array(), $debug = false)
	{
		static $fileName;
		if ($debug && !$this->getOption('verboseLogging')) {
			return;
		}
		if ($fileName == null) {
			$fileName = $this->getOption('assetsPath') . 'actions.log';
		}
		$this->modx->cacheManager->writeFile($fileName, '[' . strftime('%Y-%m-%d %H:%M:%S') . '] ' . $this->modx->lexicon('mirror.' . $key, $params) . PHP_EOL, 'a');
	}
}
