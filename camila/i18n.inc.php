<?php

  require_once(CAMILA_LIB_DIR.'m2translator/M2Translator.class.php');
  
  use \ForceUTF8\Encoding;

  /**
   * The lang files of the plugins, for the keys a plugin is allowed to add to the shared dictionary:
   * those that start with "menu." (the <lic_title> of its conf/menu.xml tabs, e.g. menu.worktable.help).
   * The rest of a plugin's lang file is its own business (the plugin reads it itself) and is not merged,
   * so a plugin can never override a key of the core or of the application.
   *
   * @return string[] paths of the plugins' lang files for the current language
   */
  function camila_plugin_lang_files() {
      global $_CAMILA;
      $home = defined('CAMILA_HOMEDIR') ? CAMILA_HOMEDIR : (defined('CAMILA_APP_PATH') ? CAMILA_APP_PATH : '');
      if ($home == '')
          return Array();
      $files = glob($home.'/plugins/*/lang/'.$_CAMILA['lang'].'.lang.php');
      return is_array($files) ? $files : Array();
  }

  function camila_translation_init($force=false) {

      global $_CAMILA;
      $tmpFile = CAMILA_TMP_DIR.'/'.$_CAMILA['lang'].'.lang.php';

      // The cached dictionary is rebuilt when a plugin's lang file is newer than it: a plugin that is
      // installed or updated does not have to clear var/tmp by hand.
      $stale = false;
      if (is_readable($tmpFile)) {
          $cachedAt = @filemtime($tmpFile);
          foreach (camila_plugin_lang_files() as $pf) {
              if (@filemtime($pf) > $cachedAt) {
                  $stale = true;
                  break;
              }
          }
      }

      if (!is_readable($tmpFile) || $force || $stale) {
          $content = @file_get_contents(CAMILA_DIR.'lang/'.$_CAMILA['lang'].'.lang.php')."\r\n";
          $content .= @file_get_contents(CAMILA_LANG_DIR.$_CAMILA['lang'].'.lang.php');
          foreach (camila_plugin_lang_files() as $pf) {
              foreach (preg_split('/\R/', (string)@file_get_contents($pf)) as $line) {
                  if (strpos(ltrim($line, "\xEF\xBB\xBF \t"), 'menu.') === 0)
                      $content .= "\r\n".trim($line, "\xEF\xBB\xBF");
              }
          }
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
