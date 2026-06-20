# Camila Framework — Report XML Guide

Report files are XML documents that define the queries and visual elements rendered
by `CamilaReport`. They live in:

```
app/<plugin>/reports/<lang>/
```

The same XML config drives all supported output formats: **HTML** (browser view),
**PDF**, **Word (.docx)** and **ODT**.

---

## Minimal structure

```xml
<?xml version='1.0' standalone='yes' ?>
<reports>
  <report>
    <id>1</id>
    <query>SELECT category, COUNT(*) AS total FROM volunteers GROUP BY category</query>
    <graphs>
      <graph>
        <id>1</id>
        <type>table</type>
        <title>Volunteers by category</title>
      </graph>
    </graphs>
  </report>
</reports>
```

A single report file can contain multiple `<report>` blocks. Each block is rendered
as a separate section in the output document.

---

## `<report>` — report block

| Element              | Required | Notes |
|----------------------|----------|-------|
| `<id>`               | Yes      | Unique identifier within the file. Any string (`1`, `r2`, `c04`, …). |
| `<query>`            | Yes*     | SQL query. Use `${TABLE.COLUMN}` Worktable placeholders resolved by the framework. |
| `<graphs>`           | Yes      | Contains one or more `<graph>` elements. |
| `<pageBreakBefore>`  | No       | `true` inserts a page break before this report block in all output formats. |

