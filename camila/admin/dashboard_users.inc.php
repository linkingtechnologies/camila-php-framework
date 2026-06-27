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

$usersI18N = [
    'save'                   => camila_get_translation('camila.save'),
    'cancel'                 => camila_get_translation('camila.cancel'),
    'edit'                   => camila_get_translation('camila.edit'),
    'delete'                 => camila_get_translation('camila.delete'),
    'users.title'            => camila_get_translation('camila.users.title'),
    'users.modal.create'     => camila_get_translation('camila.users.modal.create'),
    'users.modal.edit'       => camila_get_translation('camila.users.modal.edit'),
    'users.modal.reset'      => camila_get_translation('camila.users.modal.reset'),
    'users.new'              => camila_get_translation('camila.users.new'),
    'users.saving'           => camila_get_translation('camila.users.saving'),
    'users.error.load'       => camila_get_translation('camila.users.error.load'),
    'users.error.save'       => camila_get_translation('camila.users.error.save'),
    'users.error.delete'     => camila_get_translation('camila.users.error.delete'),
    'users.empty'            => camila_get_translation('camila.users.empty'),
    'users.search.placeholder' => camila_get_translation('camila.users.search.placeholder'),
    'users.reset.button'     => camila_get_translation('camila.users.reset.button'),
    'users.delete.confirm'   => camila_get_translation('camila.users.delete.confirm'),
    'users.page'             => camila_get_translation('camila.users.page'),
    'users.prev'             => camila_get_translation('camila.users.prev'),
    'users.next'             => camila_get_translation('camila.users.next'),
    'users.level.admin'      => camila_get_translation('camila.users.level.admin'),
    'users.level.default'    => camila_get_translation('camila.users.level.default'),
    'users.field.username'   => camila_get_translation('camila.users.field.username'),
    'users.field.password'   => camila_get_translation('camila.users.field.password'),
    'users.field.name'       => camila_get_translation('camila.users.field.name'),
    'users.field.surname'    => camila_get_translation('camila.users.field.surname'),
    'users.field.group'      => camila_get_translation('camila.users.field.group'),
    'users.field.level'      => camila_get_translation('camila.users.field.level'),
    'users.field.new_password_for' => camila_get_translation('camila.users.field.new_password_for'),
];

$refrCode  = "<script src='../../camila/js/worktable-client.js'></script>";
$refrCode .= "<script>window.APP_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES) . "</script>";
$refrCode .= "<script>window.I18N = " . json_encode($usersI18N, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>";
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
