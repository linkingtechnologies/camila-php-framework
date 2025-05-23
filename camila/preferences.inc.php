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

require_once(CAMILA_DIR.'ui.class.inc.php');

if ($_CAMILA['user_preferences']['c_ff'] == '')
    $_CAMILA['user_preferences']['c_ff'] = CAMILA_FACE;

if ($_CAMILA['user_preferences']['c_tf'] == '')
    $_CAMILA['user_preferences']['c_tf'] = CAMILA_TABLE_FACE;

$_CAMILA['page']= new CHAW_deck();
$_CAMILA['page']->camila_export_enabled = false;

$camilaUI = new CamilaUserInterface();
$camilaUI->openBox();
$camilaUI->insertTitle(camila_get_translation('camila.prefs'),'user');
$camilaUI->openButtonBar();
$camilaUI->insertSecondaryButton('./', camila_get_translation('camila.back'), 'chevron-left');
if (CAMILA_USER_CAN_CHANGE_PWD) {
	$camilaUI->insertSecondaryButton('cf_changepwd.php', camila_get_translation('camila.login.changepwd'), 'lock');
}
$camilaUI->closeButtonBar();

$myForm = new CHAW_form('cf_redirect.php');

$text = new CHAW_text(camila_get_translation('camila.prefs.fonttype'));
$text->set_br(0);
$myForm->add_text($text);
$mySelect = new CHAW_select('camila_font_face');
$mySelect3 = new CHAW_select('camila_table_font_face');

$_fields = explode(',','Arial,Times,Verdana');

foreach ($_fields as $key => $value) {
    if ($_CAMILA['user_preferences']['c_ff'] == $value)
        $mySelect->add_option($value, $value, HAW_SELECTED);
    else
        $mySelect->add_option($value, $value);

    if ($_CAMILA['user_preferences']['c_tf'] == $value)
        $mySelect3->add_option($value, $value, HAW_SELECTED);
    else
        $mySelect3->add_option($value, $value);

}

$myForm->add_select($mySelect);
$text = new CHAW_text('');
$text->set_br(2);
$myForm->add_text($text);

if ($_CAMILA['user_preferences']['c_fs'] == '')
    $_CAMILA['user_preferences']['c_fs'] = CAMILA_SIZE;

if ($_CAMILA['user_preferences']['c_ts'] == '')
    $_CAMILA['user_preferences']['c_ts'] = CAMILA_TABLE_SIZE;

$text = new CHAW_text(camila_get_translation('camila.prefs.fontsize'));
$text->set_br(0);
$myForm->add_text($text);
$mySelect = new CHAW_select('camila_font_size');
$mySelect2 = new CHAW_select('camila_table_font_size');


$_fields = explode(',','7pt,8pt,9pt,10pt,11pt,12pt,13pt,15pt,16pt,17pt,18pt,19pt,20pt,21pt,22pt,23pt,24pt');

foreach ($_fields as $key => $value) {
    if ($_CAMILA['user_preferences']['c_fs'] == $value)
        $mySelect->add_option($value, $value, HAW_SELECTED);
    else
        $mySelect->add_option($value, $value);

    if ($_CAMILA['user_preferences']['c_ts'] == $value)
        $mySelect2->add_option($value, $value, HAW_SELECTED);
    else
        $mySelect2->add_option($value, $value);

}

$myForm->add_select($mySelect);
$text = new CHAW_text('');
$text->set_br(2);
$myForm->add_text($text);

$text = new CHAW_text(camila_get_translation('camila.prefs.tables.fonttype'));
$text->set_br(0);
$myForm->add_text($text);

$myForm->add_select($mySelect3);
$text = new CHAW_text('');
$text->set_br(2);
$myForm->add_text($text);

$text = new CHAW_text(camila_get_translation('camila.prefs.tables.fontsize'));
$text->set_br(0);
$myForm->add_text($text);
$myForm->add_select($mySelect2);

$text = new CHAW_text('');
$text->set_br(2);
$myForm->add_text($text);


if (!intval($_CAMILA['user_preferences']['c_rp']))
    $_CAMILA['user_preferences']['c_rp'] = CAMILA_REPORT_RPP;

$myInput = new CHAW_input('camila_rows_per_page', $_CAMILA['user_preferences']['c_rp'], camila_get_translation('camila.prefs.tables.rowsperpage'));
$myInput->set_br(2);
$myForm->add_input($myInput);

$url=$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING'];
$url = str_replace("&"."camila_preferences", "", $url);
$url = str_replace("\?"."camila_preferences", "", $url);

$myInput = new CHAW_hidden('camila_redirect', $url);
$myForm->add_input($myInput);
$theSubmission = new CHAW_submit(camila_get_translation('camila.save'), 'submit');
$theSubmission->set_css_class('btn btn-md btn-default button is-primary is-small');
$myForm->add_submit($theSubmission);
$_CAMILA['page']->add_form($myForm);


$camilaUI->closeBox();

$_CAMILA['page']->use_simulator(CAMILA_CSS_DIR . 'skin2.css');
require(CAMILA_DIR . 'deck_settings.php');
require(CAMILA_DIR . 'footer.php');
exit();
?>