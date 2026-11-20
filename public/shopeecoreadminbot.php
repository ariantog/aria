<?php

/**
 * Shopee OAuth callback relay for the CORENATION ADMIN Open Platform app (HTTPS only).
 *
 * Register in Shopee Open Platform → CORENATION ADMIN app → Redirect URL, e.g.:
 *   https://cdn.corenationactive.com/shopeecoreadminbot.php
 *
 * Forwards to Laravel {@see route('shopee-admin.oauth.callback')} for token exchange.
 * Do not reuse shopeestockbot.php — STOCK CHECKER and CORENATION ADMIN use different Live Partner IDs.
 */

const LARAVEL_OAUTH_CALLBACK = 'https://aria3.corenationactive.com/shopee-admin/oauth/callback';

const SHARED_STATE = '';

$callbackBase = getenv('SHOPEE_COREADMIN_OAUTH_CALLBACK_URL') ?: LARAVEL_OAUTH_CALLBACK;

$code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
$shopId = isset($_GET['shop_id']) ? trim((string) $_GET['shop_id']) : '';
$state = isset($_GET['state']) ? trim((string) $_GET['state']) : '';

header('Content-Type: text/html; charset=utf-8');

function shopee_coreadmin_relay_render(string $title, string $body, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>'.htmlspecialchars($title).'</title></head>';
    echo '<body style="font-family:sans-serif;max-width:640px;margin:40px auto;padding:0 16px;line-height:1.5">';
    echo $body;
    echo '</body></html>';
    exit;
}

if ($code === '' || $shopId === '') {
    shopee_coreadmin_relay_render(
        'Missing code/shop_id',
        '<h1>Missing authorization parameters</h1>'
        .'<p>Shopee did not provide both <code>code</code> and <code>shop_id</code>. '
        .'Restart from <strong>Authorize Shopee (Admin)</strong> on Omnichannel.</p>',
        400
    );
}

if (SHARED_STATE !== '' && ! hash_equals(SHARED_STATE, $state)) {
    shopee_coreadmin_relay_render(
        'Invalid state',
        '<h1>Invalid state parameter</h1>'
        .'<p>This request did not originate from the expected authorization link.</p>',
        403
    );
}

$query = $_GET;
$target = $callbackBase;

if ($query !== []) {
    $target .= (str_contains($callbackBase, '?') ? '&' : '?').http_build_query($query);
}

header('Location: '.$target, true, 302);
exit;