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


class report_datetime extends report_field {

  var $format;
  var $timeformat = 'H:i:s';

  function datetime($field, $title)
  {
    parent::__construct($field, $title);
    $this->type = 'datetime';
    $this->inline = true;
  }

  function draw(&$row, &$fields, $readOnly = false)
  {
    if( isset($this->format) ) {
      $this->value = date($this->format, strtotime($this->value));
    }

    parent::draw($row, $fields, $readOnly);
  }

}
?>
