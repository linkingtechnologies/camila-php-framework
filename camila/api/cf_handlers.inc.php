<?php
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
        $lang = $params['lang'] ?? (defined('CAMILA_DEFAULT_LANG') ? CAMILA_DEFAULT_LANG : 'it');
        $tmpl = new CamilaTemplate($lang);
        $all  = $tmpl->getParameters();
        if (!array_key_exists($name, $all)) {
            return ['error' => 'not found', 'name' => $name];
        }
        return ['name' => $name, 'lang' => $lang, 'value' => $all[$name]];
    },
];
