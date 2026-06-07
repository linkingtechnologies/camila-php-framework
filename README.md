# Camila PHP Framework

**Camila PHP Framework** is an open-source PHP framework designed for rapidly building **data-centric web applications** — dashboards, admin panels, internal tools, and reporting systems — with minimal boilerplate.

At its core is the **Worktable** concept: database tables are described declaratively, and the framework automatically generates the corresponding UI, forms, filters, and CRUD operations. No need to hand-write views or controllers for standard data management.

---

## Core Features

### Data Tables (Worktable)
Each module is a **data sheet** (Worktable), analogous to a spreadsheet tab, automatically rendered from its declarative definition.

- Paginated table view with **column sorting** by clicking headers
- **Column selector** — show/hide columns on the fly
- **Inline editing** — click directly on a cell value to edit it in place
- **Insert / Edit / Delete** per row via dedicated buttons
- Auto-suggest / typeahead on configured fields

### Filters
- Multi-condition filter bar above every table: pick **field → operator → value** (*contains, equals, greater than, …*)
- **Group-by** filter — groups rows by a selected field and shows occurrence counts
- **Starred rows** filter — show/hide records flagged as special
- **Selected rows** filter — show/hide records ticked with a checkmark

### Export
- **XLSX** (MS Excel), **ODS** (LibreOffice), **PDF**, **RTF** (Word / Writer), **HTML** (printable)
- **CSV** — machine-readable and integration-oriented formats
- **Template engine** — apply pre-loaded document templates to produce formatted PDFs (badges with barcodes, certificates, official forms)

### Import
- **Excel import** — load a `.xlsx` file into any data sheet; first row = column headers matching field names

### REST APIs
- Expose any database table as a **JSON / REST endpoint** via the integrated `php-crud-api` layer

### Authentication & Users
- Session-based **multi-user access** (multiple concurrent users)
- Two built-in roles: **operator** (standard) and **administrator**
- Admin-only operations: Excel import, DB structure editing, bulk data reset, locked-field updates
- Per-user **change password** via preferences panel

### Reporting & Dashboards
- Summary **charts and tables** in a dedicated Reports section
- **Export report to PDF** with one click
- **Data integrity checker** — flags anomalies and inconsistencies across sheets

### Navigation & Layout
- Top menu bar with **breadcrumb navigation** sub-bar
- Per-user **preferences** panel (font type & size, password change)
- Row count displayed inline; **"show all pages"** toggle for full-list view

### Internationalisation
- Built-in **English and Italian** language files
- Extensible i18n system

### Plugin System
- Extend the framework with **custom modules** via a dedicated plugin architecture

---

## Technology

- **PHP**
- **AdoDB** for database abstraction (MySQL, PostgreSQL, SQLite, and more)
- **Bulma CSS** + jQuery for the front-end
- **Composer** for dependency management
- Export powered by **FPDF**, **php-excel-reader**, **TBS** template engine, and more

---

## License

Camila PHP Framework is free software released under the **GNU General Public License v3** (or later).
See the `LICENSE` file for details.