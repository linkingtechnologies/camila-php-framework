# API — Records (CRUD)

Base path: `/<app>/cf_api.php/records`

All endpoints require a valid session (cookie) or API key header. Responses are JSON.

---

## Table and column name mapping

Camila table and column names use spaces and mixed case (e.g. `"Volontari Preaccreditati"`, `"Data Inizio"`). The API converts them to **kebab-case lowercase**:

| Camila name | API name |
|---|---|
| `Volontari Preaccreditati` | `volontari-preaccreditati` |
| `Data Inizio` | `data-inizio` |
| `SERVIZI` | `servizi` |

---

## Authorization

### Excluded columns (hidden by default)

The following internal columns are excluded from all API responses and ignored on write:

`created`, `created_by`, `created_by_name`, `created_by_surname`, `created_src`,
`last_upd`, `last_upd_by`, `last_upd_by_name`, `last_upd_by_surname`, `last_upd_src`,
`grp`, `mod_num`, `is_deleted`, `cf_bool_is_selected`, `cf_bool_is_special`

### Control parameters

| Parameter | Effect |
|---|---|
| `?_sys=1` | Include system/audit columns in responses and accept them on write |
| `?_replica=1` | Full replica mode: implies `_sys=1` + skips auto-generation of `id` and audit fields on INSERT |

**`?_sys=1`** — expose system columns for read and write:

```
GET /records/volontari?_sys=1
GET /records/volontari/abc123?_sys=1
```

**`?_replica=1`** — replica mode for `_worktable` tables. On INSERT, Camila normally generates `id` via sequence and sets all audit fields automatically. With `_replica=1` those auto-generated values are skipped: whatever you pass in the body is written as-is.

```
POST /records/volontari?_replica=1
{
  "id": 100042,
  "nome": "Mario",
  "cognome": "Rossi",
  "created":            "2025-01-01T10:00:00",
  "created_by":         "admin",
  "created_by_name":    "Mario",
  "created_by_surname": "Rossi",
  "last_upd":           "2025-06-01T08:30:00",
  "last_upd_by":        "admin",
  "mod_num":            3
}
```

> `_replica=1` has no effect on UPDATE or DELETE — only INSERT behaviour changes.

### Excluded tables

Tables whose name ends in `_camila_users` or `_camila_files` are not accessible.

---

## GET /records/{table}

List records with optional filtering, sorting, pagination, and column selection.

**Query parameters:**

| Parameter | Type | Description |
|---|---|---|
| `filter` | string (repeatable) | AND filter: `column,operator,value` — see operators below |
| `filter1` … `filterN` | string | OR filter group: `column,operator,value` |
| `include` | string | Comma-separated column names to return |
| `exclude` | string | Comma-separated column names to omit |
| `order` | string (repeatable) | `column,asc` or `column,desc` |
| `size` | int | Max records to return (default: all) |
| `page` | int | Page number (1-based); requires `size` |

**Response (200):**

```json
{
  "records": [
    { "id": "abc", "nome": "Mario", "data-inizio": "2025-06-01" }
  ]
}
```

### Filter operators

| Operator | Meaning | Example |
|---|---|---|
| `eq` | equal | `nome,eq,Mario` |
| `neq` | not equal | `stato,neq,chiuso` |
| `lt` | less than | `eta,lt,18` |
| `lte` | less than or equal | `eta,lte,18` |
| `gt` | greater than | `eta,gt,65` |
| `gte` | greater than or equal | `eta,gte,65` |
| `cs` | contains string | `nome,cs,mar` |
| `sw` | starts with | `nome,sw,Mar` |
| `ew` | ends with | `nome,ew,io` |
| `is` | IS NULL / IS NOT NULL | `note,is,null` / `note,is,notnull` |
| `in` | in list | `stato,in,attivo,sospeso` |
| `bt` | between (two values) | `eta,bt,18,65` |

Prefix `n` negates: `ncs` = does not contain, `nbt` = not between, etc.

### AND filters (all must match)

```
GET /records/volontari?filter=stato,eq,attivo&filter=organizzazione,cs,CRI
```

### OR filters (any must match)

OR filters use numbered keys (`filter1`, `filter2`, …):

```
GET /records/volontari?filter1=organizzazione,eq,CRI&filter2=organizzazione,eq,VVF
```

### Sorting

```
GET /records/volontari?order=cognome,asc&order=nome,asc
```

### Pagination

```
GET /records/volontari?size=50&page=2
```

### Column selection

```
GET /records/volontari?include=id,cognome,nome,organizzazione
GET /records/volontari?exclude=note,payload
```

---

## GET /records/{table}/{id}

Read a single record by primary key.

**Response (200):**

```json
{ "id": "abc", "nome": "Mario", "cognome": "Rossi" }
```

**Errors:** `404` if not found.

---

## POST /records/{table}

Create a new record. Body is a JSON object with field values. Returns the new record's id.

```json
{ "nome": "Mario", "cognome": "Rossi", "organizzazione": "Croce Rossa" }
```

**Response (200):** the new `id` (string or integer).

---

## PUT /records/{table}/{id}

Full replace. All writable columns not in the body are set to `null`.

```json
{ "nome": "Mario", "cognome": "Bianchi", "organizzazione": "CRI" }
```

**Response (200):** number of affected rows (always `1`).

---

## PATCH /records/{table}/{id}

Partial update. Only the columns in the body are changed.

```json
{ "organizzazione": "VVF" }
```

**Response (200):** number of affected rows (always `1`).

---

## DELETE /records/{table}/{id}

Delete a record by primary key.

**Response (200):** number of deleted rows (always `1`).

---

## GET /records/{table}/distinct/{column}

Return distinct (unique) values for a single column. Supports the same `filter`, `order`, `size`, `page` query params as the list endpoint.

**Response (200):**

```json
{
  "records": [
    { "organizzazione": "Croce Rossa" },
    { "organizzazione": "Protezione Civile" }
  ]
}
```

---

## Sanitation

Empty strings sent for numeric, date, time, datetime, timestamp, or boolean columns are automatically converted to `null` by the sanitation middleware. This prevents type errors on strict databases.

---

## WorkTableClient — quick reference

```js
const api = WorkTableClient({ baseUrl: '...' });

// List
api.list('volontari', {
  filters: [api.filter('stato', 'eq', 'attivo')],
  order:   [['cognome', 'asc']],
  size:    100,
  page:    1,
  include: ['id', 'cognome', 'nome', 'organizzazione'],
});

// OR filter
api.list('volontari', {
  orFilters: {
    filter1: api.filter('organizzazione', 'eq', 'CRI'),
    filter2: api.filter('organizzazione', 'eq', 'VVF'),
  }
});

// CRUD
api.read('volontari', id);
api.create('volontari', { nome: 'Mario' });
api.update('volontari', id, { servizio: 'Logistica' });   // PUT (full replace)
api.remove('volontari', id);

// Table-bound shorthand
const t = api.table('volontari');
t.list({ size: 200 });
t.create({ nome: 'Mario' });
t.update(id, { servizio: 'A' });
t.remove(id);

// Distinct
api.distinct('volontari', 'organizzazione');

// Filter helper
api.filter('campo', 'eq', 'valore')   // → ['campo', 'eq', 'valore']
api.filter('eta',   'bt', 18, 65)     // → ['eta', 'bt', 18, 65]
```

> **Note:** always use `filters: [api.filter(...)]` — passing a plain object `{ filter: { campo: valore } }` is silently ignored.
