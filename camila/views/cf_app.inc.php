<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2025 Umberto Bresciani

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

defined('CAMILA_APPLICATION_NAME') or die('No direct script access.');

$_CAMILA['page']->camila_export_enabled = false;

if (isset($_REQUEST['admin'])) {
	require_once(CAMILA_DIR.'/admin/dashboards.inc.php');
} else {
	$query = 'SELECT url,short_title FROM ' . CAMILA_TABLE_PAGES . ', ' . CAMILA_TABLE_PLANG . ' WHERE ('. CAMILA_TABLE_PAGES .'.url = ' . CAMILA_TABLE_PLANG .'.page_url) and level>=' . $_CAMILA['user_level'] .' AND visible='.$_CAMILA['db']->qstr('yes').' AND active=' . $_CAMILA['db']->qstr('yes') . ' and parent=' . $_CAMILA['db']->qstr($_CAMILA['page_url']) . " and lang=" . $_CAMILA['db']->qstr($_CAMILA['lang']) . " ORDER by label_order";
	$result = $_CAMILA['db']->Execute($query);
	if ($result === false)
		camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg());
	
	$camilaUI = new CamilaUserInterface();
	$camilaUI->insertLineBreak();
	$camilaUI->insertLineBreak();
	$camilaUI->openBox();
	
	$count = 0;

	if (defined('CAMILA_APPLICATION_UI_KIT') && CAMILA_APPLICATION_UI_KIT == 'bulma') {
		$camilaUI->openMenuSection($_CAMILA['page_short_title']);
		while (!$result->EOF) {
			$camilaUI->addItemToMenuSection($result->fields['url'], $result->fields['short_title']);
			$count++;
			$result->MoveNext();
		}
		$camilaUI->closeMenuSection();
	} else {
		while (!$result->EOF) {
			$camilaUI->insertButton($result->fields['url'], $result->fields['short_title'],'');
			$count++;
			$result->MoveNext();
		}
	}
	$camilaUI->closeBox();
	
	if ($count == 0) {
		header("Location: index.php");
		exit;
	}
}
  
?>