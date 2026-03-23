<?php
declare(strict_types=1);

require_once __DIR__ . '/crypto.php';

/**
 * Sign an outgoing HTTP POST request with HTTP Signatures (rsa-sha256).
 * Returns an array of headers to include in the cURL request.
 */
function http_sig_sign(string $targetUrl, string $body, array $account): array
{
    $parsed = parse_url($targetUrl);
    $host = $parsed['host'];
    $path = ($parsed['path'] ?? '/');
    if (!empty($parsed['query'])) {
        $path .= '?' . $parsed['query'];
    }

    $date = gmdate('D, d M Y H:i:s \G\M\T');
    $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
    $keyId = public_key_id($account['username']);

    $signingString = implode("\n", [
        "(request-target): post {$path}",
        "host: {$host}",
        "date: {$date}",
        "digest: {$digest}",
        "content-type: application/activity+json",
    ]);

    $privateKey = openssl_pkey_get_private($account['private_key']);
    if ($privateKey === false) {
        throw new RuntimeException('Invalid private key for account: ' . $account['username']);
    }

    openssl_sign($signingString, $rawSig, $privateKey, OPENSSL_ALGO_SHA256);
    $signature = base64_encode($rawSig);

    $sigHeader = 'keyId="' . $keyId . '",'
        . 'algorithm="rsa-sha256",'
        . 'headers="(request-target) host date digest content-type",'
        . 'signature="' . $signature . '"';

    return [
        'Date' => $date,
        'Digest' => $digest,
        'Signature' => $sigHeader,
        'Content-Type' => 'application/activity+json',
        'Host' => $host,
    ];
}

/**
 * Verify an incoming HTTP Signature on a POST request.
 * Returns the remote actor array on success, null on failure.
 */
function http_sig_verify(array $requestHeaders, string $method, string $requestPath, string $body): ?array
{
    $headers = [];
    foreach ($requestHeaders as $k => $v) {
        $headers[strtolower($k)] = $v;
    }

    $sigHeader = $headers['signature'] ?? '';
    if (empty($sigHeader))
        return null;

    // Reject stale requests (Date must be within 12 hours)
    $dateStr = $headers['date'] ?? '';
    if (!empty($dateStr)) {
        $ts = strtotime($dateStr);
        if ($ts === false || abs(time() - $ts) > 43200) {
            return null;
        }
    }

    // Enforce Digest on POST
    if (strtoupper($method) === 'POST' && !isset($headers['digest'])) {
        return null;
    }

    // Parse Signature header
    $params = [];
    foreach (explode(',', $sigHeader) as $part) {
        if (preg_match('/^\s*(\w+)="(.*)"\s*$/', trim($part), $m)) {
            $params[$m[1]] = $m[2];
        }
    }

    $keyId = $params['keyId'] ?? '';
    $sigHeaders = $params['headers'] ?? 'date';
    $signature = $params['signature'] ?? '';

    if (empty($keyId) || empty($signature))
        return null;

    // Verify Digest if present
    if (isset($headers['digest'])) {
        $expected = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
        if (!hash_equals($expected, $headers['digest'])) {
            return null;
        }
    }

    // Fetch remote actor to get public key
    $actor = fetch_remote_actor($keyId);
    if ($actor === null)
        return null;

    $publicKeyPem = $actor['publicKey']['publicKeyPem'] ?? '';
    if (empty($publicKeyPem))
        return null;

    // Rebuild signing string
    $signingParts = [];
    foreach (explode(' ', $sigHeaders) as $hName) {
        if ($hName === '(request-target)') {
            $signingParts[] = "(request-target): " . strtolower($method) . " {$requestPath}";
        } else {
            $signingParts[] = $hName . ': ' . ($headers[$hName] ?? '');
        }
    }
    $signingString = implode("\n", $signingParts);

    $pubKey = openssl_pkey_get_public($publicKeyPem);
    if ($pubKey === false)
        return null;

    $result = openssl_verify(
        $signingString,
        base64_decode($signature),
        $pubKey,
        OPENSSL_ALGO_SHA256
    );

    return ($result === 1) ? $actor : null;
}

/**
 * Fetch a remote ActivityPub actor document by URI.
 * Strips #fragment from keyId URIs.
 */
function fetch_remote_actor(string $uri): ?array
{
    // Strip fragment (e.g. #main-key)
    $actorUri = (string) preg_replace('/#.*$/', '', $uri);

    static $cache = [];
    if (isset($cache[$actorUri]))
        return $cache[$actorUri];

    $ch = curl_init($actorUri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/activity+json, application/ld+json',
            'User-Agent: ActivityPub-Bot/1.0 (+' . base_url() . ')',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($response === false || $httpCode !== 200)
        return null;

    $actor = json_decode((string) $response, true);
    if (!is_array($actor))
        return null;

    $cache[$actorUri] = $actor;
    return $actor;
}

/**
 * Get all request headers as an associative array.
 */
function get_all_request_headers(): array
{
    if (function_exists('getallheaders')) {
        return getallheaders() ?: [];
    }
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_')) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = $v;
        }
    }
    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    }
    if (isset($_SERVER['CONTENT_LENGTH'])) {
        $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
    }
    return $headers;
}
