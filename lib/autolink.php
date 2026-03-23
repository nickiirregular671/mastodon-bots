<?php
declare(strict_types=1);

/**
 * Process raw text into HTML with autolinked URLs, #hashtags, @mentions.
 * Returns ['html' => string, 'hashtags' => string[], 'mentions' => array]
 */
function autolink_content(string $text, string $localDomain): array
{
    $hashtags = [];
    $mentions = [];

    // Escape HTML
    $html = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Newlines to <br>
    $html = nl2br($html);

    // Split on existing HTML tags so we only process plain text nodes.
    // Tokens alternate: text, tag, text, tag, ...
    $tokens = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

    $processed = [];
    foreach ($tokens as $token) {
        // Leave HTML tags untouched
        if (str_starts_with($token, '<')) {
            $processed[] = $token;
            continue;
        }

        // Autolink URLs
        $token = preg_replace_callback(
            '/https?:\/\/[^\s\<\>"\']+/i',
            static function (array $m): string {
                $url = rtrim($m[0], '.,!?);:\'">');
                $display = mb_strlen($url) > 50 ? mb_substr($url, 0, 50) . '…' : $url;
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"'
                    . ' rel="nofollow noopener noreferrer" target="_blank">'
                    . htmlspecialchars($display, ENT_QUOTES) . '</a>';
            },
            $token
        ) ?? $token;

        // After URL linking, re-split so hashtag/mention regexes skip new <a> tags
        $subTokens = preg_split('/(<[^>]+>)/u', $token, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$token];
        $subProcessed = [];
        foreach ($subTokens as $sub) {
            if (str_starts_with($sub, '<')) {
                $subProcessed[] = $sub;
                continue;
            }

            // Autolink #hashtags (text nodes only)
            $sub = preg_replace_callback(
                '/#([a-zA-Z0-9_\x{00C0}-\x{00FF}]+)/u',
                static function (array $m) use (&$hashtags, $localDomain): string {
                    $tag = $m[1];
                    $hashtags[] = strtolower($tag);
                    $href = 'https://' . $localDomain . '/tags/' . strtolower($tag);
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
                        . ' class="hashtag" rel="tag">#'
                        . htmlspecialchars($tag, ENT_QUOTES) . '</a>';
                },
                $sub
            ) ?? $sub;

            // Autolink @mentions (@user or @user@domain) (text nodes only)
            $sub = preg_replace_callback(
                '/@([a-zA-Z0-9_.-]+)(?:@([a-zA-Z0-9._-]+))?/u',
                static function (array $m) use (&$mentions, $localDomain): string {
                    $username = rtrim($m[1], '.-');
                    $domain = rtrim($m[2] ?? $localDomain, '.-');
                    $isLocal = ($domain === $localDomain);

                    if ($isLocal) {
                        $actorUri = 'https://' . $localDomain . '/users/' . $username;
                        $href = $actorUri;
                    } else {
                        $actorUri = 'acct:' . $username . '@' . $domain;
                        $href = 'https://' . $domain . '/@' . $username;
                    }

                    $mentions[] = [
                        'username' => $username,
                        'domain' => $domain,
                        'actor_uri' => $actorUri,
                    ];

                    $display = ($domain === $localDomain) ? "@{$username}" : "@{$username}@{$domain}";
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" class="mention">'
                        . htmlspecialchars($display, ENT_QUOTES) . '</a>';
                },
                $sub
            ) ?? $sub;

            $subProcessed[] = $sub;
        }
        $processed[] = implode('', $subProcessed);
    }

    $html = implode('', $processed);

    return [
        'html' => $html,
        'hashtags' => array_values(array_unique($hashtags)),
        'mentions' => $mentions,
    ];
}

/**
 * Resolve @mentions that still have an acct: URI to real actor URIs via WebFinger.
 */
function resolve_mentions(array &$mentions): void
{
    foreach ($mentions as &$m) {
        if (str_starts_with($m['actor_uri'], 'acct:')) {
            $uri = webfinger_lookup($m['username'], $m['domain']);
            if ($uri) {
                $m['actor_uri'] = $uri;
            }
        }
    }
    unset($m);
}

/**
 * Look up an actor's self URI via WebFinger.
 */
function webfinger_lookup(string $username, string $domain): ?string
{
    $url = 'https://' . $domain . '/.well-known/webfinger?resource='
        . urlencode('acct:' . $username . '@' . $domain);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/jrd+json, application/json'],
        CURLOPT_USERAGENT => 'ActivityPub-Bot/1.0',
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $code !== 200)
        return null;

    $data = json_decode((string) $response, true);
    foreach ($data['links'] ?? [] as $link) {
        if (($link['rel'] ?? '') === 'self' && isset($link['href'])) {
            return $link['href'];
        }
    }
    return null;
}
