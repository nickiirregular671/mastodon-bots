<?php
declare(strict_types=1);

$route = $_GET['_route'] ?? '';

if ($route === 'nodeinfo_discovery') {
    // /.well-known/nodeinfo — discovery document
    $response = [
        'links' => [
            [
                'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
                'href' => base_url() . '/nodeinfo/2.0',
            ],
        ],
    ];
    json_response($response, 200, 'application/json');
}

// /nodeinfo/2.0
$count = db_get("SELECT COUNT(*) as c FROM accounts");
$posts = db_get("SELECT COUNT(*) as c FROM posts WHERE deleted_at IS NULL");

$response = [
    'version'           => '2.0',
    'software'          => [
        'name'    => 'activitypub-bot',
        'version' => '1.0.0',
    ],
    'protocols'         => ['activitypub'],
    'usage'             => [
        'users' => [
            'total'          => (int)($count['c'] ?? 0),
            'activeMonth'    => (int)($count['c'] ?? 0),
            'activeHalfYear' => (int)($count['c'] ?? 0),
        ],
        'localPosts' => (int)($posts['c'] ?? 0),
    ],
    'openRegistrations' => false,
];

json_response($response, 200, 'application/json');
