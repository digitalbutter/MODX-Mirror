<?php
/*
 *
 * UTILITIES
 *
 */

function getFEMPath($category, $femPath){
	global $modx;
	
	$femPath = $category->get('category') . '/' . $femPath;
	
	if($category->get('parent') == 0){
		return $femPath;
	}
	
	return getFEMPath($category->getOne('Parent'), $femPath);
}

function mkdir_recursive($pathname, $mode = 0775){
    is_dir(dirname($pathname)) || mkdir_recursive(dirname($pathname), $mode);
    return is_dir($pathname) || @mkdir($pathname, $mode);
}


function getRelativePath($path){
	return str_replace(MODX_BASE_PATH, '', $path);
}

function scanForElements($path, $type = '.html'){
	$it = new RecursiveDirectoryIterator($path);
	$files = array();
	foreach($it as $file) {
		$filename = $file->getFilename();
		if(sizeof(explode($type, $filename)) == 2){
			$files[] = $path . $filename;
		} else if(is_dir($path . $filename) && $filename != '..' && $filename != '.'){
			$files = array_merge($files, scanForElements($path . $filename . '/', $type));
		} else {
			
		}
	}
	return $files;
}

function isAlreadyStaticElement($file){
	
	if(is_file($file)){
		return true;
	}
	return false;
}

/*
 *
 * CHUNKS
 *
 */

function getChunks(){
	//get chunks on the file system, and put them into the database.
	global $modx;
	
	$suffix = '.html';
	$root = MODX_BASE_PATH.'web_assets/chunks/';
	$files = scanForElements($root, $suffix);
	
	foreach($files as $filename) {
		if(!$chunk = $modx->getObject('modChunk', array(
			'description' => 'FEM:' . getRelativePath($filename)
		))){
			$chunk = $modx->newObject('modChunk');
			$chunk->set('description', 'FEM:' . getRelativePath($filename));
			$tokens = explode('/', $filename);
			$name = end($tokens);
			$chunk->set('name', $name);
			unset($tokens[sizeof($tokens) - 1]);
			$category = end($tokens);
			
			if($category == 'chunks'){
				$category = '';
			}
			
			if(!$cat = $modx->getObject('modCategory', array('category' => $category))){
				$cat = $modx->newObject('modCategory');
				$cat->set('category', $category);
				$cat->save();
				
				
			}
			
			$chunk->set('category', $cat->get('id'));
			
			
			$name = str_replace($suffix, '', $name);
			//check that this is unique, if it isn't we fire a warning to the user.
			
			if($exists = $modx->getObject('modChunk', array('name' => $name))){
				echo "A chunk with the name " . $name . " already exists. [" . $filename  . "]";
				exit();
			}
			
			$chunk->set('name', $name);
		}
		
		$chunk->set('snippet', file_get_contents($filename));
		$chunk->save();
				
	}
}

function putChunks(){
	//put chunks that aren't files into the file system
	global $modx;
	$criteria = $modx->newQuery('modChunk');
	$chunks = $modx->getCollection('modChunk', $criteria);
	foreach($chunks as $chunkId => $chunk){
		if(!$category = $chunk->getOne('Category')){
			$path = '';
		} else {
			$path = getFEMPath($category, '');
		}
		
		$path = MODX_BASE_PATH . 'web_assets/chunks/' . $path;
		$filename = $path . $chunk->get('name') . '.html';
		if(!isAlreadyStaticElement($filename)){
			echo $path . ' - <hr />';
			if(mkdir_recursive($path)){
				$chunk->set('description', 'FEM:' . getRelativePath($filename));
				$chunk->save();
				$fh = fopen($filename, 'w') or die("can't open file: " . $filename);
				fwrite($fh, $chunk->get('snippet'));
				fclose($fh);
			}
		
		}
		
		
		
	}
}

/*
 *
 * TEMPLATES
 *
 */
 
