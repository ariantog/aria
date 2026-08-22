"""
oauth_server.py
===============

A tiny stdlib HTTP server implementing the **Shopee OAuth callback**.

Flow
----
1. The admin opens the authorization URL (via `/authorize`) and approves the app
   for their shop.
2. Shopee redirects the browser to the registered redirect URI with `code` and
   `shop_id` query params, e.g.:

       https://<relay-domain>/shopeebot.php?code=XXXX&shop_id=123456
       (the PHP relay forwards to http://<VPS_IP>:8090/shopee/callback?...)

3. This server captures `code` + `shop_id`, exchanges them for an access_token
   (+ refresh_token) via the injected handler, and shows a success page.

Runs in a daemon thread so it coexists with the asyncio Telegram bot. The token
exchange is delegated to an injected callback to keep this module decoupled.

The redirect URI you register in the Shopee console MUST match
`CONFIG.oauth_redirect_uri`. Shopee rejects raw-IP URLs, so use the HTTPS relay
(shopeebot.php) and open the callback port (default 8090) in the firewall.
"""

from __future__ import annotations

import logging
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Callable, Optional
from urllib.parse import parse_qs, urlparse

logger = logging.getLogger(__name__)

# (code, shop_id, state) -> result message string
AuthCodeHandler = Callable[[str, int, Optional[str]], str]


class _CallbackHandler(BaseHTTPRequestHandler):
    redirect_path: str = "/shopee/callback"
    on_auth_code: Optional[AuthCodeHandler] = None

    def log_message(self, fmt: str, *args) -> None:  # noqa: A003
        logger.info("oauth-server %s - %s", self.address_string(), fmt % args)

    def _respond(self, status: int, html: str) -> None:
        body = html.encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)
        if parsed.path == "/health":
            self._respond(200, "<h1>OK</h1>")
            return

        if parsed.path.rstrip("/") != self.redirect_path.rstrip("/"):
            self._respond(404, "<h1>404 Not Found</h1>")
            return

        qs = parse_qs(parsed.query)
        code = (qs.get("code") or [""])[0]
        shop_id_raw = (qs.get("shop_id") or [""])[0]
        state = (qs.get("state") or [None])[0]

        if not code or not shop_id_raw:
            self._respond(
                400,
                "<h1>Missing code/shop_id</h1><p>Shopee did not provide both a "
                "code and a shop_id in the redirect.</p>",
            )
            return

        try:
            shop_id = int(shop_id_raw)
        except ValueError:
            self._respond(400, "<h1>Invalid shop_id</h1>")
            return

        try:
            message = "Authorization received."
            if self.on_auth_code is not None:
                message = self.on_auth_code(code, shop_id, state)
            self._respond(
                200,
                "<html><body style='font-family:sans-serif'>"
                "<h1>✅ Shopee Authorization Successful</h1>"
                f"<p>{message}</p>"
                "<p>You may close this tab and return to Telegram.</p>"
                "</body></html>",
            )
        except Exception as exc:  # noqa: BLE001
            logger.exception("Auth code handling failed")
            self._respond(
                500,
                "<html><body style='font-family:sans-serif'>"
                "<h1>❌ Authorization Failed</h1>"
                f"<pre>{exc}</pre></body></html>",
            )


class OAuthCallbackServer:
    def __init__(
        self,
        host: str,
        port: int,
        redirect_path: str,
        on_auth_code: AuthCodeHandler,
    ) -> None:
        self.host = host
        self.port = port
        self.redirect_path = redirect_path
        self.on_auth_code = on_auth_code
        self._httpd: Optional[ThreadingHTTPServer] = None
        self._thread: Optional[threading.Thread] = None

    def start(self) -> None:
        handler_cls = type(
            "BoundCallbackHandler",
            (_CallbackHandler,),
            {
                "redirect_path": self.redirect_path,
                "on_auth_code": staticmethod(self.on_auth_code),
            },
        )
        try:
            self._httpd = ThreadingHTTPServer((self.host, self.port), handler_cls)
        except OSError as exc:
            logger.error("Could not bind OAuth server on %s:%s -> %s", self.host, self.port, exc)
            return

        self._thread = threading.Thread(
            target=self._httpd.serve_forever, name="oauth-callback", daemon=True
        )
        self._thread.start()
        logger.info(
            "OAuth callback server listening on http://%s:%s%s",
            self.host, self.port, self.redirect_path,
        )

    def stop(self) -> None:
        if self._httpd is not None:
            try:
                self._httpd.shutdown()
                self._httpd.server_close()
            except Exception:  # noqa: BLE001
                pass
            logger.info("OAuth callback server stopped.")
