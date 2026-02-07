<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2026 Umberto Bresciani

    Camila PHP Framework is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Camila PHP Framework is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Camila PHP Framework. If not, see <http://www.gnu.org/licenses/>. */

require_once('../../vendor/autoload.php');
require_once('../../camila/autoloader.inc.php');

require_once('../../camila/config.inc.php');
require_once('../../camila/i18n.inc.php');
require_once('../../camila/database.inc.php');
require_once('../../camila/camila.php');
require_once('../../camila/auth.class.inc.php');
require_once('../../camila/worktable.class.inc.php');

require_once(CAMILA_VENDOR_DIR . '/adodb/adodb-php/adodb-csvlib.inc.php');

require('../../camila/api.include.php');

use Tqdev\PhpCrudApi\Api;
use Tqdev\PhpCrudApi\Config\Config;
use Tqdev\PhpCrudApi\RequestFactory;
use Tqdev\PhpCrudApi\ResponseUtils;

$camilaAuth                  = new CamilaAuth();
$camilaAuth->db              = $_CAMILA['db'];
$camilaAuth->userTable       = CAMILA_TABLE_USERS;
$camilaAuth->authUserTable   = CAMILA_AUTH_TABLE_USERS;
$camilaAuth->applicationName = CAMILA_APPLICATION_NAME;

