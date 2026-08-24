"""
config.py
=========

Immutable, environment-driven configuration for the **Shopee Ads Budget
Management Bot** (Indonesia).

ALL secrets are read from environment variables (`os.environ`) so nothing
sensitive is ever hard-coded. On a VPS these are supplied via a systemd
`EnvironmentFile=` (an `.env` file), locally via `python-dotenv` if present.

This bot is deliberately kept in its own folder / process / database so it never
overlaps with the TikTok bot. It uses a SEPARATE Telegram bot token but may
share the same admin Telegram user id.

Shopee Open Platform v2 specifics
---------------------------------
* API host (live): https://partner.shopeemobile.com
* Every Shop-level call is signed with:
      HMAC-SHA256(partner_id + api_path + timestamp + access_token + shop_id,
                  partner_key)
* Public calls (auth / token) are signed with:
      HMAC-SHA256(partner_id + api_path + timestamp, partner_key)
* OAuth: redirect the seller to /api/v2/shop/auth_partner, Shopee redirects back
  with `code` + `shop_id`, which we exchange at /api/v2/auth/token/get for an
  access_token (+ refresh_token). Shopee usually rejects raw-IP redirect URLs, so
  a small HTTPS PHP relay forwards the callback to this bot (see shopeebot.php).
"""

from __future__ import annotations

import os
from dataclasses import dataclass, field
from typing import List, Optional

# Load a local .env during development if python-dotenv is available.
# In production systemd injects the vars, so this is a no-op there.
try:  # pragma: no cover
    from dotenv import load_dotenv

    load_dotenv()
except Exception:  # noqa: BLE001
    pass


# --------------------------------------------------------------------------- #
# Small helpers for typed environment parsing
# --------------------------------------------------------------------------- #
def _env_str(key: str, default: str = "") -> str:
    val = os.environ.get(key)
    return val if val is not None and val != "" else default


def _env_int(key: str, default: int) -> int:
    raw = os.environ.get(key)
    if raw is None or raw == "":
        return default
    try:
        return int(raw)
    except ValueError:
        return default


def _env_float(key: str, default: float) -> float:
    raw = os.environ.get(key)
    if raw is None or raw == "":
        return default
    try:
        return float(raw)
    except ValueError:
        return default


def _env_bool(key: str, default: bool) -> bool:
    raw = os.environ.get(key)
    if raw is None or raw == "":
        return default
    return raw.strip().lower() in ("1", "true", "yes", "on", "y")


def _env_list_int(key: str) -> List[int]:
    raw = os.environ.get(key, "")
    out: List[int] = []
    for part in raw.replace(";", ",").split(","):
        part = part.strip()
        if not part:
            continue
        try:
            out.append(int(part))
        except ValueError:
            continue
    return out


# The four Shopee ad types this bot manages. The first three are "single ad"
# types (one ad object each) driven by their own schedule. The fourth,
# "iklan_group", is a collection of ad groups sharing one schedule with a
# ROAS-weighted split.
AD_TYPE_TOKO_AUTO = "iklan_toko_auto"     # Iklan Toko Auto / Booster
AD_TYPE_TOKO_MANUAL = "iklan_toko_manual"  # Iklan Toko Manual
AD_TYPE_PRODUK_AUTO = "iklan_produk_auto"  # Iklan Produk Otomatis
AD_TYPE_GROUP = "iklan_group"              # Iklan Group (multiple ad groups)
# The shop's live GMV-Max ("Iklan Produk GMV Max ROAS") campaign. This is the
# PRIMARY ad type the bot actually controls via the Shopee GMS API
# (edit_gms_product_campaign). It is a single campaign with one campaign-level
# daily budget, so its schedule increments that one budget directly.
AD_TYPE_GMV_MAX = "gmv_max"
# Individual Product Ads: each ad = exactly ONE item (Shopee "manual product
# ads"). The bot creates a capped pool of these, tiers their budgets by ROAS,
# turns off bad performers, and replenishes the pool daily. Manually-created
# manual ads are imported and treated as if the bot created them.
AD_TYPE_PRODUK_MANUAL = "iklan_produk_manual"

SINGLE_AD_TYPES = (AD_TYPE_TOKO_AUTO, AD_TYPE_TOKO_MANUAL, AD_TYPE_PRODUK_AUTO)
# ALL_AD_TYPES now includes GMV-Max (the live/working type) and Individual
# Product Ads plus the legacy types kept for backward compatibility (schedules/
# history may still reference them).
ALL_AD_TYPES = SINGLE_AD_TYPES + (AD_TYPE_GROUP, AD_TYPE_GMV_MAX, AD_TYPE_PRODUK_MANUAL)

