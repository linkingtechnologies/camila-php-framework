# API — Schema & Metadata

Endpoints for introspecting table structure, permissions, and sequences.

Base path: `/<app>/cf_api.php`

---

## GET /columns

List all accessible tables with their column definitions.

**Response (200):**

```json
{
  "tables": [
    {
      "name": "volontari",
      "type": "table",
      "columns": [
        { "name": "id",          "type": "integer", "pk": true,  "nullable": false },
        { "name": "nome",        "type": "varchar", "pk": false, "nullable": true  },
        { "name": "cognome",     "type": "varchar", "pk": false, "nullable": true  },
        { "name": "data-inizio", "type": "date",    "pk": false, "nullable": true  }
      ]
    }
  ]
}
```

---

## GET /columns/{table}

Column definitions for a single table.

**Response (200):**

```json
{
  "name": "volontari",
  "type": "table",
  "columns": [
    { "name": "id",     "type": "integer", "pk": true,  "nullable": false },
    { "name": "nome",   "type": "varchar", "pk": false, "nullable": true  },
    { "name": "cognome","type": "varchar", "pk": false, "nullable": true  }
  ]
}
```

**WorkTableClient:**

```js
api.describe('volontari').then(schema => console.log(schema.columns));

// table-bound:
api.table('volontari').describe();
```

---

## GET /permissions/{table}

Returns the CRUD permissions the current user has on a table.

**Response (200):**

```json
{
  "volontari": {
    "select": true,
    "insert": true,
    "update": true,
    "delete": false
  }
}
```

**WorkTableClient:**

```js
api.permissions('volontari').then(p => console.log(p));

// table-bound:
api.table('volontari').permissions();
```

---

## GET /sequence/{table}

Returns the next available sequence ID for the table. Used when you need to pre-generate an ID before creating the record (e.g. for file upload paired with record creation).

**Response (200):**

```json
{ "nextId": 100042 }
```

**WorkTableClient:**

```js
api.sequence('volontari').then(s => console.log(s.nextId));

// table-bound:
api.table('volontari').sequence();
```

---

## GET /openapi

Returns the OpenAPI 3.0 specification for all accessible endpoints and tables. Useful for auto-generating clients or exploring the API schema.

**Response (200):** OpenAPI 3.0 JSON document.

---

## Notes

- Column names in responses use the same **kebab-case** mapping as the records API (see [records design](../records/design.md#table-and-column-name-mapping)).
- The columns listed here reflect only the columns **not** excluded by the authorization middleware. Internal columns (`created`, `last_upd`, `grp`, `mod_num`, `is_deleted`, etc.) are never exposed.
