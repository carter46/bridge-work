<?php

function hj_csrf_token(): string
{
    hj_session_start();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function hj_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(hj_csrf_token()) . '">';
}

/**
 * Call at the top of every POST handler.
 */
function hj_csrf_verify(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(hj_csrf_token(), $sent)) {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Session expired</title>'
            . '<p style="font-family:sans-serif;max-width:560px;margin:4rem auto">Your session expired or the form was submitted twice. '
            . '<a href="javascript:history.back()">Go back</a>, reload the page and try again.</p>';
        exit;
    }
}

function hj_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