function getTemplates(){
	//get chunks on the file system, and put them into the database.
	global $modx;
	
	$suffix = '.html';
	$root = MODX_BASE_PATH.'web_assets/templates/';
	$files = scanForElements($root, $suffix);
	
	foreach($files as $filename) {
		if(!$template = $modx->getObject('modTemplate', array(
			'description' => 'FEM:' . getRelativePath($filename)
		))){
			$template = $modx->newObject('modTemplate');
			$template->set('description', 'FEM:' . getRelativePath($filename));
			$tokens = explode('/', $filename);
			$name = end($tokens);
			$template->set('templatename', $name);
			unset($tokens[sizeof($tokens) - 1]);
			$category = end($tokens);
			
			if($category == 'template'){
				$category = '';
			}
			
			if(!$cat = $modx->getObject('modCategory', array('category' => $category))){
				$cat = $modx->newObject('modCategory');
				$cat->set('category', $category);
				$cat->save();
			}
			
			$template->set('category', $cat->get('id'));
			
			$name = str_replace($suffix, '', $name);
			//check that this is unique, if it isn't we fire a warning to the user.
			
			if($exists = $modx->getObject('modTemplate', array('templatename' => $name))){
				echo "A template with the name " . $name . " already exists. [" . $filename  . "]";
				exit();
			}
			
			$template->set('templatename', $name);
		}
		
		$template->set('content', file_get_contents($filename));
		$template->save();
				
	}
}

function putTemplates(){
	//put chunks that aren't files into the file system
	global $modx;
	$criteria = $modx->newQuery('modTemplate');
	$templates = $modx->getCollection('modTemplate', $criteria);
	foreach($templates as $templateId => $template){
		if(!$category = $template->getOne('Category')){
			$path = '';
		} else {
			$path = getFEMPath($category, '');
		}
		
		$path = MODX_BASE_PATH . 'web_assets/templates/' . $path;
		$filename = $path . $template->get('templatename') . '.html';
		
		if(!isAlreadyStaticElement($filename)){
		
			if(mkdir_recursive($path)){
				$template->set('description', 'FEM:' . getRelativePath($filename));
				$template->save();
				$fh = fopen($filename, 'w') or die("can't open file: " . $filename);
				fwrite($fh, $template->get('content'));
				fclose($fh);
			}
		
		}
		
		
		
	}
}

/*
 *
 * SNIPPETS
 *
 */

function getSnippets(){
	//get snippets on the file system, and put them into the database.
	global $modx;
	
	$suffix = '.php';
	$root = MODX_BASE_PATH.'web_assets/snippets/';
	$files = scanForElements($root, $suffix);
	
	foreach($files as $filename) {
		if(!$snippet = $modx->getObject('modSnippet', array(
			'description' => 'FEM:' . getRelativePath($filename)
		))){
			$snippet = $modx->newObject('modSnippet');
			$snippet->set('description', 'FEM:' . getRelativePath($filename));
			echo $snippet->get('description');
			$tokens = explode('/', $filename);
			$name = end($tokens);
			$snippet->set('name', $name);
			unset($tokens[sizeof($tokens) - 1]);
			$category = end($tokens);
			if($category == 'snippets'){
				$category = '';
			}
			if(!$cat = $modx->getObject('modCategory', array('category' => $category))){
				$cat = $modx->newObject('modCategory');
				$cat->set('category', $category);
				$cat->save();
			}
			
			$snippet->set('category', $cat->get('id'));
			
			$name = str_replace($suffix, '', $name);
			//check that this is unique, if it isn't we fire a warning to the user.
			
			if($exists = $modx->getObject('modSnippet', array('name' => $name))){
				echo "A snippet with the name " . $name . " already exists. [" . $filename  . "]";
				exit();
			}
			
			$snippet->set('name', $name);
		}
		
		$snippet->set('snippet', file_get_contents($filename));
		$snippet->save();
				
	}
}

function putSnippets(){
	//put snippets that aren't files into the file system
	global $modx;
	$criteria = $modx->newQuery('modSnippet');
	$snippets = $modx->getCollection('modSnippet', $criteria);
	foreach($snippets as $snippetId => $snippet){
		if(!$category = $snippet->getOne('Category')){
			$path = '';
		} else {
			$path = getFEMPath($category, '');
		}
		
		$path = MODX_BASE_PATH . 'web_assets/snippets/' . $path;
		$filename = $path . $snippet->get('name') . '.php';
		if(!isAlreadyStaticElement($filename)){
		
			if(mkdir_recursive($path)){
				$snippet->set('description', 'FEM:' . getRelativePath($filename));
				$snippet->save();
				$fh = fopen($filename, 'w') or die("can't open file: " . $filename);
				fwrite($fh, $snippet->get('snippet'));
				fclose($fh);
			}
		}
	}
}

function mirrorChunks(){
	putChunks();
	getChunks();
}

function mirrorTemplates(){
	putTemplates();
	getTemplates();
}

function mirrorSnippets(){
	putSnippets();
	getSnippets();
}

if($modx->context->get('key') == 'mgr'){
	return;
}

mirrorTemplates();
mirrorSnippets();
mirrorChunks();
$modx->cacheManager->refresh();