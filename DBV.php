<?php

/**
 * Copyright (c) 2012 Victor Stanciu (http://victorstanciu.ro)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @package DBV
 * @version 1.0.3
 * @author Victor Stanciu <vic.stanciu@gmail.com>
 * @link http://dbv.vizuina.com
 * @copyright Victor Stanciu 2012
 */
class DBV_Exception extends Exception
{

}

class DBV
{
	
	const CLI_STEP_ALL = 'all';
	const CLI_STEP_PRE = 'pre';
	const CLI_STEP_POST = 'post';
	

    protected $_action = "index";
    protected $_adapter;
    protected $_log = array();

    public function authenticate()
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'];
        } else {
            if (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $authorization = array_key_exists('HTTP_AUTHORIZATION', $headers)
                    ? $headers['HTTP_AUTHORIZATION']
                    : '';
            }
        }

        if ($authorization) {
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($authorization, 6)));
        }
        if (strlen(DBV_USERNAME) && strlen(DBV_PASSWORD) && (!isset($_SERVER['PHP_AUTH_USER']) || !($_SERVER['PHP_AUTH_USER'] == DBV_USERNAME && $_SERVER['PHP_AUTH_PW'] == DBV_PASSWORD))) {
            header('WWW-Authenticate: Basic realm="DBV interface"');
            header('HTTP/1.0 401 Unauthorized');
            echo __('Access denied');
            exit();
        }
    }

    /**
     * @return DBV_Adapter_Interface
     */
    protected function _getAdapter()
    {
        if (!$this->_adapter) {
            $file = DBV_ROOT_PATH . DS . 'lib' . DS . 'adapters' . DS . DB_ADAPTER . '.php';
            if (file_exists($file)) {
                require_once $file;

                $class = 'DBV_Adapter_' . DB_ADAPTER;
                if (class_exists($class)) {
                    $adapter = new $class;
                    try {
                        $adapter->connect(DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD, DB_NAME);
                        $this->_adapter = $adapter;
                    } catch (DBV_Exception $e) {
                        $this->error("[{$e->getCode()}] " . $e->getMessage());
                    }
                }
            }
        }

        return $this->_adapter;
    }

    public function dispatch()
    {
        $action = $this->_getAction() . "Action";
        $this->$action();
    }

    public function indexAction()
    {
        if ($this->_getAdapter()) {
            $this->schema = $this->_getSchema();
            $this->revisions = $this->_getRevisions();
            $this->revision = $this->_getRevisionIndex();
        }

        $this->_view("index");
    }

    public function schemaAction()
    {
        $items = isset($_POST['schema']) ? $_POST['schema'] : array();

        if ($this->_isXMLHttpRequest()) {
            if (!count($items)) {
                return $this->_json(array('error' => __("You didn't select any objects")));
            }

            foreach ($items as $item) {
                switch ($_POST['action']) {
                    case 'create':
                        $this->_createSchemaObject($item);
                        break;
                    case 'export':
                        $this->_exportSchemaObject($item);
                        break;
                }
            }

            $return = array('messages' => array());
            foreach ($this->_log as $message) {
                $return['messages'][$message['type']][] = $message['message'];
            }

            $return['items'] = $this->_getSchema();

            $this->_json($return);
        }
    }

    public function revisionsAction()
    {
        $revisions = isset($_POST['revisions']) ? array_filter($_POST['revisions'], 'is_numeric') : array();
        $current_revision = $this->_getRevisionIndex();

        if (count($revisions)) {
            sort($revisions);

            foreach ($revisions as $revision) {
                $files = $this->_getRevisionFiles($revision);

                if (count($files)) {
                    foreach ($files as $file) {
                        $file = DBV_REVISIONS_PATH . DS . $revision . DS . $file;
                        if (!$this->_runFile($file)) {
                            break 2;
                        }
                        else{
                        	$this->_setRevisionIndex($revision.'/'.basename($file));
                        }
                    }
                }
                
                $this->confirm(__("Executed revision #{revision}", array('revision' => "<strong>$revision</strong>")));
            }
        }

        if ($this->_isXMLHttpRequest()) {
            $return = array(
                'messages' => array(),
                'revision' => $this->_getRevisionIndex()
            );
            foreach ($this->_log as $message) {
                $return['messages'][$message['type']][] = $message['message'];
            }
            $this->_json($return);

        } else {
            $this->indexAction();
        }
    }


    public function saveRevisionFileAction()
    {
        $revision = intval($_POST['revision']);
        if (preg_match('/^[a-z0-9\._-]+$/i', $_POST['file'])) {
            $file = $_POST['file'];
        } else {
            $this->_json(array(
                'error' => __("Filename #{file} contains illegal characters. Please contact the developer.", array('file' => $_POST['file']))
            ));
        }

        $path = DBV_REVISIONS_PATH . DS . $revision . DS . $file;
        if (!file_exists($path)) {
            $this->_404();
        }

        $content = trim($_POST['content']);
        if(empty($content)){
        	$this->_json(array(
        			'error' => __("SQL can't be empty, ensure you write at least a comment.", array())
        	));
        }

        if (!@file_put_contents($path, $content)) {
            $this->_json(array(
                'error' => __("Couldn't write file: #{path}<br />Make sure the user running DBV has adequate permissions.", array('path' => "<strong>$path</strong>"))
            ));
        }

        $this->_json(array('ok' => true, 'message' => __("File #{path} successfully saved!", array('path' => "<strong>$path</strong>"))));
    }
    
    public function addRevisionFolderAction()
    {
    	$revisions = $this->_getAllRevisions();
    	$revision = intval($_GET['revision']);

    	$revisionsDir = $this->_getRevisions();
    	if(in_array($revision, $revisionsDir)){
    		$this->_json(array('ok' => false, 'message' => __("Revision #{revision} already exists!", array('revision' => "<strong>$revision</strong>"))));
    		return;
    	}

    	$dir = DBV_REVISIONS_PATH . DS . $revision;
    	
    	umask(0); /// override system umask to force 777 (revisions & files need to be linked/unlinked)
    	if (!@file_exists($dir)) {
    		if (!@mkdir($dir, 0777)){
    			$this->_json(array('ok' => false, 'message' => __("Cannot create revision #{revision}!", array('revision' => "<strong>$revision</strong>"))));
    			return;
    		}
    		else{
    			file_put_contents($dir.'/pre.sql', '-- auto-generated pre.sql');
    			file_put_contents($dir.'/post.sql', '-- auto-generated post.sql');
    			
    			chmod($dir.'/pre.sql', 0777);
    			chmod($dir.'/post.sql', 0777);
    		}
    	}

    	$this->_json(array('ok' => true, 'message' => __("Revision #{revision} successfully added!", array('revision' => "<strong>$revision</strong>")), 'html' => $this->_templateRevision($revision)));
    }
    
    public function _cliUpdate($revision = 0, $step = self::CLI_STEP_ALL){
    	
    	$ranRevisions = $this->_getAllRevisions();
    	
    	$_ranRevisions = array();
    	foreach($ranRevisions as $ranRevision){
    		$rev = explode('/', $ranRevision);
    		if(!in_array($rev[0], $_ranRevisions)){
    			array_push($_ranRevisions, $rev[0]);
    		}
    	}
    	
    	$allRevisions = $this->_getRevisions();
    	$revisionsToRun = array_diff($allRevisions, $_ranRevisions);
    	
    	if( in_array($revision, $revisionsToRun) ){
    		echo "Running '$step' single revision [$revision] ...".PHP_EOL;
    		
    		$this->_runRevisions($revision, $step);
    	}
    	elseif(!empty($revisionsToRun)){
    		$revs = implode(', ', $revisionsToRun);
    		echo "Running '$step' all new revisions [$revs] ...".PHP_EOL;
    		
    		sort($revisionsToRun);
    		foreach($revisionsToRun as $revision){
    			$this->_runRevisions($revision, $step);
    		}
    	}
    	else{
    		echo "Nothing to run ...".PHP_EOL;
    	}
    }
    
    /** Extract & Compile all the SQL to run */
    public function _cliExtract(){}
    
    /** This looks quite barbarian but ... well */
    public function _templateRevision($revision){
	    ob_start();
	    include DBV_ROOT_PATH.DS.'templates/revision-single.php';
	    return ob_get_clean();
    }

    protected function _createSchemaObject($item)
    {
        $file = DBV_SCHEMA_PATH . DS . "$item.sql";

        if (file_exists($file)) {
            if ($this->_runFile($file)) {
                $this->confirm(__("Created schema object #{item}", array('item' => "<strong>$item</strong>")));
            }
        } else {
            $this->error(__("Cannot find file for schema object #{item} (looked in #{schema_path})", array(
                'item' => "<strong>$item</strong>",
                'schema_path' => DBV_SCHEMA_PATH
            )));
        }
    }

    protected function _exportSchemaObject($item)
    {
        try {
            $sql = $this->_getAdapter()->getSchemaObject($item);

            $file = DBV_SCHEMA_PATH . DS . "$item.sql";

            if (@file_put_contents($file, $sql)) {
                $this->confirm(__("Wrote file: #{file}", array('file' => "<strong>$file</strong>")));
            } else {
                $this->error(__("Cannot write file: #{file}", array('file' => "<strong>$file</strong>")));
            }
        } catch (DBV_Exception $e) {
            $this->error(($e->getCode() ? "[{$e->getCode()}] " : '') . $e->getMessage());
        }
    }
    
    /// CLI specific function (need to run PRE before ...)
    protected function _runRevisions($revision, $step){
    	$files = $this->_getRevisionFiles($revision);
    	if(count($files)){
	    	foreach($files as $file){
	    		$filepath = DBV_REVISIONS_PATH . DS . $revision . DS . $file;
		    	switch($step){
		    		case DBV::CLI_STEP_ALL:
		    				echo "Executing [".$step."] $revision/$file ...".PHP_EOL;
		    				if(!$this->_runFile($filepath)){
		    					return false;
		    				}
		    				$this->_setRevisionIndex($revision.'/'.basename($file));
		    			break;
		    		case DBV::CLI_STEP_PRE: case DBV::CLI_STEP_POST:
		    				if(preg_match("#^".$step."#", $file)){
		    					echo "Executing [".$step."] $revision/$file ...".PHP_EOL;
		    					if(!$this->_runFile($filepath)){
		    						return false;
		    					}
		    					$this->_setRevisionIndex($revision.'/'.basename($file));
		    				}
		    			break;
		    	}
	    	}
    	}
    	return $result;
    }

    protected function _runFile($file)
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'sql':
                $content = file_get_contents($file);
                if ($content === false) {
                    $this->error(__("Cannot open file #{file}", array('file' => "<strong>$file</strong>")));
                    return false;
                }

                try {
                    $this->_getAdapter()->query($content);
                    return true;
                } catch (DBV_Exception $e) {
                    $this->error("[{$e->getCode()}] {$e->getMessage()} in <strong>$file</strong>");
                }
                break;
        }

        return false;
    }

    protected function _getAction()
    {
        if (isset($_GET['a'])) {
            $action = $_GET['a'];
            if (in_array("{$action}Action", get_class_methods(get_class($this)))) {
                $this->_action = $action;
            }
        }
        return $this->_action;
    }

    protected function _view($view)
    {
        $file = DBV_ROOT_PATH . DS . 'templates' . DS . "$view.php";
        if (file_exists($file)) {
            include($file);
        }
    }

    protected function _getSchema()
    {
        $return = array();
        $database = $this->_getAdapter()->getSchema();
        $disk = $this->_getDiskSchema();

        if (count($database)) {
            foreach ($database as $item) {
                $return[$item]['database'] = true;
            }
        }

        if (count($disk)) {
            foreach ($disk as $item) {
                $return[$item]['disk'] = true;
            }
        }

        ksort($return);
        return $return;
    }

    protected function _getDiskSchema()
    {
        $return = array();

        foreach (new DirectoryIterator(DBV_SCHEMA_PATH) as $file) {
            if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'sql') {
                $return[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            }
        }

        return $return;
    }

    protected function _getRevisions()
    {
        $return = array();

        foreach (new DirectoryIterator(DBV_REVISIONS_PATH) as $file) {
            if ($file->isDir() && !$file->isDot() && is_numeric($file->getBasename())) {
                $return[] = $file->getBasename();
            }
        }

        rsort($return, SORT_NUMERIC);

        return $return;
    }
    
    protected function _getRevisionIndex(){
    	switch (DBV_REVISION_INDEX) {
    		case 'LAST':
    			return $this->_getCurrentRevision();
    			break;
    		case 'ALL':
    			return $this->_getAllRevisions();
    			break;
    		default:
    			$this->error("Incorrect revision index specified");
    			break;
    	}
    }

    protected function _getCurrentRevision()
    {
        switch (DBV_REVISION_STORAGE) {
            case 'FILE':
                $file = DBV_META_PATH . DS . 'revision';
                if (file_exists($file)) {
                    return intval(file_get_contents($file));
                }
                return 0;
                break;
            case 'ADAPTER':
                return $this->_getAdapter()->getCurrentRevision();
                break;
            default:
                $this->error("Incorrect revision storage specified");
                break;
        }
    }
    
    protected function _getAllRevisions()
    {
    	switch (DBV_REVISION_STORAGE) {
    		case 'FILE':
    			$file = DBV_META_PATH . DS . 'revision';
    			if (file_exists($file)) {
    				$revisions = json_decode(file_get_contents($file));
    				return is_array($revisions) ? $revisions : array();
    			}
    			return 0;
    			break;
    		case 'ADAPTER':
    			$revisions = json_decode($this->_getAdapter()->getCurrentRevision());
    			return is_array($revisions) ? $revisions : array();
    			break;
    		default:
    			$this->error("Incorrect revision storage specified");
    			break;
    	}
    }
    
    protected function _setRevisionIndex($revision, $current_revision = null){
    	switch (DBV_REVISION_INDEX) {
    		case 'LAST':
    			if($revision >= $current_revision)
    			{
    				return $this->_setCurrentRevision($revision);
    			}
    			break;
    		case 'ALL':
    			return $this->_addRevision($revision);
    			break;
    		default:
    			$this->error("Incorrect revision index specified");
    			break;
    	}
    }

    protected function _setCurrentRevision($revision)
    {
        switch (DBV_REVISION_STORAGE) {
            case 'FILE':
                $file = DBV_META_PATH . DS . 'revision';
                if (!@file_put_contents($file, $revision)) {
                    $this->error("Cannot write revision file");
                }
                break;
            case 'ADAPTER':
                if (!$this->_getAdapter()->setCurrentRevision($revision)){
                    $this->error("Cannot save revision to DB");
                }
                break;
            default:
                $this->error("Incorrect revision storage specified");
                break;
        }
    }
    
    protected function _addRevision($revision)
    {
    	$revisions = json_encode(array_unique(array_merge($this->_getAllRevisions(), array($revision))));
    	switch (DBV_REVISION_STORAGE) {
    		case 'FILE':
    			$file = DBV_META_PATH . DS . 'revision';
    			if (!@file_put_contents($file, $revisions)) {
    				$this->error("Cannot write revision file");
    			}
    			break;
    		case 'ADAPTER':
    			if (!$this->_getAdapter()->setCurrentRevision($revisions)){
    				$this->error("Cannot save revision to DB");
    			}
    			break;
    		default:
    			$this->error("Incorrect revision storage specified");
    			break;
    	}
    }
    
    protected function _isRan($revision)
    {
    	switch (DBV_REVISION_INDEX) {
    		case 'LAST':
    			return ($this->_getCurrentRevision() >= $revision);
    			break;
    		case 'ALL':
    			$files = $this->_getRevisionFiles($revision);
    			$allRevisions = $this->_getAllRevisions();
    			$isRan = true;
    			foreach($files as $file){
    				$isRan = $isRan && in_array($revision.'/'.$file, $allRevisions);
    			}
    			return $isRan;
    			break;
    		default:
    			$this->error("Incorrect revision index specified");
    			break;
    	}
    }
    
    protected function _isRanFile($revisionFile)
    {
    	switch (DBV_REVISION_INDEX) {
    		case 'ALL':
    			return in_array($revisionFile, $this->_getAllRevisions());
    			break;
    		case 'LAST': default:
    			$this->error("Incompatible revision index, you must use ALL.");
    			break;
    	}
    }

    protected function _getRevisionFiles($revision)
    {
        $dir = DBV_REVISIONS_PATH . DS . $revision;
        $return = array();

        foreach (new DirectoryIterator($dir) as $file) {
            if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'sql') {
                $return[] = $file->getBasename();
            }
        }

        rsort($return, SORT_STRING); /// Modified sort to get pre > post
        return $return;
    }

    protected function _getRevisionFileContents($revision, $file)
    {
        $path = DBV_REVISIONS_PATH . DS . $revision . DS . $file;
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        return false;
    }

    public function log($item)
    {
        $this->_log[] = $item;
    }

    public function error($message)
    {
        $item = array(
            "type" => "error",
            "message" => $message
        );
        $this->log($item);
    }

    public function confirm($message)
    {
        $item = array(
            "type" => "success",
            "message" => $message
        );
        $this->log($item);
    }

    protected function _404()
    {
        header('HTTP/1.0 404 Not Found', true);
        exit('404 Not Found');
    }

    protected function _json($data = array())
    {
        header("Content-type: application/json");
        echo (is_string($data) ? $data : json_encode($data));
        exit();
    }

    protected function _isXMLHttpRequest()
    {
        if ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            return true;
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if ($headers['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                return true;
            }
        }

        return false;
    }

    /**
     * Singleton
     * @return DBV
     */
    static public function instance()
    {
        static $instance;
        if (!($instance instanceof self)) {
            $instance = new self();
        }
        
        return $instance;
    }

}
