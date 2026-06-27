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

require_once(CAMILA_VENDOR_DIR . '/adodb/adodb-php/adodb.inc.php');
require_once(CAMILA_VENDOR_DIR . '/autoload.php');

require_once(CAMILA_DIR.'export/phpgraphlib/phpgraphlib.php');
require_once(CAMILA_DIR.'export/phpgraphlib/phpgraphlib_pie.php');
require_once(CAMILA_LIB_DIR.'qrcode/qrcode.class.php');

use Mpdf\Mpdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Element\Cell;


/**
 * Renders statistical reports defined in XML config files.
 *
 * Each report file is an XML document with the following structure:
 *
 * ```xml
 * <?xml version='1.0' standalone='yes' ?>
 * <reports>
 *   <report>
 *     <id>unique_id</id>
 *
 *     <!-- Query selection: the engine picks the first matching variant in this order:
 *          mysqlQuery (if driver=mysql) → sqliteQuery (if driver=sqlite) → query.
 *          <query/> may be left empty when only driver-specific variants are provided. -->
 *     <query>SELECT col1, col2 FROM ...</query>
 *     <mysqlQuery>SELECT ...  (MySQL-specific syntax)</mysqlQuery>
 *     <sqliteQuery>SELECT ... (SQLite-specific syntax)</sqliteQuery>
 *
 *     <graphs>
 *       <graph>
 *         <id>1</id>
 *
 *         <!-- Graph type: pie | bar | table | text -->
 *         <type>pie</type>
 *         <title>Chart title shown in the document</title>
 *
 *         <!-- pie / bar: pixel dimensions of the generated PNG image.
 *              <filename> is ignored by the engine (path is computed internally). -->
 *         <width>500</width>
 *         <height>400</height>
 *
 *         <!-- table: core options -->
 *         <sum>1</sum>                           <!-- 0|1 — append a numeric totals row -->
 *         <hideFirstColumn>true</hideFirstColumn> <!-- omit column 0 from header and rows -->
 *         <columnWidths>30,40,30</columnWidths>   <!-- comma-separated % widths used in Word/ODT output;
 *                                                      must match the number of visible columns -->
 *
 *         <!-- text: raw HTML template; ${0}, ${1}, … are replaced with query column values -->
 *         <html><![CDATA[<p>${0} — ${1}</p>]]></html>
 *
 *         <!-- ── Optional extensions ────────────────────────────────────────────── -->
 *
 *         <!-- Inline CSS applied to the HTML <table> element (HTML/PDF output only) -->
 *         <style>width:100%</style>
 *
 *         <!-- Render a 1-D barcode in a table column (HTML/PDF output via mPDF <barcode> tag) -->
 *         <barcodeColumn>2</barcodeColumn>       <!-- 0-based column index (all columns, incl. hidden) -->
 *         <barcodeType>code39extend</barcodeType>
 *         <barcodeSize>0.3</barcodeSize>
 *         <barcodeHeight>8</barcodeHeight>
 *
 *         <!-- Render a QR code image in a table column (all output formats: HTML, PDF, Word, ODT) -->
 *         <qrcodeColumn>2</qrcodeColumn>         <!-- 0-based column index (all columns, incl. hidden) -->
 *         <qrcodeSize>80</qrcodeSize>            <!-- output PNG size in pixels (default 80) -->
 *         <qrcodeLevel>M</qrcodeLevel>           <!-- ECC level: L | M | Q | H (default M) -->
 *
 *       </graph>
 *     </graphs>
 *   </report>
 * </reports>
 * ```
 *
 * Supported output formats: PDF (mPDF), HTML, Word (.docx), ODT.
 */
class CamilaReport
{
    /** @var \SimpleXMLElement Parsed XML config for the current report file. */
    private $xmlConfig;

    /** @var object Camila worktable instance used for database queries. */
    private $camilaWT;

    /** @var string Absolute path to the language-specific reports directory. */
    private $reportDir;

    /** @var array<string,string> Map of report filename (without .xml) → display subtitle. */
    private $reportList;

    /** @var string Filename key (without .xml) of the active report. */
    private $currentReport;

    /** @var string Active language code (e.g. "it"). */
    private $lang;

    /** @var bool When true, a table of contents is prepended to PDF/Word output. */
    public $shouldGenerateToc = false;

    /** @var bool When true, the HTML in $headerHtml is added as a repeating page header. */
    public $shouldGenerateHeader = false;

