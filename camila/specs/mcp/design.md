# MCP Server

Exposes the Camila data API to Claude Code via the Model Context Protocol (Streamable HTTP transport).

## Architecture

The MCP handler is an intercept inside `cf_api_controller.inc.php`. When `?mcp=1` is present on the request URL, `CamilaMcpHandler` is loaded and handles the JSON-RPC 2.0 conversation. Normal API traffic is unaffected.

```
Claude Code
    │  POST /app/<appdir>/cf_api.php?mcp=1
    │  X-API-Key: <token>
    │  Content-Type: application/json
    │  Body: {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{...}}
    ▼
cf_api_controller.inc.php
    → new Api($config)          ← plugins, auth, column mapping all baked in
    → isset($_GET['mcp'])       ← intercept here
    → CamilaMcpHandler->handle()
        → JSON-RPC dispatch
        → apiCall() builds synthetic request via RequestFactory::fromString()
        → $api->handle($syntheticRequest)  ← same $api, real auth pipeline
        → return JSON-RPC response
```

**Key property:** synthetic requests carry the `X-API-Key` header from the original request. `apiKeyDbAuth` authenticates the user → audit fields (`created_by`, `last_upd_by`) and authorization rules work exactly as in normal web requests.

## Setup (once per app)

1. Pick a user that MCP should run as (typically an admin account).
2. Set their `token` column to a random UUID:
   ```sql
   UPDATE <app>_camila_users SET token = '<uuid>' WHERE username = 'admin';
   ```
3. Add an MCP server entry to `.mcp.json` in the Claude Code project root:
   ```json
   {
     "mcpServers": {
       "camila": {
         "type": "http",
         "url": "http://localhost/app/<appdir>/cf_api.php?mcp=1",
         "headers": { "X-API-Key": "<uuid>" }
       }
     }
   }
   ```

## Exposed tools

| Tool | Method + path | Description |
|---|---|---|
| `app_status` | `GET /status/app` | App name + server timestamp |
| `list_tables` | `GET /tables` | All non-internal table names |
| `describe_table` | `GET /columns/{table}` | Column schema for a table |
| `list_records` | `GET /records/{table}` | List with filters, sort, pagination |
| `get_record` | `GET /records/{table}/{id}` | Single record by ID |
| `create_record` | `POST /records/{table}` | Create new record |
| `update_record` | `PATCH /records/{table}/{id}` | Partial update |
| `delete_record` | `DELETE /records/{table}/{id}` | Delete record |

All tools use the same column-name mapping as the REST API (Camila names → kebab-case). Excluded internal columns (`created`, `last_upd`, `grp`, etc.) are never visible. See [records design](../api/records/design.md) for filter operators and column mapping details.

## Files

| File | Role |
|---|---|
| `camila/api/cf_mcp_handler.inc.php` | MCP handler class |
| `camila/api/cf_api_controller.inc.php` | Intercept (`isset($_GET['mcp'])`) |

## Protocol

- Transport: Streamable HTTP (JSON-RPC 2.0 over POST)
- Protocol version negotiated: `2024-11-05`
- Notifications (no `id`): server returns HTTP 202, no body
- Errors: tool results set `isError: true` and include the API error payload as text

## Security

- Protocol methods (`initialize`, `ping`, `tools/list`) do not require authentication — they return metadata only.
- All data tool calls (`list_tables`, `list_records`, `create_record`, etc.) go through the full Camila auth pipeline. Without a valid `X-API-Key` token the API returns 401, surfaced as `isError: true` in the tool result.
- The handler does not add extra IP restrictions; deploy behind a firewall or bind nginx to localhost if the endpoint should not be publicly reachable.
