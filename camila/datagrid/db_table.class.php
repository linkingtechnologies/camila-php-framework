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


class dbtable
{
    private $sql;
    private $conn;
    private $result;

    public function __construct($sql, $title = '', $worktableId = '')
    {
        $this->sql = $sql;
		$this->title = $title;
		$this->worktableId = $worktableId;
    }

    public function process()
    {
		global $_CAMILA;
        $this->result = $_CAMILA['db']->Execute($this->sql);
        if (!$this->result) {
            throw new Exception("Query error: " . $this->conn->ErrorMsg());
        }
    }

    public function draw()
    {
		global $_CAMILA;
		
        if (!$this->result) {
            throw new Exception("You must call process() before draw()");
        }
		
		if ($this->title != '') {
            $text = new CHAW_text($this->title, HAW_TEXTFORMAT_BIG);
            $text->set_br(2);
            $_CAMILA['page']->add_text($text);
        }

        $table = new CHAW_table();

        $fields = $this->result->FieldCount();
        $headerRow = new CHAW_row();
		if (isset ($this->worktableId) && $this->worktableId != '') {
			$headerRow->add_column(new Chaw_Text(''));
		}
        for ($i = 0; $i < $fields; $i++) {
            $field = $this->result->FetchField($i);
            $headerRow->add_column(new Chaw_Text($field->name));
        }
        $table->add_row($headerRow);

        // Data rows
		$count = 0;
        while (!$this->result->EOF) {
            $row = new CHAW_row();
            $rowData = $this->result->GetRowAssoc(false);
			foreach ($rowData as $key => $value) {
				if ($key == 'id' && $this->worktableId != '') {
					$arr=[];
					$arr['camilakey_id'] = $value;
					$reqs = 'camila_delete=' . urlencode(serialize($arr)) . '&camila_token=' . camila_token(serialize($arr));
					$cell = new CHAW_link('X', 'cf_worktable'.$this->worktableId.'.php?'.$reqs);
					$row->add_column($cell);
					
					$reqs = 'camila_update=' . urlencode(serialize($arr)) . '&camila_token=' . camila_token(serialize($arr));
					$cell = new CHAW_link($value, 'cf_worktable'.$this->worktableId.'.php?'.$reqs);
					$row->add_column($cell);
				} else {
					$cell = new CHAW_text($value);
					$row->add_column($cell);
				}
			}
            $table->add_row($row);
            $this->result->MoveNext();
			$count++;
        }
		
		if ($count>0) {
			$_CAMILA['page']->add_table($table);
		} else {
			$text = new CHAW_text(camila_get_translation('camila.nodatafound'));
			$_CAMILA['page']->add_text($text);
		}
    }
}
