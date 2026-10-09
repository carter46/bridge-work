<?php

/**
 * 32-byte application key from config, or storage/app.key (generated on first use).
 */
function hj_app_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $hex = trim((string)hj_config('app_key', ''));
    if ($hex === '') {
        $file = HJ_ROOT . '/storage/app.key';
        if (is_file($file)) {
            $hex = trim((string)file_get_contents($file));
        } else {
            hj_ensure_dir(dirname($file));
            $hex = bin2hex(random_bytes(32));
            if (@file_put_contents($file, $hex, LOCK_EX) === false) {
                error_log('[hubjob] Could not write storage/app.key; set app_key in config/config.php');
            } else {
                @chmod($file, 0600);
            }
        }
    }
    if (!preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
        // Derive a stable 32-byte key from any non-hex value instead of failing.
        $key = hash('sha256', $hex, true);
    } else {
        $key = hex2bin($hex);
    }
    return $key;
}

function hj_encrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $key = hj_app_key();
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'v2:' . base64_encode($iv . $tag . $cipher);
}

function hj_decrypt(string $stored): string
{
    if ($stored === '') {
        return '';
    }
    $key = hj_app_key();
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false) {
        return '';
    }
    if (strpos($stored, 'v1:') === 0 && function_exists('sodium_crypto_secretbox_open')) {
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
        return $plain === false ? '' : $plain;
    }
    if (strpos($stored, 'v2:') === 0) {
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
    return '';
}

function hj_sign(string $data): string
{
    return hash_hmac('sha256', $data, hj_app_key());
}

/**
 * Signed, time-stamped token for the public application form (spam timing check).
 */
function hj_form_token(): string
{
    $payload = time() . '.' . bin2hex(random_bytes(8));
    return $payload . '.' . hj_sign('form|' . $payload);
}

/**
 * Returns the token's issue time, or null if the token is malformed or forged.
 */
function hj_form_token_time(string $token): ?int
{
    $parts = explode('.', $token);
    if (count($parts) !== 3 || !ctype_digit($parts[0])) {
        return null;
    }
    $payload = $parts[0] . '.' . $parts[1];
    if (!hash_equals(hj_sign('form|' . $payload), $parts[2])) {
        return null;
    }
    return (int)$parts[0];
}
