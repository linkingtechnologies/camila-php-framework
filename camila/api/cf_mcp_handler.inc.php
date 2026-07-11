<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2026 Umberto Bresciani

    Camila PHP Framework is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Camila PHP Framework is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Camila PHP Framework. If not, see <http://www.gnu.org/licenses/>. */

/**
 * MCP Streamable HTTP transport handler.
 *
 * Intercepts ?mcp=1 requests and speaks JSON-RPC 2.0 / MCP protocol.
 * Authentication: callers must send `X-API-Key: <token>` where token matches
 * the `token` column in the users table (apiKeyDbAuth). This gives Camila a
 * real user context so audit fields and authorization work correctly.
 *
 * Usage in cf_api_controller.inc.php (after $api = new Api($config)):
 *   if (isset($_GET['mcp'])) {
 *       $apiKey   = $request->getHeader('X-API-Key')[0] ?? '';
 *       $basePath = '/app/' . CAMILA_APP_DIR . '/cf_api.php';
 *       require_once(__DIR__ . '/cf_mcp_handler.inc.php');
 *       (new CamilaMcpHandler($api, $apiKey, $basePath))->handle();
 *       exit;
 *   }
 *
 * .mcp.json example:
 *   {
 *     "mcpServers": {
 *       "camila": {
 *         "type": "http",
 *         "url": "http://localhost/app/<appdir>/cf_api.php?mcp=1",
 *         "headers": { "X-API-Key": "<token>" }
 *       }
 *     }
 *   }
 */
class CamilaMcpHandler
{
    private $api;
    private string $apiKey;
    private string $basePath;

    public function __construct($api, string $apiKey, string $basePath)
    {
        $this->api      = $api;
        $this->apiKey   = $apiKey;
        $this->basePath = $basePath;
    }

    // -------------------------------------------------------------------------
    // JSON-RPC dispatch
    // -------------------------------------------------------------------------

    public function handle(): void
    {
        header('Content-Type: application/json');

        $raw = (string) file_get_contents('php://input');
        $rpc = json_decode($raw, true);

        if (!is_array($rpc)) {
            $this->respond(null, null, ['code' => -32700, 'message' => 'Parse error']);
            return;
        }

        // Notifications have no 'id' — respond 202 with no body
        if (!array_key_exists('id', $rpc)) {
            http_response_code(202);
            return;
        }

        $id     = $rpc['id'];
        $method = $rpc['method'] ?? '';
        $params = $rpc['params'] ?? [];

        switch ($method) {
            case 'initialize':
                $this->respond($id, [
                    'protocolVersion' => '2024-11-05',
                    'capabilities'    => ['tools' => (object) []],
                    'serverInfo'      => ['name' => 'camila-mcp', 'version' => '1.0.0'],
                ]);
                break;

            case 'ping':
                $this->respond($id, (object) []);
                break;

            case 'tools/list':
                $this->respond($id, ['tools' => $this->toolList()]);
                break;

            case 'tools/call':
                $this->callTool($id, (string) ($params['name'] ?? ''), (array) ($params['arguments'] ?? []));
                break;

            default:
                $this->respond($id, null, ['code' => -32601, 'message' => "Method not found: $method"]);
        }
    }

    // -------------------------------------------------------------------------
    // Tool registry
    // -------------------------------------------------------------------------

    private function toolList(): array
    {
        $str = fn(string $d): array => ['type' => 'string', 'description' => $d];
        $int = fn(string $d): array => ['type' => 'integer', 'description' => $d];

        return [
            $this->tool('app_status',    'Return application name and server timestamp.'),
            $this->tool('list_tables',   'List all data tables available in the application.'),
            $this->tool('describe_table','Get column schema for a table.',
                ['table' => $str('Table name (kebab-case)')],
                ['table']),
            $this->tool('list_records',
                'List records from a table with optional filters, sorting, and pagination. '
                . 'Filter syntax: "column,op,value" — operators: eq, neq, lt, lte, gt, gte, cs (contains), sw, ew, is, in, bt. '
                . 'Prefix n to negate (e.g. ncs).',
                [
                    'table'   => $str('Table name (kebab-case)'),
                    'filter'  => ['type' => 'array', 'items' => ['type' => 'string'],
                                  'description' => 'AND filters: ["column,op,value", ...]'],
                    'order'   => $str('Sort: "column,asc" or "column,desc"'),
                    'size'    => $int('Page size'),
                    'page'    => $int('Page number (1-based); requires size'),
                    'include' => $str('Comma-separated column names to return'),
                ],
                ['table']),
            $this->tool('get_record', 'Get a single record by ID.',
                ['table' => $str('Table name (kebab-case)'), 'id' => $str('Record ID')],
                ['table', 'id']),
            $this->tool('create_record', 'Create a new record in a table.',
                [
                    'table' => $str('Table name (kebab-case)'),
                    'data'  => ['type' => 'object', 'description' => 'Field values as key-value pairs (use kebab-case column names)'],
                ],
                ['table', 'data']),
            $this->tool('update_record', 'Partially update an existing record (only specified fields are changed).',
                [
                    'table' => $str('Table name (kebab-case)'),
                    'id'    => $str('Record ID'),
                    'data'  => ['type' => 'object', 'description' => 'Fields to update (kebab-case column names)'],
                ],
                ['table', 'id', 'data']),
            $this->tool('delete_record', 'Delete a record by ID.',
                ['table' => $str('Table name (kebab-case)'), 'id' => $str('Record ID')],
                ['table', 'id']),
        ];
    }

