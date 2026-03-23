<?php
declare(strict_types=1);

$username = sanitize_username($_GET['username'] ?? '');
if (empty($username)) {
    error_response(400, 'Missing username');
}

$account = get_account_by_username($username);
if (!$account) {
    error_response(404, 'Not found');
}

$tags = json_decode($account['featured_hashtags'] ?? '[]', true) ?: [];

$items = array_map(function (string $tag) use ($account): array {
    $tag = ltrim($tag, '#');
    // Count published posts using this hashtag
    $row = db_get(
        "SELECT COUNT(*) as c FROM hashtags h
         JOIN posts p ON p.id = h.post_id
         WHERE h.tag = ? AND p.account_id = ? AND p.deleted_at IS NULL AND p.visibility IN ('public','unlisted')",
        [$tag, $account['id']]
    );
    $count = (int)($row['c'] ?? 0);

    // Find date of most recent use
    $last = db_get(
        "SELECT p.published_at FROM hashtags h
         JOIN posts p ON p.id = h.post_id
         WHERE h.tag = ? AND p.account_id = ? AND p.deleted_at IS NULL AND p.visibility IN ('public','unlisted')
         ORDER BY p.published_at DESC LIMIT 1",
        [$tag, $account['id']]
    );

    $item = [
        'type'       => 'Hashtag',
        'href'       => base_url() . '/tags/' . urlencode($tag),
        'name'       => '#' . $tag,
        'statusesCount' => $count,
    ];
    if ($last) {
        $item['lastStatusAt'] = ap_timestamp($last['published_at']);
    }
    return $item;
}, $tags);

$response = [
    '@context'     => 'https://www.w3.org/ns/activitystreams',
    'id'           => featured_tags_url($username),
    'type'         => 'Collection',
    'totalItems'   => count($items),
    'items'        => $items,
];

json_response($response);
