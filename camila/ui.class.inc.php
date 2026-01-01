<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2026 Umberto Bresciani

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

	function addGridSection(int $numCols, callable $renderColumnContent)
	{
		global $_CAMILA;

		if ($numCols < 1 || $numCols > 6) {
			throw new InvalidArgumentException('startGridSection: number of columns must be between 1 and 6');
		}

		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="row columns">'));

		$colSize = intval(12 / $numCols);
		$colClass = "column is-12-mobile is-{$colSize}-desktop";

		for ($i = 0; $i < $numCols; $i++) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="' . $colClass . '">'));
			$renderColumnContent($i); // Pass column index (0-based)
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
		}

		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
	}

function addTimelineSection(array $events, string $commonIcon = 'ri-calendar-line')
{
	global $_CAMILA;

	// Load RemixIcon and start Bulma section/container
	$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '
	<link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
	<section class="section">
	  <div class="container">
	    <div class="timeline is-small">
	'));

	foreach ($events as $i => $event) {
		$start = htmlspecialchars($event['start'] ?? '');
		$end = htmlspecialchars($event['end'] ?? '');
		$label = htmlspecialchars($event['label'] ?? '');
		$description = htmlspecialchars($event['description'] ?? '');
		$badge = $event['badge'] ?? null;
		$buttons = $event['buttons'] ?? [];

		// Format date range
		$duration = $start;
		if ($end && $end !== $start) {
			$duration .= ' → ' . $end;
		}

		// Start event item block
		$html = '<div class="timeline-item">
			<div class="timeline-marker is-info"></div>
			<div class="timeline-content">
				<div class="box" style="border-left: 4px solid #209cee; padding: 1rem 1.5rem;">
					<div class="columns is-vcentered is-mobile is-multiline">

						<!-- Date and icon column -->
						<div class="column is-narrow has-text-grey">
							<i class="' . htmlspecialchars($commonIcon) . '"></i>
							<span style="margin-left: 0.5em;">' . $duration . '</span>
						</div>

						<!-- Event details column -->
						<div class="column">
							<p><strong>' . $label . '</strong>: ' . $description . '</p>';

		// Optional badge tag
		if ($badge) {
			$html .= '<span class="tag ' . htmlspecialchars($badge['class']) . '">' . htmlspecialchars($badge['label']) . '</span>';
		}

		$html .= '</div>';

		// Optional action buttons (right aligned)
		if (!empty($buttons)) {
			$html .= '<div class="column is-narrow has-text-right">';
			foreach ($buttons as $btn) {
				$btnLabel = htmlspecialchars($btn['label'] ?? 'Action');
				$btnUrl = htmlspecialchars($btn['url'] ?? '#');
				$btnClass = htmlspecialchars($btn['class'] ?? 'is-small is-link');
				$html .= '<a href="' . $btnUrl . '" class="button ' . $btnClass . '">' . $btnLabel . '</a> ';
			}
			$html .= '</div>';
		}

		// Close columns and box
		$html .= '</div></div></div></div>';

		// Optional separator between events
		if ($i < count($events) - 1) {
			$html .= '<hr style="margin: 1.5rem 0;">';
		}

		// Output HTML
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
	}

	// Close timeline structure
	$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '
	    </div>
	  </div>
	</section>
	'));
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

	function openButtonBar() {
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="buttons">'));
	}
	
	function closeButtonBar() {
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
	}
	
	function openMenuSection($title) {
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<aside class="menu"><p class="menu-label">'.$title.'</p><ul class="menu-list">'));
	}
	
	function addItemToMenuSection($url, $title) {
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<li><a href="'.$url.'">'.$title.'</a></li>'));
	}

	function closeMenuSection() {
		global $_CAMILA;
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</ul></aside>'));
	}

	function insertButton($link, $text, $icon, $br = true, $badge='', $target='_self') {
		global $_CAMILA;

		$html = '';
		if (defined('CAMILA_APPLICATION_UI_KIT') && CAMILA_APPLICATION_UI_KIT == 'bulma') {
			$icon = $this->decodeIcon($icon);
			$b = '';

			$html = '<a href="'.$link.'" target="'.$target.'" class="button is-small is-primary mb-2" aria-label="">';
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

		$html = '';
		if (defined('CAMILA_APPLICATION_UI_KIT') && CAMILA_APPLICATION_UI_KIT == 'bulma') {
			$icon = $this->decodeIcon($icon);
			$b = '';

			$html = '<a href="'.$link.'" target="'.$target.'" class="button is-small mb-2" aria-label="">';
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
	/*function insertSecondaryButton($link, $text, $icon, $br = true, $badge='', $target='_self') {
		global $_CAMILA;
		$b = '';
		if ($badge != '')
			$b=' <span class="badge">'.$badge.'</span>';
		$html = '<a href="'.$link.'" type="button" target="'.$target.'" class="btn btn-md btn-default btn-secondary btn-space" aria-label=""><span class="glyphicon glyphicon-'.$icon.'" aria-hidden="true"></span> '.$text.$b.'</a>';
		if ($br)
			$html.='<br />';
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
	}*/

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
		if (defined('CAMILA_APPLICATION_UI_KIT') && CAMILA_APPLICATION_UI_KIT == 'bulma') {
			$icon = $this->decodeIcon($icon);
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<h4><span class="icon"><i class="ri-'.$icon.'-line"></i></span> '.$text.'</h4>'));
		} else {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<h4><span class="glyphicon glyphicon-'.$icon.'"></span> '.$text.'</h4>'));
		}		
	}
	
	function insertAutoRefresh($ms) {
		global $_CAMILA;
		$refrCode = "<script>function refreshPage() {window.location.reload();};setInterval(refreshPage, $ms);</script>";
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $refrCode));
	}

	public static function insertWarning($text){
		global $_CAMILA;
		if ($_CAMILA['page'] !== null) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="alert alert-warning notification is-warning" role="alert">'.$text.'</div>'));
		} else {
			echo('<p>'.$text."</p>\n");
		}
	}
	
	public static function insertError($text){
		global $_CAMILA;
		if ($_CAMILA['page'] !== null) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="alert alert-danger notification is-danger" role="alert">'.$text.'</div>'));
		} else {
			echo('<p>'.$text."</p>\n");
		}
	}
	
	public static function insertSuccess($text){
		global $_CAMILA;
		if ($_CAMILA['page'] !== null) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="alert alert-success notification is-success" role="success">'.$text.'</div>'));
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
	
	function mountMiniApp($pluginName, $bootScript, $cssFilePath = '') {
		global $_CAMILA;
		
		$scheme = $this->isHttps() ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'];

		$config = [
			'baseUrl' => $scheme.'://'.$host.'/app/'.CAMILA_APP_DIR.'/cf_api.php'
		];

		$refrCode = "<script src='../../camila/js/worktable-client.js'></script>";
		$refrCode .= "<script>window.APP_CONFIG = ".json_encode($config, JSON_UNESCAPED_SLASHES)."</script>";
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $refrCode));

		$html = <<<HTML
		<section class="section">
		  <div class="container">
			<div id="app">
			</div>
		  </div>
		</section>

		<!-- Guard-rail: browser NON compatibili -->
		<script nomodule>
		  document.body.innerHTML = `
			<section class="section">
			  <div class="container">
				<article class="message is-danger">
				  <div class="message-header">
					<p>Browser non supportato</p>
				  </div>
				  <div class="message-body">
					Questa applicazione richiede un browser moderno.<br>
					Usa <strong>Chrome</strong> o <strong>Edge</strong> aggiornati
					(anche su mobile).
				  </div>
				</article>
			  </div>
			</section>
		  `;
		</script>

		<!-- App -->
		HTML;
		
		if ($cssFilePath != '') {
			$_CAMILA['page']->camila_add_js("<link href=\"plugins/$pluginName$cssFilePath\" rel=\"stylesheet\">\n");
		}
		
		$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
		$html = '<script type="module" src="./plugins/'.$pluginName.$bootScript.'"></script>';
		$_CAMILA['page']->camila_add_js($html);
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
	
	function isHttps(): bool
	{
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
			return true;
		}

		if (
			!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
			$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
		) {
			return true;
		}

		if (
			!empty($_SERVER['HTTP_X_FORWARDED_SSL']) &&
			$_SERVER['HTTP_X_FORWARDED_SSL'] === 'on'
		) {
			return true;
		}

		return false;
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

		if ($icon == 'plane')
			$icon = 'truck';
		
		if ($icon == 'list-alt')
			$icon = 'task';
		
		if ($icon == 'cog')
			$icon = 'settings-2';
		
		if ($icon == 'hdd')
			$icon = 'hard-drive';
		
		if ($icon == 'question-sign')
			$icon = 'questionnaire';
		
		if ($icon == 'map-marker')
			$icon = 'map-pin';
		
		if ($icon == 'warning-sign')
			$icon = 'alert';
		
		if ($icon == 'thumbs-up')
			$icon = 'thumb-up';
		
		if ($icon == 'chevron-left')
			$icon = 'arrow-go-back';

		return $icon;
	}

}

?>