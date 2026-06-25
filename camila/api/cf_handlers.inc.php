<?php
// getParams() wraps every scalar value in a single-element array (e.g. size=20 → ['20']).
// cfParam() unwraps it back to a scalar.
function cfParam(array $params, string $key, $default = null) {
    $v = $params[$key] ?? $default;
    return is_array($v) ? ($v[0] ?? $default) : $v;
}

return [
    'GET /status/app' => function(array $params, ?array $body, array $path): array {
        return [
            'status'  => 'ok',
            'app'     => defined('CAMILA_APPLICATION_NAME') ? CAMILA_APPLICATION_NAME : null,
            'time'    => date('c'),
        ];
    },

    'GET /templates/*' => function(array $params, ?array $body, array $path): array {
        $name = $path[1] ?? '';
        $lang = cfParam($params, 'lang', defined('CAMILA_DEFAULT_LANG') ? CAMILA_DEFAULT_LANG : 'it');
        $tmpl = new CamilaTemplate($lang);
        $all  = $tmpl->getParameters();
        if (!array_key_exists($name, $all)) {
            return ['error' => 'not found', 'name' => $name];
        }
        return ['name' => $name, 'lang' => $lang, 'value' => $all[$name]];
    },

    // --- User management (admin only) ---

    'GET /users' => function(array $params, ?array $body, array $path): array {
        global $_CAMILA;
        if (!(new CamilaAuth())->isAdmin()) {
            return ['__status' => 403, 'message' => 'Forbidden'];
        }
        $auth = new CamilaAuth();
        return $auth->getUsers(
            cfParam($params, 'username', ''),
            (int) cfParam($params, 'page', 1),
            (int) cfParam($params, 'size', 50)
        );
    },

    'POST /users' => function(array $params, ?array $body, array $path): array {
        global $_CAMILA;
        if (!(new CamilaAuth())->isAdmin()) {
            return ['__status' => 403, 'message' => 'Forbidden'];
        }
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        if ($username === '' || $password === '') {
            return ['__status' => 400, 'message' => 'username and password are required'];
        }
        $auth = new CamilaAuth();
		$auth->db = $_CAMILA['db'];
        $existing = $auth->db->Execute(
            'SELECT id FROM ' . CAMILA_TABLE_USERS . ' WHERE UPPER(username) = UPPER(?)', [$username]
        );
        if ($existing && $existing->RecordCount() > 0) {
            return ['__status' => 409, 'message' => 'User already exists'];
        }
        if (!$auth->createUser($username, $password, $body)) {
            return ['__status' => 500, 'message' => 'Failed to create user'];
        }
        return ['status' => 'ok', 'username' => $username];
    },

    'PATCH /users/*' => function(array $params, ?array $body, array $path): array {
        global $_CAMILA;
        if (!(new CamilaAuth())->isAdmin()) {
            return ['__status' => 403, 'message' => 'Forbidden'];
        }
        $username = $path[count($path) - 1] ?? '';
        if ($username === '') {
            return ['__status' => 400, 'message' => 'username is required'];
        }
        $auth = new CamilaAuth();
		$auth->db = $_CAMILA['db'];
        if (!$auth->updateUser($username, $body ?? [])) {
            $existing = $auth->db->Execute(
                'SELECT id FROM ' . CAMILA_TABLE_USERS . ' WHERE UPPER(username) = UPPER(?)', [$username]
            );
            if (!$existing || $existing->RecordCount() === 0) {
                return ['__status' => 404, 'message' => 'User not found'];
            }
            return ['__status' => 400, 'message' => 'No updatable fields provided'];
        }
        return ['status' => 'ok', 'username' => $username];
    },

    'POST /users/*/reset-password' => function(array $params, ?array $body, array $path): array {
        global $_CAMILA;
        if (!(new CamilaAuth())->isAdmin()) {
            return ['__status' => 403, 'message' => 'Forbidden'];
        }
        $resetIdx = array_search('reset-password', $path);
        $username = $resetIdx !== false ? ($path[$resetIdx - 1] ?? '') : '';
        $password = $body['password'] ?? '';
        if ($username === '' || $password === '') {
            return ['__status' => 400, 'message' => 'username and password are required'];
        }
        $auth = new CamilaAuth();
		$auth->db = $_CAMILA['db'];
        if (!$auth->updatePassword($username, $password)) {
            return ['__status' => 404, 'message' => 'User not found'];
        }
        return ['status' => 'ok', 'username' => $username];
    },
];
