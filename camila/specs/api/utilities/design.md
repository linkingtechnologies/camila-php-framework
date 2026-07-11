# API — Utilities

Miscellaneous endpoints for app status, template parameters, table enumeration, and CLI execution.

Base path: `/<app>/cf_api.php`

---

## GET /status/app

Returns the application name and current server timestamp. No auth required.

**Response (200):**

```json
{
  "status": "ok",
  "app":    "segreteriacampo",
  "time":   "2025-06-01T10:30:00+02:00"
}
```

`app` is `null` if `CAMILA_APPLICATION_NAME` is not defined.

**Source:** `cf_handlers.inc.php` — `'GET /status/app'`

---

## GET /templates/{name}

Read a single template parameter by name. Template parameters are key/value pairs stored in the app database and editable from the admin panel (e.g. event name, logo path, event dates).

**Path parameter:** `name` — the template parameter name (case-sensitive).

**Query parameter:** `lang` — language code (default: `CAMILA_DEFAULT_LANG`, typically `it`).

**Response (200):**

```json
{
  "name":  "evento",
  "lang":  "it",
  "value": "Alluvione Romagna 2025"
}
```

**Response when not found (200 with error field):**

```json
{ "error": "not found", "name": "unknown-param" }
```

**WorkTableClient:**

```js
api.call('GET', '/templates/evento').then(res => console.log(res.value));
api.call('GET', '/templates/logo',   null, { lang: 'en' });
```

**Source:** `cf_handlers.inc.php` — `'GET /templates/*'`

---

## GET /tables

List all table names visible through the API. Internal Camila system tables are excluded.

**Excluded suffixes:**

`_bookmarkseq`, `_camila_bookmarks`, `_camila_pages`, `_camila_pages_lang`,
`_camila_plugins`, `_camila_template_params`, `_camila_worktables`,
`_camila_worktables_cols`, `_worktablecolseq`, `_worktableseq`

**Response (200):**

```json
{
  "tables": [
    "materiali",
    "mezzi",
    "servizi",
    "volontari"
  ]
}
```

Tables are returned sorted alphabetically.

**WorkTableClient:**

```js
api.tables().then(res => console.log(res.tables));
```

**Source:** `CamilaWorktableController` in `api.include.php`

---

## POST /cli

Execute an application CLI command server-side. The command is dispatched to `CamilaAppCli::run()`.

**Request body:**

```json
{ "command": "import-volunteers --file=data.csv" }
```

**Response (200):**

```json
{ "output": "Imported 42 records.\n" }
```

The `output` field contains whatever the CLI command wrote to `$_CAMILA['cli_output']`.

**WorkTableClient:**

```js
api.call('POST', '/cli', { command: 'sync-preaccreditati' })
   .then(res => console.log(res.output));
```

**Source:** `CamilaCliController` in `api.include.php`

---

## Plugin endpoints

Each active plugin can register additional endpoints under its own prefix. The plugin's handler file is `plugins/{plugin-id}/api/handlers.inc.php` and all its routes are mounted under `/{plugin-id}/`.

Example (segreteria-campo plugin):

```js
api.call('GET',  '/segreteria-campo/status');
api.call('GET',  '/segreteria-campo/totem/organization-codes');
api.call('PUT',  '/segreteria-campo/radio/messages', { messagesPayload: [...] });
```

See the individual plugin's `specs/api/` directory for its endpoint documentation.
