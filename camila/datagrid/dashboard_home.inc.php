<?php
require_once('../../camila/worktable.class.inc.php');


	$myText = new CHAW_text('');
    $_CAMILA['page']->add_text($myText);
	
	$eventName = "P.SOCCORSO RIVOLTA";

	$myLink = new CHAW_link($eventName.' - Preaccreditamento', 'cf_worktable21.php');
	$myLink->set_css_class('btn btn-md btn-default btn-primary btn-space');
    $myLink->set_br(2);
    $_CAMILA['page']->add_link($myLink);
	
	$myLink = new CHAW_link($eventName.' - Moduli preaccreditamento', 'index.php?dashboard=97');
	$myLink->set_css_class('btn btn-md btn-default btn-primary btn-space');
    $myLink->set_br(2);
    $_CAMILA['page']->add_link($myLink);
	


/*$camilaWT  = new CamilaWorkTable();
$camilaWT->db = $_CAMILA['db'];


$vSheet = $camilaWT->getWorktableSheetId('VOLONTARI');
$mSheet = $camilaWT->getWorktableSheetId('MEZZI');
$aSheet = $camilaWT->getWorktableSheetId('ATTREZZATURE');

 
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="row">'));	
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-4">'));
$camilaUI->insertTitle('Volontari', 'user');
$camilaUI->insertButton('cf_worktable'.$vSheet.'.php?camila_update=new', 'Registrazione volontario', 'plus');
$camilaUI->insertButton('?dashboard=02', 'Movimentazione volontari', 'random');
$camilaUI->insertButton('cf_worktable'.$vSheet.'.php', 'Elenco volontari', 'list');
$camilaUI->insertButton('?dashboard=27', 'Attestati', 'duplicate');
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-4">'));
$camilaUI->insertTitle('Mezzi', 'plane');
$camilaUI->insertButton('cf_worktable'.$mSheet.'.php?camila_update=new', 'Registrazione mezzo', 'plus');
$camilaUI->insertButton('?dashboard=04', 'Movimentazione mezzi', 'random');
$camilaUI->insertButton('cf_worktable'.$mSheet.'.php', 'Elenco mezzi', 'list');
$camilaUI->insertButton('?dashboard=28', 'Attestati', 'duplicate');
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-4">'));
$camilaUI->insertTitle('Attrezzature', 'wrench');
$camilaUI->insertButton('cf_worktable'.$aSheet.'.php?camila_update=new', 'Registrazione attrezzatura', 'plus');
$camilaUI->insertButton('?dashboard=03', 'Movimentazione attrezzature', 'random');
$camilaUI->insertButton('cf_worktable'.$aSheet.'.php', 'Elenco attrezzature', 'list');
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));*/

/*	$myText = new CHAW_text('');
    $_CAMILA['page']->add_text($myText);
	
	$eventName = "CORSO BASE DICEMBRE 2016";

	$myLink = new CHAW_link($eventName.' - Preaccreditamento', 'cf_worktable6.php');
	$myLink->set_css_class('btn btn-md btn-default btn-primary btn-space');
    $myLink->set_br(2);
    $_CAMILA['page']->add_link($myLink);*/
	
	$pnet_user = $_CAMILA['user'];

	$camilaWT  = new CamilaWorkTable();
	$camilaWT->db = $_CAMILA['db'];

	$queryList = 'SELECT id,${contenuti.titolo} as title, ${contenuti.descrizione breve} as subtitle, ${contenuti.data attivazione} as date, ${contenuti.link 1} as url1,${contenuti.descrizione link 1} as descrurl1 FROM ${contenuti} order by ${contenuti.data attivazione} DESC';
	$queryDetail = 'SELECT ${contenuti.titolo} as title, ${contenuti.descrizione breve} as subtitle, ${contenuti.data attivazione} as date, ${contenuti.link 1} as url1,${contenuti.descrizione link 1} as descrurl1  FROM ${contenuti}';
	$queryOrg = 'SELECT denominazione, denominazione_normalizzata,zona,cmp,email,pec from cr_dborg where id = ' . $pnet_user ;

	require_once(CAMILA_VENDOR_DIR.'tinybutstrong/tinybutstrong/tbs_class.php');
	require_once(CAMILA_DIR.'tbs/plugins/tbsdb_jladodb.php');
	//require_once(CAMILA_LIB_DIR . 'tbs/tbs_class.php');
	//require_once(CAMILA_DIR.'tds/plugins/tbsdb_jladodb.php');

	$TBS = new clsTinyButStrong();
	$TBS->SetOption(array('render'=>TBS_OUTPUT));
	$conn = $_CAMILA['db'];
	$TBS->LoadTemplate(CAMILA_TMPL_DIR.'/tbs/it/home.htm');
	$TBS->MergeBlock('news','adodb',$camilaWT->parseWorktableSqlStatement($queryList));
	
	if ($_CAMILA['user_visibility_type'] == 'personal')
	{
		$TBS->MergeBlock('org','adodb',$queryOrg);
	}
	else
	{
		$TBS->MergeBlock('org','adodb','SELECT id from cr_dborg where id is null');
	}

	$_CAMILA['page']->add_userdefined(new CHAW_tbs($TBS));	

?>