    /** @var string HTML markup rendered as the page header (used when $shouldGenerateHeader is true). */
    public $headerHtml;

    /** @var bool When true, a footer with page number, app name and date is added. */
    public $shouldGenerateFooter = false;

    /** @var string Optional custom filename for PDF inline output (default: "Report <date>.pdf"). */
    public $outputFileName;

    /** Pie slices below this percentage are merged into a single "Altri" slice. */
    public $pieSliceThreshold = 3.0;


    /**
     * @param string $lang       Language code used to locate the reports sub-directory.
     * @param object $camilaWT   Camila worktable instance (provides DB access).
     * @param string $reportDir  Base path containing per-language report folders.
     * @param string $reportName Filename (without .xml) of the report to load.
     *                           Defaults to the first file found alphabetically.
     */
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

    /**
     * Scans the reports directory and returns all available report files.
     *
     * @return array<string,string> Keys are filenames without extension; values are the
     *                              substring after the first underscore (used as display subtitle).
     */
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
	
    /** Returns the filename key (without .xml) of the currently loaded report. */
	function getCurrentReportName() {
		return $this->currentReport;
	}
	
    /** Returns the display subtitle of the current report (the part after the first underscore in the filename), or null if no report is loaded. */
	function getCurrentReportTitle() {
		$title = null;
		if (!empty($this->reportList)) {
			$title = $this->reportList[$this->currentReport];
		}
		return $title;
	}
	
    /**
     * Returns the most appropriate query for the active database driver.
     *
     * Checks for <mysqlQuery> or <sqliteQuery> siblings first; falls back to <query>.
     *
     * @param \SimpleXMLElement $node A <report> node from the XML config.
     * @return \SimpleXMLElement The query element to execute.
     */
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

    /**
     * Renders a <graph type="text"> block.
     *
     * The graph must contain a <html> element with the HTML template.
     * Placeholders ${0}, ${1}, … are replaced with the corresponding query column values
     * from the first (and only expected) result row.
     *
     * @param \ADORecordSet|null $result Query result (ADODB_FETCH_ASSOC mode).
     * @param \SimpleXMLElement  $graph  The <graph> node.
     * @return string HTML fragment.
     */
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

