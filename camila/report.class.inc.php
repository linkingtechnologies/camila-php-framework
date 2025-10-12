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

require_once(CAMILA_VENDOR_DIR . '/adodb/adodb-php/adodb.inc.php');
require_once(CAMILA_VENDOR_DIR . '/autoload.php');

require_once(CAMILA_DIR.'export/phpgraphlib/phpgraphlib.php');
require_once(CAMILA_DIR.'export/phpgraphlib/phpgraphlib_pie.php');

use Mpdf\Mpdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Element\Cell;


class CamilaReport
{
    private $xmlConfig;
	private $camilaWT;
	private $reportDir;
	private $reportList;
	private $currentReport;
	private $lang;

	public $shouldGenerateToc = false;
	public $shouldGenerateHeader = false;
	public $headerHtml;

	public $shouldGenerateFooter = false;
	public $outputFileName;

    public function __construct($lang, $camilaWT, $reportDir, $reportName = '')
    {
		$this->lang = $lang;
		$this->camilaWT = $camilaWT;
		$this->reportDir = $reportDir . DIRECTORY_SEPARATOR. $lang;
		
		$this->reportList = $this->getReports();
		if (!empty($this->reportList)) {
			$this->currentReport = array_key_first($this->reportList);
			if ($reportName !== null && $reportName != '') {
				$this->currentReport = $reportName;
			}
			$file = $this->reportDir.DIRECTORY_SEPARATOR.$this->currentReport.'.xml';
			$this->xmlConfig = simplexml_load_file($file);
		}
    }

