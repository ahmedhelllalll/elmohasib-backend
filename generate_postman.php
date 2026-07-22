<?php
$json = shell_exec('php artisan route:list --path=api --json');
$routes = json_decode($json, true);
$items = [];

if (is_array($routes)) {
    foreach ($routes as $route) {
        $uri = $route['uri'];
        if (strpos($uri, 'api/') !== 0) continue;
        
        $methods = explode('|', $route['method']);
        $method = $methods[0] === 'HEAD' && isset($methods[1]) ? $methods[1] : $methods[0];
        
        $name = isset($route['name']) && $route['name'] ? $route['name'] : $uri;
        $item = [
            'name' => $name,
            'request' => [
                'method' => $method,
                'header' => [
                    ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text']
                ],
                'url' => [
                    'raw' => '{{base_url}}/' . $uri,
                    'host' => ['{{base_url}}'],
                    'path' => explode('/', $uri)
                ]
            ],
            'response' => []
        ];
        
        if (isset($route['middleware']) && in_array('Illuminate\Auth\Middleware\Authenticate:sanctum', $route['middleware'])) {
            $item['request']['auth'] = [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{auth_token}}', 'type' => 'string']
                ]
            ];
        }
        
        $items[] = $item;
    }
}

$collection = [
    'info' => [
        'name' => 'El-Mohasib API',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
    ],
    'item' => $items,
    'variable' => [
        ['key' => 'base_url', 'value' => 'http://localhost:8000', 'type' => 'string'],
        ['key' => 'auth_token', 'value' => 'YOUR_TOKEN_HERE', 'type' => 'string']
    ]
];

file_put_contents('El_Mohasib_Postman_Collection.json', json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Done\n";
