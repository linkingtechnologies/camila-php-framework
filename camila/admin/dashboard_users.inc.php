<?php

/* This File is part of Camila PHP Framework
   Copyright (C) 2006-2025 Umberto Bresciani

   Camila PHP Framework is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   Camila PHP Framework is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with Camila PHP Framework; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA */

$camilaUI = new CamilaUserInterface();
$camilaUI->openBox();

$string = camila_get_translation('camila.login.options.group');

// Split and clean parts
$parts = explode(';', $string);
$parts = array_map(function($item) {
    return trim(rtrim($item, ','));
}, $parts);

$result = [];

for ($i = 0; $i < count($parts); $i += 2) {
    // Default key/value
    $key = $parts[$i] ?? '';
    $value = $parts[$i + 1] ?? '';

    if (!isset($parts[$i + 1])) {
        $key = '';
        $value = $parts[$i] ?: '<>';
    }

    $result[] = '"' . $key . '": ' . $value;
}


$form = new dbform(CAMILA_TABLE_USERS, 'id', 'id,username,surname,name,grp,level,visibility_type,token', 'username', 'asc', 'username <> ' . $_CAMILA['db']->qstr($_CAMILA['user']), true, true, true, false, true);
$form->mapping=camila_get_translation('camila.mapping.admin.users');

new form_textbox($form, 'id', camila_get_translation('camila.worktable.field.id'), true, 50, 50);

new form_textbox($form, 'username', camila_get_translation('camila.login.username'), true, 50, 50);
new form_textbox($form, 'surname', camila_get_translation('camila.login.surname'), false, 50, 50);
new form_textbox($form, 'name', camila_get_translation('camila.login.name'), false, 50,50);
new form_password($form, 'password', camila_get_translation('camila.login.password'));
new form_static_listbox($form, 'level', camila_get_translation('camila.login.level'), camila_get_translation('camila.login.options.level'));
//new form_static_listbox($form, 'grp', camila_get_translation('camila.login.group'), camila_get_translation('camila.login.options.group'));
new form_textbox($form, 'grp', camila_get_translation('camila.login.group'), false, 20);
if (is_object($form->fields['grp']))
	$form->fields['grp']->help = implode("<br/>", $result);

if (is_object($form->fields['grp']))
	$form->fields['grp']->defaultvalue = 'default';

new form_static_listbox($form, 'visibility_type', camila_get_translation('camila.login.visibility'), camila_get_translation('camila.login.options.visibility'));

if (is_object($form->fields['grp']))
    $form->fields['grp']->defaultvalue = 'default';

if (is_object($form->fields['id'])) {
	$camilaAuth = new CamilaAuth();
	$camilaAuth->db = $_CAMILA['db'];
    $form->fields['id']->defaultvalue = $camilaAuth->getAutoincrementValue();
}

new form_generate_password($form, 'token', camila_get_translation('camila.login.token'), 100, false, 'alpha', 75);

$form->clear();
$form->process();
$form->draw();

$camilaUI->closeBox();
?>