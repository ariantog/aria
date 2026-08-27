"""
shopee_api.py
=============

Async client for the **Shopee Open Platform API v2** (ads module), tailored to
the four ad types this bot manages.

Authentication (v2)
-------------------
* Host: https://partner.shopeemobile.com
* Public sign (auth/token endpoints):
      base = f"{partner_id}{api_path}{timestamp}"
      sign = HMAC_SHA256(base, partner_key)
* Shop sign (all shop-scoped endpoints, incl. /api/v2/ads/*):
      base = f"{partner_id}{api_path}{timestamp}{access_token}{shop_id}"
      sign = HMAC_SHA256(base, partner_key)
* Common query params on every call: partner_id, timestamp, sign
  (+ access_token & shop_id for shop-scoped calls).

Response envelope
-----------------
Shopee returns HTTP 200 with a JSON body:
    { "error": "", "message": "", "warning": "", "request_id": "...",
      "response": { ... } }
`error == ""` means success. Any non-empty `error` is a logical failure and is
raised as `ShopeeAPIError`.

Notes
-----
* Ads budgets in Shopee are set in the shop's local currency **major unit**
  (e.g. IDR). We treat all budgets as plain IDR floats.
* This client abstracts the four ad types behind a small, uniform surface:
  `get_ads_of_type()`, `set_ad_budget()`, `set_ad_enabled()`, plus
  `get_ad_groups()` / per-group ROAS helpers for Iklan Group.
* The exact Shopee edit endpoints differ per ad type; the request builders here
  centralise those differences so the engine stays clean. Where Shopee's schema
  evolves, only this file needs updating.
"""

from __future__ import annotations

import hashlib
import hmac
import logging
import time
from typing import Any, Dict, List, Optional

import httpx

from config import (
    AD_TYPE_GROUP,
    AD_TYPE_PRODUK_AUTO,
    AD_TYPE_TOKO_AUTO,
    AD_TYPE_TOKO_MANUAL,
    Settings,
)

logger = logging.getLogger(__name__)


class ShopeeAPIError(Exception):
    """Raised when Shopee returns a non-empty `error` field or a transport error."""

    def __init__(self, message: str, error: str = "", request_id: str = "") -> None:
        super().__init__(message)
        self.error = error
        self.request_id = request_id


# Map our internal ad types to the Shopee ads `ad_type` filter used by
# get_product_level_campaign_id_list and performance endpoints.
# Shopee product ads use "auto" / "manual"; shop ads use their own endpoints.
_SHOPEE_AD_TYPE_FILTER = {
    AD_TYPE_TOKO_AUTO: "auto",     # shop auto / booster
    AD_TYPE_TOKO_MANUAL: "manual",  # shop manual
    AD_TYPE_PRODUK_AUTO: "auto",   # product auto
    AD_TYPE_GROUP: "manual",       # ad groups are manual product campaigns
}