    /**
     * Renders a <graph type="table"> block as an HTML table.
     *
     * Supported graph attributes:
     * - <hideFirstColumn>true</hideFirstColumn>  Skips column 0 in header and rows.
     * - <sum>1</sum>                             Appends a numeric totals row.
     * - <style>…</style>                         Inline CSS applied to the <table> element.
     * - <barcodeColumn>N</barcodeColumn>         Renders a mPDF <barcode> tag in column N.
     *   <barcodeType>, <barcodeSize>, <barcodeHeight> — barcode parameters.
     * - <qrcodeColumn>N</qrcodeColumn>           Renders a QR code image in column N.
     *   <qrcodeSize> (px, default 80), <qrcodeLevel> (L|M|Q|H, default M),
     *   <qrcodePadding> (cell padding in px, default 4).
     *
     * @param \ADORecordSet    $result       Query result (ADODB_FETCH_ASSOC mode).
     * @param \SimpleXMLElement $graph       The <graph> node.
     * @param bool             $noCustomCode When true, omits the wrapping <div> (used for embedding).
     * @return string HTML fragment.
     */
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
						if (isset($graph->barcodeColumn) && $cCount == (int)$graph->barcodeColumn) {
							$html .= '<td style="text-align:center;line-height: 2;"><barcode code="'.$value.'" type="'.$graph->barcodeType.'" size="'.$graph->barcodeSize.'" height="'.$graph->barcodeHeight.'" /><br/>'.$value.'</td>';
						} elseif (isset($graph->qrcodeColumn) && $cCount == (int)$graph->qrcodeColumn) {
							$qrSize    = isset($graph->qrcodeSize)    ? (int)$graph->qrcodeSize    : 80;
							$qrLevel   = isset($graph->qrcodeLevel)   ? (string)$graph->qrcodeLevel : 'M';
							$qrPadding = isset($graph->qrcodePadding) ? (int)$graph->qrcodePadding : 4;
							$imgPath   = $this->generateQrPng((string)$value, $qrSize, $qrLevel);
							$html .= '<td style="text-align:center;padding:' . $qrPadding . 'px;">'
								. ($imgPath ? '<img src="' . htmlspecialchars($imgPath) . '" width="' . $qrSize . '" height="' . $qrSize . '" /><br/>' : '')
								. htmlspecialchars($value) . '</td>';
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

    /**
     * Converts a two-column query result into a key→value array used by createGraph().
     *
     * The first column becomes the array key, the second becomes the numeric value.
     *
     * @param \ADORecordSet $result Query result (default fetch mode).
     * @return array<string,mixed>
     */
	function queryWorktableDatabase($result)
	{
		$arr = array();
		if ($result === false) return $arr;
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
	
    /**
     * Generates a QR code PNG and returns its file path.
     *
     * The PNG is cached in CAMILA_TMP_DIR using md5(value+level) as the filename,
     * so the same value encoded at the same ECC level is only rendered once per request.
     * Uses the bundled QRcode library (lib/qrcode/qrcode.class.php).
     *
     * @param string $value   Text to encode.
     * @param int    $sizePx  Output image size in pixels (default 80).
     * @param string $level   Error correction level: L | M | Q | H (default M).
     * @return string Absolute path to the PNG, or '' if $value is empty.
     */
	private function generateQrPng($value, $sizePx = 80, $level = 'M') {
		if (trim($value) === '') return '';
		$filename = CAMILA_TMP_DIR . '/qr_' . md5($value . $level) . '.png';
		if (!file_exists($filename)) {
			$qr = new QRcode(utf8_encode($value), $level);
			$qr->disableBorder();
			$qr->displayPNG($sizePx, [255,255,255], [0,0,0], $filename, 0);
		}
		return $filename;
	}

    /**
     * Generates a PHPGraphLib chart image and saves it to $filename.
     *
     * Required graph attributes: <type> (pie|bar), <width>, <height>, <title>.
     *
     * @param string            $name     Graph id (unused, kept for signature compatibility).
     * @param \SimpleXMLElement $obj      The <graph> node.
     * @param array             $data     Key→value pairs from queryWorktableDatabase().
     * @param string|null       $filename Destination path for the PNG. If null, outputs directly to browser.
     */

    private function groupSmallSlices(array $data): array
    {
        if ($this->pieSliceThreshold <= 0) return $data;
        $total  = array_sum($data) ?: 1;
        $result = [];
        $other  = 0;
        foreach ($data as $k => $v) {
            if ($v / $total * 100 < $this->pieSliceThreshold) {
                $other += $v;
            } else {
                $result[$k] = $v;
            }
        }
        if ($other > 0) $result['Altri'] = $other;
        return $result ?: $data;
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }

    private function hslToHex(float $h, float $s, float $l): string
    {
        $h /= 360; $s /= 100; $l /= 100;
        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hueToRgb($p, $q, $h + 1/3);
            $g = $this->hueToRgb($p, $q, $h);
            $b = $this->hueToRgb($p, $q, $h - 1/3);
        }
        return sprintf('#%02X%02X%02X', (int)round($r * 255), (int)round($g * 255), (int)round($b * 255));
    }

    private function buildPiePalette(int $n): array
    {
        $base = ['#4E79A7','#F28E2B','#E15759','#76B7B2','#59A14F','#EDC948','#B07AA1','#FF9DA7','#9C755F','#BAB0AC'];
        if ($n <= 10) return array_slice($base, 0, $n);
        $colors = $base;
        $extra  = $n - 10;
        for ($i = 0; $i < $extra; $i++) {
            $colors[] = $this->hslToHex(($i * 360 / $extra + 15) % 360, 55, 48);
        }
        return $colors;
    }

    /**
     * Renders a pie or bar chart as a PNG using PHP GD (built-in, no extensions needed).
     *
     * Primary chart renderer for all output formats (HTML, PDF, Word, ODT).
     * Requires composer require amenadiel/jpgraph.
     * Output is cached in CAMILA_TMP_DIR by content hash.
     *
     * @param \SimpleXMLElement $graph The <graph> node.
     * @param array             $data  Key→value pairs from queryWorktableDatabase().
     * @return string Absolute path to the PNG file, or '' if jpgraph is not installed.
     */
    private function createJpgraphPng(\SimpleXMLElement $graph, array $data): string
    {
        if (empty($data)) return '';
        if (!file_exists(CAMILA_VENDOR_DIR . '/amenadiel/jpgraph/src/graph/Graph.php')) return '';

        $width  = (int)$graph->width  ?: 500;
        $height = (int)$graph->height ?: 400;
        $type   = (string)$graph->type;
        $title  = (string)$graph->title;

        $key      = md5(serialize($data) . $type . $width . $height . $title);
        $filename = CAMILA_TMP_DIR . '/jp_chart_' . $key . '.png';
        if (file_exists($filename)) return $filename;

        try {
            if ($type === 'pie') {
                $data    = $this->groupSmallSlices($data);
                $count   = count($data);
                $maxLen  = max(array_map('strlen', array_keys($data))) + 10;
                $cols    = $maxLen > 22 ? 1 : ($maxLen > 14 ? 2 : min($count, 3));
                $rows    = (int)ceil($count / $cols);
                $legH    = $rows * 24 + 20;
                $totalH  = $height + $legH;
                $pieSize = min(0.45, (min($width, $height) * 0.38) / min($width, $totalH));
                $pieY    = ($height * 0.54) / $totalH;

                $g = new \Amenadiel\JpGraph\Graph\PieGraph($width, $totalH);
                $g->title->Set($title);
                $g->title->SetFont(FF_ARIAL, FS_BOLD, 12);
                $p = new \Amenadiel\JpGraph\Plot\PiePlot(array_values($data));
                $p->SetSliceColors($this->buildPiePalette($count));
                $p->SetLegends(array_map(fn($k) => "$k (%.1f%%)", array_keys($data)));
                $p->SetSize($pieSize);
                $p->SetCenter(0.5, $pieY);
                $g->legend->SetFont(FF_ARIAL, FS_NORMAL, 9);
                $g->legend->SetColumns($cols);
                $g->legend->SetPos(0.5, 0.99, 'center', 'bottom');
                $g->Add($p);
            } else {
                $maxLabelLen  = max(array_map('strlen', array_keys($data)));
                $labelAngle   = $maxLabelLen > 8 ? 50 : 0;
                $labelExtra   = $labelAngle > 0
                    ? (int)($maxLabelLen * 6 * sin(deg2rad($labelAngle))) + 20
                    : 0;
                $barH         = $height + $labelExtra;

                $g = new \Amenadiel\JpGraph\Graph\Graph($width, $barH);
                $g->SetScale('textlin');
                $g->SetMargin(50, 20, 40, $labelExtra + 20);
                $g->title->Set($title);
                $g->title->SetFont(FF_ARIAL, FS_BOLD, 12);
                $g->xaxis->SetFont(FF_ARIAL, FS_NORMAL, 9);
                $g->xaxis->SetLabelAngle($labelAngle);
                $g->yaxis->SetFont(FF_ARIAL, FS_NORMAL, 9);
                $allInt = array_reduce(array_values($data), fn($c, $v) => $c && (floor($v) == $v), true);
                $g->yaxis->SetLabelFormat($allInt ? '%d' : '%.1f');
                $b = new \Amenadiel\JpGraph\Plot\BarPlot(array_values($data));
                $b->SetWidth(0.6);
                $b->value->Show();
                $b->value->SetFont(FF_ARIAL, FS_NORMAL, 9);
                $b->value->SetFormat($allInt ? '%d' : '%.1f');
                $g->xaxis->SetTickLabels(array_keys($data));
                $g->Add($b);
            }
            @ob_start();
            $g->Stroke($filename);
            @ob_end_clean();
        } catch (\Throwable $e) {
            @ob_end_clean();
            error_log('[' . date('Y-m-d H:i:s') . '] jpgraph: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL, 3, CAMILA_LOG_DIR . '/jpgraph_errors.log');
            return '';
        }

        return file_exists($filename) ? $filename : '';
    }

    /**
     * Returns the path to a PNG chart for any output format.
     *
     * Tries amenadiel/jpgraph first (professional quality), then PHPGraphLib as fallback.
     *
     * @param \SimpleXMLElement $graph The <graph> node.
     * @param array             $data  Key→value pairs from queryWorktableDatabase().
     * @return string Absolute path to the PNG file.
     */
    private function getChartPng(\SimpleXMLElement $graph, array $data): string
    {
        $png = $this->createJpgraphPng($graph, $data);
        if ($png !== '') return $png;
        $key      = md5(serialize($data) . (string)$graph->type . (int)$graph->width . (int)$graph->height . (string)$graph->title);
        $filename = CAMILA_TMP_DIR . '/chart_' . $key . '.png';
        $this->createGraph('', $graph, $data, $filename);
        return $filename;
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
     * Builds the HTML fragment for a single <report> node.
     *
     * Handles all graph types: pie, bar, table, text.
     * Multiple graphs in the same report are laid out side-by-side in a <table>.
     * When no data is found, outputs a localised "no data" message.
     *
     * @param \SimpleXMLElement $report       The <report> node.
     * @param int               $index        0-based position within the file (used for anchor id).
     * @param string            $title        Section heading text.
     * @param bool              $noCustomCode When true, omits mPDF-specific wrapper tags (e.g. for embedding in Word).
     * @return string HTML fragment ready for mPDF::WriteHTML().
     */
    public function generateHtmlContent($report, $index, $title, $noCustomCode = false)
    {
        $html = '';
		if (!$noCustomCode && isset($report->pageBreakBefore) && (string)$report->pageBreakBefore === 'true') {
			$html .= '<pagebreak />';
		}
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
		
		if (/*isset($report->query) || */isset($query)) {

			$result2 = $this->camilaWT->startExecuteQuery($query,true,ADODB_FETCH_ASSOC);
			$result = $this->camilaWT->startExecuteQuery($query);

			if ($result === false) {
				$errorId     = strtoupper(substr(md5(uniqid('', true)), 0, 8));
				$resolvedSql = $this->camilaWT->parseWorktableSqlStatement($query, true);
				$trace       = array_map(
				    fn($f) => ($f['file'] ?? '') . ':' . ($f['line'] ?? '') . ' ' . ($f['function'] ?? ''),
				    debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
				);
				$logLine     = '[' . date('Y-m-d H:i:s') . '] [' . $errorId . '] '
				             . $this->camilaWT->db->ErrorMsg()
				             . ' | SQL: ' . $resolvedSql
				             . PHP_EOL . implode(PHP_EOL, array_map(fn($l) => '  ' . $l, $trace));
				file_put_contents(
				    rtrim(CAMILA_LOG_DIR, '/\\') . '/report-query-error.log',
				    $logLine . PHP_EOL,
				    FILE_APPEND
				);
				camila_error_page('', 'An error occurred while generating the report (ref: ' . $errorId . ')');
				return '';
			}

			$data = $this->queryWorktableDatabase($result);
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
					if ($gCount>1)
						$html .= '<td width="50%" style="vertical-align: middle;">';
					$png   = $this->getChartPng($graph, $data);
					$width = (int)$graph->width ?: 500;
					$html .= '<div><img src="' . htmlspecialchars($png) . '" width="' . $width . '" /></div>';
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


    /**
     * Renders a query result as a PHPWord table inside the given container.
     *
     * Supports the same graph attributes as generateTable():
     * <hideFirstColumn>, <sum>, <columnWidths>, <qrcodeColumn>/<qrcodeSize>/<qrcodeLevel>.
     * Column widths are specified as comma-separated percentages in <columnWidths>;
     * if omitted, columns are divided equally across $totalWidth twips.
     *
     * @param object            $container  PHPWord Section, Cell, or Header/Footer.
     * @param \ADORecordSet     $result     Query result (ADODB_FETCH_ASSOC mode).
     * @param \SimpleXMLElement $graph      The <graph> node.
     * @param int               $totalWidth Available width in twips (default 9065 ≈ A4 usable width).
     * @param array             $fontStyle  PHPWord font style array applied to all cells.
     */
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
				$origIdx = $skipFirst ? $i + 1 : $i;

				if (isset($graph->qrcodeColumn) && $origIdx == (int)$graph->qrcodeColumn) {
					$qrSize  = isset($graph->qrcodeSize)  ? (int)$graph->qrcodeSize  : 80;
					$qrLevel = isset($graph->qrcodeLevel) ? (string)$graph->qrcodeLevel : 'M';
					$imgPath = $this->generateQrPng((string)$value, $qrSize, $qrLevel);
					$cell = $table->addCell($customWidths[$i], $cellStyle);
					if ($imgPath) {
						$cell->addImage($imgPath, [
							'width'     => round($qrSize * 0.75),
							'height'    => round($qrSize * 0.75),
							'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
						]);
					}
					$cell->addText((string)$value, $fontStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
				} else {
					$table->addCell($customWidths[$i], $cellStyle)->addText((string)$value, $fontStyle);
				}

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

    /**
     * Adds the content of one <report> node to a PHPWord Section.
     *
     * Multiple graphs are placed side-by-side in a borderless table;
     * a single graph is rendered directly into the section.
     * Supports pie, bar (rendered as PNG via PHPGraphLib) and table types.
     *
     * @param Section           $section PHPWord section to append content to.
     * @param \SimpleXMLElement $report  The <report> node.
     * @param int               $index   Report position (unused, kept for API consistency).
     * @param string            $title   Section heading (already added by the caller).
     */
	public function generateWordContent(Section $section, $report, $index, $title)
	{
		if (isset($report->pageBreakBefore) && (string)$report->pageBreakBefore === 'true') {
			$section->addPageBreak();
		}

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
						$this->addAutoScaledImageToCell($cell, $this->getChartPng($graph, $data), $equalWidth - 200);
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
						$this->addAutoScaledImageToCell($section, $this->getChartPng($graph, $data), $totalWidth);
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

    /**
     * Adds an image to a PHPWord container, scaling it down to fit within $maxWidthTwip.
     *
     * If the image's natural width (at 96 dpi) exceeds the available width, it is scaled
     * proportionally. The final dimensions are converted from pixels to points (×0.75).
     *
     * @param object $cellOrSection PHPWord Section or Cell.
     * @param string $imagePath     Absolute path to the image file.
     * @param int    $maxWidthTwip  Maximum width in twips (1 twip = 1/1440 inch).
     */
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

    /**
     * Renders all reports to a PDF and sends it inline to the browser.
     *
     * Uses mPDF. Respects $shouldGenerateToc, $shouldGenerateHeader, $shouldGenerateFooter.
     * The output filename defaults to "Report <date>.pdf"; override via $outputFileName.
     */
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

    /**
     * Renders all reports as HTML widgets and appends them to the current Camila page.
     *
     * Supports pie, bar and table graph types.
     * Pie/bar charts are rendered as PNG via jpgraph (or PHPGraphLib fallback),
     * embedded as base64 data URIs.
     * Errors in individual reports are caught and displayed inline.
     */
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
						if (count($data) > 0) {
							$png = $this->getChartPng($v3, $data);
							if ($png !== '' && file_exists($png)) {
								$b64 = base64_encode(file_get_contents($png));
								$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<img src="data:image/png;base64,' . $b64 . '" style="max-width:100%;" />'));
							}
						} else {
							$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<p><i>'.camila_get_translation('camila.nodatafound').'</i></p>'));
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
	
    /**
     * Generates and outputs a chart image directly to the browser (used by dashboard AJAX requests).
     *
     * @param string $rId    Report id to match against <report><id>.
     * @param string $gId    Graph id to match against <graph><id>.
     * @param string $report Unused (kept for API compatibility).
     */
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

    /**
     * Builds and returns the PhpWord document object with all report content.
     *
     * Shared by outputDocxToBrowser() and outputOdtToBrowser().
     * Applies header/footer when $shouldGenerateHeader/$shouldGenerateFooter are set.
     * Adds a TOC when $shouldGenerateToc is true.
     *
     * @return PhpWord
     */
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

    /** Sends all reports as a Word (.docx) file download to the browser. */
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

    /** Sends all reports as an OpenDocument Text (.odt) file download to the browser. */
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
    /**
     * Converts HTML <table> elements found in $html into PHPWord table objects.
     *
     * Parses inline styles on <table> (border, cellpadding) and <td> (width, vertical-align,
     * text-align) to replicate the visual layout in Word/ODT. Cell content is delegated to
     * parseCellContent(). Used internally to convert the $headerHtml to a Word header.
     *
     * @param object $container PHPWord Section, Header, or Footer.
     * @param string $html      HTML string containing one or more <table> elements.
     * @param string $basePath  Base path for resolving relative image src paths.
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
    /**
     * Populates a PHPWord Cell from the child nodes of an HTML <td> element.
     *
     * Handles: <img> (auto-scaled), <div> (spacing or text), <span>, <p>, <strong>,
     * and bare text nodes. Extracts bold, color and alignment from inline styles.
     *
     * @param Cell          $cell     Target PHPWord cell.
     * @param \DOMElement   $td       The source <td> DOM element.
     * @param string        $basePath Base path for resolving relative image src paths.
     * @param string|null   $align    Text alignment: "left" | "center" | "right" | null.
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