if (basename($_SERVER['PHP_SELF']) == 'cf_api.php' || basename($_SERVER['SCRIPT_NAME']) == 'cf_api.php') {

	global $_CAMILA;
	
	$camilaWT = new CamilaWorkTable();
	$camilaWT->db = $_CAMILA['db'];
	$mapping = $camilaWT->getWorktableColumnMapping();

	$conf = [];
	if ($_CAMILA['db']->databaseType == 'sqlite' || $_CAMILA['db']->databaseType == 'sqlite3') {
		$conf = [
			 'driver' => $_CAMILA['db']->dataProvider,
			 'address' => $_CAMILA['db']->host,
			 'basePath' => '/app/'.CAMILA_APP_DIR.'/cf_api.php'];
	} else {
		$conf = [
			'driver' => $_CAMILA['db']->dataProvider,
			'address' => $_CAMILA['db']->host,
			'basePath' => '/app/'.CAMILA_APP_DIR.'/cf_api.php',
			'port' => $_CAMILA['db']->port,
			'username' => $_CAMILA['db']->user,
			'password' => parse_url(CAMILA_DB_DSN, PHP_URL_PASS),
			'database' => $_CAMILA['db']->database
		];

		if (defined('CAMILA_AUTH_DSN') && CAMILA_AUTH_DSN !== CAMILA_DB_DSN) {
			$auth = parse_url(CAMILA_AUTH_DSN);
			$database = isset($auth['path']) ? ltrim($auth['path'], '/') : null;
			$conf = array_merge($conf, [
				'dbAuth.driver'   => $auth['scheme'] ?? null,
				'dbAuth.address'  => $auth['host']   ?? null,
				'dbAuth.port'     => $auth['port']   ?? null,
				'dbAuth.username' => $auth['user']   ?? null,
				'dbAuth.password' => $auth['pass']   ?? null,
				'dbAuth.database' => $database,
			]);
			$conf = array_merge($conf, [
				'apiKeyDbAuth.driver'   => $auth['scheme'] ?? null,
				'apiKeyDbAuth.address'  => $auth['host']   ?? null,
				'apiKeyDbAuth.port'     => $auth['port']   ?? null,
				'apiKeyDbAuth.username' => $auth['user']   ?? null,
				'apiKeyDbAuth.password' => $auth['pass']   ?? null,
				'apiKeyDbAuth.database' => $database,
			]);
        }

	}

	$conf['debug'] = true;
	$conf['middlewares'] = 'dbAuth,apiKeyDbAuth,authorization';
	$conf['authorization.tableHandler'] = function ($operation, $tableName) {
		$ret = true;
		if (str_ends_with($tableName,'_camila_users') || str_ends_with($tableName,'_camila_files'))
			$ret = false;
		/*if (!str_starts_with($tableName,CAMILA_APP_DIR))
			$ret = false;*/
		return $ret;
	};
	//$conf['dbAuth.usersTable']=CAMILA_TABLE_USERS;
	
	if (defined('CAMILA_AUTH_TABLE_USERS')) {
		$conf['dbAuth.loginTable']=CAMILA_AUTH_TABLE_USERS;
		$conf['dbAuth.usersTable']=CAMILA_TABLE_USERS;
		$conf['apiKeyDbAuth.loginTable']=CAMILA_AUTH_TABLE_USERS;
		$conf['apiKeyDbAuth.usersTable']=CAMILA_TABLE_USERS;
	} else {
		$conf['dbAuth.loginTable']=CAMILA_TABLE_USERS;
		$conf['apiKeyDbAuth.loginTable']=CAMILA_TABLE_USERS;
	}
	
	$conf['apiKeyDbAuth.apiKeyColumn']='token';

	$conf['authorization.columnHandler'] = function ($operation, $tableName, $columnName) {
		$ret = true;
		$excluded = [
			'created',
			'created_by',
			'created_by_name',
			'created_by_surname',
			'created_src',
			'last_upd',
			'last_upd_by',
			'last_upd_by_name',
			'last_upd_by_surname',
			'last_upd_src',
			'grp',
			'mod_num',
			'is_deleted',
			'cf_bool_is_selected',
			'cf_bool_is_special',
		];
		
		$ret = !in_array($columnName, $excluded);

		return $ret;
	};
	$conf['mapping'] = $mapping;
	$conf['controllers'] = 'records,openapi,status,columns';
	$conf['customControllers'] = 'Tqdev\PhpCrudApi\CamilaCliController,Tqdev\PhpCrudApi\CamilaWorktableController';
	//$conf['apiKeyAuth.keys'] = CAMILA_APIKEYAUTH_KEYS;
	$config = new Config($conf);

	$request = RequestFactory::fromGlobals();
	$api = new Api($config);
	$response = $api->handle($request);
	ResponseUtils::output($response);
} else {

	if (!isset($_SERVER['PHP_AUTH_USER'])) {
		$camilaAuth->raiseError();
		exit;
	} else {
		$url            = $_SERVER['REQUEST_URI'];
		$method         = $_SERVER['REQUEST_METHOD'];
		$getArgs        = $_GET;
		$postArgs       = $_POST;
		$requestContent = file_get_contents('php://input');
		//parse_str(file_get_contents('php://input'), $putArgs);
		//parse_str(file_get_contents('php://input'), $deleteArgs);
		
		if (!$camilaAuth->checkCredentials($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']))
		{
			$camilaAuth->raiseError();
			exit;
		}

		$urlParts  = parse_url($url);
		// substring from 1 to avoid leading slash
		$pathParts = explode('/', substr($urlParts['path'], 1));
		
		$version    = $pathParts[array_search('api', $pathParts) + 1];
		$resource   = $pathParts[array_search('api', $pathParts) + 2];
		$resourceId = $pathParts[array_search('api', $pathParts) + 3];
		
		switch ($method) {
			
			case 'GET':
				
				switch ($resource) {
					
					case 'query':
						
						$query        = $getArgs['q'];
						$camilaWT     = new CamilaWorkTable();
						//$camilaWT->wtTable = 'cms_camila_worktables';
						//$camilaWT->wtColumn = 'cms_camila_worktables_cols';
						$camilaWT->db = $_CAMILA['db'];
						global $camilaWT;
						//echo $query;
						$result = $camilaWT->startExecuteQuery($query);

						if ($result) {
							//$rs->timeToLive = 1;
							//echo _rs2serialize($result,$conn,$sql);
							
							echo '{"done" : true,"totalSize" : ' . $result->RecordCount() . ',"records" : [';
							
							$count = 0;
							while (!$result->EOF) {
								$a = $result->fields;
								if ($count > 0)
									echo ",";
								echo json_encode($a);
								//print_r($a);
								$count++;                            
								$result->MoveNext();
							}
							
							echo ']}';
							
							$result = $camilaWT->endExecuteQuery();
							
							//$result->Close();
						} else
							err($conn->ErrorNo() . $sep . $conn->ErrorMsg());

						
						
						if ($collectionId != '') {
							
						} else {
							
						}
						
						break;
						

						case 'objects':

						//echo $resourceId;
						$camilaWT     = new CamilaWorkTable();
						$camilaWT->db = $_CAMILA['db'];
						global $camilaWT;
						$result2 = $camilaWT->getWorktableColumns($resourceId);
						while (!$result2->EOF) {
							$b = $result2->fields;
							print_r($b);
							//$ttemp->setVariable($a['short_title'].'.'.$b['name'], $prefix ? $a['tablename'].'.'.$b['col_name'] : $b['col_name'], true);
							
							echo "!";
							$result2->MoveNext();
						}
						break;
				}
				break;
			
			case 'PATCH':
				echo "!!!";
				
				switch ($collection) {
						
				}
				break;

			case 'POST':
				echo "!!!";
				
				switch ($collection) {
						
				}
				break;
		}
	}
}
?>
