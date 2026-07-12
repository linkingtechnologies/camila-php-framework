# API — Worktable Import

Import endpoint for worktables.

Base path: `/<app>/cf_api.php`

---

## POST /tables/{name}/import

Import all data rows from a spreadsheet into the worktable identified by `{name}` (the mapped API name, as returned by `GET /tables`).

Column matching is done by name (case-insensitive, UTF-8). Columns present in the spreadsheet header but absent from the worktable definition are ignored. Worktable columns absent from the spreadsheet get their `default_value` if one is configured.

**Path parameter:** `name` — mapped API name of the worktable (e.g. `materiali`), as returned by `GET /tables`.

**Query parameter:** `sheet` — zero-based sheet index (default: `0`).

**Auth:** any authenticated user (session cookie or `X-API-Key`).

---

### File upload (multipart)

Send the file as a `multipart/form-data` POST with field name `file`.

```
POST /tables/materiali/import
Content-Type: multipart/form-data; boundary=...

--boundary
Content-Disposition: form-data; name="file"; filename="materiali.xlsx"
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet

<binary xlsx content>
--boundary--
```

**WorkTableClient:**

```js
const file = document.querySelector('input[type=file]').files[0];
api.importTableUpload('materiali', file)
   .then(res => console.log(res.imported));  // { status: "ok", imported: 42, ... }

// With explicit sheet index:
api.importTableUpload('materiali', file, 1);
```

---

### Predefined file (JSON body)

To import from a file already on the server (e.g. a plugin example), send a JSON body with `filepath` — a path relative to `CAMILA_APP_PATH`.

```
POST /tables/materiali/import
Content-Type: application/json

{
  "filepath": "/plugins/segreteria-campo/examples/it/DB MATERIALI_Dati esempio - Database materiali.xlsx"
}
```

**WorkTableClient:**

```js
api.importTable('materiali', '/plugins/segreteria-campo/examples/it/DB MATERIALI_Dati esempio.xlsx');

// With explicit sheet index:
api.importTable('materiali', filepath, 1);
```

---

### Response (200)

```json
{
  "status":   "ok",
  "imported": 42,
  "failed":   0,
  "total":    42
}
```

| Field | Description |
|---|---|
| `imported` | Rows successfully inserted |
| `failed` | Rows that failed to insert (DB error) |
| `total` | Total data rows in the sheet (excluding header) |

**Errors:**

| Code | Condition |
|---|---|
| `400` | No file provided, unsupported format (not `.xlsx`/`.xls`) |
| `404` | Worktable not found, or `filepath` file does not exist |
| `500` | Failed to load column definitions |

---

## Import behaviour

- Row 1 is treated as the header row; data starts at row 2.
- Empty rows (all mapped columns empty) are skipped.
- Each inserted record gets: `id` (sequence), `uuid` (if UUID mode active), full audit fields (`created`, `last_upd`, etc.) with `created_src = 'import'`.
- Type conversions applied: `date`, `datetime`, `number` (raw), `hyperlink`.
- `force_case` (upper/lower) applied per column.
- Inserts are committed in batches of 200 rows.

**Source:** `camila/api/cf_handlers.inc.php` — `'POST /tables/*/import'` → delegates to `configurator::importXlsData()` in `camila/datagrid/configurator.class.php`
