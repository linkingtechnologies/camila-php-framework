<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2024 Umberto Bresciani

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

require_once(CAMILA_VENDOR_DIR . '/adodb/adodb-php/adodb.inc.php');

require_once('worktable.class.inc.php');

class CamilaIntegrity
{
    public $camilaWT;
	private $confXml;

    function __construct($xmlFile)
    {	
		$this->confXml = $xmlFile;
    }

	function loadXmlFromFile() {
		$conf = new SimpleXMLElement(file_get_contents($this->confXml));
		return $conf;
	}

	function getChecks() {
		$conf = $this->loadXmlFromFile();
		return $conf->checks;
	}
	
	function check($obj) {
		$result = $this->camilaWT->startExecuteQuery($obj->query);
		$count = -1;
		$error = false;
		if ($result) {
			$count = $result->RecordCount();
		} else {
			$error = true;
		}
		$ret = new stdClass;
		
		if ($count > 0)
		{
			$ret->code = (string)$obj->result->multi->code;
			$ret->message = (string)$obj->result->multi->message;
			$ret->count = $count;
			$ret->fix = (string)$obj->fix;
		}
		else
		{	if ($error) {
				$ret->code = 'queryerror';
				$ret->message = 'Errore query ' . $this->camilaWT->parseWorktableSqlStatement($obj->query);
			} else {
				$ret->code = (string)$obj->result->none->code;
				$ret->message = (string)$obj->result->none->message;
			}
		}
		$this->camilaWT->endExecuteQuery();
		return $ret;
	}

}

?>