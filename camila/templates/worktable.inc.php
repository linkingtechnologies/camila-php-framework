<?php
$_CAMILA['page']->camila_worktable = true;

$wt_id = substr($_SERVER['PHP_SELF'], 12, -4);
$wt_short_title = '${wt_short_title}';
$wt_full_title = '${wt_full_title}';

$filter = '';

if ($_CAMILA['user_visibility_type']=='personal')
	$filter= ' where ${personal_visibility_field}='.$_CAMILA['db']->qstr($_CAMILA['user']);

if ($_CAMILA['user_visibility_type']=='group' && '${group_visibility_field}' != '')
	$filter= ' where ${group_visibility_field}='.$_CAMILA['db']->qstr($_CAMILA['user_group']);


if (intval($wt_id) > 0)
    $_CAMILA['page']->camila_worktable_id = $wt_id;

function worktable_get_safe_temp_filename($name) {
    global $_CAMILA;
    return CAMILA_TMP_DIR . '/lastval_' . $_CAMILA['lang'] . '_' . preg_replace('/[^a-z]/', '', strtolower($name));
}

function worktable_get_last_value_from_file($name) {
    return file_get_contents(worktable_get_safe_temp_filename($name));
}


function worktable_get_next_autoincrement_value($table, $column) {

    global $_CAMILA;

    $result = $_CAMILA['db']->Execute('select max('.$column.') as id from ' . $table);
    if ($result === false)
        camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg());

    return intval($result->fields['id']) + 1;

}


function worktable_parse_default_expression($expression, $form) {
    return camila_parse_default_expression($expression, $form->fields['id']->defaultvalue);
}

function worktable_get_parentid($wtId, $lookupParentColumn, $lookupParentTable, $lookupChildColumn) {
	$pid = '';
	global $_CAMILA;
	if (isset($_REQUEST['camila_addparams']) && $_REQUEST['camila_addparams'] !='') {
		$params_array = [];
		$camila_addparams_decoded = html_entity_decode($_REQUEST['camila_addparams']);
		parse_str($camila_addparams_decoded, $params_array);
		if (isset($params_array['pid'])) {
			$pid = $params_array['pid'];
		}
	} else {
		if (isset($_REQUEST['pid'])) {
			$pid = $_REQUEST['pid'];
		} else {
			$rid = CAMILA_TABLE_WORKP . $wtId. '_id';
			if (isset($_REQUEST['camila_update']) || isset ($_REQUEST[$rid])) {
				$u = unserialize(stripslashes($_REQUEST['camila_update']));
				$id = $u['camilakey_id'];
				if ($id == '') {
					$id = $_REQUEST[$rid];
				}
				$table = CAMILA_TABLE_WORKP . $wtId;
				//$check = camila_token($_REQUEST['camila_update']);
				$rr = $_CAMILA['db']->Execute("select $lookupChildColumn from $table WHERE id =".$_CAMILA['db']->qstr($id));
				if ($rr === false) {
					camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg()); 
				} else {
					$rrr = $_CAMILA['db']->Execute("SELECT id FROM $lookupParentTable WHERE $lookupParentColumn =". $_CAMILA['db']->qstr($rr->fields[$lookupChildColumn]));
					if ($rrr === false) {
						camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg()); 
					} else {
						$pid = $rrr->fields['id'];
					}
				}
			}							
		}
	}
	return $pid;
}

