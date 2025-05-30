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
$configurator = new configurator();

if (isset($_REQUEST['camila_worktable_op'])) {
	$camilaUI = new CamilaUserInterface();

	$camilaUI->insertLineBreak();
	$camilaUI->insertLineBreak();

	$camilaUI->openBox();

    $configurator->operation($_REQUEST['camila_custom'], $_REQUEST['camila_worktable_op'], $_REQUEST['camila_returl']);
	$camilaUI->closeBox();
}
else
    $configurator->admin();

?>
