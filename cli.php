<?php

define('CLI_ROOT_PATH', dirname(__FILE__));

function error($message){
	die($message.PHP_EOL);
}

require_once CLI_ROOT_PATH . DIRECTORY_SEPARATOR . 'config.php';
require_once CLI_ROOT_PATH . DIRECTORY_SEPARATOR . 'lib/functions.php';
require_once CLI_ROOT_PATH . DIRECTORY_SEPARATOR . 'DBV.php';

if(empty($argv[1]) || $argv[1] == 'help'){
	echo "DBV Command-Line Tool
			
Usage 
	php cli.php <update|extract> <pre|post|all> [revision_id]
			
	update|extract		The action to run
	pre|post|all		Defines the SQL Workflow segment to run
	revision_id		Pass a single revision id to run, otherwise, will run all new revisions
			
";
}

$dbv = DBV::instance();

/// Définition des arguments pour l'action
$action = 'help';
$step = DBV::CLI_STEP_ALL;
$revision = 0;
$force = false;

switch($argv[2]){
	case DBV::CLI_STEP_ALL: case DBV::CLI_STEP_PRE: case DBV::CLI_STEP_POST:
			$step =  $argv[2];
		break;
	default:
		error('Unknown step');
}

if(isset($argv[3])){
	$revision = $argv[3];
}

if(isset($argv[4]) && $argv[4] == '--force'){
	$force = true;
}

switch($argv[1]){
	case 'update':
			$dbv->_cliUpdate($revision, $step, $force);
		break;
	case 'extract':
			$dbv->_cliExtract($revision, $step, $force);
		break;
	default:
		error('Unknown action');
}