    private function tool(string $name, string $desc, array $props = [], array $required = []): array
    {
        return [
            'name'        => $name,
            'description' => $desc,
            'inputSchema' => [
                'type'       => 'object',
                'properties' => empty($props) ? (object) [] : $props,
                'required'   => $required,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Tool execution
    // -------------------------------------------------------------------------

    private function callTool($id, string $name, array $args): void
    {
        try {
            switch ($name) {
                case 'app_status':
                    $data = $this->apiCall('GET', '/status/app', null, []);
                    break;
                case 'list_tables':
                    $data = $this->apiCall('GET', '/tables', null, []);
                    break;
                case 'describe_table':
                    $data = $this->apiCall('GET', '/columns/' . rawurlencode($args['table'] ?? ''), null, []);
                    break;
                case 'list_records':
                    $q = [];
                    if (!empty($args['filter']))  $q['filter']  = (array) $args['filter'];
                    if (!empty($args['order']))   $q['order']   = $args['order'];
                    if (!empty($args['size']))    $q['size']    = (int) $args['size'];
                    if (!empty($args['page']))    $q['page']    = (int) $args['page'];
                    if (!empty($args['include'])) $q['include'] = $args['include'];
                    $data = $this->apiCall('GET', '/records/' . rawurlencode($args['table'] ?? ''), null, $q);
                    break;
                case 'get_record':
                    $data = $this->apiCall('GET',
                        '/records/' . rawurlencode($args['table'] ?? '') . '/' . rawurlencode($args['id'] ?? ''),
                        null, []);
                    break;
                case 'create_record':
                    $data = $this->apiCall('POST', '/records/' . rawurlencode($args['table'] ?? ''),
                        $args['data'] ?? [], []);
                    break;
                case 'update_record':
                    $data = $this->apiCall('PATCH',
                        '/records/' . rawurlencode($args['table'] ?? '') . '/' . rawurlencode($args['id'] ?? ''),
                        $args['data'] ?? [], []);
                    break;
                case 'delete_record':
                    $data = $this->apiCall('DELETE',
                        '/records/' . rawurlencode($args['table'] ?? '') . '/' . rawurlencode($args['id'] ?? ''),
                        null, []);
                    break;
                default:
                    $this->respond($id, null, ['code' => -32602, 'message' => "Unknown tool: $name"]);
                    return;
            }
        } catch (\Throwable $e) {
            $this->respond($id, [
                'content' => [['type' => 'text', 'text' => 'Internal error: ' . $e->getMessage()]],
                'isError' => true,
            ]);
            return;
        }

        $isError = isset($data['__status']) && (int) $data['__status'] >= 400;
        unset($data['__status']);

        $this->respond($id, [
            'content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]],
            'isError' => $isError,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal API bridge
    // -------------------------------------------------------------------------

    /**
     * Calls the Camila API internally using RequestFactory::fromString().
     * The X-API-Key header is forwarded so apiKeyDbAuth authenticates the user.
     *
     * Multi-value params (e.g. filter) must be passed as PHP arrays; http_build_query
     * serialises them as filter[0]=...&filter[1]=... which RequestUtils::getParams()
     * then normalises back to filter[]=...  before parse_str.
     */
    private function apiCall(string $method, string $path, ?array $body, array $query): array
    {
        $uri = $this->basePath . $path;
        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $headerLines = '';
        if ($this->apiKey !== '') {
            $headerLines .= 'X-API-Key: ' . $this->apiKey . "\n";
        }
        if ($body !== null) {
            $headerLines .= "Content-Type: application/json\n";
        }

        $bodyStr = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';
        $raw     = $method . ' ' . $uri . "\n" . $headerLines . "\n" . $bodyStr;

        $request  = \Tqdev\PhpCrudApi\RequestFactory::fromString($raw);
        $response = $this->api->handle($request);

        $status  = $response->getStatusCode();
        $resBody = (string) $response->getBody();
        $data    = json_decode($resBody, true);

        if (!is_array($data)) {
            $data = ['raw' => $resBody];
        }
        if ($status >= 400) {
            $data['__status'] = $status;
        }
        return $data;
    }

    // -------------------------------------------------------------------------

    private function respond($id, $result, ?array $error = null): void
    {
        $out = ['jsonrpc' => '2.0', 'id' => $id];
        if ($error !== null) {
            $out['error'] = $error;
        } else {
            $out['result'] = $result;
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    }
}
