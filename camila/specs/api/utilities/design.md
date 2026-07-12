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

## GET /templates

List all template parameters for a given language.

**Query parameter:** `lang` — language code (default: `CAMILA_DEFAULT_LANG`, typically `it`).

**Response (200):**

```json
{
  "lang": "it",
  "templates": [
    { "name": "evento",  "lang": "it", "value": "Alluvione Romagna 2025" },
    { "name": "logo",    "lang": "it", "value": "logo-cri.png" }
  ]
}
```

**WorkTableClient:**

```js
api.call('GET', '/templates').then(res => res.templates);
api.call('GET', '/templates', null, { lang: 'en' });
```

**Source:** `cf_handlers.inc.php` — `'GET /templates'`

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

## PUT /templates/{name}

Update a single template parameter. Requires admin.

**Path parameter:** `name` — the template parameter name.

**Query parameter:** `lang` — language code (default: `CAMILA_DEFAULT_LANG`).

**Request body:**

```json
{ "value": "Alluvione Romagna 2026" }
```

**Response (200):**

```json
{ "status": "ok", "name": "evento", "lang": "it" }
```

**Errors:** `400` if `value` missing, `403` if not admin.

**WorkTableClient:**

```js
api.call('PUT', '/templates/evento', { value: 'Alluvione Romagna 2026' });
api.call('PUT', '/templates/logo',   { value: 'logo-vvf.png' }, { lang: 'en' });
```

**Source:** `cf_handlers.inc.php` — `'PUT /templates/*'`

---

## GET /tables

List all table names visible through the API. Internal Camila system tables are excluded.

**Excluded suffixes:**

`_bookmarkseq`, `_camila_bookmarks`, `_camila_pages`, `_camila_pages_lang`,
`_camila_plugins`, `_camila_template_params`, `_camila_worktables`,
`_camila_worktables_cols`, `_worktablecolseq`, `_worktableseq`

Table names are the **mapped API names** derived from the worktable `short_title` (kebab-case, lowercased, accents removed). The underlying DB table name (e.g. `sc_worktable29`) is never exposed.

**Query parameters:**

| Parameter | Description |
|---|---|
| `metadata=1` | Return objects instead of strings, with worktable registry data (`id`, `short_title`) |
| `count=1` | Requires `metadata=1`. Add a `count` field with the number of records in each table |

**Response (200) — default:**

```json
{ "tables": ["materiali", "volontari"] }
```

**Response (200) — with `?metadata=1`:**

```json
{
  "tables": [
    { "name": "materiali", "id": 29, "short_title": "Materiali" },
    { "name": "volontari", "id": 30, "short_title": "Volontari" }
  ]
}
```

**Response (200) — with `?metadata=1&count=1`:**

```json
{
  "tables": [
    { "name": "materiali", "id": 29, "short_title": "Materiali", "count": 150 },
    { "name": "volontari", "id": 30, "short_title": "Volontari", "count": 42 }
  ]
}
```

Tables without a registry entry appear with only `name` (no `id`, `short_title`, or `count`).

Tables are returned sorted alphabetically.

**WorkTableClient:**

```js
api.tables().then(res => console.log(res.tables));
api.call('GET', '/tables', null, { metadata: '1' });
api.call('GET', '/tables', null, { metadata: '1', count: '1' });
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
