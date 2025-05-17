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

    private function generateTable($result, $graph, $noCustomCode = false)
    {
        // Generate the table headers
		if ($result->RecordCount()>0) {
			$html = '';
			if (!$noCustomCode) {
				$html .= '<div>';
			}
			$html .= '<table border="1" cellspacing="0" cellpadding="5">';
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
						$html .= '<td>' . htmlspecialchars($value) . '</td>';

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
		$html .= '<h2 id="table' . $index . '" style="page-break-after: avoid;">' . htmlspecialchars($title) . '</h2>';
		$query = $this->getQuery($report);
		
		$title = (string) $report->graphs->graph[0]->title;
		$rId = (string) $report->id;

		$result2 = $this->camilaWT->startExecuteQuery($query,true,ADODB_FETCH_ASSOC);
		$result = $this->camilaWT->startExecuteQuery($query);
		$data = $this->queryWorktableDatabase($result);

		if (!$result) {
			throw new Exception('Error executing query: ' . $this->db->ErrorMsg());
		}

		// Add the title
		//$html .= '<h2>' . htmlspecialchars($title) . '</h2>';
		//$html .= '<div style="page-break-before: avoid; display: flex; justify-content: space-between;">';
		
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
			}
		}
		
		if ($notEmptyCount == 0) {
			$html .= '<td><p>Nessun dato disponibile.</p></td>';
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
			$container->addText("Nessun dato disponibile.", $fontStyle);
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
					$cell->addText("Nessun dato disponibile.");
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
					$section->addText("No data available.");
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
			'margin_top' => 35,
			'margin_bottom' => 15,
			'margin_left' => 15,
			'margin_right' => 15,
		]);

        // Add header and footer
		$t = new CamilaTemplate($this->lang);
		$params = $t->getParameters();

		$logoPath = CAMILA_TMPL_DIR . '/images/'.$this->lang.'/'.$params['logo']; // Percorso assoluto o accessibile
		$evento = htmlspecialchars($params['evento']);
		$comune = htmlspecialchars($params['comune']);
		$segreteria = htmlspecialchars($params['segreteriacampo'] . ' ' . $params['nomecampo']);
		$headerHtml = '
		<table width="100%" style="border: none;">
		  <tr>
			<td width="60" style="vertical-align: top;">
			  <img src="' . $logoPath . '" width="55" height="55">
			</td>
			<td style="vertical-align: top; font-size: 12pt; padding-left: 10px;">
			  <div><strong>' . $evento . '</strong></div>
			  <div><strong>' . $comune . '</strong></div>
			  <div style="color: red;"><strong>' . $segreteria . '</strong></div>
			</td>
		  </tr>
		</table>
		<div style="height: 15px;"></div>';
		$mpdf->SetHTMLHeader($headerHtml);
		
        $mpdf->SetFooter('{PAGENO} | |' . CAMILA_APPLICATION_NAME . "\n".'<br/>Report del '.date('m/d/Y') . ' ore ' . date('H:i'));
		$mpdf->use_kwt = true;

        // Initialize the Table of Contents (ToC)
        $tocHtml = '<h1>Indice</h1><ol>';
        $contentHtml = '';

        // Iterate over each report in the XML
		
		$count = 1;
        foreach ($this->xmlConfig->report as $index => $report) {
            $title = (string) $report->graphs->graph[0]->title;

            //$tocHtml .= '<li><a href="#table' . $index . '">' . htmlspecialchars($title) . '</a></li>';
			$tocHtml .= '<li>' . htmlspecialchars($title) . '</li>';

            // Add the section title and the table content
            $contentHtml .= '<div style="white-space: nowrap;">'.$this->generateHtmlContent($report, $index, $count. '. ' . $title).'</div>';
			$count++;
        }

        // Close the Table of Contents
        $tocHtml .= '</ol>';

        // Add ToC and content to the PDF
        $mpdf->WriteHTML($tocHtml); // Add ToC at the start
        $mpdf->WriteHTML($contentHtml); // Add the content

        // Output the PDF to the browser (inline)
		
		$date = $this->camilaWT->db->UserDate(date('Y-m-d'), camila_get_locale_date_adodb_format());
		//$pdf->SetTitle('Report '.$date.'.pdf');
	
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
							$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<p><i>Nessun dato disponibile.</i></p>'));
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

		$header = $section->addHeader();
		$footer = $section->addFooter();

		$t = new CamilaTemplate($this->lang);
		$table = $header->addTable();
		$table->addRow();
		$params = $t->getParameters();
		$directory = CAMILA_TMPL_DIR . '/images/'.$this->lang;
		$cell1 = $table->addCell(1000); 
		$cell1->addImage(
			$directory.'/'.$params['logo'],
			['width' => 40, 'height' => 40, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]
		);
		$cell2 = $table->addCell(8000);
		$cell2->addText($params['evento'], ['bold' => true], ['lineHeight' => 1.2]);
		$cell2->addText($params['comune'], ['bold' => true], ['lineHeight' => 1.2]);
		$cell2->addText($params['segreteriacampo']. ' ' . $params['nomecampo'], ['bold' => true, 'color' => 'FF0000'], ['lineHeight' => 1.2]);
		$cell2->addText('', null, ['spaceAfter' => 200]);

		$footer->addPreserveText('{PAGE} | ' . CAMILA_APPLICATION_NAME . ' | Report del ' . date('d/m/Y') . ' ore ' . date('H:i'));

		// Index
		$section->addText('Indice', ['bold' => true, 'size' => 16]);
		$section->addTOC(['spaceAfter' => 200]);

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

}
?>