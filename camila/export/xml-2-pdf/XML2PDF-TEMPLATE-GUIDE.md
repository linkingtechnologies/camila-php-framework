# xml-2-pdf Template Guide

Templates are UTF-8 (or ISO-8859-1) XML files that describe a PDF document.
Variable placeholders use the `${variableName}` syntax and are replaced by the
application before rendering.

---

## Document structure

```xml
<?xml version='1.0' encoding='iso-8859-1'?>
<pdf creator="my app" title="Document title">
  <body format="A4" orientation="P" unit="mm" margins="10">

    <page>
      <!-- content here -->
    </page>

  </body>
</pdf>
```

---

## `<body>` — document settings

| Attribute      | Values / Notes                                      |
|----------------|-----------------------------------------------------|
| `format`       | `A4`, `A3`, `letter`, … (FPDF page sizes)          |
| `orientation`  | `P` portrait (default) · `L` landscape             |
| `unit`         | `mm` (default) · `pt` · `cm` · `in`               |
| `margins`      | single value sets all four margins                  |
| `marginleft`   | left margin                                         |
| `marginright`  | right margin                                        |
| `margintop`    | top margin                                          |
| `marginbottom` | bottom margin (also sets auto page-break threshold) |
| `font`         | default font for the document                       |
| `fontsize`     | default font size                                   |
| `fontstyle`    | default font style (`B`, `I`, `U`, combinations)   |
| `fontcolor`    | default font color (`#rrggbb`)                      |

---

## `<page>` — add a page

```xml
<page orientation="P" width="100" height="150"
      font="helvetica" fontsize="10" fontstyle="B" fontcolor="#000000">
```

| Attribute     | Notes                                              |
|---------------|----------------------------------------------------|
| `orientation` | `P` or `L` — overrides body default for this page |
| `width`       | custom page width (use with `height`)             |
| `height`      | custom page height                                |
| `font` / `fontsize` / `fontstyle` / `fontcolor` | page-level font defaults |

---

## `<importpage>` — embed an existing PDF page


```xml
<importpage file="/absolute/path/to/source.pdf" page="1" />
```

| Attribute | Notes                              |
|-----------|------------------------------------|
| `file`    | absolute path to the PDF file      |
| `page`    | page number to import (1-based)    |

The imported page is placed at position (0, 0) and scaled to fill the current
page. Place it as the first element inside `<page>` so other content renders
on top.

---

## `<paragraph>` — text block

```xml
<paragraph
  position="absolute" top="50" left="20" width="80" height="30"
  font="helvetica" fontsize="12" fontstyle="B" fontcolor="#333333"
  textalign="C" lineheight="6" linespacing="120"
  border="1" bordercolor="#000000"
  fill="1" fillcolor="#ffff00"
  align="C"
  valign="bottom"
>
  ${variableName}
</paragraph>
```

### Positioning

| Attribute  | Values                                                        |
|------------|---------------------------------------------------------------|
| `position` | `absolute` — uses `top`/`left` as absolute coords on the page |
|            | `relative` (default) — offsets from current cursor position  |
| `top`      | vertical position / offset in current unit                   |
| `left`     | horizontal position / offset in current unit                 |
| `width`    | box width in current unit                                    |
| `height`   | box height — required for `valign` middle/bottom             |

`top` and `left` support simple math expressions (e.g. `top="10+5*2"`).

### Text

| Attribute     | Values                              |
|---------------|-------------------------------------|
| `font`        | `helvetica`, `times`, `courier`     |
| `fontsize`    | point size (e.g. `12`)             |
| `fontstyle`   | `B` bold · `I` italic · `U` underline · combinations (`BI`, `BU`, …) |
| `fontcolor`   | `#rrggbb` hex or CSS color name (e.g. `red`, `black`) |
| `textalign`   | `L` · `R` · `C` · `J` (justify)   |
| `lineheight`  | line height in current unit (default: 5 mm) · special value `fontsize` sets it equal to the current font size |
| `linespacing` | interline as % of font size         |

### Box

| Attribute     | Values                                              |
|---------------|-----------------------------------------------------|
| `border`      | `0` none · `1` all sides · `LRTB` (any combination) |
| `bordercolor` | `#rrggbb`                                           |
| `fill`        | `0` transparent · `1` filled                       |
| `fillcolor`   | `#rrggbb`                                           |
| `align`       | `L`/`R`/`C` — aligns the box itself on the page (relative mode only) |
| `valign`      | `top` (default) · `middle` · `bottom` — vertical text alignment inside `height` |

---

## `<image>` — embed an image

```xml
<image file="/path/to/image.png"
       position="absolute" top="10" left="10"
       width="50" height="30" />
```

| Attribute  | Notes                                    |
|------------|------------------------------------------|
| `file`     | path to image (JPG, PNG, GIF)            |
| `position` | `absolute` or `relative`                |
| `top`      | vertical position                        |
| `left`     | horizontal position                      |
| `width`    | display width (0 = auto)                 |
| `height`   | display height (0 = auto)                |
| `type`     | force image type (`jpg`, `png`, `gif`)   |
| `content`  | base64-encoded image data (alternative to `file`) |

---

## `<qrcode>` — QR code

```xml
<qrcode content="${url}" position="absolute" top="10" left="10"
        width="30" height="30" level="H" />
```

| Attribute | Values                                     |
|-----------|--------------------------------------------|
| `content` | text / URL to encode                       |
| `level`   | error correction: `L` · `M` · `Q` · `H`  |
| `size`    | module size in pixels                      |
| `base64`  | `1` to output as base64 image              |

