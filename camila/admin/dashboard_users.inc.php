<?php
/* This File is part of Camila PHP Framework
   Copyright (C) 2006-2025 Umberto Bresciani */

$camilaUI = new CamilaUserInterface();

$scheme = $camilaUI->isHttps() ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$config = [
    'baseUrl'           => $scheme . '://' . $host . '/app/' . CAMILA_APP_DIR . '/cf_api.php',
    'apiKeyHeaderName'  => 'Authorization',
    'apiKeyHeaderValue' => 'PHPSESSID',
];

$refrCode  = "<script src='../../camila/js/worktable-client.js'></script>";
$refrCode .= "<script>window.APP_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES) . "</script>";
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $refrCode));

$html = <<<HTML
<div id="app"></div>
<script nomodule>
  document.body.innerHTML = `
    <section class="section"><div class="container">
      <article class="message is-danger">
        <div class="message-header"><p>Browser non supportato</p></div>
        <div class="message-body">
          Questa applicazione richiede un browser moderno.<br>
          Usa <strong>Chrome</strong> o <strong>Edge</strong> aggiornati.
        </div>
      </article>
    </div></section>`;
</script>
HTML;

$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
$_CAMILA['page']->camila_add_js("<script type='module' src='../../camila/admin/app-users.js'></script>");
?>
