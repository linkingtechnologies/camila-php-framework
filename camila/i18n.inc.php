<?php
  require_once(CAMILA_LIB_DIR.'m2translator/M2Translator.class.php');
  
  use \ForceUTF8\Encoding;

  function camila_translation_init($force=false) {

      global $_CAMILA;
      if (!is_readable(CAMILA_TMP_DIR.'/'.$_CAMILA['lang'].'.lang.php') || $force) {
          $content = @file_get_contents(CAMILA_DIR.'lang/'.$_CAMILA['lang'].'.lang.php')."\r\n";
          $content .= @file_get_contents(CAMILA_LANG_DIR.$_CAMILA['lang'].'.lang.php');
          $tmpFile = CAMILA_TMP_DIR.'/'.$_CAMILA['lang'].'.lang.php';
          $fh = fopen($tmpFile, 'w');
          if (!$fh) {
              $err = error_get_last();
              die("Can't open TEMP lang file ($tmpFile)! Error: " . print_r($err, true));
          }
          fwrite($fh, $content);
          fclose($fh);
      }

      $_CAMILA['i18n'] = new M2Translator($_CAMILA['lang'], CAMILA_TMP_DIR.'/');

  }

  function camila_get_translation($string)
  {
      global $_CAMILA;

      if (!is_object($_CAMILA['i18n']))
          camila_translation_init();

      if ($_CAMILA['i18n']->get($string) != '*' . $string . '*')
          return $_CAMILA['i18n']->get($string);
      else
          return '';
  }

  function camila_get_translation_array($options_string)
  {
      global $_CAMILA;

      $arr1 = explode(',', camila_get_translation($options_string));

      $tr = Array();
      foreach($arr1 as $name => $value) {
          $arr2 = explode(';', $value);
	  $tr[$arr2[0]] = $arr2[1]; 
      }
      
      return $tr;
  }

  function camila_error_text($msg)
  {
      global $_CAMILA;
      if (is_object($_CAMILA['page'])) {
          $msg=\ForceUTF8\Encoding::toUTF8($msg);
          $text = new CHAW_text($msg, HAW_TEXTFORMAT_BOLD);
		  $text->set_br(0);
          $msg = str_replace("\n", '\n', $msg);

		  $myHtmlCode = '<div class="alert alert-danger notification is-danger" role="alert"><i class="ri-error-warning-line"></i>';
		  $myDiv = new HAW_raw(HAW_HTML, $myHtmlCode);
		  $_CAMILA['page']->add_raw($myDiv);
          $_CAMILA['page']->add_text($text);		  
		  $myHtmlCode = '</div>';
		  $myDiv = new HAW_raw(HAW_HTML, $myHtmlCode);
		  $_CAMILA['page']->add_raw($myDiv);
      } else {
          echo $msg;
      }
  }

  function camila_information_text($msg)
  {
	  global $_CAMILA;
	  if (php_sapi_name() == "cli" || $_CAMILA['cli']) {
			echo $msg."\n";
	  } else if (isset($_CAMILA['cli_args']) && $_CAMILA['cli_args'] != '') {
			$_CAMILA['cli_output'] .= $msg."\n";
	  }else {
      
	  $myHtmlCode = '<div class="alert alert-success notification is-success" role="alert">';
	  $myDiv = new HAW_raw(HAW_HTML, $myHtmlCode);
	  $_CAMILA['page']->add_raw($myDiv);
      $text = new CHAW_text($msg, HAW_TEXTFORMAT_BOLD);
      $_CAMILA['page']->add_text($text);
	  $myHtmlCode = '</div>';
	  $myDiv = new HAW_raw(HAW_HTML, $myHtmlCode);
	  $_CAMILA['page']->add_raw($myDiv);
	}
  }

  function camila_warning_text($msg)
  {
	  global $_CAMILA;
	  if (php_sapi_name() == "cli" || $_CAMILA['cli']) {
			echo $msg."\n";
	  } else if (isset($_CAMILA['cli_args']) && $_CAMILA['cli_args'] != '') {
			$_CAMILA['cli_output'] .= $msg."\n";
	  }else {
      
	  $myHtmlCode = '<div class="notification is-warning" role="alert">';
	  $myDiv = new HAW_raw(HAW_HTML, $myHtmlCode);
	  $_CAMILA['page']->add_raw($myDiv);
      $text = new CHAW_text($msg, HAW_TEXTFORMAT_BOLD);
      $_CAMILA['page']->add_text($text);
	  $myHtmlCode = '</div>';
	  $myDiv = new HAW_raw(HAW_HTML, $myHtmlCode);
	  $_CAMILA['page']->add_raw($myDiv);
	}
  }
  
?>