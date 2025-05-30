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


class CAMILA_XLS_deck extends CHAW_deck
{
    function create_page() {
		$this->_create_page(true);
	}
	
	
    function _create_page($isExcel)
    {
        
        global $_CAMILA;
        
        if ($_REQUEST['camila_export_action'] == '' || $_REQUEST['camila_export_action'] == 'download' || $_REQUEST['camila_export_action'] == 'sendmail')
            $fname = tempnam(CAMILA_TMP_DIR, 'export.xls');
        else {
            if (!$this->camila_export_file_exists || $_REQUEST['camila_export_overwrite'] == 'y')
                $fname = $this->camila_export_get_dir() . $this->camila_export_filename();
            else
                $fname = tempnam(CAMILA_TMP_DIR, 'export.xls');
        }

        //$workbook->setTempDir(CAMILA_TMP_DIR);
        $spreadsheet = new Spreadsheet();
        $spreadsheet->setActiveSheetIndex(0);
        
		$maxlength = 31;
		$i18nStr = ' - ' . camila_get_translation('camila.worktable.worksheet.data');
        $spreadsheet->getActiveSheet()->setTitle(substr($_CAMILA['page_short_title'],0,$maxlength-strlen($i18nStr)) . ' - ' . camila_get_translation('camila.worktable.worksheet.data'));
        
        $i = 0;
        $m = camila_get_translation('camila.dateformat.monthpos');
        $d = camila_get_translation('camila.dateformat.daypos');
        $y = camila_get_translation('camila.dateformat.yearpos');
        
        //$date_format = $workbook->addFormat();
        //$fmt = str_replace(Array('d', 'm', 'y'), Array('dd', 'mm', 'yyyy'), strtolower($_CAMILA['date_format']));
        //$date_format->setNumFormat($fmt);
        
        $dataFound = false;
        
        while (isset($this->element[$i])) {
            $page_element = $this->element[$i];
            switch ($page_element->get_elementtype()) {
                case HAW_TABLE: {
                    $table = $this->element[$i];
                    
                    $row = $table->row[0];
                    for ($b = 0; $b < $row->number_of_columns; $b++) {
                        $column = $row->column[$b];
                        if (is_object($column) && $column->get_elementtype() == HAW_PLAINTEXT)
                            $text = $column->get_text();
                        if (is_object($column) && $column->get_elementtype() == HAW_LINK)
                            $text = $column->get_label();
                        
                        $spreadsheet->getActiveSheet()->setCellValue([$b + 1, $a + 1], ($text));
                        
                    }
                    
                    if (!$_CAMILA['page']->camila_worktable || ($_CAMILA['page']->camila_worktable && ($_REQUEST['camila_worktable_export'] == 'all' || $_REQUEST['camila_worktable_export'] == 'dataonly'))) {
                        
                        for ($a = 1; $a < $table->number_of_rows; $a++) {
                            $row       = $table->row[$a];
                            $dataFound = true;
                            
                            for ($b = 0; $b < $row->number_of_columns; $b++) {
                                $column = $row->column[$b];
                                
                                if (is_object($column) && $column->get_elementtype() == HAW_LINK) {
                                    $text = $column->get_label();
                                    $url  = $column->get_url();
                                    $spreadsheet->getActiveSheet()->setCellValue([$b + 1, $a + 1], $text);
									//FIX ME
                                    //$spreadsheet->getActiveSheet()->getCellValueByColumnAndRow($b + 1, $a + 1)->getHyperlink()->setUrl($url);
                                } else {
                                    
                                    if (is_object($column) && $column->get_elementtype() == HAW_PLAINTEXT)
                                        $text = $column->get_text();
                                    
									//echo $column->metatype;
                                    switch ($column->metatype) {
                                        
                                        case 'I':
                                        case 'N':
                                            if ($text != '') {
                                                $spreadsheet->getActiveSheet()->setCellValue([$b + 1, $a + 1], intval($text));
                                            }
                                            break;

                                        case 'D':
                                            if ($text != '') {
                                                $date = new DateTime();
                                                $date->setDate(intval(substr($text, $y, 4)), intval(substr($text, $m, 2)), intval(substr($text, $d, 2)));
                                                $date->setTime(0,0,0,0);
												$excelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date);
												//echo $excelDateValue;
												if ($isExcel) {
													$spreadsheet->getActiveSheet()->setCellValue([$b + 1, $a + 1], $excelDateValue);
												} else {
													$spreadsheet->getActiveSheet()->setCellValue([$b + 1, $a + 1], $date);
												}
                                                $spreadsheet->getActiveSheet()->getStyle([$b + 1, $a + 1])->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DDMMYYYY);
                                            }
											break;
										case 'T':
                                            if ($text != '') {
												
                                                $date = new DateTime();
                                                $date->setDate(intval(substr($text, $y, 4)), intval(substr($text, $m, 2)), intval(substr($text, $d, 2)));
                                                if (strlen($text) == 19) {

													$date->setTime(intval(substr($text, 11, 2)),intval(substr($text, 14, 2)),intval(substr($text, 17, 2)),0);
												} else {
													$date->setTime(0,0,0,0);
												}
												$excelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date);
												if ($isExcel) {
													$spreadsheet->getActiveSheet()->setCellValue([$b + 1, $a + 1], $excelDateValue);
												} else {
													$spreadsheet->getActiveSheet()->setCellValue([$b + 1, $a + 1], $date);
												}
                                                $spreadsheet->getActiveSheet()->getStyle([$b + 1, $a + 1])->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DDMMYYYY);
                                            }
                                            break;
                                        
                                        default:
                                            $spreadsheet->getActiveSheet()->setCellValue([$b + 1, $a + 1], ($text));
                                    }
                                }
                            }
                        }
                    }
                    break;
                }
            }
            $i++;
        }
        
        if ($_CAMILA['page']->camila_worktable && ($_REQUEST['camila_worktable_export'] == 'all' || $_REQUEST['camila_worktable_export'] == 'confonly')) {
            //$worksheet =& $workbook->addworksheet($_CAMILA['page_short_title'] . ' - ' . camila_get_translation('camila.worktable.worksheet.conf'));

            //$aLeft = $workbook->addformat();
            //$aLeft->setAlign('left');
			
			$maxlength = 31;
			$i18nStr = ' - ' . camila_get_translation('camila.worktable.worksheet.conf');					
			$myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, substr($_CAMILA['page_short_title'],0,$maxlength-strlen($i18nStr)) . ' - ' . camila_get_translation('camila.worktable.worksheet.conf'));
			$spreadsheet->addSheet($myWorkSheet);
			$spreadsheet->setActiveSheetIndex(1);

            $opt   = Array();
            $opt[] = camila_get_translation('camila.worktable.field.sequence');
            $opt[] = camila_get_translation('camila.worktable.field.name.abbrev');
            $opt[] = camila_get_translation('camila.worktable.field.type');
            $opt[] = camila_get_translation('camila.worktable.field.listofvalues');
            $opt[] = camila_get_translation('camila.worktable.field.maxlength');
            $opt[] = camila_get_translation('camila.worktable.field.required');
            $opt[] = camila_get_translation('camila.worktable.field.defaultval');
            $opt[] = camila_get_translation('camila.worktable.field.readonly');
            $opt[] = camila_get_translation('camila.worktable.field.visible');
            $opt[] = camila_get_translation('camila.worktable.field.force');
            $opt[] = camila_get_translation('camila.worktable.field.unique');
            $opt[] = camila_get_translation('camila.worktable.field.options');
            $opt[] = camila_get_translation('camila.worktable.field.autosuggestwtname');
            $opt[] = camila_get_translation('camila.worktable.field.autosuggestwtcolname');
            $opt[] = camila_get_translation('camila.worktable.field.help');
            //$opt[] = '';
            $opt[] = camila_get_translation('camila.worktable.configuration');
            $opt[] = camila_get_translation('camila.worktable.name');
            $opt[] = camila_get_translation('camila.worktable.desc');
            $opt[] = camila_get_translation('camila.worktable.order.by');
            $opt[] = camila_get_translation('camila.worktable.order.dir');
            $opt[] = camila_get_translation('camila.worktable.canupdate');
            $opt[] = camila_get_translation('camila.worktable.caninsert');
            $opt[] = camila_get_translation('camila.worktable.candelete');
            $opt[] = camila_get_translation('camila.worktable.category');
            
            foreach ($opt as $key => $value) {
                $text = $opt[$key];
                $spreadsheet->getActiveSheet()->setCellValue([1, intval($key) + 2], ($text));
            }
            
            //$worksheet->setColumn(0, 0, 30);
            $id = substr($_SERVER['PHP_SELF'], 12, -4);

            $result = $_CAMILA['db']->Execute('select * from ' . CAMILA_TABLE_WORKC . ' where (wt_id=' . $_CAMILA['db']->qstr($id) . ' and is_deleted<>' . $_CAMILA['db']->qstr('y') . ') order by sequence');
            if ($result === false)
                camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg());

            $yesNoArr     = camila_get_translation_array('camila.worktable.options.noyes');
            $fieldTypeArr = camila_get_translation_array('camila.worktable.options.fieldtype');
            $forceArr     = camila_get_translation_array('camila.worktable.options.force');
            $orderDirArr  = camila_get_translation_array('camila.worktable.options.order.dir');
            $colArray     = Array();

            $count = 1;
            while (!$result->EOF) {
                $colArray[$result->fields['col_name']] = $result->fields['name'];
                $text = $result->fields['name'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 1], ($text));
                if ($_REQUEST['camila_worktable_export'] == 'all' && !$dataFound)
					$spreadsheet->getSheet(0)->setCellValue([$count, 1], $text);
				
                $text = $result->fields['sequence'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 2], intval($text));
                $text = $result->fields['name_abbrev'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 3], ($text));
                $text = $fieldTypeArr[$result->fields['type']];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 4], ($text));
                $text = $result->fields['listbox_options'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 5], ($text));
                $text = $result->fields['maxlength'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 6], intval(($text)));				
				$text = $yesNoArr[$result->fields['required']];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 7], $text);
                $text = $result->fields['default_value'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 8], ($text));
                $text = $text = $yesNoArr[$result->fields['readonly']];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 9], ($text));
                $text = $text = $yesNoArr[$result->fields['visible']];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 10], ($text));
                $text = $forceArr[$result->fields['force_case']];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 11], ($text));
                $text = $yesNoArr[$result->fields['must_be_unique']];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 12], ($text));
                $text = $result->fields['field_options'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 13], ($text));
                $text = $result->fields['autosuggest_wt_name'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 14], ($text));
                $text = $result->fields['autosuggest_wt_colname'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 15], ($text));
                $text = $result->fields['help'];
                $spreadsheet->getActiveSheet()->setCellValue([$count+1, 16], ($text));
                
                $count++;
                $result->MoveNext();
            }
            //$worksheet->setColumn(1, $count /*-1*/ , 15);
            
            $result = $_CAMILA['db']->Execute('select * from ' . CAMILA_TABLE_WORKT . ' where id=' . $_CAMILA['db']->qstr($id));
            if ($result === false)
                camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg());
            
            $text = $result->fields['short_title'];
            $spreadsheet->getActiveSheet()->setCellValue([2, 18], ($text));
            $text = $result->fields['full_title'];
            $spreadsheet->getActiveSheet()->setCellValue([2, 19], ($text));
            $text = $colArray[$result->fields['order_field']];
            $spreadsheet->getActiveSheet()->setCellValue([2, 20], ($text));
            $text = $orderDirArr[$result->fields['order_dir']];
            $spreadsheet->getActiveSheet()->setCellValue([2, 21], ($text));
            $text = $yesNoArr[$result->fields['canupdate']];
            $spreadsheet->getActiveSheet()->setCellValue([2, 22], ($text));
            $text = $yesNoArr[$result->fields['caninsert']];
            $spreadsheet->getActiveSheet()->setCellValue([2, 23], ($text));
            $text = $yesNoArr[$result->fields['candelete']];
            $spreadsheet->getActiveSheet()->setCellValue([2, 24], ($text));
            $text = $result->fields['category'];
            $spreadsheet->getActiveSheet()->setCellValue([2, 25], ($text));
            
            $text = camila_get_translation('camila.worktable.bookmarks');
            $spreadsheet->getActiveSheet()->setCellValue([3, 17], ($text));
        
            $query = 'select base_url,url,title from ' . CAMILA_APPLICATION_PREFIX . 'camila_bookmarks where base_url=' . $_CAMILA['db']->qstr('cf_worktable' . $id . '.php') . ' order by sequence';
            
            $result = $_CAMILA['db']->Execute($query);
            if ($result === false)
                camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg());
            
            $i = 0;
            while (!$result->EOF) {
                $i++;
                
                $text = $result->fields['title'];
                $spreadsheet->getActiveSheet()->setCellValue([3, 17 + $i], ($text));

                $url  = parse_url($result->fields['url'], PHP_URL_QUERY);
                $qArr = $this->parse_query_string($url);
                
                $text = $qArr['filter'];
                $spreadsheet->getActiveSheet()->setCellValue([4, 17 + $i], ($text));

				if (str_starts_with($result->fields['url'], 'index.php')) {
					$url = $result->fields['url'];
					$spreadsheet->getActiveSheet()->setCellValue([4, 17 + $i], ($url));
				}

                $result->MoveNext();
            }
            
            
        }
        
        if ($_CAMILA['page']->camila_worktable && !$dataFound && $_REQUEST['camila_worktable_export'] == 'dataonly') {
            $id = substr($_SERVER['PHP_SELF'], 12, -4);
            
            $result = $_CAMILA['db']->Execute('select * from ' . CAMILA_TABLE_WORKC . ' where (wt_id=' . $_CAMILA['db']->qstr($id) . ' and is_deleted<>' . $_CAMILA['db']->qstr('y') . ') order by sequence');
            if ($result === false)
                camila_error_page(camila_get_translation('camila.sqlerror') . ' ' . $_CAMILA['db']->ErrorMsg());
            
            $count = 1;
            while (!$result->EOF) {
                $text = $result->fields['name'];
                //$dWorksheet->writeString(0, $count-1, ($text));
                $spreadsheet->getActiveSheet()->setCellValue([0, $count - 1], ($text));
                $count++;
                $result->MoveNext();
            }
            
        }
		
		$spreadsheet->setActiveSheetIndex(0);

		$sheet = $spreadsheet->getActiveSheet();
		$cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
		$cellIterator->setIterateOnlyExistingCells(true);
		foreach ($cellIterator as $cell) {
			$sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
		}

        //$workbook->close();
        
        if ($_REQUEST['camila_export_action'] == '' || $_REQUEST['camila_export_action'] == 'download') {
            //        header("Content-Type: application/x-msexcel; name=\"".$this->camila_export_safe_filename() . '.' . $this->camila_export_get_ext()."\"");
            //        header("Content-Disposition: attachment; filename=\"".$this->camila_export_safe_filename() . '.' . $this->camila_export_get_ext()."\"");
            // Redirect output to a client’s web browser (Xls)
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $this->camila_export_safe_filename() . '.' . $this->camila_export_get_ext() . '"');
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0
			if ($isExcel) {
				$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
			} else {
				$writer = IOFactory::createWriter($spreadsheet, 'Ods');
			}
            $writer->save('php://output');            
        }
        
        //$fh = fopen($fname, "rb");
        
        if ($_REQUEST['camila_export_action'] == '' || $_REQUEST['camila_export_action'] == 'download') {
            //fpassthru($fh);
            //unlink($fname);
        }
        
        
        if ($_REQUEST['camila_export_action'] == 'sendmail') {
            
            /*global $_CAMILA;
            
            require_once(CAMILA_LIB_DIR . 'phpmailer/class.phpmailer.php');
            $mail = new PHPMailer();
            
            if (CAMILA_MAIL_IS_SMTP)
                $mail->IsSMTP();
            $mail->Host     = CAMILA_MAIL_HOST;
            $mail->SMTPAuth = CAMILA_MAIL_SMTP_AUTH;
            
            $mail->From     = CAMILA_WORKTABLE_CONFIRM_VIA_MAIL_FROM;
            $mail->FromName = CAMILA_WORKTABLE_CONFIRM_VIA_MAIL_FROM_NAME;
            
            $mail->AddAttachment($fname, 'file.xls');
            
            $mail->AddAddress(CAMILA_WORKTABLE_CONFIRM_VIA_MAIL_TO);
                        
            $mail->IsHTML(false);
            
            $mail->Subject = CAMILA_WORKTABLE_CONFIRM_VIA_MAIL_SUBJECT;
            
            $text          = camila_get_translation('camila.worktable.confirm') . " - " . camila_get_translation('camila.login.username') . ': ' . $_CAMILA['user_name'];
            $mail->Body    = $text;
            $mail->AltBody = $text;
       
            $mail->Send();
            unlink($fname);*/
   
        }        
        
    }
    
    
    function parse_query_string($str)
    {
        $op    = array();
        $pairs = explode("&", $str);
        foreach ($pairs as $pair) {
            list($k, $v) = array_map("urldecode", explode("=", $pair));
            $op[$k] = $v;
        }
        return $op;
    }
    
}
;

?>