<?php
  /* This File is part of Camila PHP Framework
   Copyright (C) 2006-2025 Umberto Bresciani
   
   Camila PHP Framework is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.
   
   Camila PHP Framework is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.
   
   You should have received a copy of the GNU General Public License
   along with Camila PHP Framework; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA */
  
  require_once CAMILA_VENDOR_DIR . 'autoload.php';

  use PhpOffice\PhpSpreadsheet\Helper\Sample;
  use PhpOffice\PhpSpreadsheet\IOFactory;
  use PhpOffice\PhpSpreadsheet\Spreadsheet;
  
  
  class CAMILA_XML2PDF_deck extends CHAW_deck
  {
      var $pdf;
      var $textpending = '';

	function create_page()
    {
		global $_CAMILA;
		$ext = $this->_find_extension($_REQUEST['camila_xml2pdf']);
		$camilaTemplate = new CamilaTemplate($_CAMILA['lang']);
		if ($ext == 'json') {
			$spreadsheet = new Spreadsheet();
			$spreadsheet->setActiveSheetIndex(0);
			$i18nStr = ' - ' . camila_get_translation('camila.worktable.worksheet.data');
			$spreadsheet->getActiveSheet()->setTitle(substr($_CAMILA['page_short_title'],0,$maxlength-strlen($i18nStr)) . ' - ' . camila_get_translation('camila.worktable.worksheet.data'));

			$file = $camilaTemplate->getXmlTemplatePath($_REQUEST['camila_xml2pdf']);
			$json = file_get_contents($file);
			$obj = json_decode($json, true);
			$query = $obj['sql'];
			$camilaWT  = new CamilaWorkTable();
			$camilaWT->db = $_CAMILA['db'];
			$r = $camilaWT->startExecuteQuery($query);
			$columns = $r->fieldCount();
			  
			for ($colIndex=0;$colIndex<$columns;$colIndex++)
			{
				$fieldInfo = $r->fetchField($colIndex);
				$spreadsheet->getActiveSheet()->setCellValue([$colIndex+1, 1], $fieldInfo->name);

			}  
			$count = 2;
			while (!$r->EOF) {
				foreach ($r->fields as $k => $v) {
					
					if(strlen($v)==19 && preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/", $v)) {
						$d = new DateTime();
                        $d->setDate(intval(substr($v, 0, 4)), intval(substr($v, 5, 2)), intval(substr($v, 8, 2)));
						$d->setTime(intval(substr($v, 11, 2)), intval(substr($v, 14, 2)), intval(substr($v, 17, 2)));					
						$excelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($d);
						$spreadsheet->getActiveSheet()->setCellValue([$k+1, $count], $excelDateValue);
						$spreadsheet->getActiveSheet()->getStyle([$k+1, $count])->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DATETIME);						
					} elseif (strlen($v)==10 && preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}/", $v)) {
						$d = new DateTime();
                        $d->setDate(intval(substr($v, 0, 4)), intval(substr($v, 5, 2)), intval(substr($v, 8, 2)));
						$d->setTime(0,0,0);
						$excelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($d);
						$spreadsheet->getActiveSheet()->setCellValue([$k+1, $count], $excelDateValue);
						$spreadsheet->getActiveSheet()->getStyle([$k+1, $count])->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DDMMYYYY);
					} else {
						$spreadsheet->getActiveSheet()->setCellValue([$k+1, $count], $v);
					}
				}
				$count++;
				$r->MoveNext();
			}
			//exit();
			
			$sheet = $spreadsheet->getActiveSheet();
			$cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
			$cellIterator->setIterateOnlyExistingCells(true);
			foreach ($cellIterator as $cell) {
				$sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
			}
			
			$filename = str_replace('.json', '.xlsx', $_REQUEST['camila_xml2pdf']);

			// Redirect output to a client’s web browser (Xls)
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0
			$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
			//	$writer = IOFactory::createWriter($spreadsheet, 'Ods');
            $writer->save('php://output');            
		  }
		  
		  if ($ext == 'xml') {
          
			  require_once(CAMILA_DIR . 'export/xml-2-pdf/Xml2Pdf.php');


			  //$xmlfile = CAMILA_TMPL_DIR . '/' . $_CAMILA['lang'] . '/' . $_REQUEST['camila_xml2pdf'];
			  $xmlfile = $camilaTemplate->getXmlTemplatePath($_REQUEST['camila_xml2pdf']);

			  
			$xmlFirstNode = ''; 
			$reader = new XMLReader();
			$reader->open($xmlfile);

			while ($reader->read()) {
				if ($reader->nodeType === XMLReader::ELEMENT) {
					$xmlFirstNode = $reader->name;
					break;
				}
			}

			$reader->close();
			
			if ($_REQUEST['filename'] != '')
			  {
				  $this->title = $this->filter_filename($_REQUEST['filename'], true);
			  }
			
			if ($xmlFirstNode == 'reports') {
				$camilaWT  = new CamilaWorkTable();
				$camilaWT->db = $_CAMILA['db'];
				$lang = $_CAMILA['lang'];

				$info = pathinfo($xmlfile);
				$reportName = $info['filename'];	
				$folder = pathinfo($xmlfile, PATHINFO_DIRNAME);
				$reportDir = dirname($folder);

				$camilaReport = new CamilaReport($lang, $camilaWT, $reportDir, $reportName);
				$camilaReport->shouldGenerateToc = false;
				$camilaReport->shouldGenerateHeader = false;
				$camilaReport->shouldGenerateFooter = false;
				$camilaReport->outputFileName = $this->title.'.pdf';
				$camilaReport->outputPdfToBrowser();
			} else {
				
				$xml = '';

			  $t = new MiniTemplator;
			  $t->readTemplateFromFile($xmlfile);


			  if ($_REQUEST['camila_xml2pdf_checklist_options_0'] != 'y')
			  {
				  $format = camila_get_locale_date_adodb_format();
				  $text=date($format);
				  $t->setVariable(camila_get_translation('camila.export.template.date'), isUTF8($text) ? utf8_decode($text) : $text, true);

				  $text=date($format.' H:i');
				  $t->setVariable(camila_get_translation('camila.export.template.timestamp'), isUTF8($text) ? utf8_decode($text) : $text, true);
				  
				  //2016
				  $t->setVariable(camila_get_translation('camila.export.template.worktable.filter'), isUTF8($text) ? utf8_decode($_CAMILA['page']->camila_worktable_filter) : $_CAMILA['page']->camila_worktable_filter, true);
				  
				  //2019
				  foreach ($_CAMILA['page']->camila_worktable_filter_values as $k => $v) {
					  $t->setVariable(camila_get_translation('camila.export.template.worktable.filter') . ' ' . $k, isUTF8($text) ? utf8_decode($v) : $v, true);
				  }

				  $sheetName = substr ( $_REQUEST['camila_xml2pdf'] , 0 , strpos($_REQUEST['camila_xml2pdf'], '_'));
				  $t->setVariable(camila_get_translation('camila.export.template.worktable.name'), isUTF8($text) ? utf8_decode($sheetName) : $sheetName, true);
			  }

			  $i = 0;
			  while (isset($this->element[$i])) {
				  $page_element = $this->element[$i];
				  switch ($page_element->get_elementtype()) {
					  case HAW_FORM: {
						  $i = 0;
						  while (isset($page_element->element[$i])) {
							  $form_element = $page_element->element[$i];
							  $form_fieldname = substr($form_element->name, strlen($_CAMILA['datagrid_form']->name) + 1);
							  $form_label = $_CAMILA['datagrid_form']->fields[$form_fieldname]->title;


							  switch ($form_element->get_elementtype()) {
								  //case HAW_IMAGE:
								  //case HAW_RADIO:
								  //case HAW_RULE:
								  case HAW_HIDDEN:
								  case HAW_INPUT:
								  case HAW_TEXTAREA: {
									  $text = html_entity_decode($form_element->value);

									  for ($ii = 0; $ii < $form_element->br - 1; $ii++)
										  $text .= "\n";
									  $t->setVariable($form_element->label, htmlspecialchars(isUTF8($text) ? utf8_decode($text) : $text, ENT_XML1, 'ISO-8859-1'), true);
									  $t->setVariable($form_label, htmlspecialchars(isUTF8($text) ? utf8_decode($text) : $text, ENT_XML1, 'ISO-8859-1'), true);

									  break;
								  }

								  case HAW_SELECT: {
									  foreach ($form_element->options as $key => $value) {
										  if ($value['value'] == $form_element->value)
											  $text = $value['label'];
									  }
									  
									  $text = html_entity_decode($text);
									  $t->setVariable($form_element->label, htmlspecialchars(isUTF8($text) ? utf8_decode($text) : $text, ENT_XML1, 'ISO-8859-1'), true);
									  $t->setVariable($form_label, htmlspecialchars(isUTF8($text) ? utf8_decode($text) : $text, ENT_XML1, 'ISO-8859-1'), true);

									  break;
								  }

								  case HAW_CHECKBOX: {
									  if (!$form_element->is_checked())
										  break;
									  
									  //$text = html_entity_decode($form_element->label);
									  
									  $nl = 1;
									  if ($form_element->br > 0)
										  $nl = $form_element->br;
									  for ($ii = 0; $ii < $nl; $ii++)
										  $text .= "\n";

									  $t->setVariable($form_element->name, htmlspecialchars(isUTF8($text) ? utf8_decode($text) : $text, ENT_XML1, 'ISO-8859-1'), true);
									  $t->setVariable($form_label, htmlspecialchars(isUTF8($text) ? utf8_decode($text) : $text, ENT_XML1, 'ISO-8859-1'), true);

									  break;
								  }

								  case HAW_PLAINTEXT: {
									  break;
								  }
							  }
							  
							  $i++;
						  }
						  $t->addBlock('form');
						  break;

					  }

					  case HAW_PLAINTEXT: {
						  if ($this->element[$i]->text == camila_get_translation('camila.nodatafound') && $_CAMILA['datagrid_nodata'] == 1) {

							  $rowsperpage = 0;
							  if ($t->blockExists('row1')) {
								  $rowsperpage = 1;
								  while ($t->blockExists('row'.($rowsperpage+1))){
									  $rowsperpage++;
							  }

							  if ($rowsperpage > 0) {

								  for ($ii=0; $ii<$rowsperpage; $ii++) {

									  $t->addBlock('row'.($ii + 1));

								  }
								  $t->addBlock('table');

							  }

						  }

						  }
						  break;
					  }
					  
					  case HAW_LINK: {
						  $link = $this->element[$i];
						  
						  for ($ii = 0; $ii < $link->br; $ii++)
							  $suffix .= "\n";

						  //$this->pdf_text(isUTF8($link->label) ? utf8_decode($link->label).$suffix : $link->label.$suffix);
						  break;
					  }
					  
					  case HAW_TABLE: {
						  $table = $this->element[$i];

						  $cols = array();
						  $rowsperpage = 0;
						  $rownum = 1;
						  $pagnum = 1;
						  $multitable = false;

						  if ($t->blockExists('row1')) {
							  $multitable = true;
							  $rowsperpage = 1;
							  while ($t->blockExists('row'.($rowsperpage+1))){
								  $rowsperpage++;
							  }

						  }

						  if ($_REQUEST['camila_xml2pdf_checklist_options_0'] != 'y')
						  {
							  $row = $table->row[0];

							  for ($b = 0; $b < $row->number_of_columns; $b++) {
								  $column = $row->column[$b];
								  $cols[$b] = strtolower($column->text);
							  }

							  $t->setVariable(camila_get_translation('camila.xml2pdf.table.totalrows'),intval($table->number_of_rows)-1, true);

							  for ($a = 1; $a < $table->number_of_rows; $a++) {

								  $row = $table->row[$a];

								  for ($b = 0; $b < $row->number_of_columns; $b++) {
									  $column = $row->column[$b];

									  if (is_object($column) && $column->get_elementtype() == HAW_PLAINTEXT) {
										  $text = $column->get_text();
									  }

									  if (is_object($column) && $column->get_elementtype() == HAW_LINK) {
										  $text = $column->get_label();
									  }

									  //$t->setVariable($cols[$b], isUTF8($text) ? utf8_decode($text) : $text, true);
									  $t->setVariable($cols[$b], htmlspecialchars(isUTF8($text) ? utf8_decode($text) : $text, ENT_XML1, 'ISO-8859-1'), true);
									  $t->setVariable(camila_get_translation('camila.xml2pdf.table.row.num'),$a,true);

								  }


								  if (!$multitable)
									  $t->addBlock('row');
								  else
									  $t->addBlock('row'.$rownum);

								  $rownum++;

								  if ($rownum>$rowsperpage) {
									  $rownum = 1;
									  $pagnum++;
									  $t->addBlock('table');
								  }
							  }

							  if (!$multitable || ($rownum>1 && $rownum<=$rowsperpage) || ($multitable && $pagnum==1))
								  $t->addBlock('table');
						  }
						  else
						  {

							  if ($rowsperpage > 0) {

								  for ($ii=0; $ii<$rowsperpage; $ii++) {

									  $t->addBlock('row'.($ii + 1));

								  }
								  $t->addBlock('table');

							  }
						  }

								  $a = 1;
								  $row = $table->row[$a];

								  for ($b = 0; $b < $row->number_of_columns; $b++) {
									  $column = $row->column[$b];

									  if (is_object($column) && $column->get_elementtype() == HAW_PLAINTEXT) {
										  $text = $column->get_text();
									  }

									  if (is_object($column) && $column->get_elementtype() == HAW_LINK) {
										  $text = $column->get_label();
									  }

									  $t->setVariable($cols[$b], htmlspecialchars(isUTF8($text) ? utf8_decode($text) : $text, ENT_XML1, 'ISO-8859-1'), true);
									  $t->setVariable(camila_get_translation('camila.xml2pdf.table.row.num'),$a,true);

								  }

						  break;
					  }
				  }
				  $i++;
			  }
			  
			  $t->generateOutputToString($xml);
			  
			  //echo $xml;

			  $obj = new Xml2Pdf($xml);
			  $pdf = $obj->render();
			  $pdf->Output($this->title . '.pdf', 'I');
			}
		  }
      }
	  
	 function filter_filename($filename, $beautify=true) {
			// sanitize filename
			$filename = preg_replace(
				'~
				[<>:"/\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
				[\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
				[\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
				[#\[\]@!$&\'()+,;=]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
				[{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
				~x',
				'-', $filename);
			// avoids ".", ".." or ".hiddenFiles"
			$filename = ltrim($filename, '.-');
			// optional beautification
			if ($beautify) $filename = $this->beautify_filename($filename);
			// maximise filename length to 255 bytes http://serverfault.com/a/9548/44086
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			$filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');
			return $filename;
		}
		
		function beautify_filename($filename) {
			// reduce consecutive characters
			$filename = preg_replace(array(
				// "file   name.zip" becomes "file-name.zip"
				'/ +/',
				// "file___name.zip" becomes "file-name.zip"
				'/_+/',
				// "file---name.zip" becomes "file-name.zip"
				'/-+/'
			), '-', $filename);
			$filename = preg_replace(array(
				// "file--.--.-.--name.zip" becomes "file.name.zip"
				'/-*\.-*/',
				// "file...name..zip" becomes "file.name.zip"
				'/\.{2,}/'
			), '.', $filename);
			// lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
			$filename = mb_strtolower($filename, mb_detect_encoding($filename));
			// ".file-name.-" becomes "file-name"
			$filename = trim($filename, '.-');
			return $filename;
		}
		
		function _find_extension($filename)
        {
            $filename = strtolower($filename) ;
            $exts = explode(".", $filename) ;
            $n = count($exts)-1;
            $exts = $exts[$n];
            return $exts;
        }
  }
?>