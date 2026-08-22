<?php

/**
 * HTTPS OAuth relay for Shopee Open Platform (Shopee rejects raw IP redirect URLs).
 * Register this URL in the Shopee app as Redirect URL:
 *   https://<your-domain>/shopeebot.php
 *
 * Forwards code + shop_id to the Laravel OAuth callback.
 */
$callbackBase = getenv('SHOPEE_OAUTH_CALLBACK_URL') ?: 'https://aria.corenationactive.com/shopee-ads/oauth/callback';

$query = $_GET;
$target = $callbackBase;

if ($query !== []) {
    $target .= (str_contains($callbackBase, '?') ? '&' : '?').http_build_query($query);
}

header('Location: '.$target, true, 302);
exit;
