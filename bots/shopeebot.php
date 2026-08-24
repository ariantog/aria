<?php
/**
 * shopeebot.php — Shopee OAuth callback relay
 * ===========================================
 *
 * PLACEMENT:
 *   Upload this file to the document root of your HTTPS domain so it is reachable
 *   at, e.g.:   https://cdn.corenationactive.com/shopeebot.php
 *
 * REGISTER THIS URL in the Shopee Open Platform console as the app's
 *   "Redirect URL":   https://cdn.corenationactive.com/shopeebot.php
 *   (Shopee rejects raw-IP redirect URLs, which is why this HTTPS relay exists.)
 *
 * WHAT IT DOES:
 *   1. Shopee redirects the seller's browser here with ?code=...&shop_id=...
 *   2. This script forwards code + shop_id to the bot's internal callback on the
 *      VPS (http://<VPS_IP>:8090/shopee/callback), where the bot exchanges them
 *      for an access_token + refresh_token (the bot holds the Partner Key — this
 *      file needs NO secret).
 *   3. It shows the bot's success/error page to the seller.
 *
 * SECURITY:
 *   - Contains NO secrets. It only relays a one-time, short-lived `code`.
 *   - Optionally set SHARED_STATE to a hard-to-guess value and use the same value
 *     in the bot's authorization link to reject random hits.
 */

// ---------------------------------------------------------------------------
// CONFIG — change BOT_CALLBACK if your VPS IP / port / path ever changes.
// (Shopee bot uses port 8090 so it never collides with the TikTok bot's 8080.)
// ---------------------------------------------------------------------------
const BOT_CALLBACK = 'http://109.123.255.0:8090/shopee/callback';

// Optional CSRF-style guard. Leave '' to disable.
const SHARED_STATE = '';

// HTTP timeout (seconds) when talking to the bot.
const RELAY_TIMEOUT = 15;

// ---------------------------------------------------------------------------
// 1) Read the parameters Shopee appended to the redirect.
// ---------------------------------------------------------------------------
$code    = isset($_GET['code'])    ? trim($_GET['code'])    : '';
$shop_id = isset($_GET['shop_id']) ? trim($_GET['shop_id']) : '';
$state   = isset($_GET['state'])   ? trim($_GET['state'])   : '';

header('Content-Type: text/html; charset=utf-8');

function render_page(string $title, string $body, int $http_code = 200): void {
    http_response_code($http_code);
    echo "<!doctype html><html><head><meta charset='utf-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1'>";
    echo "<title>{$title}</title></head>";
    echo "<body style='font-family:sans-serif;max-width:640px;margin:40px auto;padding:0 16px;line-height:1.5'>";
    echo $body;
    echo "</body></html>";
    exit;
}

// ---------------------------------------------------------------------------
// 2) Validate input.
// ---------------------------------------------------------------------------
if ($code === '' || $shop_id === '') {
    render_page(
        'Missing code/shop_id',
        "<h1>❌ Missing authorization parameters</h1>"
        . "<p>Shopee did not provide both <code>code</code> and <code>shop_id</code> "
        . "in the redirect. Please restart from the Telegram bot (<code>/authorize</code>).</p>",
        400
    );
}

if (SHARED_STATE !== '' && !hash_equals(SHARED_STATE, $state)) {
    render_page(
        'Invalid state',
        "<h1>❌ Invalid state parameter</h1>"
        . "<p>This request did not originate from the expected authorization link.</p>",
        403
    );
}

// ---------------------------------------------------------------------------
// 3) Relay code + shop_id to the bot's internal callback on the VPS.
// ---------------------------------------------------------------------------
$relay_url = BOT_CALLBACK . '?' . http_build_query([
    'code'    => $code,
    'shop_id' => $shop_id,
    'state'   => $state,
]);

$bot_response = '';
$curl_error   = '';
$http_status  = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($relay_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => RELAY_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $bot_response = curl_exec($ch);
    if ($bot_response === false) {
        $curl_error = curl_error($ch);
    }
    $http_status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => ['timeout' => RELAY_TIMEOUT]]);
    $bot_response = @file_get_contents($relay_url, false, $ctx);
    if ($bot_response === false) {
        $curl_error = 'file_get_contents failed (is allow_url_fopen enabled?)';
    } else {
        $http_status = 200;
    }
}

// ---------------------------------------------------------------------------
// 4) Show the result.
// ---------------------------------------------------------------------------
if ($bot_response !== '' && $bot_response !== false && $http_status >= 200 && $http_status < 400) {
    echo $bot_response;
    exit;
}

render_page(
    'Authorization relay failed',
    "<h1>⚠️ Could not reach the bot</h1>"
    . "<p>The authorization code was received, but forwarding it to the bot on "
    . "the VPS failed.</p>"
    . "<p><strong>HTTP status:</strong> " . htmlspecialchars((string) $http_status) . "</p>"
    . ($curl_error ? "<p><strong>Error:</strong> " . htmlspecialchars($curl_error) . "</p>" : "")
    . "<p>Check that the bot service is running and that port 8090 is open on "
    . "the VPS firewall, then retry <code>/authorize</code> in Telegram.</p>",
    502
);