if (camila_form_in_update_mode('${table}')) {

    <!-- $BeginBlock require -->
    require_once(CAMILA_DIR . 'datagrid/elements/form/${form_require}.php');
    <!-- $EndBlock require -->

    $form = new dbform('${table}', 'id');
	$lookupParentColumn = '${lookup_parent_column}';
	$lookupParentTable = '${lookup_parent_table}';
	$lookupChildColumn = '${lookup_child_column}';

    if ($_CAMILA['adm_user_group'] != CAMILA_ADM_USER_GROUP)
    {
        $form->caninsert = ${caninsert};
        $form->candelete = ${candelete};
        $form->canupdate = ${canupdate};
    } else if ($_CAMILA['adm_user_group'] == CAMILA_ADM_USER_GROUP) {
        $form->caninsert = true;
        $form->candelete = true;
        $form->canupdate = true;
    }
	
	if (${has_parent}) {
		$form->caninsert = false;
	}

    $form->drawrules = true;
    $form->drawheadersubmitbutton = false;

    new form_textbox($form, 'id', camila_get_translation('camila.worktable.field.id'));
    if (is_object($form->fields['id'])) {
        if ($_REQUEST['camila_update'] == 'new' && !isset($_REQUEST['camila_phpform_sent'])) {
            $_CAMILA['db_genid'] = $_CAMILA['db']->GenID(CAMILA_APPLICATION_PREFIX.'worktableseq', 100000);
            $form->fields['id']->defaultvalue = $_CAMILA['db_genid'];
        }
        $form->fields['id']->updatable = false;
        $form->fields['id']->forcedraw = true;
    }

	new form_hidden($form, 'uuid', camila_get_translation('camila.worktable.field.uuid'));

	if (defined('CAMILA_APPLICATION_UUID_ENABLED') && CAMILA_APPLICATION_UUID_ENABLED === true) {
        if ($_REQUEST['camila_update'] == 'new' && !isset($_REQUEST['camila_phpform_sent'])) {
            $form->fields['uuid']->defaultvalue = camila_generate_uuid();
        }
		if (is_object($form->fields['uuid'])) {
			$form->fields['uuid']->updatable = false;
			$form->fields['uuid']->forcedraw = true;			
		}		
	}

    <!-- $BeginBlock element -->
    ${form_element}
    <!-- $EndBlock element -->

	if (!${is_parent}) {
		if (CAMILA_WORKTABLE_SPECIAL_ICON_ENABLED || $_CAMILA['adm_user_group'] == CAMILA_ADM_USER_GROUP)
			new form_static_listbox($form, 'cf_bool_is_selected', camila_get_translation('camila.worktable.field.selected'), camila_get_translation('camila.worktable.options.noyes'));

		if (CAMILA_WORKTABLE_SELECTED_ICON_ENABLED || $_CAMILA['adm_user_group'] == CAMILA_ADM_USER_GROUP)
			new form_static_listbox($form, 'cf_bool_is_special', camila_get_translation('camila.worktable.field.special'), camila_get_translation('camila.worktable.options.noyes'));
	} else {
		
	}
	
	if($_REQUEST['camila_update'] == 'new') {
		if (isset($_GET['pid'])) {

			$result = $_CAMILA['db']->Execute('select '.$_GET['pf'].' as value FROM ${table_prefix}'.$_GET['pt'].' WHERE id = ' . $_CAMILA['db']->qstr($_GET['pid']));
			if ($result === false)
				camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg());

			if (is_object($form->fields[$_GET['cf']])) {
				$form->fields[$_GET['cf']]->defaultvalue = $result->fields['value'];;
				$form->fields[$_GET['cf']]->updatable = false;
				$form->fields[$_GET['cf']]->forcedraw = true;
			}
		}

	}



    if ($_REQUEST['camila_update'] != 'new' && !${is_parent}) {

		new form_datetime($form, 'created', camila_get_translation('camila.worktable.field.created'));
		if (is_object($form->fields['created'])) $form->fields['created']->updatable = false;

		new form_textbox($form, 'created_by', camila_get_translation('camila.worktable.field.created_by'));
		if (is_object($form->fields['created_by'])) $form->fields['created_by']->updatable = false;

		new form_textbox($form, 'created_by_surname', camila_get_translation('camila.worktable.field.created_by_surname'));
		if (is_object($form->fields['created_by_surname'])) $form->fields['created_by_surname']->updatable = false;

		new form_textbox($form, 'created_by_name', camila_get_translation('camila.worktable.field.created_by_name'));
		if (is_object($form->fields['created_by_name'])) $form->fields['created_by_name']->updatable = false;

		new form_static_listbox($form, 'created_src', camila_get_translation('camila.worktable.field.created_src'), camila_get_translation('camila.worktable.options.recordmodsrc'));
		if (is_object($form->fields['created_src'])) $form->fields['created_src']->updatable = false;

		new form_datetime($form, 'last_upd', camila_get_translation('camila.worktable.field.last_upd'));
		if (is_object($form->fields['last_upd'])) $form->fields['last_upd']->updatable = false;

		new form_textbox($form, 'last_upd_by', camila_get_translation('camila.worktable.field.last_upd_by'));
		if (is_object($form->fields['last_upd_by'])) $form->fields['last_upd_by']->updatable = false;

		new form_textbox($form, 'last_upd_by_surname', camila_get_translation('camila.worktable.field.last_upd_by_surname'));
		if (is_object($form->fields['last_upd_by_surname'])) $form->fields['last_upd_by_surname']->updatable = false;

		new form_textbox($form, 'last_upd_by_name', camila_get_translation('camila.worktable.field.last_upd_by_name'));
		if (is_object($form->fields['last_upd_by_name'])) $form->fields['last_upd_by_name']->updatable = false;

		new form_textbox($form, 'last_upd_by_name', camila_get_translation('camila.worktable.field.last_upd_by_name'));
		if (is_object($form->fields['last_upd_by_name'])) $form->fields['last_upd_by_name']->updatable = false;

		new form_static_listbox($form, 'last_upd_src', camila_get_translation('camila.worktable.field.last_upd_src'), camila_get_translation('camila.worktable.options.recordmodsrc'));
		if (is_object($form->fields['last_upd_src'])) $form->fields['last_upd_src']->updatable = false;

		new form_textbox($form, 'mod_num', camila_get_translation('camila.worktable.field.mod_num'));
		if (is_object($form->fields['mod_num'])) $form->fields['mod_num']->updatable = false;

	}

	${form_readonly_record_script}
	
    ${autosuggest_script}

    $form->process();
    
    $form->draw();
	
	if($_REQUEST['camila_update'] != 'new') {
		${form_parent_buttons_script}
	}

} else {
      $report_fields = '${report_fields}';
      $default_fields = '${default_fields}';

      if (isset($_REQUEST['camila_rest'])) {
          $report_fields = str_replace('cf_bool_is_special,', '', $report_fields);
          $report_fields = str_replace('cf_bool_is_selected,', '', $report_fields);
          $default_fields = $report_fields;
      }

      if ($_CAMILA['page']->camila_exporting())
          $mapping = '${mapping}';
      else
          $mapping = '${mapping_abbrev}';

	  $stmt = 'select ' . $report_fields . ' from ${table}';
	  
	    $caninsert = ${caninsert};
        $candelete = ${candelete};
        $canupdate = ${canupdate};

	if ($_CAMILA['adm_user_group'] == CAMILA_ADM_USER_GROUP) {
        $caninsert = true;
        $candelete = true;
        $canupdate = true;
    }

      $report = new report($stmt.$filter, '', '${order_field}', '${order_dir}', $mapping, null, 'id', $default_fields, '', (isset($_REQUEST['camila_rest'])) ? false : $canupdate, (isset($_REQUEST['camila_rest'])) ? false : $candelete);
	  
	  ${report_functions_script}

	  ${report_readonly_record_script}

      if (($caninsert || $_CAMILA['adm_user_group'] == CAMILA_ADM_USER_GROUP) && !isset($_REQUEST['camila_rest'])) {
		  if (!${has_parent}) {
			$report->additional_links = Array(camila_get_translation('camila.report.insertnew') => basename($_SERVER['PHP_SELF']) . '?camila_update=new');

			$myImage1 = new CHAW_image(CAMILA_IMG_DIR . 'wbmp/add.wbmp', CAMILA_IMG_DIR . 'png/add.png', '-');
			$report->additional_links_css_classes = Array(camila_get_translation('camila.report.insertnew') => 'btn '.CAMILA_UI_DEFAULT_BTN_SIZE.' btn-default btn-primary button is-primary is-small');
		  }

          if (($_CAMILA['adm_user_group'] == CAMILA_ADM_USER_GROUP) || CAMILA_WORKTABLE_IMPORT_ENABLED)          
          $report->additional_links[camila_get_translation('camila.worktable.import')] = 'cf_worktable_wizard_step4.php?camila_custom=' . $wt_id . '&camila_returl=' . urlencode($_SERVER['PHP_SELF']);
      }

      if ($_CAMILA['adm_user_group'] == CAMILA_ADM_USER_GROUP) {
          $report->additional_links[camila_get_translation('camila.worktable.rebuild')] = 'cf_worktable_admin.php?camila_custom=' . $wt_id . '&camila_worktable_op=rebuild' . '&camila_returl=' . urlencode($_SERVER['PHP_SELF']);
          $report->additional_links[camila_get_translation('camila.worktable.reconfig')] = 'cf_worktable_wizard_step2.php?camila_custom=' . $wt_id . '&camila_returl=' . urlencode($_SERVER['PHP_SELF']);
      }

      if (CAMILA_WORKTABLE_CONFIRM_VIA_MAIL_ENABLED) {
          $report->additional_links[camila_get_translation('camila.worktable.confirm')] = basename($_SERVER['PHP_SELF']) . '?camila_visible_cols_only=y&camila_worktable_export=dataonly&camila_pagnum=-1&camila_export_filename=WORKTABLE&camila_export_action=sendmail&hidden=camila_xls&camila_export_format=camila_xls&camila_xls=Esporta';

          $myImage1 = new CHAW_image(CAMILA_IMG_DIR . 'wbmp/accept.wbmp', CAMILA_IMG_DIR . 'png/accept.png', '-');
          $report->additional_links_images[camila_get_translation('camila.worktable.confirm')]=$myImage1;

      }

      $report->formulas=${formulas}
      $report->queries=${queries}

      ${menuitems_script}

      $report->process();
      $report->draw();
}
?>