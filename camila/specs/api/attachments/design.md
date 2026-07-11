# API — Attachments

File upload and serving for record-linked images.

Base path: `/<app>/cf_api.php/attachments`

---

## Overview

Each record can have at most one attachment. Attachments are stored as binary files on the server filesystem at:

```
{CAMILA_FM_ROOTDIR}/attachments/{table}/{id}.bin   ← binary content
{CAMILA_FM_ROOTDIR}/attachments/{table}/{id}.meta  ← MIME type string
```

**Constraint:** only `image/*` files are accepted. Uploads of other MIME types are rejected with `400`.

---

## POST /attachments/{table}/{id}

Upload an image and associate it with record `{id}` in `{table}`. Uses `multipart/form-data` with the file in a field named `file`. Overwrites any existing attachment for that record.

**Request:** `Content-Type: multipart/form-data`, field `file`.

**Response (201):**

```json
{ "url": "/attachments/volontari/abc123" }
```

**Errors:**

| Code | Cause |
|---|---|
| `400` | No file uploaded, upload error, or non-image MIME type |
| `400` | Invalid record ID format |
| `500` | Cannot write to storage directory |

---

## GET /attachments/{table}/{id}

Serve the binary. The response has `Content-Disposition: attachment` — the browser triggers a file download.

For inline display (e.g. `<img src>`), use `fetchAttachment()` in WorkTableClient to get a blob URL instead.

**Response headers:**

| Header | Value |
|---|---|
| `Content-Type` | MIME type (e.g. `image/jpeg`) |
| `Content-Disposition` | `attachment; filename="{id}.{ext}"` |
| `X-Attachment-Ext` | File extension (e.g. `jpg`) |
| `Cache-Control` | `max-age=3600` |

**Errors:** `404` if not found, `400` if ID format is invalid.

---

## HEAD /attachments/{table}/{id}

Check existence without downloading the binary. Returns `200` with MIME/extension headers if the attachment exists, `404` otherwise.

**Response headers (200):**

| Header | Value |
|---|---|
| `Content-Type` | MIME type |
| `X-Attachment-Ext` | File extension |

---

## DELETE /attachments/{table}/{id}

Remove the binary and its metadata file.

**Response:** `204 No Content`.

**Errors:** `404` if not found.

---

## GET /attachments/{table}

List all record IDs that have an attachment in the given table.

**Response (200):**

```json
{
  "ids": [
    { "id": "abc123", "mime": "image/jpeg", "ext": "jpg" },
    { "id": "def456", "mime": "image/png",  "ext": "png" }
  ]
}
```

Returns `{ "ids": [] }` if the table has no attachments.

---

## Supported MIME types

`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`, `image/bmp`, `image/tiff`, `image/avif`, `image/heic`, `image/heif`

Any other MIME type is rejected at upload time.

---

## WorkTableClient — quick reference

```js
const t = api.table('volontari');

// Upload
const file = document.querySelector('input[type=file]').files[0];
t.uploadAttachment(id, file).then(res => console.log(res.url));

// Direct download URL (triggers browser download)
const url = t.attachmentUrl(id);
window.open(url);

// Blob URL for inline display
t.fetchAttachment(id).then(res => {
  const blobUrl = URL.createObjectURL(res.blob);
  document.querySelector('img').src = blobUrl;
});

// Check existence
t.hasAttachment(id).then(info => {
  if (!info) console.log('no attachment');
  else console.log(info.mime, info.ext);
});

// List all IDs with attachments
t.listAttachments().then(res => console.log(res.ids));

// Delete
t.deleteAttachment(id);
```
