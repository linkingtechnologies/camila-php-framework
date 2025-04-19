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

class CamilaUserInterface
{
	function __construct()
    {
    }

	function insertImage($src, $br = true) {
		global $_CAMILA;
		$html = '<img src="'.$src.'" />';
		if ($br)
			$html.='<br />';
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
	}
	
	function openBox() {
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="box">'));
	}
	
	function closeBox() {
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
	}
	
	function insertButton($link, $text, $icon, $br = true, $badge='', $target='_self') {
		global $_CAMILA;

		$html = '';
		if (defined('CAMILA_APPLICATION_UI_KIT') && CAMILA_APPLICATION_UI_KIT == 'bulma') {
			$icon = $this->decodeIcon($icon);
			$b = '';
			
			$html = '<a href="'.$link.'" target="'.$target.'" class="button is-link mb-2" aria-label="">';
			if ($icon != '')
				$html.=' <span class="icon"><i class="ri-'.$icon.'-line"></i></span>';
			$html .= '<span>'.$text.'</span>';
			$html .= '</a>';
			if ($br)
				$html.='<br />';
		
		} else {
			$b = '';
			if ($badge != '')
				$b=' <span class="badge">'.$badge.'</span>';
			$html = '<a href="'.$link.'" type="button" target="'.$target.'" class="btn btn-md btn-default btn-primary btn-space" aria-label=""><span class="glyphicon glyphicon-'.$icon.'" aria-hidden="true"></span> '.$text.$b.'</a>';
			if ($br)
				$html.='<br />';
		}

		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
	}
	
	function insertSecondaryButton($link, $text, $icon, $br = true, $badge='', $target='_self') {
		global $_CAMILA;
		$b = '';
		if ($badge != '')
			$b=' <span class="badge">'.$badge.'</span>';
		$html = '<a href="'.$link.'" type="button" target="'.$target.'" class="btn btn-md btn-default btn-secondary btn-space" aria-label=""><span class="glyphicon glyphicon-'.$icon.'" aria-hidden="true"></span> '.$text.$b.'</a>';
		if ($br)
			$html.='<br />';
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
	}

	function insertTitle($text, $icon=''){
		global $_CAMILA;

		if (defined('CAMILA_APPLICATION_UI_KIT') && CAMILA_APPLICATION_UI_KIT == 'bulma') {
			$icon = $this->decodeIcon($icon);
			$raw = '<h3 class="title is-4">';
			if ($icon != '')
				$raw .= '<span class="icon is-medium" style="vertical-align: middle; margin-right: 0.4rem;"><i class="ri-'.$icon.'-line ri-lg"></i></span>';
			
		} else {
			$raw = '<h3>';
			if ($icon != '')
				$raw .= '<span class="glyphicon glyphicon-'.$icon.'"></span> ';
		}
		$raw .= $text.'</h3>';
		
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $raw));
	}
	
	function insertSubTitle($text, $icon = ''){
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<h4><span class="glyphicon glyphicon-'.$icon.'"></span> '.$text.'</h4>'));
	}
	
	function insertAutoRefresh($ms) {
		global $_CAMILA;
		$refrCode = "<script>function refreshPage() {window.location.reload();};setInterval(refreshPage, $ms);</script>";
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $refrCode));
	}

	public static function insertWarning($text){
		global $_CAMILA;
		if ($_CAMILA['page'] !== null) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="alert alert-warning" role="alert">'.$text.'</div>'));
		} else {
			echo('<p>'.$text."</p>\n");
		}
	}
	
	public static function insertError($text){
		global $_CAMILA;
		if ($_CAMILA['page'] !== null) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="alert alert-danger" role="alert">'.$text.'</div>'));
		} else {
			echo('<p>'.$text."</p>\n");
		}
	}
	
	public static function insertSuccess($text){
		global $_CAMILA;
		if ($_CAMILA['page'] !== null) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="alert alert-success" role="success">'.$text.'</div>'));
		} else {
			echo('<p>'.$text."</p>\n");
		}
	}

	function insertDivider(){
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<br /><hr />'));
	}
	
	function insertText($txt, $br=1) {
		global $_CAMILA;
		$text = new CHAW_text($txt);
		$text->set_br($br);
		$_CAMILA['page']->add_text($text);
	}
	
	function insertLink($link, $txt, $br=1) {
		global $_CAMILA;
		$myLink = new CHAW_link($txt, $link);
		//$myLink->set_css_class('btn');
		$myLink->set_br($br);
		$_CAMILA['page']->add_link($myLink);
	}
	
	function insertLineBreak() {
		global $_CAMILA;
		$html.='<br />';
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
	}
	
	function printHomeMenu($confFile, $defaultId = '') {
		$current = Array();
		global $_CAMILA;
		$menu = new SimpleXMLElement(file_get_contents($confFile));
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div id="linkset2"><div id="nav" class="tabs is-boxed"><ul class="nav nav-tabs">'));

		foreach ($menu as $k => $v) {
			$curr = false;
			if ((isset($_REQUEST['dashboard']) && strpos('?'.$_SERVER['QUERY_STRING'], (string)($v->url)) !==  false) || ((string)($v->id) == $defaultId) || (isset($_REQUEST['dashboard']) && strpos(','.(string)($v->pages).',', ','.$_REQUEST['dashboard'].',') !==  false))
			{
				$current = Array('id' => (string)$v->id, 'url' => (string)$v->url, 'title' => (string)$v->title);
				$curr = true;
			}
			$url = $v->url;
			$title = '';
			if ((string)$v->lic_title != '')
				$title = camila_get_translation((string)$v->lic_title);
			else
				$title = $v->title;

			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<li role="presentation" class="'. ($curr ? 'active' : '' ).'"><a class="'. ($curr ? 'active' : '' ).' is-size-7" href="'.$url.'">'.$title.'</a></li>'));
		}

		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</ul></div></div>'));
		return $current;
	}
	
	function decodeIcon($icon) {
		if ($icon == 'plus')
			$icon = 'add';
		
		if ($icon == 'random')
			$icon = 'drag-move';
		
		if ($icon == 'list')
			$icon = 'file-list';
		
		if ($icon == 'duplicate')
			$icon = 'file-list-3';
		
		if ($icon == 'wrench')
			$icon = 'tools';
		
		return $icon;
	}

}

?>