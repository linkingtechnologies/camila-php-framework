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

$camilaUI = new CamilaUserInterface();
$camilaUI->insertLineBreak();
$camilaUI->insertLineBreak();

$camilaUI->openBox();
$camilaUI->insertTitle(camila_get_translation('camila.worktable.configuration'),'cog');
$configurator = new configurator();
$configurator->start_wizard();
$camilaUI->closeBox();
?>
