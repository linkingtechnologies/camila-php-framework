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

    public function __construct($sql)
    {
        $this->sql = $sql;
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
        if (!$this->result) {
            throw new Exception("You must call process() before draw()");
        }

		global $_CAMILA;
        $table = new CHAW_table();

        $fields = $this->result->FieldCount();
        $headerRow = new CHAW_row();
        for ($i = 0; $i < $fields; $i++) {
            $field = $this->result->FetchField($i);
            $headerRow->add_column(new Chaw_Text($field->name));
        }
        $table->add_row($headerRow);

        // Data rows
        while (!$this->result->EOF) {
            $row = new CHAW_row();
            $rowData = $this->result->GetRowAssoc(false); // false = lowercase keys
			foreach ($rowData as $value) {
				$cell = new CHAW_text($value);
				$row->add_column($cell);
			}
            $table->add_row($row);
            $this->result->MoveNext(); // go to next row
        }

        $_CAMILA['page']->add_table($table);
    }
}