class ShopeeAdsClient:
    """Thin async wrapper over the Shopee Open Platform v2 ads endpoints."""

    def __init__(self, cfg: Settings, token_persist_cb=None) -> None:
        self.cfg = cfg
        self._client = httpx.AsyncClient(
            base_url=cfg.shopee_api_base.rstrip("/"),
            timeout=httpx.Timeout(30.0, connect=10.0),
        )
        # Optional callback invoked after a successful auto-refresh with
        # (access_token, refresh_token). Used by the bot to persist the new
        # tokens to .env so they survive restarts.
        self._token_persist_cb = token_persist_cb
        # Serialise refreshes so concurrent 401s don't trigger a stampede of
        # refresh calls (Shopee invalidates a refresh_token once it's used).
        import asyncio as _asyncio

        self._refresh_lock = _asyncio.Lock()

    def set_token_persist_cb(self, cb) -> None:
        """Register the callback used to persist refreshed tokens."""
        self._token_persist_cb = cb

    async def aclose(self) -> None:
        await self._client.aclose()

    # ------------------------------------------------------------------ #
    # Auto token refresh
    # ------------------------------------------------------------------ #
    async def _refresh_and_persist(self, *, expected_old_token: str = "") -> bool:
        """
        Refresh the access token and persist it via the callback.

        Serialised by ``_refresh_lock``. If another coroutine already refreshed
        the token while we were waiting for the lock (i.e. the current token no
        longer matches ``expected_old_token``), we skip and report success so
        the caller simply retries with the already-fresh token.

        Returns True when a usable access token is in place afterwards.
        """
        async with self._refresh_lock:
            # Someone else may have refreshed while we waited for the lock.
            if expected_old_token and self.cfg.shopee_access_token != expected_old_token:
                return True
            if not self.cfg.shopee_refresh_token or not self.cfg.shopee_shop_id:
                logger.error("[auth] cannot auto-refresh: missing refresh_token/shop_id.")
                return False
            try:
                tokens = await self.refresh_access_token()
            except Exception:  # noqa: BLE001
                logger.exception("[auth] auto-refresh call failed.")
                return False
            access = tokens.get("access_token") or ""
            refresh = tokens.get("refresh_token") or ""
            if not access:
                logger.error("[auth] auto-refresh returned no access_token: %s", tokens)
                return False
            # Update the in-memory (frozen) config in place.
            object.__setattr__(self.cfg, "shopee_access_token", access)
            if refresh:
                object.__setattr__(self.cfg, "shopee_refresh_token", refresh)
            # Persist so the new tokens survive a restart.
            if self._token_persist_cb is not None:
                try:
                    self._token_persist_cb(access, refresh)
                except Exception:  # noqa: BLE001
                    logger.exception("[auth] token persist callback failed (non-fatal).")
            logger.info("[auth] access token auto-refreshed and persisted.")
            return True

    # ------------------------------------------------------------------ #
    # Signing helpers
    # ------------------------------------------------------------------ #
    def _sign(self, base_string: str) -> str:
        return hmac.new(
            self.cfg.shopee_partner_key.encode("utf-8"),
            base_string.encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()

    def _public_params(self, api_path: str) -> Dict[str, Any]:
        ts = int(time.time())
        base = f"{self.cfg.shopee_partner_id}{api_path}{ts}"
        return {
            "partner_id": self.cfg.shopee_partner_id,
            "timestamp": ts,
            "sign": self._sign(base),
        }

    def _shop_params(self, api_path: str) -> Dict[str, Any]:
        ts = int(time.time())
        token = self.cfg.shopee_access_token
        shop_id = self.cfg.shopee_shop_id
        base = f"{self.cfg.shopee_partner_id}{api_path}{ts}{token}{shop_id}"
        return {
            "partner_id": self.cfg.shopee_partner_id,
            "timestamp": ts,
            "access_token": token,
            "shop_id": shop_id,
            "sign": self._sign(base),
        }

    # ------------------------------------------------------------------ #
    # Low-level request
    # ------------------------------------------------------------------ #
    # Shopee error strings that mean "the access token is stale/invalid" and a
    # refresh + retry is worth attempting.
    _TOKEN_ERRORS = frozenset(
        {
            "invalid_access_token",
            "invalid_acceess_token",  # Shopee's own typo, seen in the wild
            "error_auth",
            "access_token_expired",
            "token_expired",
        }
    )

    async def _request_once(
        self,
        method: str,
        api_path: str,
        *,
        public: bool = False,
        query: Optional[Dict[str, Any]] = None,
        body: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        params = self._public_params(api_path) if public else self._shop_params(api_path)
        if query:
            params.update(query)

        try:
            resp = await self._client.request(
                method.upper(), api_path, params=params, json=body
            )
        except httpx.HTTPError as exc:
            raise ShopeeAPIError(f"HTTP transport error calling {api_path}: {exc}") from exc

        # A stale token can surface either as HTTP 403 with an error body OR as
        # HTTP 200 with a non-empty `error`. Parse the body in both cases so the
        # retry wrapper can decide whether to refresh.
        try:
            data = resp.json()
        except Exception:  # noqa: BLE001
            data = None

        if resp.status_code != 200:
            err = str((data or {}).get("error", "") or "") if isinstance(data, dict) else ""
            raise ShopeeAPIError(
                f"HTTP {resp.status_code} from {api_path}: {resp.text[:300]}",
                error=err,
            )

        if not isinstance(data, dict):
            raise ShopeeAPIError(f"Invalid JSON from {api_path}: {resp.text[:300]}")

        # Shopee success => error == "" (empty string)
        err = str(data.get("error", "") or "")
        if err:
            raise ShopeeAPIError(
                f"Shopee API error on {api_path}: {err} - {data.get('message', '')}",
                error=err,
                request_id=str(data.get("request_id", "")),
            )
        return data

    async def _request(
        self,
        method: str,
        api_path: str,
        *,
        public: bool = False,
        query: Optional[Dict[str, Any]] = None,
        body: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        """
        Perform a signed request. For shop-scoped calls, if the access token is
        rejected as stale/invalid, transparently auto-refresh once and retry.
        Public (auth) calls are never retried this way.
        """
        try:
            return await self._request_once(
                method, api_path, public=public, query=query, body=body
            )
        except ShopeeAPIError as exc:
            if public or exc.error not in self._TOKEN_ERRORS:
                raise
            old_token = self.cfg.shopee_access_token
            logger.warning(
                "[auth] %s rejected token (%s); attempting auto-refresh + retry.",
                api_path, exc.error,
            )
            refreshed = await self._refresh_and_persist(expected_old_token=old_token)
            if not refreshed:
                raise
            # Retry exactly once with the fresh token (signature is rebuilt).
            return await self._request_once(
                method, api_path, public=public, query=query, body=body
            )

    # ------------------------------------------------------------------ #
    # OAuth
    # ------------------------------------------------------------------ #
    def build_authorization_url(self, state: str = "") -> str:
        """
        Build the Shopee seller authorization URL. Sending the seller here lets
        them approve the app; Shopee then redirects to `redirect` with `code` and
        `shop_id` query params.
        """
        api_path = "/api/v2/shop/auth_partner"
        ts = int(time.time())
        base = f"{self.cfg.shopee_partner_id}{api_path}{ts}"
        sign = self._sign(base)
        redirect = self.cfg.oauth_redirect_uri
        url = (
            f"{self.cfg.shopee_api_base.rstrip('/')}{api_path}"
            f"?partner_id={self.cfg.shopee_partner_id}"
            f"&timestamp={ts}"
            f"&sign={sign}"
            f"&redirect={redirect}"
        )
        if state:
            url += f"&state={state}"
        return url

    async def exchange_code_for_token(self, code: str, shop_id: int) -> Dict[str, Any]:
        """
        Exchange the OAuth `code` (with the returned shop_id) for an
        access_token + refresh_token via /api/v2/auth/token/get.
        """
        api_path = "/api/v2/auth/token/get"
        body = {
            "code": code,
            "shop_id": int(shop_id),
            "partner_id": self.cfg.shopee_partner_id,
        }
        data = await self._request("POST", api_path, public=True, body=body)
        # Response typically at top level (not under "response") for token/get.
        access = data.get("access_token") or data.get("response", {}).get("access_token", "")
        refresh = data.get("refresh_token") or data.get("response", {}).get("refresh_token", "")
        return {
            "access_token": access,
            "refresh_token": refresh,
            "shop_id": int(shop_id),
            "expire_in": data.get("expire_in") or data.get("response", {}).get("expire_in"),
        }

    async def refresh_access_token(self) -> Dict[str, Any]:
        """Refresh the access token using the stored refresh token."""
        api_path = "/api/v2/auth/access_token/get"
        body = {
            "refresh_token": self.cfg.shopee_refresh_token,
            "shop_id": self.cfg.shopee_shop_id,
            "partner_id": self.cfg.shopee_partner_id,
        }
        data = await self._request("POST", api_path, public=True, body=body)
        access = data.get("access_token") or data.get("response", {}).get("access_token", "")
        refresh = data.get("refresh_token") or data.get("response", {}).get("refresh_token", "")
        return {"access_token": access, "refresh_token": refresh}

    # ------------------------------------------------------------------ #
    # Generic ads helpers
    # ------------------------------------------------------------------ #
    async def get_total_balance(self) -> float:
        """Real-time ads credit balance (sanity/health check)."""
        data = await self._request("GET", "/api/v2/ads/get_total_balance")
        return float(data.get("response", {}).get("total_balance", 0.0))

    @staticmethod
    def _today_ddmmyyyy() -> str:
        # Shopee performance endpoints use DD-MM-YYYY. Use Jakarta date.
        from datetime import datetime
        from zoneinfo import ZoneInfo

        return datetime.now(ZoneInfo("Asia/Jakarta")).strftime("%d-%m-%Y")

    async def _campaign_id_list(self, ad_type_filter: str) -> List[int]:
        """List campaign ids for a given Shopee ad_type filter ('auto'/'manual'/'all')."""
        ids: List[int] = []
        offset = 0
        limit = 50
        while True:
            data = await self._request(
                "GET",
                "/api/v2/ads/get_product_level_campaign_id_list",
                query={"ad_type": ad_type_filter, "offset": offset, "limit": limit},
            )
            resp = data.get("response", {}) or {}
            batch = resp.get("campaign_list", []) or resp.get("campaign_id_list", []) or []
            for entry in batch:
                cid = entry.get("campaign_id") if isinstance(entry, dict) else entry
                if cid is not None:
                    ids.append(int(cid))
            if not resp.get("has_next_page") or not batch:
                break
            offset += limit
        return ids

    async def _campaign_setting_info(self, campaign_ids: List[int]) -> List[Dict[str, Any]]:
        """Fetch settings (name, budget, status) for up to 100 campaign ids."""
        if not campaign_ids:
            return []
        out: List[Dict[str, Any]] = []
        for i in range(0, len(campaign_ids), 100):
            chunk = campaign_ids[i : i + 100]
            data = await self._request(
                "GET",
                "/api/v2/ads/get_product_level_campaign_setting_info",
                query={
                    "info_type_list": "1,2,3,4",
                    "campaign_id_list": ",".join(str(c) for c in chunk),
                },
            )
            resp = data.get("response", {}) or {}
            for c in resp.get("campaign_list", []) or []:
                common = c.get("common_info", {}) or {}
                budget_info = c.get("manual_bidding_info", {}) or c.get("auto_bidding_info", {}) or {}
                out.append(
                    {
                        "campaign_id": str(c.get("campaign_id")),
                        "campaign_name": common.get("ad_name") or c.get("ad_name") or f"Campaign {c.get('campaign_id')}",
                        "budget": float(
                            budget_info.get("campaign_budget")
                            or common.get("campaign_budget")
                            or c.get("campaign_budget")
                            or 0.0
                        ),
                        "status": (common.get("campaign_status") or c.get("campaign_status") or "").lower(),
                        "ad_type_raw": common.get("ad_type") or c.get("ad_type") or "",
                        "raw": c,
                    }
                )
        return out

    async def _roas_by_campaign(self, campaign_ids: List[int]) -> Dict[str, float]:
        """Return {campaign_id: broad_roi} for today, for the given campaigns."""
        roas: Dict[str, float] = {}
        if not campaign_ids:
            return roas
        today = self._today_ddmmyyyy()
        for i in range(0, len(campaign_ids), 100):
            chunk = campaign_ids[i : i + 100]
            try:
                data = await self._request(
                    "GET",
                    "/api/v2/ads/get_product_campaign_daily_performance",
                    query={
                        "start_date": today,
                        "end_date": today,
                        "campaign_id_list": ",".join(str(c) for c in chunk),
                    },
                )
            except ShopeeAPIError as exc:
                logger.warning("ROAS fetch failed for chunk: %s", exc)
                continue
            resp = data.get("response", {}) or {}
            for shop in resp.get("campaign_list", resp.get("campaign_data_list", [])) or []:
                # daily performance nests per-campaign metrics_list
                cid = str(shop.get("campaign_id", ""))
                metrics = shop.get("metrics_list", []) or []
                if metrics:
                    m = metrics[-1]
                    roas[cid] = float(m.get("broad_roi", m.get("broad_roas", 0.0)) or 0.0)
        return roas

    async def get_item_ads_roas(
        self, campaign_ids: List[int]
    ) -> Dict[str, float]:
        """
        Public: today's ROAS (broad_roi) keyed by campaign_id for individual
        (manual) product ads.

        This is the CORRECT source for per-item-ad ROAS. The GMV-Max item
        performance report (`get_gms_item_performance`) describes items inside
        the *GMV-Max* campaign, NOT standalone manual product ads, so it must
        never be used to score manual ads — doing so attaches the wrong ROAS
        (or 0.0) to each ad.
        """
        return await self._roas_by_campaign([int(c) for c in campaign_ids])

    # ------------------------------------------------------------------ #
    # Public surface used by the engine
    # ------------------------------------------------------------------ #
    async def get_ads_of_type(self, ad_type: str, only_active: bool = True) -> List[Dict[str, Any]]:
        """
        Return the ad(s)/campaign(s) for one of our four ad types, normalised to:
            {campaign_id, campaign_name, budget, status, ad_type_raw, raw}

        For the three "single ad" types this normally returns a single object,
        but we return a list for uniformity (and robustness if Shopee reports
        more than one).
        """
        flt = _SHOPEE_AD_TYPE_FILTER.get(ad_type, "all")
        ids = await self._campaign_id_list(flt)
        infos = await self._campaign_setting_info(ids)
        if only_active:
            infos = [c for c in infos if c["status"] in ("", "ongoing", "running", "active", "scheduled")]
        return infos

    async def get_ad_groups(self, only_active: bool = True) -> List[Dict[str, Any]]:
        """
        Return all Iklan Group ad groups with per-group ROAS, normalised to:
            {campaign_id, campaign_name, budget, status, roas, raw}
        """
        ids = await self._campaign_id_list(_SHOPEE_AD_TYPE_FILTER[AD_TYPE_GROUP])
        infos = await self._campaign_setting_info(ids)
        if only_active:
            infos = [c for c in infos if c["status"] in ("", "ongoing", "running", "active", "scheduled")]
        roas_map = await self._roas_by_campaign([int(c["campaign_id"]) for c in infos])
        for c in infos:
            c["roas"] = float(roas_map.get(c["campaign_id"], 0.0))
        return infos

    async def set_ad_budget(
        self, ad_type: str, campaign_id: str, new_budget: float
    ) -> Dict[str, Any]:
        """
        Update the daily budget of one ad/campaign. Shopee uses different edit
        endpoints per ad type; we route accordingly.
        """
        new_budget = round(float(new_budget), 2)
        if ad_type == AD_TYPE_PRODUK_AUTO or ad_type == AD_TYPE_TOKO_AUTO:
            # Auto product / shop-booster ads use edit_auto_product_ads
            api_path = "/api/v2/ads/edit_auto_product_ads"
            body = {"campaign_id": int(campaign_id), "budget": new_budget}
        else:
            # Manual product ads & ad groups use edit_manual_product_ads
            api_path = "/api/v2/ads/edit_manual_product_ads"
            body = {"campaign_id": int(campaign_id), "budget": new_budget}
        return await self._request("POST", api_path, body=body)

    async def set_ad_enabled(
        self, ad_type: str, campaign_id: str, enabled: bool
    ) -> Dict[str, Any]:
        """
        Turn an ad group ON or OFF (NOT pause). For Iklan Group we set the
        campaign status; a turned-off group keeps running under Iklan Produk
        Otomatis, whereas pause would hide it entirely.

        Shopee edit endpoints accept an `action`/`status` toggle; we send
        "close" to turn off and "open" to turn on.
        """
        api_path = "/api/v2/ads/edit_manual_product_ads"
        body = {
            "campaign_id": int(campaign_id),
            "action": "open" if enabled else "close",
        }
        return await self._request("POST", api_path, body=body)

    # ------------------------------------------------------------------ #
    # GMV-Max (GMS) campaign  -- the shop's live "Iklan Produk GMV Max"
    #
    # Shopee's GMV-Max Shop campaign is ONE campaign with ONE campaign-level
    # daily_budget. Live budget is read via get_gms_campaign_performance (preferred)
    # and get_product_level_campaign_setting_info before falling back to DB tracking.
    # ------------------------------------------------------------------ #
    @staticmethod
    def _date_range_ddmmyyyy(days_back: int = 0) -> tuple:
        from datetime import datetime, timedelta
        from zoneinfo import ZoneInfo

        wib = ZoneInfo("Asia/Jakarta")
        end = datetime.now(wib)
        start = end - timedelta(days=max(0, days_back))
        return start.strftime("%d-%m-%Y"), end.strftime("%d-%m-%Y")

    async def get_gms_campaign(self, days_back: int = 0) -> Optional[Dict[str, Any]]:
        """
        Discover the shop's live GMV-Max campaign and its recent performance.

        Returns:
            {campaign_id, expense, gmv, roas, clicks, impression, orders, raw}
        or None if there is no active GMV-Max campaign / the read fails.

        The date window defaults to today; pass days_back>0 for a wider ROAS
        sample (Shopee allows up to ~3 months, 6 months back).
        """
        start, end = self._date_range_ddmmyyyy(days_back)
        try:
            data = await self._request(
                "POST",
                "/api/v2/ads/get_gms_campaign_performance",
                body={"start_date": start, "end_date": end},
            )
        except ShopeeAPIError as exc:
            logger.warning("get_gms_campaign_performance failed: %s", exc)
            return None
        resp = data.get("response", {}) or {}
        cid = resp.get("campaign_id")
        if not cid:
            return None
        rep = resp.get("report", {}) or {}
        return {
            "campaign_id": str(cid),
            "expense": float(rep.get("expense", 0.0) or 0.0),
            "gmv": float(rep.get("broad_gmv", rep.get("gmv", 0.0)) or 0.0),
            "roas": float(rep.get("broad_roi", rep.get("roas", 0.0)) or 0.0),
            "clicks": int(rep.get("clicks", 0) or 0),
            "impression": int(rep.get("impression", 0) or 0),
            "orders": int(rep.get("broad_order", rep.get("orders", 0)) or 0),
            "raw": resp,
        }

    async def get_gms_item_performance(
        self, days_back: int = 0, limit: int = 100
    ) -> List[Dict[str, Any]]:
        """
        Per-item performance inside the GMV-Max campaign, normalised to:
            {item_id, roas, expense, gmv, clicks, impression, orders}
        Sorted by ROAS descending. Paginated internally. Best-effort ([] on fail).
        """
        start, end = self._date_range_ddmmyyyy(days_back)
        out: List[Dict[str, Any]] = []
        offset = 0
        page = max(1, min(int(limit), 100))
        while True:
            try:
                data = await self._request(
                    "POST",
                    "/api/v2/ads/get_gms_item_performance",
                    body={"start_date": start, "end_date": end,
                          "offset": offset, "limit": page},
                )
            except ShopeeAPIError as exc:
                logger.warning("get_gms_item_performance failed: %s", exc)
                break
            resp = data.get("response", {}) or {}
            for it in resp.get("result_list", []) or []:
                rep = it.get("report", {}) or {}
                out.append(
                    {
                        "item_id": int(it.get("item_id", 0) or 0),
                        "roas": float(rep.get("broad_roi", rep.get("roas", 0.0)) or 0.0),
                        "expense": float(rep.get("expense", 0.0) or 0.0),
                        "gmv": float(rep.get("broad_gmv", rep.get("gmv", 0.0)) or 0.0),
                        "clicks": int(rep.get("clicks", 0) or 0),
                        "impression": int(rep.get("impression", 0) or 0),
                        "orders": int(rep.get("broad_order", rep.get("orders", 0)) or 0),
                    }
                )
            if not resp.get("has_next_page"):
                break
            offset += page
            if offset > 1000:  # hard safety stop
                break
        out.sort(key=lambda x: x["roas"], reverse=True)
        return out

    @staticmethod
    def _extract_campaign_budget_from_setting_row(row: Dict[str, Any]) -> Optional[float]:
        """Best-effort daily budget from campaign setting / GMS performance payloads."""
        common = row.get("common_info", {}) or {}
        manual = row.get("manual_bidding_info", {}) or {}
        auto = row.get("auto_bidding_info", {}) or {}
        budget_info = row.get("budget_info", {}) or {}
        gms = row.get("gms_info", {}) or row.get("gms_campaign_info", {}) or row.get("shop_gms_info", {}) or {}

        candidates = [
            row.get("daily_budget"),
            row.get("campaign_budget"),
            budget_info.get("daily_budget"),
            budget_info.get("campaign_budget"),
            gms.get("daily_budget"),
            gms.get("campaign_budget"),
            common.get("daily_budget"),
            common.get("campaign_budget"),
            manual.get("daily_budget"),
            manual.get("campaign_budget"),
            auto.get("daily_budget"),
            auto.get("campaign_budget"),
        ]
        for value in candidates:
            if value is not None and float(value) > 0:
                return float(value)
        return None

    @staticmethod
    def _extract_gms_budget_from_performance_payload(resp: Dict[str, Any]) -> Optional[float]:
        budget = ShopeeAdsClient._extract_campaign_budget_from_setting_row(resp)
        if budget is not None:
            return budget
        for key in (
            "campaign_info",
            "campaign_setting",
            "gms_info",
            "gms_campaign_info",
            "setting",
            "common_info",
        ):
            block = resp.get(key)
            if isinstance(block, dict):
                budget = ShopeeAdsClient._extract_campaign_budget_from_setting_row(block)
                if budget is not None:
                    return budget
        return None

    @staticmethod
    def _is_gms_campaign_setting_row(row: Dict[str, Any], expected_campaign_id: str) -> bool:
        cid = str(row.get("campaign_id", ""))
        if cid == expected_campaign_id:
            return True
        common = row.get("common_info", {}) or {}
        ad_type = str(common.get("ad_type") or row.get("ad_type") or "").lower()
        if ad_type and ("gms" in ad_type or "gmv" in ad_type):
            return True
        return "gms_info" in row or "gms_campaign_info" in row

    async def _gms_budget_from_performance(self, campaign_id: str) -> Optional[float]:
        start, end = self._date_range_ddmmyyyy(0)
        try:
            data = await self._request(
                "POST",
                "/api/v2/ads/get_gms_campaign_performance",
                body={"start_date": start, "end_date": end},
            )
        except ShopeeAPIError as exc:
            logger.warning("get_gms_live_budget performance fetch failed for %s: %s", campaign_id, exc)
            return None
        resp = data.get("response", {}) or {}
        response_cid = str(resp.get("campaign_id", ""))
        if response_cid and response_cid != str(campaign_id):
            logger.warning(
                "get_gms_live_budget performance campaign_id mismatch (expected %s, got %s)",
                campaign_id,
                response_cid,
            )
        budget = self._extract_gms_budget_from_performance_payload(resp)
        if budget is None:
            logger.warning(
                "get_gms_live_budget: budget missing in performance payload for %s (keys=%s)",
                campaign_id,
                list(resp.keys()),
            )
        return budget

    async def _scan_gms_budget_from_campaign_lists(self, campaign_id: str) -> Optional[float]:
        target_id = int(campaign_id)
        for ad_type_filter in ("gms", "all"):
            ids = await self._campaign_id_list(ad_type_filter)
            if not ids:
                continue
            if target_id in ids:
                ids = [target_id]
            elif ad_type_filter == "gms":
                continue
            for i in range(0, len(ids), 100):
                chunk = ids[i : i + 100]
                infos = await self._campaign_setting_info(chunk)
                for info in infos:
                    raw = info.get("raw", {}) or {}
                    if not self._is_gms_campaign_setting_row(raw, str(campaign_id)):
                        continue
                    budget = self._extract_campaign_budget_from_setting_row(raw)
                    if budget is not None:
                        return budget
        return None

    async def get_gms_live_budget(self, campaign_id: str) -> Optional[float]:
        """
        Live GMV-Max daily budget — try GMS performance, setting info, then list scan.
        """
        live = await self._gms_budget_from_performance(campaign_id)
        if live is not None:
            logger.info("[gmv_max] live budget %.0f from get_gms_campaign_performance", live)
            return live

        try:
            infos = await self._campaign_setting_info([int(campaign_id)])
        except ShopeeAPIError as exc:
            logger.warning("get_gms_live_budget setting fetch failed for %s: %s", campaign_id, exc)
            infos = []
        if infos:
            raw = infos[0].get("raw", {}) or {}
            budget = self._extract_campaign_budget_from_setting_row(raw)
            if budget is not None:
                logger.info("[gmv_max] live budget %.0f from campaign setting info", budget)
                return budget

        live = await self._scan_gms_budget_from_campaign_lists(campaign_id)
        if live is not None:
            logger.info("[gmv_max] live budget %.0f from campaign list scan", live)
            return live

        logger.warning("get_gms_live_budget: all sources failed for campaign %s", campaign_id)
        return None

    async def set_gms_budget(
        self, campaign_id: str, daily_budget: float
    ) -> Dict[str, Any]:
        """Set the GMV-Max campaign's daily budget (edit_action=change_budget)."""
        import uuid

        body = {
            "campaign_id": int(campaign_id),
            "edit_action": "change_budget",
            "daily_budget": round(float(daily_budget), 2),
            "reference_id": str(uuid.uuid4()),
        }
        return await self._request(
            "POST", "/api/v2/ads/edit_gms_product_campaign", body=body
        )

    async def set_gms_roas_target(
        self, campaign_id: str, roas_target: float
    ) -> Dict[str, Any]:
        """Set the GMV-Max ROAS target (0 => auto-bidding; >0 => custom ROAS)."""
        import uuid

        body = {
            "campaign_id": int(campaign_id),
            "edit_action": "change_roas_target",
            "roas_target": round(float(roas_target), 1),
            "reference_id": str(uuid.uuid4()),
        }
        return await self._request(
            "POST", "/api/v2/ads/edit_gms_product_campaign", body=body
        )

    async def set_gms_enabled(
        self, campaign_id: str, enabled: bool
    ) -> Dict[str, Any]:
        """Pause / resume the GMV-Max campaign."""
        import uuid

        body = {
            "campaign_id": int(campaign_id),
            "edit_action": "resume" if enabled else "pause",
            "reference_id": str(uuid.uuid4()),
        }
        return await self._request(
            "POST", "/api/v2/ads/edit_gms_product_campaign", body=body
        )

    # ------------------------------------------------------------------ #
    # Individual Product Ads (manual product ads; each ad == exactly 1 item)
    #
    # These use the product-level manual ad endpoints:
    #   create_manual_product_ads          - create one ad for one item
    #   edit_manual_product_ads            - change budget / open / close
    #   get_product_level_campaign_id_list - list manual ads (detect user-made)
    #   get_product_level_campaign_setting_info - budget/status/item per ad
    #   get_create_product_ad_budget_suggestion - min/recommended budget
    #   get_product_recommended_roi_target - recommended ROI for an item
    # ------------------------------------------------------------------ #
    async def list_manual_product_ads(
        self, only_active: bool = True
    ) -> List[Dict[str, Any]]:
        """
        Return all MANUAL product ads (each backs one item), normalised to:
            {campaign_id, campaign_name, budget, status, item_id, raw}

        Used to (a) count running individual ads toward the cap and (b) import
        ads the user created manually so the bot can manage them too.
        """
        ids = await self._campaign_id_list("manual")
        infos = await self._campaign_setting_info(ids)
        out: List[Dict[str, Any]] = []
        for c in infos:
            raw = c.get("raw", {}) or {}
            common = raw.get("common_info", {}) or {}
            item_ids = common.get("item_id_list") or c.get("item_id_list") or []
            item_id = int(item_ids[0]) if item_ids else 0
            status = c.get("status", "")
            if only_active and status not in (
                "", "ongoing", "running", "active", "scheduled"
            ):
                continue
            # ROAS target lives under auto_bidding_info.roas_target for
            # auto-bidding ads (0/absent => auto bidding, no fixed target).
            auto = raw.get("auto_bidding_info") or {}
            roas_target = float(auto.get("roas_target", 0.0) or 0.0)
            bidding = common.get("bidding_method", "") or raw.get("bidding_method", "")
            out.append(
                {
                    "campaign_id": str(c["campaign_id"]),
                    "campaign_name": c.get("campaign_name", ""),
                    "budget": float(c.get("budget", 0.0) or 0.0),
                    "status": status,
                    "item_id": item_id,
                    "roas_target": roas_target,
                    "bidding_method": bidding,
                    "raw": c,
                }
            )
        return out

    async def create_manual_product_ad(
        self,
        item_id: int,
        budget: float,
        *,
        roas_target: float = 0.0,
        reference_id: str = "",
        start_date: Optional[str] = None,
    ) -> Dict[str, Any]:
        """
        Create ONE individual product ad for a single item, auto bidding, at the
        given daily budget. Returns {"campaign_id": <id>, "raw": <response>}.
        Raises ShopeeAPIError on failure.
        """
        import uuid

        if not reference_id:
            reference_id = f"item-{item_id}-{uuid.uuid4().hex[:12]}"
        start = start_date or self._today_ddmmyyyy()
        body: Dict[str, Any] = {
            "reference_id": reference_id,
            "budget": round(float(budget), 2),
            "start_date": start,
            "bidding_method": "auto",
            "item_id": int(item_id),
        }
        if roas_target and roas_target > 0:
            body["roas_target"] = round(float(roas_target), 1)
        data = await self._request(
            "POST", "/api/v2/ads/create_manual_product_ads", body=body
        )
        resp = data.get("response", {}) or {}
        return {"campaign_id": resp.get("campaign_id"), "raw": data}

    def _edit_manual_ad_body(
        self, campaign_id: str, edit_action: str, **extra: Any
    ) -> Dict[str, Any]:
        """
        Build the body for /api/v2/ads/edit_manual_product_ads.

        Shopee requires EVERY edit to carry both a `reference_id` (any unique
        string; the campaign_id works fine) and an `edit_action` from the
        allowed list: start / pause / resume / stop / delete / change_budget /
        change_duration / change_location / change_enhanced_cpc /
        change_roas_target / change_time_slot / change_product_placement / ...
        """
        cid = int(campaign_id)
        body: Dict[str, Any] = {
            "reference_id": str(cid),
            "campaign_id": cid,
            "edit_action": edit_action,
        }
        body.update(extra)
        return body

    async def set_item_ad_budget(
        self, campaign_id: str, new_budget: float
    ) -> Dict[str, Any]:
        """Change an individual product ad's daily budget."""
        return await self._request(
            "POST",
            "/api/v2/ads/edit_manual_product_ads",
            body=self._edit_manual_ad_body(
                campaign_id, "change_budget", budget=round(float(new_budget), 2)
            ),
        )

    async def set_item_ad_enabled(
        self, campaign_id: str, enabled: bool
    ) -> Dict[str, Any]:
        """Turn an individual product ad ON (resume) or OFF (pause)."""
        return await self._request(
            "POST",
            "/api/v2/ads/edit_manual_product_ads",
            body=self._edit_manual_ad_body(
                campaign_id, "resume" if enabled else "pause"
            ),
        )

    async def stop_item_ad(self, campaign_id: str) -> Dict[str, Any]:
        """
        Permanently END an individual product ad (edit_action=stop).

        Unlike `pause` (which leaves the ad "nonaktif"/inactive and resumable),
        `stop` moves the campaign to "berakhir"/ended. This is what the bot's
        low-ROAS turn-off wants: end the standalone ad (its items keep running
        under the auto product ads / GMV-Max), rather than merely pausing it.
        """
        return await self._request(
            "POST",
            "/api/v2/ads/edit_manual_product_ads",
            body=self._edit_manual_ad_body(campaign_id, "stop"),
        )

    async def set_item_ad_roas_target(
        self, campaign_id: str, roas_target: float
    ) -> Dict[str, Any]:
        """
        Change an individual (auto-bidding) product ad's ROAS target.

        Pass roas_target > 0 to set an explicit target; the value is rounded to
        1 decimal to match Shopee's create/edit contract.
        """
        return await self._request(
            "POST",
            "/api/v2/ads/edit_manual_product_ads",
            body=self._edit_manual_ad_body(
                campaign_id, "change_roas_target",
                roas_target=round(float(roas_target), 1),
            ),
        )

    async def get_item_budget_suggestion(self, item_id: int) -> Dict[str, float]:
        """
        Shopee's suggested budget for a new manual ad on this item:
            {min_budget, recommended_budget, max_budget}
        Best-effort: returns {} on failure.
        """
        import uuid

        try:
            data = await self._request(
                "GET",
                "/api/v2/ads/get_create_product_ad_budget_suggestion",
                query={
                    "reference_id": str(uuid.uuid4()),
                    "product_selection": "manual",
                    "campaign_placement": "all",
                    "bidding_method": "auto",
                    "item_id": int(item_id),
                },
            )
        except ShopeeAPIError as exc:
            logger.warning("get_create_product_ad_budget_suggestion failed: %s", exc)
            return {}
        b = (data.get("response", {}) or {}).get("budget", {}) or {}
        return {
            "min_budget": float(b.get("min_budget", 0.0) or 0.0),
            "recommended_budget": float(b.get("recommended_budget", 0.0) or 0.0),
            "max_budget": float(b.get("max_budget", 0.0) or 0.0),
        }

    async def get_item_recommended_roi(self, item_id: int) -> Dict[str, float]:
        """
        Shopee's recommended ROI target for an item:
            {lower_bound, exact, upper_bound}
        Best-effort: returns {} on failure.
        """
        import uuid

        try:
            data = await self._request(
                "GET",
                "/api/v2/ads/get_product_recommended_roi_target",
                query={"reference_id": str(uuid.uuid4()), "item_id": int(item_id)},
            )
        except ShopeeAPIError as exc:
            logger.warning("get_product_recommended_roi_target failed: %s", exc)
            return {}
        resp = data.get("response", {}) or {}
        return {
            "lower_bound": float((resp.get("lower_bound", {}) or {}).get("value", 0.0) or 0.0),
            "exact": float((resp.get("exact", {}) or {}).get("value", 0.0) or 0.0),
            "upper_bound": float((resp.get("upper_bound", {}) or {}).get("value", 0.0) or 0.0),
        }

    # ------------------------------------------------------------------ #
    # Group replenishment: candidate discovery + create new group ads
    # ------------------------------------------------------------------ #
    async def get_recommended_items(self) -> List[Dict[str, Any]]:
        """
        Shop-level recommended SKUs from Shopee's algorithm, each tagged with
        one or more of: "best selling", "best ROI", "top search".

        Returns a list of:
            {item_id, sku_tags, item_status, ongoing_ad_types, raw}

        Only items that are eligible for ads and not already running as an ad
        are useful candidates; the engine filters further. Empty list on
        failure so replenishment can gracefully fall back to group harvesting.
        """
        try:
            data = await self._request("GET", "/api/v2/ads/get_recommended_item_list")
        except ShopeeAPIError as exc:
            logger.warning("get_recommended_item_list failed: %s", exc)
            return []
        resp = data.get("response", [])
        # Shopee may nest under response or response.item_list; handle both.
        if isinstance(resp, dict):
            resp = resp.get("item_list", resp.get("recommended_item_list", [])) or []
        out: List[Dict[str, Any]] = []
        for it in resp or []:
            if not isinstance(it, dict):
                continue
            out.append(
                {
                    "item_id": int(it.get("item_id", 0)),
                    "sku_tags": [str(t).lower() for t in (it.get("sku_tag_list") or [])],
                    "item_status": [str(s).lower() for s in (it.get("item_status_list") or [])],
                    "ongoing_ad_types": [
                        str(a).lower() for a in (it.get("ongoing_ad_type_list") or [])
                    ],
                    "raw": it,
                }
            )
        return out

    async def harvest_group_items(self, campaign_ids: List[int]) -> Dict[str, List[int]]:
        """
        Extract the item_id(s) backing each given group campaign, so the engine
        can recycle the good SKUs from exhausted groups into a fresh group.

        Returns {campaign_id(str): [item_id, ...]}. Best-effort; a campaign
        whose items can't be resolved simply maps to an empty list.
        """
        result: Dict[str, List[int]] = {}
        if not campaign_ids:
            return result
        for i in range(0, len(campaign_ids), 100):
            chunk = campaign_ids[i : i + 100]
            try:
                data = await self._request(
                    "GET",
                    "/api/v2/ads/get_product_level_campaign_setting_info",
                    query={
                        "info_type_list": "1,2,3",
                        "campaign_id_list": ",".join(str(c) for c in chunk),
                    },
                )
            except ShopeeAPIError as exc:
                logger.warning("harvest_group_items failed for chunk: %s", exc)
                continue
            resp = data.get("response", {}) or {}
            for c in resp.get("campaign_list", []) or []:
                cid = str(c.get("campaign_id"))
                items: List[int] = []
                # item ids can live in several shapes depending on ad type
                for key in ("item_id_list", "item_list", "selected_item_list"):
                    val = c.get(key) or (c.get("common_info", {}) or {}).get(key)
                    if isinstance(val, list):
                        for entry in val:
                            iid = entry.get("item_id") if isinstance(entry, dict) else entry
                            if iid:
                                items.append(int(iid))
                result[cid] = items
        return result

    async def check_new_group_eligibility(self) -> Dict[str, Any]:
        """
        Best-effort eligibility probe. Uses the GMS eligibility endpoint if the
        shop has it; a non-eligible / missing endpoint just means we fall back
        to the manual-product-ads create path (or to suggest-only).

        Returns {eligible: bool, reason: str}.
        """
        try:
            data = await self._request(
                "GET", "/api/v2/ads/check_create_gms_product_campaign_eligibility"
            )
            resp = data.get("response", {}) or {}
            return {
                "eligible": bool(resp.get("is_eligible", False)),
                "reason": str(resp.get("reason", "") or ""),
            }
        except ShopeeAPIError as exc:
            return {"eligible": False, "reason": f"probe_failed: {exc.error or exc}"}

    async def create_group_ad(
        self,
        item_id: int,
        budget: float,
        *,
        roas_target: float = 0.0,
        reference_id: str = "",
        start_date: Optional[str] = None,
    ) -> Dict[str, Any]:
        """
        Create a NEW group ad campaign (running alongside existing groups) from a
        single high-performing product, via /api/v2/ads/create_manual_product_ads
        with auto bidding + a ROAS target.

        A fresh group starts at `budget` (same starting budget as other groups).
        `reference_id` prevents duplicate creation on retry; a unique one is
        generated if not supplied.

        Returns {"campaign_id": <new id>, "raw": <full response>}.
        Raises ShopeeAPIError on failure so the engine can fall back to
        suggest-only mode.
        """
        import uuid

        if not reference_id:
            reference_id = f"grp-{item_id}-{uuid.uuid4().hex[:12]}"
        start = start_date or self._today_ddmmyyyy()
        body: Dict[str, Any] = {
            "reference_id": reference_id,
            "budget": round(float(budget), 2),
            "start_date": start,
            "bidding_method": "auto",
            "item_id": int(item_id),
        }
        if roas_target and roas_target > 0:
            body["roas_target"] = round(float(roas_target), 1)
        data = await self._request(
            "POST", "/api/v2/ads/create_manual_product_ads", body=body
        )
        resp = data.get("response", {}) or {}
        return {"campaign_id": resp.get("campaign_id"), "raw": data}