> `*` `<query>` can be left empty (`<query/>`) when driver-specific variants are
> provided (see [Driver-specific queries](#driver-specific-queries)).

---

## Driver-specific queries

When MySQL and SQLite require different syntax (date formatting, string functions…),
provide both variants. The engine picks the first one that matches the active driver
and ignores the other. A generic `<query>` can coexist as a fallback.

```xml
<report>
  <id>3</id>
  <query/>   <!-- empty fallback, overridden below -->
  <mysqlQuery>
    SELECT DATE_FORMAT(date_col, '%e/%c') AS dt, description FROM log ORDER BY dt
  </mysqlQuery>
  <sqliteQuery>
    SELECT strftime('%d/%m', date_col) AS dt, description FROM log ORDER BY dt
  </sqliteQuery>
  <graphs>…</graphs>
</report>
```

Selection order: `mysqlQuery` → `sqliteQuery` → `query`.

---

## `<graph>` — visual element

Each `<graph>` inside `<graphs>` defines one visual element (chart or table).
Multiple graphs in the same report are displayed side-by-side.

| Element   | Required | Notes |
|-----------|----------|-------|
| `<id>`    | Yes      | Unique within the report (e.g. `1`, `2`). |
| `<type>`  | Yes      | `pie` · `bar` · `table` · `text` |
| `<title>` | Yes      | Heading shown above the element. |

---

## Graph types

### `pie` and `bar` — charts

Renders a pie or bar chart image from a **two-column query** (label, value).

```xml
<graph>
  <id>1</id>
  <type>pie</type>
  <title>Volunteers by province</title>
  <width>500</width>    <!-- image width in pixels -->
  <height>400</height>  <!-- image height in pixels -->
</graph>
```

```xml
<graph>
  <id>1</id>
  <type>bar</type>
  <title>Registered vehicles by province</title>
  <width>500</width>
  <height>200</height>
</graph>
```

The query must return exactly two columns: the first is used as the label,
the second as the numeric value.

```sql
SELECT province, COUNT(*) AS total FROM volunteers GROUP BY province
```

> `<filename>` is accepted in the XML for compatibility but is ignored —
> the engine computes the image path internally.

---

### `table` — data table

Renders the full query result as a formatted table.

```xml
<graph>
  <id>2</id>
  <type>table</type>
  <title>Accredited volunteers</title>
  <sum>1</sum>
  <hideFirstColumn>true</hideFirstColumn>
  <columnWidths>10,30,30,30</columnWidths>
</graph>
```

| Element             | Default | Notes |
|---------------------|---------|-------|
| `<sum>`             | `0`     | `1` appends a totals row summing all numeric columns. |
| `<hideFirstColumn>` | `false` | `true` hides column 0 from header and rows. Useful when the first column is a sort key (e.g. a raw timestamp) that should not be shown. |
| `<columnWidths>`    | equal   | Comma-separated percentages for Word/ODT output. Must match the number of **visible** columns. Ignored in HTML/PDF. |

#### Combining a chart and a table

Place both graphs in the same `<report>` and they will appear side-by-side:

```xml
<graphs>
  <graph>
    <id>1</id>
    <type>pie</type>
    <title>By province</title>
    <width>500</width>
    <height>400</height>
  </graph>
  <graph>
    <id>2</id>
    <type>table</type>
    <title>By province</title>
    <columnWidths>50,50</columnWidths>
    <sum>1</sum>
  </graph>
</graphs>
```

---

### `text` — free HTML block

Renders a raw HTML template. Column values from the query replace `${0}`, `${1}`, …
(0-based column index). Only the first result row is used.

```xml
<graph>
  <id>1</id>
  <type>text</type>
  <title>Summary</title>
  <html><![CDATA[
    <p>Total volunteers: <strong>${1}</strong></p>
  ]]></html>
</graph>
```

---

## Optional extensions

### `<style>` — custom table CSS

Applies inline CSS to the HTML `<table>` element (HTML and PDF output only).

```xml
<style>width:100%;font-size:9pt</style>
```

### `<barcodeColumn>` — 1-D barcode in a column

Renders a 1-D barcode (via mPDF) in the specified column of a `table` graph.
Works in HTML and PDF output only.

```xml
<barcodeColumn>2</barcodeColumn>       <!-- 0-based, counts all columns including hidden ones -->
<barcodeType>code39extend</barcodeType>
<barcodeSize>0.3</barcodeSize>
<barcodeHeight>8</barcodeHeight>
```

### `<qrcodeColumn>` — QR code in a column

Renders a QR code image in the specified column of a `table` graph.
Works in all output formats (HTML, PDF, Word, ODT).

```xml
<qrcodeColumn>2</qrcodeColumn>   <!-- 0-based, counts all columns including hidden ones -->
<qrcodeSize>80</qrcodeSize>      <!-- output image size in pixels (default: 80) -->
<qrcodeLevel>M</qrcodeLevel>     <!-- error correction: L | M | Q | H (default: M) -->
<qrcodePadding>4</qrcodePadding> <!-- cell padding in pixels (default: 4) -->
```

---

## Complete example

The report below mirrors `02_Finale.xml` from the segreteria-campo plugin.
It demonstrates driver-specific queries, a hidden sort column, column widths,
and a combined pie+table layout.

```xml
<?xml version='1.0' standalone='yes' ?>
<reports>

  <!-- Report 1: organisations by province — pie chart + table side by side -->
  <report>
    <id>1</id>
    <query>
      SELECT ${VOLUNTEERS.PROVINCE} AS 'PROVINCE',
             COUNT(DISTINCT ${volunteers.ORGANISATION}) AS 'NUM. ORGANISATIONS'
      FROM ${VOLUNTEERS}
      GROUP BY ${volunteers.PROVINCE}
      ORDER BY ${volunteers.PROVINCE}
    </query>
    <graphs>
      <graph>
        <id>1</id>
        <type>table</type>
        <title>Accredited organisations by province</title>
        <columnWidths>50,50</columnWidths>
        <sum>1</sum>
      </graph>
      <graph>
        <id>2</id>
        <type>pie</type>
        <title>Accredited organisations</title>
        <width>500</width>
        <height>400</height>
      </graph>
    </graphs>
  </report>

  <!-- Report 2: chronological log — starts on a new page, hidden sort column, driver-specific date format -->
  <report>
    <id>2</id>
    <pageBreakBefore>true</pageBreakBefore>
    <query/>
    <mysqlQuery>
      SELECT ${LOG.timestamp} AS TS,
             DATE_FORMAT(${LOG.timestamp}, '%e/%c %H:%i') AS 'Date/time',
             ${LOG.description} AS 'Description'
      FROM ${LOG}
      ORDER BY TS
    </mysqlQuery>
    <sqliteQuery>
      SELECT ${LOG.timestamp} AS TS,
             CAST(strftime('%d', ${LOG.timestamp}) AS INTEGER) || '/' ||
             CAST(strftime('%m', ${LOG.timestamp}) AS INTEGER) || ' ' ||
             strftime('%H:%M', ${LOG.timestamp}) AS 'Date/time',
             ${LOG.description} AS 'Description'
      FROM ${LOG}
      ORDER BY TS
    </sqliteQuery>
    <graphs>
      <graph>
        <id>1</id>
        <type>table</type>
        <title>Recorded activities</title>
        <hideFirstColumn>true</hideFirstColumn>   <!-- hide the raw TS sort column -->
        <columnWidths>20,80</columnWidths>
      </graph>
    </graphs>
  </report>

</reports>
```

---

## Tips

- **Column placeholders** use the form `${TABLE.COLUMN}` (Worktable placeholders
  resolved by the framework before the query is executed). Table and column names
  are case-insensitive.
- **`<sum>`** only totals columns whose values are numeric. Non-numeric cells
  are left blank in the totals row.
- **`<columnWidths>`** percentages must add up to 100 and must match the number
  of visible columns (after `<hideFirstColumn>` is applied). They affect Word/ODT
  only; HTML and PDF use the browser/renderer defaults.
- **`<qrcodeColumn>` and `<barcodeColumn>`** indices count from 0 and include
  the hidden column if `<hideFirstColumn>true</hideFirstColumn>` is set.
- Comments (`<!-- … -->`) are allowed anywhere in the XML and are a good way
  to disable a report block without deleting it.
