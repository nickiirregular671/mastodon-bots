<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const AP_PUBLIC  = 'https://www.w3.org/ns/activitystreams#Public';
const AP_CONTEXT = [
    'https://www.w3.org/ns/activitystreams',
    'https://w3id.org/security/v1',
    [
        'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
        'toot'        => 'http://joinmastodon.org/ns#',
        'gts'         => 'https://gotosocial.org/ns#',
        'featured'     => ['@id' => 'toot:featured',     '@type' => '@id'],
        'featuredTags' => ['@id' => 'toot:featuredTags', '@type' => '@id'],
        'alsoKnownAs' => ['@id' => 'as:alsoKnownAs', '@type' => '@id'],
        'movedTo'     => ['@id' => 'as:movedTo',     '@type' => '@id'],
        // FEP-044f quote posts (canonical) + compatibility aliases
        'quote'            => ['@id' => 'https://w3id.org/fep/044f#quote',   '@type' => '@id'],
        'quoteUrl'         => ['@id' => 'as:quoteUrl',                        '@type' => '@id'],
        '_misskey_quote'   => 'https://misskey-hub.net/ns/#_misskey_quote',
        // FEP-044f / GoToSocial interaction policy
        'interactionPolicy'   => ['@id' => 'gts:interactionPolicy',   '@type' => '@id'],
        'canQuote'            => ['@id' => 'gts:canQuote',            '@type' => '@id'],
        'automaticApproval'   => ['@id' => 'gts:automaticApproval',   '@type' => '@id'],
        'Hashtag'          => 'as:Hashtag',
        'sensitive'   => 'as:sensitive',
        'indexable'   => 'toot:indexable',
        'blurhash'    => 'toot:blurhash',
        'focalPoint'  => ['@container' => '@list', '@id' => 'toot:focalPoint'],
    ],
];

