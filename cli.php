<?php

define('CLI_ROOT_PATH', dirname(__FILE__));

require_once CLI_ROOT_PATH . DIRECTORY_SEPARATOR . 'config.php';
require_once CLI_ROOT_PATH . DIRECTORY_SEPARATOR . 'lib/functions.php';
require_once CLI_ROOT_PATH . DIRECTORY_SEPARATOR . 'DBV.php';

if(empty($argv[1]) || $argv[1] == 'help'){
	echo "DBV Command-Line Tool
			
Usage 
	php -f cli.php <pre|post|all> [revision_id]
			
	pre|post|all	Defines the segment to run
	revision_id		Pass a single revision id to run, otherwise, will run all new revisions
			
";
}

$dbv = DBV::instance();

/// Définition des arguments pour l'action
$revision = 0;
$step = DBV::CLI_STEP_ALL;
$force = false;

switch($argv[1]){
	case DBV::CLI_STEP_ALL: case DBV::CLI_STEP_PRE: case DBV::CLI_STEP_POST:
			$step =  $argv[1];
		break;
	default:
		die('Unknown step');
}

if(isset($argv[2])){
	$revision = $argv[2];
}

if(isset($argv[3]) && $argv[3] == '--force'){
	$force = true;
}

$dbv->_cliAction($revision, $step, $force);
