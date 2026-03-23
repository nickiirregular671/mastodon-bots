<?php
declare(strict_types=1);

// WebFinger: /.well-known/webfinger?resource=acct:username@domain

require_once LIB_PATH . '/activity.php';

$resource = $_GET['resource'] ?? '';

if (empty($resource)) {
    error_response(400, 'Missing resource parameter');
}

// Support acct: and https: URIs
$username = null;
if (str_starts_with($resource, 'acct:')) {
    $acct = substr($resource, 5); // strip "acct:"
    [$user, $host] = array_pad(explode('@', $acct, 2), 2, '');
    $domain = get_domain();
    if ($host !== $domain) {
        error_response(404, 'Unknown domain');
    }
    $username = sanitize_username($user);
} elseif (str_starts_with($resource, 'https://')) {
    // e.g. https://domain/users/username
    $path = parse_url($resource, PHP_URL_PATH) ?? '';
    if (preg_match('#^/users/([a-zA-Z0-9_-]+)$#', $path, $m)) {
        $username = $m[1];
    }
}

if (empty($username)) {
    error_response(400, 'Invalid resource format');
}

$account = get_account_by_username($username);
if (!$account) {
    error_response(404, 'Account not found');
}

$domain   = get_domain();
$response = [
    'subject' => 'acct:' . $account['username'] . '@' . $domain,
    'aliases' => [
        actor_url($account['username']),
    ],
    'links' => [
        [
            'rel'  => 'http://webfinger.net/rel/profile-page',
            'type' => 'text/html',
            'href' => actor_url($account['username']),
        ],
        [
            'rel'  => 'self',
            'type' => 'application/activity+json',
            'href' => actor_url($account['username']),
        ],
        [
            'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
            'template' => 'https://' . $domain . '/authorize_interaction?uri={uri}',
        ],
    ],
];

json_response($response, 200, 'application/jrd+json');