# The ad types whose CURRENT budgets count toward the combined daily cap. Iklan
# Toko has no budget-set endpoint and the legacy product-level campaigns are all
# closed/ended, so today GMV-Max and Individual Product Ads count toward the cap.
CAPPED_AD_TYPES = (AD_TYPE_GMV_MAX, AD_TYPE_PRODUK_MANUAL)

AD_TYPE_LABELS = {
    AD_TYPE_TOKO_AUTO: "Iklan Toko Auto/Booster",
    AD_TYPE_TOKO_MANUAL: "Iklan Toko Manual",
    AD_TYPE_PRODUK_AUTO: "Iklan Produk Otomatis",
    AD_TYPE_GROUP: "Iklan Group",
    AD_TYPE_GMV_MAX: "Iklan Produk GMV Max",
    AD_TYPE_PRODUK_MANUAL: "Iklan Produk (Individual)",
}

# Minimum daily budget Shopee accepts for a manual product ad (IDR), confirmed
# via get_create_product_ad_budget_suggestion (min_budget).
ITEM_AD_MIN_BUDGET = 25_000.0


@dataclass(frozen=True)
class Settings:
    """Immutable snapshot of all configuration for one bot run."""

    # --- Telegram (SEPARATE token from the TikTok bot; same admin id allowed) --
    telegram_bot_token: str
    allowed_telegram_user_ids: List[int] = field(default_factory=list)

    # --- Shopee Open Platform credentials -------------------------------------
    shopee_partner_id: int = 0            # a.k.a. app_id
    shopee_partner_key: str = ""          # a.k.a. app secret / partner key
    shopee_shop_id: int = 0               # the seller's shop id
    shopee_access_token: str = ""         # obtained via /authorize (OAuth)
    shopee_refresh_token: str = ""        # used to auto-refresh access_token
    shopee_api_base: str = "https://partner.shopeemobile.com"

    # --- OAuth callback (VPS) --------------------------------------------------
    vps_public_ip: str = ""
    oauth_callback_host: str = "0.0.0.0"
    oauth_callback_port: int = 8090       # DIFFERENT port from the TikTok bot (8080)
    oauth_redirect_path: str = "/shopee/callback"
    # HTTPS relay URL registered in the Shopee console (raw IPs are rejected).
    shopee_redirect_uri_override: str = ""

    # --- Database --------------------------------------------------------------
    db_path: str = "shopee_bot_data.db"

    # --- Budget defaults (IDR) seeded into SQLite on first run -----------------
    # daily_max_budget is the COMBINED daily cap across ALL ad types (toko_auto +
    # toko_manual + produk_auto + all ad groups). starting_budget is the legacy
    # global fallback; each ad type also has its OWN starting budget below.
    default_starting_budget: float = 100_000.0
    default_daily_max_budget: float = 1_000_000.0

    # Per-ad-type starting budgets (IDR). For iklan_group this is the starting
    # budget PER GROUP (so total group spend at reset = per_group * group_count).
    default_starting_budget_toko_auto: float = 100_000.0
    default_starting_budget_toko_manual: float = 100_000.0
    default_starting_budget_produk_auto: float = 100_000.0
    default_starting_budget_group: float = 100_000.0
    # GMV-Max starting daily budget (IDR), applied at each daily reset. This is
    # the primary live ad type.
    default_starting_budget_gmv_max: float = 100_000.0

    # --- Iklan Group ROAS logic defaults --------------------------------------
    # Weighted split across the three ROAS tiers (high / mid / low). Must sum ~1.
    group_split_high: float = 0.60
    group_split_mid: float = 0.30
    group_split_low: float = 0.10
    # Ad groups whose ROAS stays below this after >= N increments get TURNED OFF.
    group_roas_off_threshold: float = 6.0
    group_off_after_increments: int = 2

    # --- Group replenishment ---------------------------------------------------
    # When exhausted groups get turned off, the pool of active high-performing
    # groups shrinks. Replenishment tops the pool back up by creating NEW group
    # campaigns from high-performing products (best ROI / best selling), running
    # alongside existing groups so overall group ROAS stays healthy.
    group_replenish_enabled: bool = True
    # Target number of ACTIVE groups to maintain. If the active count drops
    # below this, replenishment creates enough new groups to reach it.
    group_target_active_count: int = 5
    # Max new groups to create in a single replenishment run (safety cap).
    group_replenish_max_per_run: int = 3
    # Minimum ROAS a recycled SKU (from a turned-off group) must have had to be
    # eligible for a fresh group. Shopee "best ROI"/"best selling" tags always
    # qualify regardless.
    group_replenish_min_roas: float = 6.0
    # ROAS target applied to freshly created groups (0 => GMV-Max auto bidding).
    group_new_roas_target: float = 0.0
    # Daily WIB time to run replenishment (HH:MM). Empty string disables the
    # auto schedule (manual /replenish still works).
    group_replenish_time: str = "02:00"

    # --- Individual Product Ads (each ad = 1 item) ----------------------------
    # Master toggle for the whole individual-product-ads subsystem.
    item_ads_enabled: bool = True
    # Hard CAP on the number of running individual ads (bot-created AND manually
    # created ads counted together). Replenishment tops the pool up to this.
    max_item_ads: int = 10
    # Starting daily budget (IDR) for each newly created individual ad. Flat
    # value (Shopee minimum is 25,000).
    item_ad_starting_budget: float = 25_000.0
    # Auto-replenish toggle: when on, the daily job creates enough new ads to
    # bring the active pool back up to max_item_ads.
    item_replenish_enabled: bool = True
    # Max new individual ads to create in a single replenish run (safety cap).
    item_replenish_max_per_run: int = 5
    # Daily WIB time (HH:MM) to run individual-ad replenishment.
    item_replenish_time: str = "02:30"
    # ROAS turn-off rule for individual ads.
    item_roas_off_threshold: float = 6.0
    item_off_after_checks: int = 2
    # ROAS target for freshly created individual ads (0 => auto bidding). When
    # item_use_recommended_roi is True the bot instead sets each ad's ROAS to the
    # API-recommended value (get_product_recommended_roi_target.exact).
    item_new_roas_target: float = 0.0
    item_use_recommended_roi: bool = False
    # Weighted split across the three ROAS tiers (high / mid / low) for the
    # per-increment budget distribution. Must sum ~1.
    item_split_high: float = 0.60
    item_split_mid: float = 0.30
    item_split_low: float = 0.10
    # Candidate selection strategy for replenishment: 'sales' | 'roas' |
    # 'stock' | 'mixed'. Default 'sales' (sales first, ROAS as tiebreaker).
    item_selection: str = "sales"

    # --- Misc ------------------------------------------------------------------
    timezone: str = "Asia/Jakarta"        # Indonesia WIB (GMT+7)
    daily_reset_hour: int = 0             # 00:00 by default
    daily_reset_minute: int = 1           # ...:01 -> 00:01 WIB
    log_level: str = "INFO"

    # ---- Derived ----
    @property
    def oauth_redirect_uri(self) -> str:
        """
        The redirect URI passed to Shopee's auth_partner. Prefer the HTTPS relay
        override (required because Shopee rejects raw-IP URLs); otherwise fall
        back to the raw VPS URL.
        """
        if self.shopee_redirect_uri_override:
            return self.shopee_redirect_uri_override
        host = self.vps_public_ip or "127.0.0.1"
        return f"http://{host}:{self.oauth_callback_port}{self.oauth_redirect_path}"

    @property
    def tiktok_conflict_note(self) -> str:
        return (
            "Runs independently of the TikTok bot: own folder, own DB, own "
            "Telegram token, own callback port."
        )

    def is_shopee_configured(self) -> bool:
        """True when the minimum set of Shopee creds needed to call the API exist."""
        return bool(
            self.shopee_partner_id
            and self.shopee_partner_key
            and self.shopee_shop_id
            and self.shopee_access_token
        )

    def validate(self) -> List[str]:
        """Return a list of human-readable configuration problems (empty = OK)."""
        problems: List[str] = []
        if not self.telegram_bot_token:
            problems.append("TELEGRAM_BOT_TOKEN is not set.")
        if not self.allowed_telegram_user_ids:
            problems.append("ALLOWED_TELEGRAM_USER_ID is not set (no admins).")
        if not self.shopee_partner_id:
            problems.append("SHOPEE_PARTNER_ID is not set.")
        if not self.shopee_partner_key:
            problems.append("SHOPEE_PARTNER_KEY is not set.")
        # shop_id / access_token can be filled later via /authorize.
        return problems


