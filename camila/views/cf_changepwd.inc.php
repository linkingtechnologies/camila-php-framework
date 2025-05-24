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

if (CAMILA_USER_CAN_CHANGE_PWD) {
	global $_CAMILA;

	$camilaAuth = new CamilaAuth();
	$camilaAuth->db = $_CAMILA['db'];
	
	$camilaUI = new CamilaUserInterface();
	
	$camilaUI->insertLineBreak();
	$camilaUI->insertLineBreak();

	$camilaUI->openBox();
	$camilaUI->insertTitle(camila_get_translation('camila.login.changepwd'),'user');
	
	$camilaUI->openButtonBar();
	$camilaUI->insertSecondaryButton('./', camila_get_translation('camila.back'), 'chevron-left');
	$camilaUI->closeButtonBar();

	$username = $camilaAuth->getUsernameFromSession();
	
	$form = new phpform('cf_changepwd');
	$form->submitbutton = camila_get_translation('camila.updatebutton');
	$form->drawrules = false;
	$form->preservecontext = true;
	new form_textbox($form, 'username', camila_get_translation('camila.login.username'));
	$form->fields['username']->value = $username;
	$form->fields['username']->updatable = false;
	new form_password($form, 'old', camila_get_translation('camila.form.oldpassword'), true, 30, 200);
	new form_password($form, 'new', camila_get_translation('camila.form.password'), true, 30, 200);
	new form_password($form, 'confirmnew', camila_get_translation('camila.form.confirmpassword'), true, 30, 200);

	if ($form->process())
	{
		$old = $form->fields['old']->value;
		$new = $form->fields['new']->value;
		$old2 = $form->fields['old']->originalValue;
		$new2 = $form->fields['new']->originalValue;
		$confirmnew = $form->fields['confirmnew']->value;
		$confirmnew2 = $form->fields['confirmnew']->originalValue;
		if ($new2 != $confirmnew2) {
			camila_error_text(camila_get_translation('camila.formvalidationerror911'));
		}
		else
		{
			if (!$camilaAuth->checkCredentials($username, $old2))
				camila_error_text(camila_get_translation('camila.formvalidationerror910').'!');
			else
			{
				if($camilaAuth->updatePassword($username, $new))
					camila_information_text(camila_get_translation('camila.form.data.updated'));
				else
					camila_error_text(camila_get_translation('camila.erroroccurred'));
			}
		}
	}
	else
		$form->draw();
	
	$camilaUI->closeBox();
}

?>