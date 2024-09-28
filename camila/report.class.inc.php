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
require_once(CAMILA_VENDOR_DIR . '/autoload.php');

require_once(CAMILA_DIR.'export/phpgraphlib/phpgraphlib.php');
require_once(CAMILA_DIR.'export/phpgraphlib/phpgraphlib_pie.php');

use Mpdf\Mpdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;


class CamilaReport
{
    private $xmlConfig;
	private $camilaWT;
	private $reportDir;
	private $reportList;
	private $currentReport;

    /**
     * Constructor to load the database connection and the XML configuration file.
     *
     * @param ADOConnection $db Database connection object.
     * @param string $xmlFilePath Path to the XML configuration file.
     */
    public function __construct($lang, $camilaWT, $reportDir, $reportName = '')
    {
        //$this->db = $db;
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

    /**
     * Executes all queries defined in the XML and generates HTML content for tables and graphs.
     *
     * @return string The generated HTML with tables, titles, and graphs.
     */
    public function generateHtmlContent($report, $index, $title)
    {
        $html = '';
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

		// Handle graphs and tables
		
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
					$html .= $this->generateTable($result2, $graph).'</td>';
					if ($gCount>1)
						$html .= '</td>';
				}
			}
		}
		
		if ($notEmptyCount == 0) {
			$html .= '<td><p>Nessun dato!</p></td>';
		}
		
		if ($gCount>1) {
			$html .= '</tr></table>';
			//$html .= '</div>';
		}
		
		$html.= '</nobreak></div></mpdf>';
        return $html;
    }

    /**
     * Generate the table from the query result and XML configuration.
     *
     * @param object $result The query result from ADODB.
     * @param SimpleXMLElement $graph The graph element from the XML configuration.
     * @return string The generated HTML table.
     */
    private function generateTable($result, $graph)
    {
        // Generate the table headers
		if ($result->RecordCount()>0) {
			$html = '<div><table border="1" cellspacing="0" cellpadding="5">';
			$columns = array_keys($result->fields);
			$html .= '<thead><tr>';
			foreach ($columns as $column) {
				$html .= '<th>' . ucfirst($column) . '</th>';
			}
			$html .= '</tr></thead>';

			// Populate the table with data
			$html .= '<tbody>';
			$totalRow = [];
			while (!$result->EOF) {
				$html .= '<tr>';
				foreach ($columns as $column) {
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
				$html .= '</tr>';
				$result->MoveNext();
			}

			// If sum is enabled, add a total row at the end of the table
			if ((int)$graph->sum == 1) {
				$html .= '<tr>';
				$count = 0;
				foreach ($columns as $column) {
					if ($count ==0)
						$html .= '<td><strong></td>';
					else
						$html .= '<td><strong>' . ($totalRow[$column] ?? '') . '</strong></td>';
					$count++;
				}
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
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

    /**
     * Generates a PDF report with multiple tables and graphs (images) from the XML configuration.
     */
    public function outputPdfToBrowser()
    {
        // Create an instance of mPDF
        $mpdf = new Mpdf();

        // Add header and footer
		$t = new CamilaTemplate('it');
        $mpdf->SetHeader('Intervento "' . $t->getParameters()['evento'] . '"');
        $mpdf->SetFooter('{PAGENO} | |' . CAMILA_APPLICATION_NAME . "\n".'<br/>Report del '.date('m/d/Y') . ' ore ' . date('H:i'));
		$mpdf->use_kwt = true;

        // Initialize the Table of Contents (ToC)
        $tocHtml = '<h1>Indice</h1><ul>';
        $contentHtml = '';

        // Iterate over each report in the XML
        foreach ($this->xmlConfig->report as $index => $report) {
            $title = (string) $report->graphs->graph[0]->title;

            // Add entry to the Table of Contents with an internal link
            $tocHtml .= '<li><a href="#table' . $index . '">' . htmlspecialchars($title) . '</a></li>';

            // Add the section title and the table content
            //
            $contentHtml .= $this->generateHtmlContent($report, $index, $title);
        }

        // Close the Table of Contents
        $tocHtml .= '</ul>';

        // Add ToC and content to the PDF
        $mpdf->WriteHTML($tocHtml); // Add ToC at the start
        $mpdf->WriteHTML($contentHtml); // Add the content

        // Output the PDF to the browser (inline)
		
		$date = $this->camilaWT->db->UserDate(date('Y-m-d'), camila_get_locale_date_adodb_format());
		//$pdf->SetTitle('Report '.$date.'.pdf');
	
        $mpdf->Output('Report '.$date.'.pdf', \Mpdf\Output\Destination::INLINE);
    }

    /**
     * Generates a Word report (.docx) with multiple tables and graphs from the XML configuration.
     */
    public function outputWordToBrowser()
    {
        // Create a new Word document
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Add a Table of Contents (ToC)
        $section->addText("Table of Contents");
        $section->addTOC();  // Adds automatic Table of Contents

        // Add header and footer
        $header = $section->addHeader();
        $header->addText("Report");
        $footer = $section->addFooter();
        $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', null, array('alignment' => 'center'));

        // Iterate over each report in the XML and generate tables and images
        foreach ($this->xmlConfig->report as $index => $report) {
            $title = (string) $report->graphs->graph[0]->title;

            // Add the title to the Word document
            $section->addTitle($title, 1);  // Level 1 heading for the ToC

            // Handle graphs and tables
            foreach ($report->graphs->graph as $graph) {
                $type = (string) $graph->type;

                if ($type === 'bar') {
                    // Add image to the document
                    $filename = (string) $graph->filename;
                    $width = (int) $graph->width;
                    $height = (int) $graph->height;
                    $section->addImage($filename, array('width' => $width, 'height' => $height));
                }

                if ($type === 'table') {
                    // Add the table to the Word document
                    $table = $section->addTable();
                    $result = $this->db->Execute((string)$report->query);
                    $columns = array_keys($result->fields);

                    // Add table headers
                    $table->addRow();
                    foreach ($columns as $column) {
                        $table->addCell()->addText(ucfirst($column));
                    }

                    // Add table data
                    while (!$result->EOF) {
                        $table->addRow();
                        foreach ($columns as $column) {
                            $table->addCell()->addText($result->fields[$column]);
                        }
                        $result->MoveNext();
                    }
                }
            }
        }

        // Save and output the Word document to the browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="report.docx"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
    }

    /**
     * Generates an ODF report (.odt) with multiple tables and graphs from the XML configuration.
     */
    public function outputOdfToBrowser()
    {
        // Create a new ODF document
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Add a Table of Contents (ToC)
        $section->addText("Table of Contents");
        $section->addTOC();  // Adds automatic Table of Contents

        // Add header and footer
        $header = $section->addHeader();
        $header->addText("Report");
        $footer = $section->addFooter();
        $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', null, array('alignment' => 'center'));

        // Iterate over each report in the XML and generate tables and images
        foreach ($this->xmlConfig->report as $index => $report) {
            $title = (string) $report->graphs->graph[0]->title;

            // Add the title to the ODF document
            $section->addTitle($title, 1);  // Level 1 heading for the ToC

            // Handle graphs and tables
            foreach ($report->graphs->graph as $graph) {
                $type = (string) $graph->type;

                if ($type === 'bar') {
                    // Add image to the document
                    $filename = (string) $graph->filename;
                    $width = (int) $graph->width;
                    $height = (int) $graph->height;
                    $section->addImage($filename, array('width' => $width, 'height' => $height));
                }

                if ($type === 'table') {
                    // Add the table to the ODF document
                    $table = $section->addTable();
                    $result = $this->db->Execute((string)$report->query);
                    $columns = array_keys($result->fields);

                    // Add table headers
                    $table->addRow();
                    foreach ($columns as $column) {
                        $table->addCell()->addText(ucfirst($column));
                    }

                    // Add table data
                    while (!$result->EOF) {
                        $table->addRow();
                        foreach ($columns as $column) {
                            $table->addCell()->addText($result->fields[$column]);
                        }
                        $result->MoveNext();
                    }
                }
            }
        }

        // Save and output the ODF document to the browser
        header('Content-Type: application/vnd.oasis.opendocument.text');
        header('Content-Disposition: attachment;filename="report.odt"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($phpWord, 'ODText');
        $writer->save('php://output');
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

	function outputHtmlToBrowser() {
		global $_CAMILA;
		
		$reports = $this->xmlConfig->report;
		foreach ($reports as $k => $v) {
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="row">'));	
			$query = $this->getQuery($v);

			$data = $this->camilaWT->queryWorktableDatabase($query);

			$arr = $v->graphs->graph;
			for ($i=0; $i<count($arr);$i++)
			{
				$v3 = $arr[$i];
				if ($v3->type == 'pie' || $v3->type == 'bar') {
					$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-8">'));
					$image1 = new HAW_image("?dashboard=m1&rid=".$v2->id.'&gid='.$v3->id, "?dashboard=m1&rid=".$v->id.'&gid='.$v3->id, ":-)");
					$image1->set_br(1);
					if (count($data)>0)
					{
						$_CAMILA['page']->add_image($image1);
					}
					else
					{
						$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<p>'.$v3->title . ' - Nessun dato!'.'</p>'));
						//$camilaUI->insertWarning($v3->title . ' - Nessun dato!');
					}
					$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
				}
				if ($v3->type == 'table') {
					$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-4">'));
					$myDiv = new HAW_raw(HAW_HTML, $this->createTable($v3->id, $v3, $data));
					$_CAMILA['page']->add_raw($myDiv);
					$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
				}
			}
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
			
			$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<hr/>'));
			//$camilaUI->insertDivider();
		}	
		
	}
	
	function outputImageToBrowser($rId, $gId) {
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
}
?>