function build_actor(array $account): array {
    $u   = $account['username'];
    $url = actor_url($u);

    $actor = [
        '@context'                  => AP_CONTEXT,
        'id'                        => $url,
        'type'                      => 'Service',
        'following'                 => following_url($u),
        'followers'                 => followers_url($u),
        'inbox'                     => inbox_url($u),
        'outbox'                    => outbox_url($u),
        'preferredUsername'         => $u,
        'name'                      => $account['display_name'] ?: $u,
        'summary'                   => nl2br(htmlspecialchars($account['bio'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'url'                       => profile_url($u),
        'manuallyApprovesFollowers' => (bool)$account['manually_approves_followers'],
        'discoverable'              => (bool)$account['discoverable'],
        'indexable'                 => (bool)$account['indexable'],
        'featuredTags'              => featured_tags_url($u),
        'published'                 => ap_timestamp($account['created_at']),
        'publicKey'                 => [
            'id'           => $url . '#main-key',
            'owner'        => $url,
            'publicKeyPem' => $account['public_key'],
        ],
        'endpoints' => [
            'sharedInbox' => base_url() . '/inbox',
        ],
        'interactionPolicy' => [
            'canQuote' => [
                'automaticApproval' => AP_PUBLIC,
            ],
        ],
    ];

    if (!empty($account['avatar_path'])) {
        $actor['icon'] = [
            'type'      => 'Image',
            'mediaType' => mime_content_type(BASE_PATH . '/uploads/avatars/' . $account['avatar_path']) ?: 'image/jpeg',
            'url'       => base_url() . '/uploads/avatars/' . $account['avatar_path'],
        ];
    }

    if (!empty($account['header_path'])) {
        $actor['image'] = [
            'type'      => 'Image',
            'mediaType' => mime_content_type(BASE_PATH . '/uploads/headers/' . $account['header_path']) ?: 'image/jpeg',
            'url'       => base_url() . '/uploads/headers/' . $account['header_path'],
        ];
    }

    if (!empty($account['moved_to'])) {
        $actor['movedTo'] = $account['moved_to'];
    }

    $alsoKnownAs = json_decode($account['also_known_as'] ?? '[]', true);
    if (!empty($alsoKnownAs)) {
        $actor['alsoKnownAs'] = $alsoKnownAs;
    }

    $profileFields = json_decode($account['profile_fields'] ?? '[]', true) ?: [];
    if (!empty($profileFields)) {
        $actor['attachment'] = array_map(function (array $f): array {
            $value = $f['value'];
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $esc   = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $value = '<a href="' . $esc . '" rel="me nofollow noopener noreferrer" target="_blank">' . $esc . '</a>';
            }
            return ['type' => 'PropertyValue', 'name' => $f['name'], 'value' => $value];
        }, $profileFields);
    }

    return $actor;
}

function build_note(array $post, array $account, array $attachments = [], array $tags = []): array {
    $mentionedUris = array_map(fn($m) => $m['actor_uri'], $tags['mentions'] ?? []);
    $addr          = visibility_to_addressees($post['visibility'], $account['username'], $mentionedUris);

    $note = [
        '@context'     => AP_CONTEXT,
        'id'           => $post['activity_id'],
        'type'         => 'Note',
        'published'    => ap_timestamp($post['published_at']),
        'attributedTo' => actor_url($account['username']),
        'content'      => $post['content_html'],
        'to'           => $addr['to'],
        'cc'           => $addr['cc'],
        'sensitive'    => !empty($post['content_warning']),
        'language'     => $post['language'] ?: 'en',
        'url'          => base_url() . '/@' . $account['username'] . '/' . basename($post['activity_id']),
        'interactionPolicy' => [
            'canQuote' => [
                'automaticApproval' => AP_PUBLIC,
            ],
        ],
    ];

    if (!empty($post['content_warning'])) {
        $note['summary'] = $post['content_warning'];
    }

    if (!empty($post['in_reply_to_id'])) {
        $note['inReplyTo'] = $post['in_reply_to_id'];
    }

    if (!empty($post['updated_at'])) {
        $note['updated'] = ap_timestamp($post['updated_at']);
    }

    if (!empty($post['quote_url'])) {
        $note['quote']          = $post['quote_url'];  // FEP-044f canonical
        $note['quoteUrl']       = $post['quote_url'];  // Mastodon/GTS compat
        $note['_misskey_quote'] = $post['quote_url'];  // Misskey compat
    }

    // AP tag array
    $apTags = [];
    foreach ($tags['hashtags'] ?? [] as $ht) {
        $tag = is_array($ht) ? $ht['tag'] : $ht;
        $apTags[] = [
            'type' => 'Hashtag',
            'href' => base_url() . '/tags/' . strtolower($tag),
            'name' => '#' . $tag,
        ];
    }
    foreach ($tags['mentions'] ?? [] as $m) {
        // name must include domain for AP spec compliance
        $mentionName = str_contains($m['username'], '@') ? '@' . $m['username'] : '@' . $m['username'] . '@' . get_domain();
        $apTags[] = [
            'type' => 'Mention',
            'href' => $m['actor_uri'],
            'name' => $mentionName,
        ];
    }
    if (!empty($apTags)) {
        $note['tag'] = $apTags;
    }

    // Media attachments
    if (!empty($attachments)) {
        $note['attachment'] = array_map(function (array $att): array {
            $obj = [
                'type'      => 'Document',
                'mediaType' => $att['mime_type'],
                'url'       => $att['file_url'],
            ];
            if (!empty($att['alt_text']))  $obj['name']     = $att['alt_text'];
            if (!empty($att['blurhash']))  $obj['blurhash'] = $att['blurhash'];
            if ($att['width'] > 0)         $obj['width']    = (int)$att['width'];
            if ($att['height'] > 0)        $obj['height']   = (int)$att['height'];
            if ($att['duration'] > 0)      $obj['duration'] = (float)$att['duration'];
            return $obj;
        }, $attachments);
    }

    return $note;
}

function build_create(array $note, array $account): array {
    return [
        '@context'  => AP_CONTEXT,
        'id'        => $note['id'] . '/activity',
        'type'      => 'Create',
        'actor'     => actor_url($account['username']),
        'published' => $note['published'],
        'to'        => $note['to'],
        'cc'        => $note['cc'],
        'object'    => $note,
    ];
}

function build_update(array $note, array $account): array {
    return [
        '@context'  => AP_CONTEXT,
        'id'        => $note['id'] . '/update/' . time(),
        'type'      => 'Update',
        'actor'     => actor_url($account['username']),
        'published' => ap_timestamp(),
        'to'        => [AP_PUBLIC],
        'cc'        => [followers_url($account['username'])],
        'object'    => $note,
    ];
}

function build_delete(string $postActivityId, array $account): array {
    return [
        '@context' => AP_CONTEXT,
        'id'       => $postActivityId . '/delete',
        'type'     => 'Delete',
        'actor'    => actor_url($account['username']),
        'to'       => [AP_PUBLIC],
        'object'   => [
            'id'   => $postActivityId,
            'type' => 'Tombstone',
        ],
    ];
}

function build_follow(array $account, string $targetActorUri): array {
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/follows/' . generate_uuid(),
        'type'     => 'Follow',
        'actor'    => actor_url($account['username']),
        'object'   => $targetActorUri,
    ];
}

function build_unfollow(array $account, string $targetActorUri): array {
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/unfollows/' . generate_uuid(),
        'type'     => 'Undo',
        'actor'    => actor_url($account['username']),
        'object'   => [
            'type'   => 'Follow',
            'actor'  => actor_url($account['username']),
            'object' => $targetActorUri,
        ],
    ];
}

