<?php
declare(strict_types=1);

function generate_rsa_keypair(): array {
    $config = [
        'digest_alg'       => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    $res = openssl_pkey_new($config);
    if ($res === false) {
        throw new RuntimeException('Failed to generate RSA key pair: ' . openssl_error_string());
    }

    openssl_pkey_export($res, $privateKey);
    $details  = openssl_pkey_get_details($res);
    $publicKey = $details['key'];

    return [
        'public'  => $publicKey,
        'private' => $privateKey,
    ];
}

function public_key_id(string $username): string {
    return actor_url($username) . '#main-key';
}
