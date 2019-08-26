<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2019 Umberto Bresciani

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


require_once('../../camila/autoloader.inc.php');

require('../../camila/config.inc.php');

require('../../camila/i18n.inc.php');
require('../../camila/camila_hawhaw.php');
require('../../camila/database.inc.php');

defined('CAMILA_APPLICATION_NAME') or die('No direct script access.');

global $_CAMILA;

($_REQUEST['lang'] != '') or die('Lang is not set.');

camila_translation_init();

$_CAMILA['page'] = new CHAW_deck('', HAW_ALIGN_LEFT); 
$_CAMILA['page']->camila_export_enabled = false;

$camilaAuth = new CamilaAuth();
$db = $camilaAuth->getDatabaseConnection(CAMILA_DB_DSN);

if (is_object($db)) {
	$camilaAuth->db = $db;
	if ($camilaAuth->checkUserTable() >=0) {		
		$configurator = new configurator();
		$configurator->db = $db;		
		$resultTemp = $db->Execute('select id from ' . CAMILA_TABLE_WORKT);
        if ($resultTemp === false) {
            camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $this->db->ErrorMsg());
		} else {
			while (!$resultTemp->EOF) {
				$successTemp = $configurator->create_script_from_template($resultTemp->fields['id']);
				camila_information_text('WorkTable rebuild ['.$resultTemp->fields['id'].']: OK');
				$resultTemp->MoveNext();
			}
		}
	}
} else {
	camila_error_text('Database Connection: KO');
}

$_CAMILA['page']->use_simulator(CAMILA_CSS_DIR . 'skin0.css');
$_CAMILA['page']->create_page();
?>
