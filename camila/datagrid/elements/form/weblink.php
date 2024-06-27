<?php

/* This File is part of Camila PHP Framework
   Copyright (C) 2006-2024 Umberto Bresciani

   Camila PHP Framework is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   Camila PHP Framework is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with Camila Framework; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA */




  class form_weblink extends form_textbox
  {

      function validate()
      {
          parent::validate();

		if (!$this->skip_validation) {
          $strlen = strlen($this->value);

          if ($strlen > 0) {
              if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $this->value)) {
                  $this->form->validator->setError($this->field, 912);
              }
          }
		}
      }

      function draw(&$form)
      {
          if ($this->updatable) {
              $myInput = new CHAW_input($this->key, $this->value, $this->title . $this->labelseparator);
			  $myInput->set_br($this->br);
			  $myInput->set_css_class($this->css_class);
              if ($this->maxlength > 0)
                  $myInput->set_maxlength($this->maxlength);
              if ($this->size > 0)
                  $myInput->set_size($this->size);
              $form->add_input($myInput);
          } else {
              $myHidden = new CHAW_hidden($this->key, $this->value);
              $form->add_hidden($myHidden);
              $myText = new CHAW_link($this->title.$this->labelseparator, $this->value);
			  $myText->set_br(0);
              $form->add_text($myText);
          }
		  
		  if ($this->updatable && $this->autosuggest_table != '' && strpos($this->key, 'worktable') !== false) {
			  $code = ( '<script type=\'text/javascript\'>xParent(\''.$this->key.'\',true).removeChild(xNextSib(\''.$this->key.'\',\'br\'))</script>' );
              $js = new CHAW_js($code);
              $form->add_userdefined($js);
			  $autosuggest_callback = 'camila_autosuggest_cb_' . $this->field;
			  $url = $this->autosuggest_script . "?camila_autosuggest&table=" . $this->autosuggest_table . "&field=" . $this->autosuggest_field . "&id=" . $this->autosuggest_idfield . "&infofields=" . urlencode($this->autosuggest_infofields) . "&pickfields=" . urlencode($this->autosuggest_pickfields) . "&maxresults=" . $this->autosuggest_maxresults . "&";
			  $l = '<a tabindex="-1" onclick="camila_autosuggest_open_modal(\''.$url.'\',\''.$autosuggest_callback.'\',\''.$this->key.'\',\''.addslashes($this->title).'\')" class=""><span class="glyphicon glyphicon-search"></span></a>';
              $popup = new CHAW_js($l);
              $form->add_userdefined($popup);
		  }
      }
  }
?>