	function getReports() {
		$extension = 'xml';
		if (!is_dir($this->reportDir)) {
			return [];
		}
		$filenames = [];
		$directory = $this->reportDir;
		$dir = opendir($directory);

		while (($file = readdir($dir)) !== false) {
			$filePath = $directory . DIRECTORY_SEPARATOR . $file;

			if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) === $extension) {
				$filenameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
				$underscorePos = strpos($filenameWithoutExt, '_');
				if ($underscorePos !== false) {
					$substringFromUnderscore = substr($filenameWithoutExt, $underscorePos + 1);
				} else {
					$substringFromUnderscore = '';
				}
				$filenames[$filenameWithoutExt] = $substringFromUnderscore;
			}
		}
		closedir($dir);
		ksort($filenames);
		return $filenames;
	}
	
	function getCurrentReportName() {
		return $this->currentReport;
	}
	
	function getCurrentReportTitle() {
		$title = null;
		if (!empty($this->reportList)) {
			$title = $this->reportList[$this->currentReport];
		}
		return $title;
	}
	
	function getQuery($node) {
		$dbType = $this->camilaWT->db->dataProvider;
		$query = $node->query;
		if (isset($node->mysqlQuery) && $dbType == 'mysql') {
			$query = $node->mysqlQuery;
		}
		if (isset($node->sqliteQuery) && $dbType == 'sqlite') {
			$query = $node->sqliteQuery;
		}
		return $query;
	}

	function createTable($name, $obj, $data) {
		$html = '';
		if (count($data)>0) {
			$html = "<p>$obj->title</p><table>";
			$sum = $obj->sum;
			$total = 0;
			foreach($data as $key => $val) {
				$html .= "<tr><td>$key</td><td>$val</td></tr>";
				if ($sum != '')
				{
					$total += $val;
				}
			}
			if ($total>0) {
				$html .= "<tr><td></td><td>$total</td></tr>";
			}

			$html .= '</table>';
		}
		return $html;
	}

    private function generateText($result, $graph, $noCustomCode = false)
    {
		
		$html = (string)$graph->html;
		
		if ($result != null) {
			if ($result->RecordCount()>0) {
				while (!$result->EOF) {
					$c = 0;
					foreach ($result->fields as $index => $value) {
						$html = str_replace('${' . $c . '}', $value, $html);
						$c++;
					}
					$result->MoveNext();
				}
			}
		}

        return $html;
    }

    private function generateTable($result, $graph, $noCustomCode = false)
    {
        // Generate the table headers
		if ($result->RecordCount()>0) {
			$html = '';
			if (!$noCustomCode) {
				$html .= '<div>';
			}
			$html .= '<table border="1" cellspacing="0" cellpadding="5" '.(isset($graph->style)?' style="'.$graph->style.'"':'').'>';
			$columns = array_keys($result->fields);
			$html .= '<thead><tr>';
			$skipFirst = false;
			if (isset($graph->hideFirstColumn) && $graph->hideFirstColumn == true) {
				$skipFirst = true;
			}
			$cCount = 0;
			foreach ($columns as $column) {
				if ($cCount == 0 && $skipFirst) {
				} else {
					$html .= '<th>' . ucfirst($column) . '</th>';
				}
				$cCount++;
			}
			$html .= '</tr></thead>';

			// Populate the table with data
			$html .= '<tbody>';
			$totalRow = [];
			while (!$result->EOF) {
				$html .= '<tr>';
				$cCount = 0;
				foreach ($columns as $column) {
					if ($cCount == 0 && $skipFirst) {
					} else {
						$value = $result->fields[$column];
						if (isset($graph->barcodeColumn) && $cCount == $graph->barcodeColumn) {
							$html .= '<td style="text-align:center;line-height: 2;"><barcode code="'.$value.'" type="'.$graph->barcodeType.'" size="'.$graph->barcodeSize.'" height="'.$graph->barcodeHeight.'" />
							<br/>'.$value.'</td>';
						} else {
							$html .= '<td>' . htmlspecialchars($value) . '</td>';
						}

						// Handle column summing if required by XML
						if ((int)$graph->sum == 1) {
							if (!isset($totalRow[$column])) {
								$totalRow[$column] = 0;
							}
							$totalRow[$column] += (is_numeric($value) ? $value : 0);
						}
					}
					$cCount++;
				}
				$html .= '</tr>';
				$result->MoveNext();
			}

			// If sum is enabled, add a total row at the end of the table
			if ((int)$graph->sum == 1) {
				$html .= '<tr>';
				$count = 0;
				foreach ($columns as $column) {
					if ($count == 0 && $skipFirst) {
					} else {
						if ($count == 0 || ($count ==1 && $skipFirst))
							$html .= '<td><strong></td>';
						else
							$html .= '<td><strong>' . ($totalRow[$column] ?? '') . '</strong></td>';
					}
					$count++;
				}
				$html .= '</tr>';
			}

			$html .= '</tbody></table>';

			if (!$noCustomCode) {
				$html .= '</div>';
			}
			
		}

        return $html;
    }

	function queryWorktableDatabase($result)
	{
		$arr = array();
		if($result->RecordCount()>0)
		{			
			while (!$result->EOF) {
				$a = $result->fields;
				$arr[$a[0]]=$a[1];
				$result->MoveNext();
			}
		}
		return $arr;
	}
	
	function createGraph($name, $obj, $data, $filename = null) {
		if (count($data)>0)
		{
			if ((string)$obj->type == 'pie')
			{
				$graph = new PHPGraphLibPie((int)$obj->width, (int)$obj->height, $filename);
				$graph->addData($data);
				$graph->setTitle($obj->title);
				$graph->setLabelTextColor('50,50,50');
				$graph->setLegendTextColor('50,50,50');
				$graph->createGraph();
				//if ($graph->error != null && count($graph->error) > 0) 
				//	{echo "!";}
			}
			else if ((string)$obj->type == 'bar')
			{
				$graph = new PHPGraphLib((int)$obj->width, (int)$obj->height, $filename);
				$graph->addData($data);
				$graph->setTitle($obj->title);
				//$graph->setLabelTextColor('50,50,50');
				//$graph->setLegendTextColor('50,50,50');
				$graph->setupXAxis(40);
				$graph->createGraph();
				//if ($graph->error != null && count($graph->error) > 0)
				//{echo "!";}
			}
		}
	}


    public function generateHtmlContent($report, $index, $title, $noCustomCode = false)
    {
        $html = '';
		if (!$noCustomCode)
			$html.= '<mpdf><div keep-with-next="true"><nobreak>';
		if (isset($report->id)) {
			$html .= '<h2 id="table' . $index . '" style="page-break-after: avoid;">' . htmlspecialchars($title) . '</h2>';
		}
		$query = $this->getQuery($report);
		
		$title = (string) $report->graphs->graph[0]->title;
		$rId = (string) $report->id;
		
		$result2 = null;
		$result = null;
		$data = [];
		
		if (isset($report->query)) {

			$result2 = $this->camilaWT->startExecuteQuery($query,true,ADODB_FETCH_ASSOC);
			$result = $this->camilaWT->startExecuteQuery($query);
			$data = $this->queryWorktableDatabase($result);

			if (!$result) {
				throw new Exception('Error executing query: ' . $this->db->ErrorMsg());
			}
		}
		
		$gCount = 0;
		foreach ($report->graphs->graph as $graph) {
			$gCount++;
		}
		if ($gCount>1)
			$html .= '<table><tr>';
		
		$notEmptyCount = 0;
		foreach ($report->graphs->graph as $graph) {
			$type = (string) $graph->type;
			$gId = (string) $graph->id;
			
			if (count($data) > 0) {

				if ($type === 'bar' || $type === 'pie') {
					$filename = CAMILA_TMP_DIR.'/g'.$rId.'_'.$gId.'.png';
					$this->createGraph($gId, $graph, $data, $filename);					
					$width = (int) $graph->width;
					$height = (int) $graph->height;
					if ($gCount>1)
						$html .= '<td width="50%" style="vertical-align: middle;">';
					$html .= '<div><img src="' . htmlspecialchars($filename) . '" width="' . $width . '" height="' . $height . '" /></div>';
					if ($gCount>1)
						$html .= '</td>';
					$notEmptyCount++;
				}

				if ($type === 'table') {
					// Generate table content
					if ($gCount>1)
						$html .= '<td width="50%" style="vertical-align: middle;">';
					$html .= $this->generateTable($result2, $graph, $noCustomCode).'</td>';
					if ($gCount>1)
						$html .= '</td>';
					$notEmptyCount++;
				}
				
				if ($type === 'text') {
					if ($gCount>1)
						$html .= '<td width="50%" style="vertical-align: middle;">';
					$html .= $this->generateText($result2, $graph, $noCustomCode).'</td>';
					if ($gCount>1)
						$html .= '</td>';
					$notEmptyCount++;
				}
				
			}
			
			if ($type === 'text' && !isset($report->query)) {
				if ($gCount>1)
					$html .= '<td width="50%" style="vertical-align: middle;">';
				$html .= $this->generateText($result2, $graph, $noCustomCode).'</td>';
				if ($gCount>1)
					$html .= '</td>';
				$notEmptyCount++;
			}
			
		}
		
		if (isset($report->id) && $notEmptyCount == 0) {
			$html .= '<td><p>'.camila_get_translation('camila.nodatafound').'</p></td>';
		}
		
		if ($gCount>1) {
			$html .= '</tr></table>';
			//$html .= '</div>';
		}
		
		if (!$noCustomCode) {
			$html.= '</nobreak></div></mpdf>';
		}
        return $html;
    }


	private function generatePhpWordTable($container, $result, $graph, $totalWidth = 9065, $fontStyle)
	{
		if ($result->RecordCount() <= 0) {
			$container->addText(camila_get_translation('camila.nodatafound'), $fontStyle);
			return;
		}

		$table = $container->addTable([
			'borderSize' => 4,
			'borderColor' => '000000',
			'cellMargin' => 80,
		]);
		
		$cellStyle = ['borderSize' => 4, 'borderColor' => '000000'];

		$columns = array_keys($result->fields);
		$skipFirst = isset($graph->hideFirstColumn) && $graph->hideFirstColumn == true;
		$sumEnabled = isset($graph->sum) && ((int)$graph->sum === 1);

		$activeColumns = $skipFirst ? array_slice($columns, 1) : $columns;
		if (empty($activeColumns)) {
			$container->addText("No visible columns.");
			return;
		}

		$colCount = count($activeColumns);
		$customWidths = [];

		// Try to use provided columnWidths (percent values)
		if (!empty($graph->columnWidths)) {
			$percentValues = array_map('trim', explode(',', (string)$graph->columnWidths));
			if (count($percentValues) === $colCount) {
				$sum = array_sum($percentValues);
				if ($sum > 0) {
					foreach ($percentValues as $percent) {
						$customWidths[] = round(($totalWidth * ((float)$percent)) / 100);
					}
				}
			}
		}

		// Fallback: divide 100% equally across all visible columns
		if (empty($customWidths)) {
			$percentPerCol = 100 / $colCount;
			foreach (range(1, $colCount) as $_) {
				$customWidths[] = round(($totalWidth * $percentPerCol) / 100);
			}
		}

		// Header
		$table->addRow(null, ['tblHeader' => true]);
		foreach ($activeColumns as $i => $colName) {
			$table->addCell($customWidths[$i],$cellStyle)->addText(ucfirst($colName), array_merge($fontStyle,['bold' => true]));
		}

		$totalRow = [];

		// Data rows
		while (!$result->EOF) {
			$table->addRow();
			foreach ($activeColumns as $i => $column) {
				$value = $result->fields[$column];
				$table->addCell($customWidths[$i], $cellStyle)->addText((string)$value, $fontStyle);

				if ($sumEnabled && is_numeric($value)) {
					if (!isset($totalRow[$column])) {
						$totalRow[$column] = 0;
					}
					$totalRow[$column] += $value;
				}
			}
			$result->MoveNext();
		}

		// Totals row
		if ($sumEnabled) {
			$table->addRow();
			foreach ($activeColumns as $i => $column) {
				$val = isset($totalRow[$column]) ? $totalRow[$column] : '';
				$table->addCell($customWidths[$i],$cellStyle)->addText((string)$val, array_merge($fontStyle,['bold' => true]));
			}
		}
	}

	public function generateWordContent(Section $section, $report, $index, $title)
	{
		$query = $this->getQuery($report);
		$title = (string) $report->graphs->graph[0]->title;
		$rId = (string) $report->id;

		$result2 = $this->camilaWT->startExecuteQuery($query, true, ADODB_FETCH_ASSOC);
		$result = $this->camilaWT->startExecuteQuery($query);
		$data = $this->queryWorktableDatabase($result);

		if (!$result) {
			throw new Exception('Error executing query: ' . $this->db->ErrorMsg());
		}

		// Add title (used for TOC)
		//$section->addTitle(htmlspecialchars($title), 2);

		$gCount = count($report->graphs->graph);
		//$elements = iterator_to_array($report->graphs->graph);
		
		$cellStyle = ['borderSize' => 0, 'borderColor' => 'ffffff'];
		$fontStyleNormal = ['size' => 8, 'name' => 'Arial'];
		$fontStyleSmall = ['size' => 6, 'name' => 'Arial'];
		$totalWidth = 9065; // usable width on A4

		if ($gCount > 1) {
			$table = $section->addTable([
				'borderSize' => 0,
				'cellMargin' => 100,
			]);

			$table->addRow();
			
			$equalWidth = floor($totalWidth / $gCount);

			$contentAdded = false;
			foreach ($report->graphs->graph as $graph) {
				$type = (string) $graph->type;
				$gId = (string) $graph->id;
				
				
				if (count($data) > 0) {
					if ($type == 'bar' || $type == 'pie') {
						$cell = $table->addCell($equalWidth, $cellStyle);
						$filename = CAMILA_TMP_DIR . '/g' . $rId . '_' . $gId . '.png';
						$this->createGraph($gId, $graph, $data, $filename);
						$this->addAutoScaledImageToCell($cell, $filename, $equalWidth - 200);
						$contentAdded = true;
					}

					if ($type == 'table') {
						$cell = $table->addCell($equalWidth, $cellStyle);
						$this->generatePhpWordTable($cell, $result2, $graph, $equalWidth-200, $fontStyleSmall);
						$contentAdded = true;
					}
				}

				
			}
			if (!$contentAdded) {
					$cell = $table->addCell($equalWidth, $cellStyle);
					$cell->addText(camila_get_translation('camila.nodatafound'));
			}
		} else {
			// Single graph/table → render directly
			foreach ($report->graphs->graph as $graph) {
				$type = (string) $graph->type;
				$gId = (string) $graph->id;

				if (count($data) > 0) {
					if ($type === 'bar' || $type === 'pie') {
						$filename = CAMILA_TMP_DIR . '/g' . $rId . '_' . $gId . '.png';
						$this->createGraph($gId, $graph, $data, $filename);
						$this->addAutoScaledImageToCell($section, $filename, $totalWidth);
					}

					if ($type === 'table') {
						$this->generatePhpWordTable($section, $result2, $graph, $totalWidth + 100, $fontStyleNormal);
					}
				} else {
					$section->addText(camila_get_translation('camila.nodatafound'));
				}
			}
		}
	}

	private function addAutoScaledImageToCell($cellOrSection, $imagePath, $maxWidthTwip = 4000)
	{
		if (!file_exists($imagePath)) {
			$cellOrSection->addText('Immagine non trovata.');
			return;
		}

		list($widthPx, $heightPx) = getimagesize($imagePath);

		$dpi = 96; // Default Word DPI
		$twipPerInch = 1440;

		// Convert max width from twip to pixels
		$maxWidthInches = $maxWidthTwip / $twipPerInch;
		$maxWidthPx = $maxWidthInches * $dpi;

		if ($widthPx > $maxWidthPx) {
			$scale = $maxWidthPx / $widthPx;
			$widthPx = round($widthPx * $scale);
			$heightPx = round($heightPx * $scale);
		}

		$cellOrSection->addImage($imagePath, [
			'width' => $widthPx*.75,
			'height' => $heightPx*.75,
			'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
		]);
	}

    public function outputPdfToBrowser()
    {
        $mpdf = new \Mpdf\Mpdf([
			'margin_top' => ($this->shouldGenerateHeader) ? 35 : 15,
			'margin_bottom' => 15,
			'margin_left' => 15,
			'margin_right' => 15,
			'default_font' => 'freesans'
		]);

        // Add header and footer
		if ($this->shouldGenerateHeader)
			$mpdf->SetHTMLHeader($this->headerHtml);

		if ($this->shouldGenerateFooter)
			$mpdf->SetFooter('{PAGENO} | |' . CAMILA_APPLICATION_NAME . "\n".'<br/>Report del '.date('m/d/Y') . ' ore ' . date('H:i'));
		
		$mpdf->use_kwt = true;
		
		$mpdf->setTitle('PDF');

        // Initialize the Table of Contents (ToC)
        $tocHtml = '<h1>Indice</h1><ol>';
        $contentHtml = '';

        // Iterate over each report in the XML		
		$count = 1;
        foreach ($this->xmlConfig->report as $index => $report) {
            $title = (string) $report->graphs->graph[0]->title;
			if ($this->shouldGenerateToc && isset($report->id)) {
				$tocHtml .= '<li>' . htmlspecialchars($title) . '</li>';
			}
            // Add the section title and the table content
            $contentHtml .= '<div style="white-space: nowrap;">'.$this->generateHtmlContent($report, $index, $count. '. ' . $title).'</div>';
			if (isset($report->id))
				$count++;
        }

		if ($this->shouldGenerateToc) {
			$tocHtml .= '</ol>';
			$mpdf->WriteHTML($tocHtml);
		}

        $mpdf->WriteHTML($contentHtml); // Add the content

        // Output the PDF to the browser (inline)
		$date = $this->camilaWT->db->UserDate(date('Y-m-d'), camila_get_locale_date_adodb_format());
		
		if (!empty($this->outputFileName))
			$mpdf->Output($this->outputFileName, \Mpdf\Output\Destination::INLINE);
		else
			$mpdf->Output('Report '.$date.'.pdf', \Mpdf\Output\Destination::INLINE);
    }

	function outputHtmlToBrowser() {
		global $_CAMILA;
		
		$reports = $this->xmlConfig->report;
		foreach ($reports as $k => $v) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="row columns is-multiline">'));	
			$query = $this->getQuery($v);
			try {
				$data = $this->camilaWT->queryWorktableDatabase($query);
				$result2 = $this->camilaWT->startExecuteQuery($query,true,ADODB_FETCH_ASSOC);

				$gCount = 0;
				$title = '';
				foreach ($v->graphs->graph as $graph) {
					$gCount++;
					if ($title == '')
						$title = $graph->title;
				}
				
				$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 column is-12">'));
				$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<h3>'.$title.'</h3>'));
				$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
				
				$arr = $v->graphs->graph;
				for ($i=0; $i<count($arr);$i++)
				{
					$v3 = $arr[$i];
					if ($v3->type == 'pie' || $v3->type == 'bar') {
						$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-8 column is-12-mobile is-8-desktop">'));
						$image1 = new HAW_image("?dashboard=m1&rid=".$v2->id.'&gid='.$v3->id, "?dashboard=m1&rid=".$v->id.'&gid='.$v3->id.'&report='.urlencode($this->currentReport), ":-)");
						$image1->set_br(1);
						if (count($data)>0)
						{
							$_CAMILA['page']->add_image($image1);
						}
						else
						{
							$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<p><i>'.camila_get_translation('camila.nodatafound').'</i></p>'));
							//$camilaUI->insertWarning($v3->title . ' - Nessun dato!');
						}
						$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
					}
					if ($v3->type == 'table') {
						if ($gCount>1)
							$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-4 column is-12-mobile is-4-desktop">'));
						else
							$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-12 column is-12">'));
						//$myDiv = new HAW_raw(HAW_HTML, $this->createTable($v3->id, $v3, $data));
						$myDiv = new HAW_raw(HAW_HTML, $this->generateTable($result2, $v3));
						
						$_CAMILA['page']->add_raw($myDiv);
						$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
					}
				}
			} catch (Throwable $e) {
				$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<p><i>Problemi nella generazione del report '.$v->id.'.</i></p>'));
			}
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
			
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<hr/>'));
			//$camilaUI->insertDivider();
		}	
		
	}
	
	function outputImageToBrowser($rId, $gId, $report) {
		global $_CAMILA;
		$reports = $this->xmlConfig->report;
		
		foreach ($reports as $k => $v) {
			if ($rId == ($v->id)) {
				$query = $this->getQuery($v);
				
				$data = $this->camilaWT->queryWorktableDatabase($query);
				
				foreach ($v->graphs->graph as $k2 => $v2) {
					if ($gId == $v2->id) {
						if (count($data)>0)
							$this->createGraph($v2->id, $v2, $data);
					}
				}
			}
		}
	}

    public function getPhpWordDocument()
	{
		$phpWord = new PhpWord();
		
		$phpWord->addTitleStyle(1, [
			'bold' => true,
			'size' => 14,
			'color' => '333366',
			'spaceAfter' => 200,
		], [
			'spaceBefore' => 200,
			'spaceAfter' => 100,
			'keepNext' => true,
		]);

		$section = $phpWord->addSection();

		if ($this->shouldGenerateHeader) {
			$header = $section->addHeader();
			$this->convertHtmlTableToPhpWord($header, $this->headerHtml);
		}

		if ($this->shouldGenerateFooter)
			$footer = $section->addFooter();

		if ($this->shouldGenerateFooter)
			$footer->addPreserveText('{PAGE} | ' . CAMILA_APPLICATION_NAME . ' | Report del ' . date('d/m/Y') . ' ore ' . date('H:i'));
		
		if ($this->shouldGenerateToc) {
			$section->addText('Indice', ['bold' => true, 'size' => 16], ['spaceAfter' => 200]);
			$section->addTOC(['spaceAfter' => 200]);
		}

		// Content
		$count = 1;
		foreach ($this->xmlConfig->report as $index => $report) {
			$title = $count . '. ' . (string) $report->graphs->graph[0]->title;
			$section->addTitle($title, 1);
			$this->generateWordContent($section, $report, $index, $title);
			$count++;
		}

		return $phpWord;
	}

    public function outputDocxToBrowser()
	{
		$phpWord = $this->getPhpWordDocument();

		// Output a browser
		$date = $this->camilaWT->db->UserDate(date('Y-m-d'), camila_get_locale_date_adodb_format());
		$fileName = 'Report_' . $date . '.docx';

		header("Content-Description: File Transfer");
		header('Content-Disposition: attachment; filename="' . $fileName . '"');
		header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

		$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
		$objWriter->save("php://output");
		exit;
	}

    public function outputOdtToBrowser()
    {
		$phpWord = $this->getPhpWordDocument();

		// Output a browser
		$date = $this->camilaWT->db->UserDate(date('Y-m-d'), camila_get_locale_date_adodb_format());
		$fileName = 'Report_' . $date . '.odt';

        // Save and output the ODF document to the browser
		header("Content-Description: File Transfer");
        header('Content-Type: application/vnd.oasis.opendocument.text');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($phpWord, 'ODText');
        $writer->save('php://output');
		exit;
    }

	/**
	 * Converts HTML <table> elements to PHPWord tables inside the given container (header, section, footer).
	 *
	 * @param object $container PHPWord container (Section, Header, Footer)
	 * @param string $html The HTML string to parse
	 * @param string $basePath Base path for resolving image sources
	 */
	function convertHtmlTableToPhpWord($container, $html, $basePath = '')
	{
		$dom = new DOMDocument();
		@$dom->loadHTML($html);

		$tables = $dom->getElementsByTagName('table');

		// Calculate usable width based on container
		$usableWidth = 9600; // fallback
		if (method_exists($container, 'getStyle')) {
			$style = $container->getStyle();
			if ($style !== null) {
				$pageWidth = $style->getPageSizeW(); // e.g. 11906 for A4
				$marginLeft = $style->getMarginLeft();
				$marginRight = $style->getMarginRight();
				$usableWidth = $pageWidth - $marginLeft - $marginRight;
			}
		}

		foreach ($tables as $htmlTable) {
			$style = [
				'borderSize' => 6,
				'borderColor' => '000000',
				'cellMargin' => 80,
			];

			$styleAttr = $htmlTable->getAttribute('style') ?? '';
			$normalized = strtolower(str_replace(' ', '', $styleAttr));
			$rules = explode(';', $normalized);

			foreach ($rules as $rule) {
				if (str_contains($rule, 'border:none')) {
					$style['borderSize'] = 0;
					$style['borderColor'] = 'FFFFFF';
					$style['borderInsideH'] = 0;
					$style['borderInsideV'] = 0;
				} elseif (preg_match('/border:(\d+)pxsolid#?([a-f0-9]{3,6})/', $rule, $m)) {
					$style['borderSize'] = (int)$m[1];
					$style['borderColor'] = strtoupper($m[2]);
				} elseif (str_contains($rule, 'border-collapse:collapse')) {
					$style['borderInsideH'] = $style['borderSize'];
					$style['borderInsideV'] = $style['borderSize'];
				} elseif (preg_match('/cellpadding:(\d+)/', $rule, $m)) {
					$style['cellMargin'] = (int)$m[1];
				}
			}

			$table = $container->addTable($style);

			foreach ($htmlTable->getElementsByTagName('tr') as $tr) {
				$table->addRow();

				foreach ($tr->getElementsByTagName('td') as $td) {
					$width = 4500;
					$valign = 'top';
					$align = null;

					// Width from attribute
					if ($td->hasAttribute('width')) {
						$widthAttr = trim($td->getAttribute('width'));
						if (str_ends_with($widthAttr, '%')) {
							$percent = (float)rtrim($widthAttr, '%');
							$width = round($usableWidth * ($percent / 100));
						} else {
							$width = (int)$widthAttr * 50;
						}
					}

					// Width & align from inline style
					$cellStyle = strtolower($td->getAttribute('style') ?? '');
					if (preg_match('/width:\s*([\d.]+)%/', $cellStyle, $m)) {
						$width = round($usableWidth * ((float)$m[1] / 100));
					} elseif (preg_match('/width:\s*(\d+)px/', $cellStyle, $m)) {
						$width = (int)$m[1] * 50;
					}

					if (preg_match('/vertical-align:\s*(top|middle|bottom)/i', $cellStyle, $m)) {
						$valign = strtolower($m[1]) === 'middle' ? 'center' : strtolower($m[1]);
					}
					if (preg_match('/text-align:\s*(left|center|right)/i', $cellStyle, $m)) {
						$align = strtolower($m[1]);
					}

					$cell = $table->addCell($width, ['valign' => $valign]);
					$this->parseCellContent($cell, $td, $basePath, $align);
				}
			}
		}
	}

	/**
	 * Parses HTML content inside a <td> and adds it to the PHPWord cell.
	 *
	 * @param Cell $cell The PHPWord cell to add content to
	 * @param DOMElement $td The HTML <td> element
	 * @param string $basePath Base path to resolve image paths
	 * @param string|null $align Optional text alignment: left, center, right
	 */
	function parseCellContent($cell, $td, $basePath, $align = null)
	{
		$alignmentMap = [
			'left' => Jc::LEFT,
			'center' => Jc::CENTER,
			'right' => Jc::RIGHT,
		];
		$textAlign = $alignmentMap[$align] ?? null;

		foreach ($td->childNodes as $child) {
			if ($child instanceof DOMElement) {
				$tag = strtolower($child->tagName);
				$text = trim($child->textContent);
				$bold = $child->getElementsByTagName('strong')->length > 0;
				$color = '000000';

				// Extract color if present
				if ($child->hasAttribute('style') &&
					preg_match('/color:\s*(#[a-f0-9]{3,6}|red)/i', $child->getAttribute('style'), $m)) {
					$color = strtolower($m[1]) === 'red' ? 'FF0000' : strtoupper(str_replace('#', '', $m[1]));
				}

				switch ($tag) {
					case 'img':
						$src = $child->getAttribute('src');
						if (!empty($basePath) && !str_starts_with($src, '/')) {
							$src = rtrim($basePath, '/') . '/' . $src;
						}

						if (file_exists($src)) {
							[$realWidth, $realHeight] = getimagesize($src);

							// Defaults
							$width = (int)$child->getAttribute('width');
							$height = (int)$child->getAttribute('height');

							// Inline CSS
							$style = strtolower($child->getAttribute('style') ?? '');
							if (preg_match('/width:\s*(\d+)px/', $style, $m)) {
								$width = (int)$m[1];
							}
							if (preg_match('/height:\s*(\d+)px/', $style, $m)) {
								$height = (int)$m[1];
							}

							// Auto-scale
							if ($width && !$height) {
								$scale = $width / $realWidth;
								$height = round($realHeight * $scale);
							} elseif (!$width && $height) {
								$scale = $height / $realHeight;
								$width = round($realWidth * $scale);
							}

							// Default fallback
							if (!$width && !$height) {
								$maxWidth = 100;
								if ($realWidth > $maxWidth) {
									$scale = $maxWidth / $realWidth;
									$width = $maxWidth;
									$height = round($realHeight * $scale);
								} else {
									$width = $realWidth;
									$height = $realHeight;
								}
							}

							$cell->addImage($src, [
								'width' => $width,
								'height' => $height,
								'alignment' => Jc::LEFT,
							]);
						}
						break;
					case 'div':
						$style = strtolower($child->getAttribute('style') ?? '');

						// If div is used only for spacing (empty + height), add vertical space
						if (preg_match('/height:\s*(\d+)px/', $style, $m) && $text === '') {
							$spaceTwips = (int)$m[1] * 20; // convert px to twips
							$cell->addText('', [], ['spaceBefore' => $spaceTwips]);
							break;
						}

						// Normal content
						if ($text) {
							$cell->addText($text, [
								'bold' => $bold,
								'color' => $color,
								'size' => 11,
							], [
								'alignment' => $textAlign,
								'spaceAfter' => 0,
							]);
						}
						break;
					case 'span':
					case 'p':
					case 'strong':
						if ($text) {
							$cell->addText($text, [
								'bold' => $bold,
								'color' => $color,
								'size' => 11,
							], [
								'alignment' => $textAlign,
								'spaceAfter' => 0,
							]);
						}
						break;

					default:
						if ($text) {
							$cell->addText($text, [], [
								'alignment' => $textAlign,
								'spaceAfter' => 0,
							]);
						}
						break;
				}
			} elseif ($child instanceof DOMText) {
				$text = trim($child->wholeText);
				if ($text) {
					$cell->addText($text, [], [
						'alignment' => $textAlign,
						'spaceAfter' => 0,
					]);
				}
			}
		}
	}


}
?>