<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2021 Umberto Bresciani

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

//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);

require_once CAMILA_LIB_DIR . 'simple-cache/src/CacheException.php';
require_once CAMILA_LIB_DIR . 'simple-cache/src/CacheInterface.php';
require_once CAMILA_LIB_DIR . 'simple-cache/src/InvalidArgumentException.php';

require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Reader/IReader.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Reader/BaseReader.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Reader/IReadFilter.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Reader/DefaultReadFilter.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Reader/Security/XmlScanner.php');

require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Calculation/Functions.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Shared/File.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Shared/StringHelper.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Shared/Date.php');

require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Spreadsheet.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Calculation/Calculation.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Calculation/Category.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Calculation/Engine/CyclicReferenceStack.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Calculation/Engine/Logger.php');

require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/IComparable.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/Worksheet.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/PageSetup.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/PageMargins.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/HeaderFooter.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/SheetView.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/Protection.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/Dimension.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/RowDimension.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/ColumnDimension.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Worksheet/AutoFilter.php');

require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Reader/Xlsx.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Reader/Xlsx/Theme.php');

require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/ReferenceHelper.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Exception.php');


require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Cell/IValueBinder.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Cell/DefaultValueBinder.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Cell/Coordinate.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Cell/Cell.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Cell/DataType.php');

require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Collection/CellsFactory.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Collection/Cells.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Settings.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Collection/Memory.php');


require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Document/Properties.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Document/Security.php');

require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Supervisor.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/NumberFormat.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Protection.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Style.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Font.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Color.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Fill.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Border.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Borders.php');
require_once(CAMILA_LIB_DIR . 'PhpOffice/PhpSpreadsheet/Style/Alignment.php');

/*$dir = new RecursiveDirectoryIterator(CAMILA_LIB_DIR . 'PhpOffice/');
foreach (new RecursiveIteratorIterator($dir) as $file) {
    if (!is_dir($file)) {
        if( fnmatch('*.php', $file) ) {
		require_once $file;
		}
	}
}*/


//use PhpOffice\PhpSpreadsheet\Helper\Sample;
//use PhpOffice\PhpSpreadsheet\IOFactory;
//use PhpOffice\PhpSpreadsheet\Spreadsheet;
//fix me
//use PhpOffice\PhpSpreadsheet\Shared\Date;


class Spreadsheet_Excel_Reader {
	
	var $spreadsheet;

	var $colnames = array();
	var $colindexes = array();
	var $standardColWidth = 0;
	var $defaultColWidth = 0;


	function val($row,$col,$sheet=0) {
		$val = '';
		if ($this->spreadsheet->getSheet($sheet)->getCellByColumnAndRow($col, $row, false) != null)
			$val = $this->spreadsheet->getSheet($sheet)->getCellByColumnAndRow($col, $row, false)->getValue();
		return $val;
	}
	
	function value($row,$col,$sheet=0) {
		return $this->val($row,$col,$sheet);
	}
	
	function cell($row,$col,$sheet=0) {
		$val = '';
		if ($this->spreadsheet->getSheet($sheet)->getCellByColumnAndRow($col, $row, false) != null)
			$val = $this->spreadsheet->getSheet($sheet)->getCellByColumnAndRow($col, $row, false);
		return $val;
	}
	
	function isDateTimeCell($pCell) {
		$isDateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($pCell);
		return $isDateTime;
	}
	
	function excelToDateTimeObject($val) {
		$date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
		return $date;
	}

	/*function info($row,$col,$type='',$sheet=0) {
		$col = $this->getCol($col);
		if (array_key_exists('cellsInfo',$this->sheets[$sheet])
				&& array_key_exists($row,$this->sheets[$sheet]['cellsInfo'])
				&& array_key_exists($col,$this->sheets[$sheet]['cellsInfo'][$row])
				&& array_key_exists($type,$this->sheets[$sheet]['cellsInfo'][$row][$col])) {
			return $this->sheets[$sheet]['cellsInfo'][$row][$col][$type];
		}
		return "";
	}*/

	function type($row,$col,$sheet=0) {
		$val = '';
		if ($this->spreadsheet->getSheet($sheet)->getCellByColumnAndRow($col, $row, false) != null)
			$val = $this->spreadsheet->getSheet($sheet)->getCellByColumnAndRow($col, $row, false)->getDataType();
		return $val;

	}
	
	/*function raw($row,$col,$sheet=0) {
		return $this->info($row,$col,'raw',$sheet);
	}

	function rowspan($row,$col,$sheet=0) {
		$val = $this->info($row,$col,'rowspan',$sheet);
		if ($val=="") { return 1; }
		return $val;
	}

	function colspan($row,$col,$sheet=0) {
		$val = $this->info($row,$col,'colspan',$sheet);
		if ($val=="") { return 1; }
		return $val;
	}

	function hyperlink($row,$col,$sheet=0) {
		$link = $this->sheets[$sheet]['cellsInfo'][$row][$col]['hyperlink'];
		if ($link) {
			return $link['link'];
		}
		return '';
	}*/

	function rowcount($sheet=0) {
		$val = $this->spreadsheet->getSheet($sheet)->getHighestRow();
		return $val;
		/*return $this->sheets[$sheet]['numRows'];*/
	}

	/*function colcount($sheet=0) {
		return $this->sheets[$sheet]['numCols'];
	}*/

	var $boundsheets = array();
	var $colInfo = array();
   	var $rowInfo = array();
	
	var $sheets = array();

	var $data;

	function Spreadsheet_Excel_Reader($file='',$store_extended_info=true,$outputEncoding='') {
		$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
		//$reader->setReadDataOnly(true);
		$spreadsheet = $reader->load($file);
		$this->spreadsheet = $spreadsheet;
		
		$sheetNames = $spreadsheet->getSheetNames();
		foreach ($sheetNames as $sheetIndex => $sheetName) {
			$this->boundsheets[$sheetIndex]['name']=$sheetName;
			//fix me
			$this->sheets[$sheetIndex]=$sheetName;
		}
	}
}

?>