function build_accept_follow(array $account, array $followActivity): array {
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/accepts/' . generate_uuid(),
        'type'     => 'Accept',
        'actor'    => actor_url($account['username']),
        'object'   => $followActivity['id'] ?? $followActivity,
    ];
}

function build_accept_quote_request(array $account, array $quoteRequest, string $stampUrl): array {
    return [
        '@context' => [
            'https://www.w3.org/ns/activitystreams',
            ['QuoteRequest' => 'https://w3id.org/fep/044f#QuoteRequest'],
        ],
        'type'   => 'Accept',
        'id'     => actor_url($account['username']) . '/accepts/' . generate_uuid(),
        'actor'  => actor_url($account['username']),
        'to'     => [$quoteRequest['actor'] ?? ''],
        'object' => $quoteRequest,
        'result' => $stampUrl,
    ];
}

function build_reject_follow(array $account, array $followActivity): array {
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/rejects/' . generate_uuid(),
        'type'     => 'Reject',
        'actor'    => actor_url($account['username']),
        'object'   => $followActivity['id'] ?? $followActivity,
    ];
}

function build_block(array $account, string $targetActorUri): array {
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/blocks/' . generate_uuid(),
        'type'     => 'Block',
        'actor'    => actor_url($account['username']),
        'object'   => $targetActorUri,
    ];
}

function build_unblock(array $account, string $targetActorUri): array {
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/unblocks/' . generate_uuid(),
        'type'     => 'Undo',
        'actor'    => actor_url($account['username']),
        'object'   => build_block($account, $targetActorUri),
    ];
}

function build_move(array $account, string $newAccountUri): array {
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/moves/' . generate_uuid(),
        'type'     => 'Move',
        'actor'    => actor_url($account['username']),
        'object'   => actor_url($account['username']),
        'target'   => $newAccountUri,
    ];
}

function build_announce(array $account, string $postUri): array {
    return [
        '@context'  => 'https://www.w3.org/ns/activitystreams',
        'id'        => actor_url($account['username']) . '/announces/' . generate_uuid(),
        'type'      => 'Announce',
        'actor'     => actor_url($account['username']),
        'to'        => [AP_PUBLIC],
        'cc'        => [followers_url($account['username'])],
        'object'    => $postUri,
        'published' => ap_timestamp(),
    ];
}

function build_ordered_collection(string $id, int $totalItems, ?string $firstPage = null): array {
    $col = [
        '@context'   => 'https://www.w3.org/ns/activitystreams',
        'id'         => $id,
        'type'       => 'OrderedCollection',
        'totalItems' => $totalItems,
    ];
    if ($firstPage !== null) {
        $col['first'] = $firstPage;
    }
    return $col;
}

function build_ordered_collection_page(string $id, string $partOf, array $items, ?string $next = null): array {
    $page = [
        '@context'     => 'https://www.w3.org/ns/activitystreams',
        'id'           => $id,
        'type'         => 'OrderedCollectionPage',
        'partOf'       => $partOf,
        'orderedItems' => $items,
    ];
    if ($next !== null) {
        $page['next'] = $next;
    }
    return $page;
}

/**
 * Load tags (hashtags + mentions) for a post from DB.
 */
function load_post_tags(int $postId): array {
    $hashtags = db_all("SELECT tag FROM hashtags WHERE post_id = ?", [$postId]);
    $mentions = db_all("SELECT actor_uri, username FROM mentions WHERE post_id = ?", [$postId]);
    return ['hashtags' => $hashtags, 'mentions' => $mentions];
}

/**
 * Load media attachments for a post from DB.
 */
function load_post_attachments(int $postId): array {
    return db_all(
        "SELECT * FROM media_attachments WHERE post_id = ? ORDER BY id ASC",
        [$postId]
    );
}

/**
 * Build a full Create{Note} activity from a post DB row.
 */
function post_to_create_activity(array $post, array $account): array {
    $attachments = load_post_attachments((int)$post['id']);
    $tags        = load_post_tags((int)$post['id']);
    $note        = build_note($post, $account, $attachments, $tags);
    return build_create($note, $account);
}