---

## `<table>` / `<tr>` / `<td>` — data table

Column widths can be absolute values or percentages of table width.
Row height is calculated automatically from cell content.

```xml
<table width="170" left="10" fontsize="10" lineheight="6"
       font="helvetica" border="1" bordercolor="#cccccc">

  <tr fontstyle="B" fill="1" fillcolor="#eeeeee">
    <td width="40%">Name</td>
    <td width="60%">Value</td>
  </tr>

  <tr>
    <td>${name}</td>
    <td>${value}</td>
  </tr>

</table>
```

`<tr>` and `<td>` share the same font/color/border attributes as `<paragraph>`.
Properties set on `<tr>` are inherited by its `<td>` cells unless overridden.

---

## `<header>` / `<footer>` — repeating elements

Content inside `<header>` or `<footer>` repeats on every page between `start`
and `end`.

```xml
<header top="5" left="10" start="1" end="99"
        border="0" fill="0" align="C">
  <paragraph font="helvetica" fontsize="8">My document — page ${page}</paragraph>
</header>
```

| Attribute | Notes                          |
|-----------|--------------------------------|
| `top`     | vertical position              |
| `left`    | horizontal position            |
| `start`   | first page to show on         |
| `end`     | last page to show on          |
| `align`   | `L` · `R` · `C`              |

---

## `<barcode>` — 1D barcode

```xml
<barcode barcode="${code}" norm="code39extend"
         x="20" y="50" width="0.3" height="8"
         position="absolute" />
```

| Attribute  | Notes                                                        |
|------------|--------------------------------------------------------------|
| `barcode`  | value to encode                                              |
| `norm`     | barcode standard: `code39extend` · `code39` · `ean13` · etc.|
| `x`        | horizontal position (note: uses `x`/`y`, not `left`/`top`)  |
| `y`        | vertical position                                            |
| `width`    | bar width (thin bar width in mm, e.g. `0.3`)                |
| `height`   | barcode height in current unit                               |
| `position` | `absolute` or `relative`                                    |

---

## `<ln>` — line break

```xml
<ln lineheight="10" />
```

Advances the cursor vertically by `lineheight` mm (default: 5).

---

## `<box>` — rectangle

```xml
<box top="20" left="20" bottom="80" right="190"
     linecolor="#000000" linewidth="0.5" />
```

---

## `<roundedbox>` — rectangle with rounded corners

```xml
<roundedbox top="20" left="20" width="80" height="40" radius="5"
            style="D" linecolor="#000000" linewidth="0.5"
            fillcolor="#ffffff" />
```

| Attribute   | Notes                                          |
|-------------|------------------------------------------------|
| `radius`    | corner radius in current unit                  |
| `style`     | `D` draw only · `F` fill · `DF` draw and fill |
| `angle`     | rotation angle in degrees                      |

---

## `<!-- $Include -->` — inline file inclusion

Camila Framework replaces `<!-- $Include path -->` with the contents of the
referenced file before XML parsing. Useful for shared values (logo path, event
name, location) that are stored once and reused across templates.

```xml
<image file="<!-- $Include var/templates/it/logo.txt -->" ... />
<paragraph ...><!-- $Include var/templates/it/evento.txt --></paragraph>
```

The path is relative to the application root. The file should contain plain
text (no XML). The directive works inside attribute values and tag content.

---

## Template blocks

Camila Framework uses XML comments to delimit repeatable sections:

```xml
<!-- $BeginBlock blockName -->
  <!-- content repeated for each data row -->
<!-- $EndBlock blockName -->
```

Multiple blocks can coexist in the same template (e.g. one for a form view and
one for a table view). Camila Framework selects the block to use at runtime.

### Multi-item layouts (row blocks)

To place multiple items per page, define one block per position and name them
`row1`, `row2`, … `rowN`. Camila Framework fills them in order, starting a new
page when all row blocks on the current page have been used.

```xml
<!-- $BeginBlock table -->
<page>

<!-- $BeginBlock row1 -->
  <!-- first item, positioned at top of page -->
<!-- $EndBlock row1 -->

<!-- $BeginBlock row2 -->
  <!-- second item, positioned below row1 -->
<!-- $EndBlock row2 -->

<!-- $BeginBlock row3 -->
  <!-- third item -->
<!-- $EndBlock row3 -->

</page>
<!-- $EndBlock table -->
```

Use `position="absolute"` with math expressions to place each row at a fixed
offset, e.g. `top="13.5+54*0"`, `top="13.5+54*1"`, `top="13.5+54*2"` for
items spaced 54 mm apart.

---

## Tips

- All coordinates and sizes are in the unit set on `<body>` (`mm` by default).
- `position="absolute"` ignores margins — coordinates are relative to the
  physical page corner (0, 0).
- `${variableName}` placeholders are replaced before XML parsing; any valid
  XML characters are safe, but `<`, `>`, `&` in values must be escaped as
  `&lt;`, `&gt;`, `&amp;`.
- Font names are case-insensitive core FPDF fonts: `helvetica`, `times`,
  `courier`, `symbol`, `zapfdingbats`.
- Colors accept `#rrggbb` hex strings or CSS color names (`red`, `black`, `white`, etc.).
- `border` on `<paragraph>` accepts FPDF cell border syntax: `0`, `1`, or any
  combination of `L`, `R`, `T`, `B` (e.g. `border="LB"` draws left + bottom).