def load_config() -> Settings:
    """Read environment variables and build the immutable Settings object."""
    return Settings(
        # Telegram
        telegram_bot_token=_env_str("TELEGRAM_BOT_TOKEN"),
        allowed_telegram_user_ids=_env_list_int("ALLOWED_TELEGRAM_USER_ID"),
        # Shopee
        shopee_partner_id=_env_int("SHOPEE_PARTNER_ID", 0),
        shopee_partner_key=_env_str("SHOPEE_PARTNER_KEY"),
        shopee_shop_id=_env_int("SHOPEE_SHOP_ID", 0),
        shopee_access_token=_env_str("SHOPEE_ACCESS_TOKEN"),
        shopee_refresh_token=_env_str("SHOPEE_REFRESH_TOKEN"),
        shopee_api_base=_env_str("SHOPEE_API_BASE", "https://partner.shopeemobile.com"),
        # OAuth callback
        vps_public_ip=_env_str("VPS_PUBLIC_IP"),
        oauth_callback_host=_env_str("OAUTH_CALLBACK_HOST", "0.0.0.0"),
        oauth_callback_port=_env_int("OAUTH_CALLBACK_PORT", 8090),
        oauth_redirect_path=_env_str("OAUTH_REDIRECT_PATH", "/shopee/callback"),
        shopee_redirect_uri_override=_env_str("SHOPEE_REDIRECT_URI_OVERRIDE"),
        # Database
        db_path=_env_str(
            "DB_PATH",
            os.path.join(os.path.dirname(os.path.abspath(__file__)), "shopee_bot_data.db"),
        ),
        # Budget defaults
        default_starting_budget=_env_float("DEFAULT_STARTING_BUDGET", 100_000.0),
        default_daily_max_budget=_env_float("DEFAULT_DAILY_MAX_BUDGET", 1_000_000.0),
        default_starting_budget_toko_auto=_env_float(
            "DEFAULT_STARTING_BUDGET_TOKO_AUTO",
            _env_float("DEFAULT_STARTING_BUDGET", 100_000.0),
        ),
        default_starting_budget_toko_manual=_env_float(
            "DEFAULT_STARTING_BUDGET_TOKO_MANUAL",
            _env_float("DEFAULT_STARTING_BUDGET", 100_000.0),
        ),
        default_starting_budget_produk_auto=_env_float(
            "DEFAULT_STARTING_BUDGET_PRODUK_AUTO",
            _env_float("DEFAULT_STARTING_BUDGET", 100_000.0),
        ),
        default_starting_budget_group=_env_float(
            "DEFAULT_STARTING_BUDGET_GROUP",
            _env_float("DEFAULT_STARTING_BUDGET", 100_000.0),
        ),
        default_starting_budget_gmv_max=_env_float(
            "DEFAULT_STARTING_BUDGET_GMV_MAX",
            _env_float("DEFAULT_STARTING_BUDGET", 100_000.0),
        ),
        # Iklan Group ROAS logic
        group_split_high=_env_float("GROUP_SPLIT_HIGH", 0.60),
        group_split_mid=_env_float("GROUP_SPLIT_MID", 0.30),
        group_split_low=_env_float("GROUP_SPLIT_LOW", 0.10),
        group_roas_off_threshold=_env_float("GROUP_ROAS_OFF_THRESHOLD", 6.0),
        group_off_after_increments=_env_int("GROUP_OFF_AFTER_INCREMENTS", 2),
        # Group replenishment
        group_replenish_enabled=_env_bool("GROUP_REPLENISH_ENABLED", True),
        group_target_active_count=_env_int("GROUP_TARGET_ACTIVE_COUNT", 5),
        group_replenish_max_per_run=_env_int("GROUP_REPLENISH_MAX_PER_RUN", 3),
        group_replenish_min_roas=_env_float("GROUP_REPLENISH_MIN_ROAS", 6.0),
        group_new_roas_target=_env_float("GROUP_NEW_ROAS_TARGET", 0.0),
        group_replenish_time=_env_str("GROUP_REPLENISH_TIME", "02:00"),
        # Individual Product Ads
        item_ads_enabled=_env_bool("ITEM_ADS_ENABLED", True),
        max_item_ads=_env_int("MAX_ITEM_ADS", 10),
        item_ad_starting_budget=_env_float("ITEM_AD_STARTING_BUDGET", 25_000.0),
        item_replenish_enabled=_env_bool("ITEM_REPLENISH_ENABLED", True),
        item_replenish_max_per_run=_env_int("ITEM_REPLENISH_MAX_PER_RUN", 5),
        item_replenish_time=_env_str("ITEM_REPLENISH_TIME", "02:30"),
        item_roas_off_threshold=_env_float("ITEM_ROAS_OFF_THRESHOLD", 6.0),
        item_off_after_checks=_env_int("ITEM_OFF_AFTER_CHECKS", 2),
        item_new_roas_target=_env_float("ITEM_NEW_ROAS_TARGET", 0.0),
        item_use_recommended_roi=_env_bool("ITEM_USE_RECOMMENDED_ROI", False),
        item_split_high=_env_float("ITEM_SPLIT_HIGH", 0.60),
        item_split_mid=_env_float("ITEM_SPLIT_MID", 0.30),
        item_split_low=_env_float("ITEM_SPLIT_LOW", 0.10),
        item_selection=_env_str("ITEM_SELECTION", "sales"),
        # Misc
        timezone=_env_str("TIMEZONE", "Asia/Jakarta"),
        daily_reset_hour=_env_int("DAILY_RESET_HOUR", 0),
        daily_reset_minute=_env_int("DAILY_RESET_MINUTE", 1),
        log_level=_env_str("LOG_LEVEL", "INFO"),
    )


# Single shared, immutable configuration instance imported across the app.
CONFIG: Settings = load_config()
