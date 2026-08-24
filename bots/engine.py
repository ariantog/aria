"""
engine.py
=========

The automation "brain" of the **Shopee Ads Budget Management Bot**.

It has two behaviours, both purely time-based (fired by the scheduler):

1. Single-ad increment  (Iklan Toko Auto/Booster, Iklan Toko Manual,
   Iklan Produk Otomatis)
   ------------------------------------------------------------------
   Each of these ad types has its own schedule (clock time + fixed IDR). At the
   scheduled time we add that fixed IDR to the ad's budget, clamped to the daily
   cap. Same behaviour as the TikTok bot's single-campaign ramp.

2. Iklan Group increment (multiple ad groups, SHARED schedule)
   -----------------------------------------------------------
   The group schedule carries ONE total increment pool (e.g. 1,000,000 IDR). At
   the scheduled time we:
     a. fetch all active ad groups + each group's ROAS,
     b. rank them and split them into three ROAS tiers (high / mid / low),
     c. distribute the pool by weight (default 60% / 30% / 10%) across the tiers
        (evenly within a tier), clamped per-group to the daily cap,
     d. if a group has stayed under the ROAS "off" threshold (default 6) for
        >= N increments (default 2), TURN IT OFF (not pause) — it keeps running
        under Iklan Produk Otomatis but is no longer a standalone group ad.

Daily reset (00:00/00:01 WIB)
-----------------------------
`run_daily_reset()` resets every managed ad's budget to `starting_budget`,
re-opens any groups that were turned off, and zeroes the per-day group counters.

All Shopee/DB calls are wrapped in try/except so one failing ad never aborts the
whole run and an API outage never crashes the process.
"""

from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass, field
from datetime import datetime
from typing import Any, Dict, List, Optional

from config import (
    AD_TYPE_GMV_MAX,
    AD_TYPE_GROUP,
    AD_TYPE_LABELS,
    AD_TYPE_PRODUK_AUTO,
    AD_TYPE_PRODUK_MANUAL,
    AD_TYPE_TOKO_AUTO,
    AD_TYPE_TOKO_MANUAL,
    CAPPED_AD_TYPES,
    ITEM_AD_MIN_BUDGET,
    SINGLE_AD_TYPES,
    Settings,
)
from database import Database
from shopee_api import ShopeeAdsClient, ShopeeAPIError

logger = logging.getLogger(__name__)


# --------------------------------------------------------------------------- #
# Pure helpers (no I/O) — easy to unit test
# --------------------------------------------------------------------------- #
def compute_new_budget(
    current_budget: float, increment_idr: float, daily_cap: float
) -> Dict[str, Any]:
    """
    Add a fixed IDR increment to a single ad's budget, clamped to the daily cap.

    * already at/over cap  -> hold (capped=True, applied 0)
    * full increment would exceed cap -> apply only the remainder (capped=True)
    * otherwise -> apply full increment
    """
    current_budget = max(float(current_budget), 0.0)
    increment_idr = max(float(increment_idr), 0.0)
    daily_cap = max(float(daily_cap), 0.0)

    if daily_cap > 0 and current_budget >= daily_cap:
        return {
            "new_budget": round(current_budget, 2),
            "applied_increment": 0.0,
            "capped": True,
            "action": f"Kept stable (already at daily cap {daily_cap:,.0f})",
        }

    proposed = current_budget + increment_idr
    if daily_cap > 0 and proposed > daily_cap:
        applied = daily_cap - current_budget
        return {
            "new_budget": round(daily_cap, 2),
            "applied_increment": round(applied, 2),
            "capped": True,
            "action": f"Increased by {applied:,.0f} IDR (capped at daily budget {daily_cap:,.0f})",
        }

    return {
        "new_budget": round(proposed, 2),
        "applied_increment": round(increment_idr, 2),
        "capped": False,
        "action": f"Increased by {increment_idr:,.0f} IDR",
    }


def split_group_increment(
    groups: List[Dict[str, Any]],
    total_increment: float,
    split_high: float = 0.60,
    split_mid: float = 0.30,
    split_low: float = 0.10,
) -> Dict[str, float]:
    """
    Split a total increment pool across ad groups by ROAS tier.

    Groups are sorted by ROAS descending and divided into three tiers as evenly
    as possible (high / mid / low). Each tier receives `split_*` share of the
    pool, distributed evenly among the groups in that tier. Groups with the
    highest ROAS get the largest per-group increment.

    Returns {campaign_id: increment_idr}. Robust to 1, 2 or many groups.
    """
    result: Dict[str, float] = {}
    active = [g for g in groups if not g.get("turned_off")]
    n = len(active)
    if n == 0 or total_increment <= 0:
        return result

    # Normalize weights (in case they don't sum to exactly 1).
    w_sum = split_high + split_mid + split_low
    if w_sum <= 0:
        split_high, split_mid, split_low = 0.60, 0.30, 0.10
        w_sum = 1.0
    wh, wm, wl = split_high / w_sum, split_mid / w_sum, split_low / w_sum

    ordered = sorted(active, key=lambda g: float(g.get("roas", 0.0)), reverse=True)

    if n == 1:
        result[str(ordered[0]["campaign_id"])] = round(total_increment, 2)
        return result

    if n == 2:
        # High tier gets (wh)+(wm) share proportionally; low tier gets wl-ish.
        # Simpler: top group gets high share, bottom gets low share, normalized.
        top_w = wh
        bot_w = wl
        s = top_w + bot_w
        result[str(ordered[0]["campaign_id"])] = round(total_increment * top_w / s, 2)
        result[str(ordered[1]["campaign_id"])] = round(total_increment * bot_w / s, 2)
        return result

    # n >= 3: split into three tiers as evenly as possible.
    base = n // 3
    rem = n % 3
    # Give any remainder to higher tiers first (high, then mid).
    high_n = base + (1 if rem >= 1 else 0)
    mid_n = base + (1 if rem == 2 else 0)
    low_n = n - high_n - mid_n

    tiers = [
        (ordered[0:high_n], wh),
        (ordered[high_n : high_n + mid_n], wm),
        (ordered[high_n + mid_n :], wl),
    ]
    for tier_groups, weight in tiers:
        if not tier_groups:
            continue
        pool = total_increment * weight
        per = pool / len(tier_groups)
        for g in tier_groups:
            result[str(g["campaign_id"])] = round(per, 2)
    return result


# --------------------------------------------------------------------------- #
# Replenishment candidate ranking (pure, testable)
# --------------------------------------------------------------------------- #
# Shopee recommended-item tags, best first. A "best ROI" item is the strongest
# signal for a fresh high-performing group; then best selling; then top search.
_TAG_PRIORITY = {
    "best roi": 3,
    "best_roi": 3,
    "best selling": 2,
    "best_selling": 2,
    "top search": 1,
    "top_search": 1,
}


def _tag_score(tags: List[str]) -> int:
    return max(
        (_TAG_PRIORITY.get(str(t).strip().lower(), 0) for t in (tags or [])),
        default=0,
    )


def rank_candidates(
    recommended: List[Dict[str, Any]],
    recycled: List[Dict[str, Any]],
    *,
    exclude_item_ids: Optional[set] = None,
    exclude_ongoing: bool = True,
    min_recycled_roas: float = 6.0,
) -> List[Dict[str, Any]]:
    """
    Merge and rank candidate SKUs for new groups from two sources:

      * ``recommended`` — Shopee's shop-level recommended items, each:
            {item_id, sku_tags, item_status, ongoing_ad_types}
        A tagged (best ROI / best selling / top search) item qualifies.
      * ``recycled`` — good SKUs harvested from turned-off / exhausted groups:
            {item_id, roas, source_group}
        Qualifies only if its ROAS >= ``min_recycled_roas``.

    Rules:
      * Drop any item already used recently (``exclude_item_ids``).
      * Drop recommended items already running an ad when ``exclude_ongoing``
        (unless they carry a strong tag — a best-ROI item is worth a dedicated
        group even if it's in the auto pool).
      * De-duplicate by item_id, keeping the higher-scoring entry.

    Returns a ranked list (best first) of:
        {item_id, source, score, roas, reason}
    """
    exclude = set(exclude_item_ids or set())
    by_item: Dict[int, Dict[str, Any]] = {}

    def _consider(entry: Dict[str, Any]) -> None:
        iid = int(entry["item_id"])
        if iid in exclude or iid <= 0:
            return
        prev = by_item.get(iid)
        if prev is None or entry["score"] > prev["score"] or (
            entry["score"] == prev["score"] and entry["roas"] > prev["roas"]
        ):
            by_item[iid] = entry

    # Recommended items (source (a))
    for it in recommended or []:
        tags = it.get("sku_tags", [])
        score = _tag_score(tags)
        if score == 0:
            continue  # untagged => not a strong recommendation
        ongoing = [
            a for a in it.get("ongoing_ad_types", [])
            if a and "no ongoing" not in str(a).lower()
        ]
        # Skip items already advertised, unless they carry the top "best ROI" tag.
        if exclude_ongoing and ongoing and score < 3:
            continue
        # Only advertise ads-eligible items.
        statuses = [str(s).strip().lower() for s in it.get("item_status", [])]
        if statuses and not any(s in ("normal", "eligible", "") for s in statuses):
            continue
        _consider(
            {
                "item_id": int(it["item_id"]),
                "source": "recommended",
                "score": 100 + score,  # recommended tier sits above recycled
                "roas": float(it.get("roas", 0.0) or 0.0),
                "reason": "tags: " + ", ".join(tags),
            }
        )

    # Recycled good SKUs from exhausted groups (source (b))
    for it in recycled or []:
        roas = float(it.get("roas", 0.0) or 0.0)
        if roas < min_recycled_roas:
            continue
        _consider(
            {
                "item_id": int(it["item_id"]),
                "source": "recycled",
                "score": int(roas),  # below the recommended tier
                "roas": roas,
                "reason": f"recycled from {it.get('source_group', 'exhausted group')} (ROAS {roas:.1f})",
            }
        )

    return sorted(by_item.values(), key=lambda e: (e["score"], e["roas"]), reverse=True)


# --------------------------------------------------------------------------- #
# Result dataclasses
# --------------------------------------------------------------------------- #
@dataclass
class ReplenishResult:
    item_id: int
    source: str          # 'recommended' | 'recycled'
    reason: str
    budget: float
    roas_target: float
    mode: str            # 'created' | 'suggested' | 'failed'
    campaign_id: Optional[str] = None
    error: Optional[str] = None


@dataclass
class IncrementResult:
    ad_type: str
    campaign_id: str
    campaign_name: str
    budget_before: float
    budget_after: float
    requested_increment: float
    applied_increment: float
    capped: bool
    action: str
    roas: Optional[float] = None
    turned_off: bool = False


@dataclass
class ItemReplenishResult:
    item_id: int
    source: str          # 'sales' | 'roas' | 'stock' | 'recommended' | 'recycled'
    reason: str
    budget: float
    roas_target: float
    mode: str            # 'created' | 'suggested' | 'failed'
    campaign_id: Optional[str] = None
    error: Optional[str] = None


# --------------------------------------------------------------------------- #
# Engine
# --------------------------------------------------------------------------- #
class AutomationEngine:
    def __init__(self, db: Database, client: ShopeeAdsClient, cfg: Settings) -> None:
        self.db = db
        self.client = client
        self.cfg = cfg

    # ----------------------- per-type starting budget ------------------ #
    @staticmethod
    def _starting_budget_map(settings: Dict[str, Any]) -> Dict[str, float]:
        """
        Resolve each ad type's starting budget, falling back to the legacy global
        ``starting_budget`` when a per-type column is missing/zero. For
        ``iklan_group`` the value is PER GROUP.
        """
        legacy = float(settings.get("starting_budget", 100_000.0) or 100_000.0)

        def pick(key: str) -> float:
            val = settings.get(key)
            try:
                val = float(val)
            except (TypeError, ValueError):
                return legacy
            return val if val > 0 else legacy

        return {
            AD_TYPE_TOKO_AUTO: pick("starting_budget_toko_auto"),
            AD_TYPE_TOKO_MANUAL: pick("starting_budget_toko_manual"),
            AD_TYPE_PRODUK_AUTO: pick("starting_budget_produk_auto"),
            AD_TYPE_GROUP: pick("starting_budget_group"),
            AD_TYPE_GMV_MAX: pick("starting_budget_gmv_max"),
            # PER-AD starting budget for individual product ads.
            AD_TYPE_PRODUK_MANUAL: pick("item_ad_starting_budget"),
        }

    # ------------------------- combined daily cap ----------------------- #
    async def _current_total_budget(self, settings: Optional[Dict[str, Any]] = None) -> float:
        """
        Current total daily budget across all CAP-COUNTED ad types.

        In the current Shopee API scope only GMV-Max exposes a settable,
        live budget (Iklan Toko has no budget-set endpoint; the legacy
        product-level campaigns are all closed/ended). So the combined daily
        cap is enforced against the GMV-Max budget the bot tracks in the DB.
        """
        if settings is None:
            settings = await asyncio.to_thread(self.db.get_settings)
        total = 0.0
        if AD_TYPE_GMV_MAX in CAPPED_AD_TYPES:
            total += float(settings.get("gms_current_budget", 0.0) or 0.0)
        if AD_TYPE_PRODUK_MANUAL in CAPPED_AD_TYPES:
            # Sum the daily budget of every ACTIVE individual product ad the bot
            # tracks (bot-created + imported manual). Turned-off ads don't count.
            try:
                item_ads = await asyncio.to_thread(
                    self.db.get_item_ads, active_only=True
                )
                total += sum(float(a.get("budget", 0.0) or 0.0) for a in item_ads)
            except Exception:  # noqa: BLE001
                logger.exception("[cap] failed to sum item-ad budgets")
        return round(total, 2)

    async def _combined_headroom(self, settings: Dict[str, Any]) -> float:
        """
        Remaining IDR that may still be added across all cap-counted ad types
        before hitting the combined ``daily_max_budget``. Never negative.
        """
        cap = float(settings.get("daily_max_budget", 1_000_000.0))
        current = await self._current_total_budget(settings)
        return max(cap - current, 0.0)

    # ---------------------------- GMV-Max ------------------------------- #
    async def _discover_gms_campaign_id(self, settings: Dict[str, Any]) -> Optional[str]:
        """
        Return the GMV-Max campaign id, discovering it live and caching it in
        the DB if not already stored.
        """
        cid = settings.get("gms_campaign_id")
        if cid:
            return str(cid)
        camp = await self.client.get_gms_campaign()
        if not camp:
            return None
        cid = camp["campaign_id"]
        await asyncio.to_thread(self.db.update_setting, gms_campaign_id=str(cid))
        return str(cid)

    async def apply_gmv_max_increment(
        self, increment_idr: float, label: str = ""
    ) -> List[IncrementResult]:
        """
        Add a fixed IDR to the single GMV-Max campaign's daily budget, clamped so
        the tracked GMV-Max budget never exceeds the combined ``daily_max_budget``.

        The current budget is read from the DB (the bot owns it, since Shopee's
        GMS API has no "get budget" endpoint), incremented, pushed via
        ``edit_gms_product_campaign`` (change_budget), then persisted back.
        """
        results: List[IncrementResult] = []
        settings = await asyncio.to_thread(self.db.get_settings)

        cid = await self._discover_gms_campaign_id(settings)
        if not cid:
            logger.error("[gmv_max] no active GMV-Max campaign found; increment skipped.")
            return results

        current = float(settings.get("gms_current_budget", 0.0) or 0.0)
        # If the bot has never set a budget yet, seed from the starting budget.
        if current <= 0:
            current = self._starting_budget_map(settings)[AD_TYPE_GMV_MAX]

        cap = float(settings.get("daily_max_budget", 1_000_000.0))
        # Combined cap headroom (GMV-Max is the only cap-counted type today).
        headroom = max(cap - current, 0.0)
        calc = compute_new_budget(current, increment_idr, current + headroom)
        new_budget = calc["new_budget"]

        # Pull a fresh ROAS reading for the log/report (best-effort).
        roas = None
        try:
            camp = await self.client.get_gms_campaign()
            if camp:
                roas = float(camp.get("roas", 0.0))
        except Exception:  # noqa: BLE001
            pass

        action = calc["action"]
        if label:
            action = f"[{label}] {action}"

        try:
            if calc["applied_increment"] > 0.01:
                await self.client.set_gms_budget(cid, new_budget)
                await asyncio.to_thread(
                    self.db.update_setting, gms_current_budget=new_budget
                )
            else:
                logger.info("[gmv_max] combined cap reached; no increment applied.")
            await asyncio.to_thread(
                self.db.log_budget_change,
                ad_type=AD_TYPE_GMV_MAX,
                campaign_id=cid,
                campaign_name=AD_TYPE_LABELS[AD_TYPE_GMV_MAX],
                budget_before=current,
                budget_after=new_budget,
                roas=roas,
                action_taken=action,
            )
            results.append(
                IncrementResult(
                    ad_type=AD_TYPE_GMV_MAX,
                    campaign_id=cid,
                    campaign_name=AD_TYPE_LABELS[AD_TYPE_GMV_MAX],
                    budget_before=current,
                    budget_after=new_budget,
                    requested_increment=float(increment_idr),
                    applied_increment=calc["applied_increment"],
                    capped=calc["capped"],
                    action=action,
                    roas=roas,
                )
            )
        except ShopeeAPIError as exc:
            logger.error("[gmv_max] increment failed for %s: %s", cid, exc)
        except Exception:  # noqa: BLE001
            logger.exception("[gmv_max] unexpected increment error for %s", cid)

        logger.info(
            "[gmv_max] increment run complete: %s -> %s (applied %s).",
            f"{current:,.0f}", f"{new_budget:,.0f}", f"{calc['applied_increment']:,.0f}",
        )
        return results

    # ---------------------- daily spend estimate ------------------------ #
    def estimate_daily_spend(
        self,
        settings: Dict[str, Any],
        schedules: List[Dict[str, Any]],
        item_ads_current: float = 0.0,
        item_ads_count: int = 0,
    ) -> Dict[str, Any]:
        """
        Estimate how much the bot expects to spend in one day and how it's
        distributed, so the user always knows the plan.

        For GMV-Max: starting budget + the sum of all enabled GMV-Max scheduled
        increments = the campaign's expected end-of-day budget. This is then
        clamped by the combined ``daily_max_budget`` (the real ceiling).

        Returns:
            {
              "gmv_max": {"start": float, "increments": float, "planned": float},
              "planned_total": float,        # before the cap
              "cap": float,
              "capped_total": float,         # min(planned_total, cap)
              "over_cap": bool,
            }
        """
        start_map = self._starting_budget_map(settings)
        cap = float(settings.get("daily_max_budget", 1_000_000.0))

        inc_by_type: Dict[str, float] = {}
        for s in schedules:
            if not s.get("enabled", 1):
                continue
            inc_by_type[s["ad_type"]] = inc_by_type.get(s["ad_type"], 0.0) + float(
                s.get("increment_idr", 0.0)
            )

        gmv_start = start_map[AD_TYPE_GMV_MAX]
        gmv_inc = inc_by_type.get(AD_TYPE_GMV_MAX, 0.0)
        gmv_planned = gmv_start + gmv_inc

        # Individual product ads: sum of the current active-ad budgets (passed in
        # by the caller after a DB read) plus any scheduled item-ad increments.
        item_start = float(item_ads_current or 0.0)
        item_inc = inc_by_type.get(AD_TYPE_PRODUK_MANUAL, 0.0)
        item_planned = item_start + item_inc

        planned_total = gmv_planned + item_planned
        capped_total = min(planned_total, cap)
        return {
            "gmv_max": {"start": gmv_start, "increments": gmv_inc, "planned": gmv_planned},
            "item_ads": {
                "start": item_start,
                "increments": item_inc,
                "planned": item_planned,
                "count": int(item_ads_count or 0),
            },
            "planned_total": planned_total,
            "cap": cap,
            "capped_total": capped_total,
            "over_cap": planned_total > cap + 0.01,
        }

    # -------------------------- single ad types ------------------------- #
    async def apply_single_increment(
        self, ad_type: str, increment_idr: float, label: str = ""
    ) -> List[IncrementResult]:
        """
        Add a fixed IDR to each ad of a single-ad type.

        The increment is clamped so the COMBINED budget across all ad types never
        exceeds ``daily_max_budget``. Headroom is shared, first-come-first-served
        within a run: once the combined cap is reached, remaining ads hold steady.
        """
        results: List[IncrementResult] = []
        settings = await asyncio.to_thread(self.db.get_settings)
        # Combined headroom across ALL ad types (not a per-ad cap).
        headroom = await self._combined_headroom(settings)

        try:
            ads = await self.client.get_ads_of_type(ad_type, only_active=True)
        except ShopeeAPIError as exc:
            logger.error("[%s] increment aborted: %s", ad_type, exc)
            return results
        except Exception:  # noqa: BLE001
            logger.exception("[%s] increment aborted (unexpected)", ad_type)
            return results

        for ad in ads:
            try:
                # Cap each ad's applied increment to whatever combined headroom
                # is left. compute_new_budget treats the second arg as the max
                # allowed final budget, so cap = current + remaining_headroom.
                per_ad_cap = float(ad["budget"]) + headroom
                calc = compute_new_budget(ad["budget"], increment_idr, per_ad_cap)
                new_budget = calc["new_budget"]
                headroom = max(headroom - calc["applied_increment"], 0.0)
                if calc["applied_increment"] > 0.01:
                    await self.client.set_ad_budget(ad_type, ad["campaign_id"], new_budget)

                action = calc["action"]
                if label:
                    action = f"[{label}] {action}"
                await asyncio.to_thread(
                    self.db.log_budget_change,
                    ad_type=ad_type,
                    campaign_id=ad["campaign_id"],
                    campaign_name=ad["campaign_name"],
                    budget_before=ad["budget"],
                    budget_after=new_budget,
                    action_taken=action,
                )
                results.append(
                    IncrementResult(
                        ad_type=ad_type,
                        campaign_id=ad["campaign_id"],
                        campaign_name=ad["campaign_name"],
                        budget_before=ad["budget"],
                        budget_after=new_budget,
                        requested_increment=float(increment_idr),
                        applied_increment=calc["applied_increment"],
                        capped=calc["capped"],
                        action=action,
                    )
                )
            except ShopeeAPIError as exc:
                logger.error("[%s] increment failed for %s: %s", ad_type, ad.get("campaign_id"), exc)
            except Exception:  # noqa: BLE001
                logger.exception("[%s] unexpected increment error for %s", ad_type, ad.get("campaign_id"))

        logger.info("[%s] increment run complete: %d ad(s).", ad_type, len(results))
        return results

    # ----------------------------- iklan group -------------------------- #
    async def apply_group_increment(
        self, total_increment: float, label: str = ""
    ) -> List[IncrementResult]:
        """
        Distribute a shared increment pool across all active ad groups weighted
        by ROAS tier, clamp per-group to the daily cap, and TURN OFF groups that
        stay under the ROAS threshold for too many increments.
        """
        results: List[IncrementResult] = []
        settings = await asyncio.to_thread(self.db.get_settings)
        # Combined headroom shared across ALL ad types.
        headroom = await self._combined_headroom(settings)
        split_high = float(settings.get("group_split_high", 0.60))
        split_mid = float(settings.get("group_split_mid", 0.30))
        split_low = float(settings.get("group_split_low", 0.10))
        roas_off = float(settings.get("group_roas_off_threshold", 6.0))
        off_after = int(settings.get("group_off_after_increments", 2))

        try:
            groups = await self.client.get_ad_groups(only_active=True)
        except ShopeeAPIError as exc:
            logger.error("[group] increment aborted: %s", exc)
            return results
        except Exception:  # noqa: BLE001
            logger.exception("[group] increment aborted (unexpected)")
            return results

        if not groups:
            logger.info("[group] no active ad groups found.")
            return results

        # Merge current DB turned_off state so we don't feed already-off groups.
        for g in groups:
            st = await asyncio.to_thread(self.db.get_group_state, g["campaign_id"])
            g["turned_off"] = bool(st.get("turned_off"))

        # Never distribute more than the combined headroom allows across all
        # ad types. If headroom is smaller than the scheduled pool, shrink it.
        effective_pool = min(float(total_increment), headroom)
        if effective_pool < float(total_increment):
            logger.info(
                "[group] pool %.0f trimmed to combined headroom %.0f.",
                total_increment, effective_pool,
            )
        alloc = split_group_increment(
            groups, effective_pool, split_high, split_mid, split_low
        )
        # Track how much of the headroom remains as we apply per-group; each
        # group is clamped to the running headroom so the combined cap holds.
        group_headroom = headroom

        for g in groups:
            cid = str(g["campaign_id"])
            name = g["campaign_name"]
            roas = float(g.get("roas", 0.0))
            st = await asyncio.to_thread(self.db.get_group_state, cid)

            # --- ROAS turn-off rule (evaluated per increment) ---
            streak = int(st.get("low_roas_streak", 0))
            inc_today = int(st.get("increments_today", 0))
            if roas < roas_off:
                streak += 1
            else:
                streak = 0

            already_off = bool(st.get("turned_off"))

            # Turn OFF once a group has stayed under the ROAS threshold for
            # `off_after` consecutive increment checks (default 2). The streak is
            # incremented above on each under-threshold check, so `streak >=
            # off_after` means it has been low for that many increments in a row.
            should_turn_off = not already_off and streak >= off_after

            if already_off:
                # Skip budget changes for groups already turned off today.
                await asyncio.to_thread(
                    self.db.upsert_group_state,
                    cid,
                    campaign_name=name,
                    last_roas=roas,
                    low_roas_streak=streak,
                )
                continue

            if should_turn_off:
                try:
                    await self.client.set_ad_enabled(AD_TYPE_GROUP, cid, enabled=False)
                    action = (
                        f"Turned OFF (ROAS {roas:.2f} < {roas_off:.0f} for "
                        f"{streak} checks; keeps running under Iklan Produk Otomatis)"
                    )
                    if label:
                        action = f"[{label}] {action}"
                    await asyncio.to_thread(
                        self.db.upsert_group_state,
                        cid, campaign_name=name, last_roas=roas,
                        low_roas_streak=streak, turned_off=1,
                    )
                    await asyncio.to_thread(
                        self.db.log_budget_change,
                        ad_type=AD_TYPE_GROUP, campaign_id=cid, campaign_name=name,
                        budget_before=g["budget"], budget_after=g["budget"],
                        roas=roas, action_taken=action,
                    )
                    results.append(
                        IncrementResult(
                            ad_type=AD_TYPE_GROUP, campaign_id=cid, campaign_name=name,
                            budget_before=g["budget"], budget_after=g["budget"],
                            requested_increment=0.0, applied_increment=0.0,
                            capped=False, action=action, roas=roas, turned_off=True,
                        )
                    )
                except ShopeeAPIError as exc:
                    logger.error("[group] turn-off failed for %s: %s", cid, exc)
                except Exception:  # noqa: BLE001
                    logger.exception("[group] unexpected turn-off error for %s", cid)
                continue

            # --- normal weighted increment ---
            inc = float(alloc.get(cid, 0.0))
            try:
                # Clamp each group so the COMBINED total across all ad types
                # never exceeds daily_max_budget (headroom shared with singles).
                per_group_cap = float(g["budget"]) + group_headroom
                calc = compute_new_budget(g["budget"], inc, per_group_cap)
                new_budget = calc["new_budget"]
                group_headroom = max(group_headroom - calc["applied_increment"], 0.0)
                if calc["applied_increment"] > 0.01:
                    await self.client.set_ad_budget(AD_TYPE_GROUP, cid, new_budget)

                tier_note = f"ROAS {roas:.2f}"
                action = f"{calc['action']} | {tier_note}"
                if label:
                    action = f"[{label}] {action}"

                await asyncio.to_thread(
                    self.db.upsert_group_state,
                    cid, campaign_name=name, last_roas=roas,
                    low_roas_streak=streak, increments_today=inc_today + 1,
                )
                await asyncio.to_thread(
                    self.db.log_budget_change,
                    ad_type=AD_TYPE_GROUP, campaign_id=cid, campaign_name=name,
                    budget_before=g["budget"], budget_after=new_budget,
                    roas=roas, action_taken=action,
                )
                results.append(
                    IncrementResult(
                        ad_type=AD_TYPE_GROUP, campaign_id=cid, campaign_name=name,
                        budget_before=g["budget"], budget_after=new_budget,
                        requested_increment=inc, applied_increment=calc["applied_increment"],
                        capped=calc["capped"], action=action, roas=roas,
                    )
                )
            except ShopeeAPIError as exc:
                logger.error("[group] increment failed for %s: %s", cid, exc)
            except Exception:  # noqa: BLE001
                logger.exception("[group] unexpected increment error for %s", cid)

        logger.info(
            "[group] increment run complete (pool %.0f IDR across %d group(s)).",
            total_increment, len(groups),
        )
        return results

    # ----------------------------- daily reset -------------------------- #
    async def run_daily_reset(self) -> List[Dict[str, Any]]:
        """
        Reset every managed ad (all four types) to `starting_budget`, re-open any
        turned-off groups, and zero the per-day group counters. Fires at
        00:00/00:01 WIB.
        """
        results: List[Dict[str, Any]] = []
        settings = await asyncio.to_thread(self.db.get_settings)
        # Per-ad-type starting budgets (fall back to legacy global if unset).
        start_map = self._starting_budget_map(settings)

        # Single ad types
        for ad_type in SINGLE_AD_TYPES:
            starting_budget = start_map[ad_type]
            try:
                ads = await self.client.get_ads_of_type(ad_type, only_active=True)
            except Exception as exc:  # noqa: BLE001
                logger.exception("[%s] daily reset fetch failed: %s", ad_type, exc)
                continue
            for ad in ads:
                try:
                    await self.client.set_ad_budget(ad_type, ad["campaign_id"], starting_budget)
                    await asyncio.to_thread(
                        self.db.log_budget_change,
                        ad_type=ad_type, campaign_id=ad["campaign_id"],
                        campaign_name=ad["campaign_name"], budget_before=ad["budget"],
                        budget_after=starting_budget,
                        action_taken=f"Daily reset to starting budget {starting_budget:,.0f}",
                    )
                    results.append({"ad_type": ad_type, "campaign_id": ad["campaign_id"],
                                    "budget_after": starting_budget})
                except Exception as exc:  # noqa: BLE001
                    logger.exception("[%s] reset failed for %s: %s", ad_type, ad.get("campaign_id"), exc)

        # Ad groups: reset budget + re-open turned-off groups
        try:
            groups = await self.client.get_ad_groups(only_active=False)
        except Exception as exc:  # noqa: BLE001
            logger.exception("[group] daily reset fetch failed: %s", exc)
            groups = []

        # Per-GROUP starting budget: each group resets to this SAME amount, so
        # total group spend at reset = group_starting_budget * number_of_groups.
        group_starting_budget = start_map[AD_TYPE_GROUP]
        for g in groups:
            cid = str(g["campaign_id"])
            try:
                st = await asyncio.to_thread(self.db.get_group_state, cid)
                if st.get("turned_off"):
                    try:
                        await self.client.set_ad_enabled(AD_TYPE_GROUP, cid, enabled=True)
                    except Exception:  # noqa: BLE001
                        logger.exception("[group] re-open failed for %s", cid)
                await self.client.set_ad_budget(AD_TYPE_GROUP, cid, group_starting_budget)
                await asyncio.to_thread(
                    self.db.log_budget_change,
                    ad_type=AD_TYPE_GROUP, campaign_id=cid,
                    campaign_name=g["campaign_name"], budget_before=g["budget"],
                    budget_after=group_starting_budget,
                    action_taken=f"Daily reset to starting budget {group_starting_budget:,.0f} (per-group; group re-opened)",
                )
                results.append({"ad_type": AD_TYPE_GROUP, "campaign_id": cid,
                                "budget_after": group_starting_budget})
            except Exception as exc:  # noqa: BLE001
                logger.exception("[group] reset failed for %s: %s", cid, exc)

        # GMV-Max: reset the single campaign's daily budget to its starting
        # budget and persist the tracked value (the bot owns this number).
        gmv_start = start_map[AD_TYPE_GMV_MAX]
        try:
            cid = await self._discover_gms_campaign_id(settings)
            if cid:
                await self.client.set_gms_budget(cid, gmv_start)
                await asyncio.to_thread(
                    self.db.update_setting, gms_current_budget=gmv_start
                )
                await asyncio.to_thread(
                    self.db.log_budget_change,
                    ad_type=AD_TYPE_GMV_MAX, campaign_id=cid,
                    campaign_name=AD_TYPE_LABELS[AD_TYPE_GMV_MAX],
                    budget_before=float(settings.get("gms_current_budget", 0.0) or 0.0),
                    budget_after=gmv_start,
                    action_taken=f"Daily reset to starting budget {gmv_start:,.0f}",
                )
                results.append({"ad_type": AD_TYPE_GMV_MAX, "campaign_id": cid,
                                "budget_after": gmv_start})
            else:
                logger.warning("[gmv_max] daily reset: no active campaign found.")
        except Exception as exc:  # noqa: BLE001
            logger.exception("[gmv_max] daily reset failed: %s", exc)

        # Individual product ads: reset each tracked ad's budget to the per-ad
        # starting budget, re-open any we turned off, and zero per-day counters.
        item_start = start_map[AD_TYPE_PRODUK_MANUAL]
        try:
            item_ads = await asyncio.to_thread(self.db.get_item_ads)
            for a in item_ads:
                cid = str(a["campaign_id"])
                try:
                    # Ads we ENDED for sustained low ROAS are "berakhir" for that
                    # day — you can't reset an ended campaign's budget, and we do
                    # NOT resume it. Instead, at the daily reset we RELEASE the ad
                    # from active tracking (mark it closed + clear its per-day
                    # counters). This frees the cap slot AND makes its item
                    # eligible again, so today's replenish job can freshly re-pick
                    # it (if it now looks good) or choose different items.
                    if int(a.get("turned_off", 0)) or a.get("status") == "ended":
                        await asyncio.to_thread(
                            self.db.upsert_item_ad, cid,
                            status="closed", turned_off=0,
                            increments_today=0, low_roas_streak=0,
                        )
                        logger.info(
                            "[item] daily reset released ended ad %s (item now re-pickable)",
                            cid,
                        )
                        continue
                    await self.client.set_item_ad_budget(cid, item_start)
                    await asyncio.to_thread(
                        self.db.upsert_item_ad, cid,
                        budget=item_start, status="ongoing",
                        increments_today=0, low_roas_streak=0, turned_off=0,
                    )
                    await asyncio.to_thread(
                        self.db.log_budget_change,
                        ad_type=AD_TYPE_PRODUK_MANUAL, campaign_id=cid,
                        campaign_name=f"Item ad {a.get('item_id')}",
                        budget_before=float(a.get("budget", 0.0) or 0.0),
                        budget_after=item_start,
                        action_taken=f"Daily reset to starting budget {item_start:,.0f}",
                    )
                    results.append({"ad_type": AD_TYPE_PRODUK_MANUAL,
                                    "campaign_id": cid, "budget_after": item_start})
                except Exception as exc:  # noqa: BLE001
                    logger.exception("[item] reset failed for %s: %s", cid, exc)
        except Exception as exc:  # noqa: BLE001
            logger.exception("[item] daily reset fetch failed: %s", exc)

        await asyncio.to_thread(self.db.reset_group_states)
        await asyncio.to_thread(
            self.db.update_setting,
            last_daily_reset=datetime.now().isoformat(timespec="seconds"),
        )
        logger.info(
            "Daily reset complete. %d ad(s) reset "
            "(gmv_max=%.0f, toko_auto=%.0f, toko_manual=%.0f, produk_auto=%.0f, group/each=%.0f).",
            len(results),
            start_map[AD_TYPE_GMV_MAX],
            start_map[AD_TYPE_TOKO_AUTO],
            start_map[AD_TYPE_TOKO_MANUAL],
            start_map[AD_TYPE_PRODUK_AUTO],
            start_map[AD_TYPE_GROUP],
        )
        return results

    # --------------------------- replenishment -------------------------- #
    async def replenish_groups(self) -> List["ReplenishResult"]:
        """
        Top the active-group pool back up to the configured target count by
        creating NEW group campaigns from high-performing products.

        Flow:
          1. Count currently ACTIVE groups. If >= target, do nothing.
          2. Gather candidates:
               (a) Shopee shop-level recommended items (best ROI / best
                   selling / top search),
               (b) good SKUs recycled from turned-off / exhausted groups
                   (ROAS >= group_replenish_min_roas).
          3. Rank + de-dup (skipping items used in the last few days).
          4. For the top N (= target - active, capped by max_per_run) create a
             new group via create_group_ad() at the shared starting budget.
             If the create API is unavailable / not permitted, fall back to
             SUGGEST-ONLY (record + return so the bot can message the admin).
        Never raises; returns a list of ReplenishResult.
        """
        results: List[ReplenishResult] = []
        settings = await asyncio.to_thread(self.db.get_settings)

        if not int(settings.get("group_replenish_enabled", 1)):
            logger.info("[replenish] disabled in settings; skipping.")
            return results

        target = int(settings.get("group_target_active_count", 5))
        max_per_run = int(settings.get("group_replenish_max_per_run", 3))
        min_roas = float(settings.get("group_replenish_min_roas", 6.0))
        # New groups start at the PER-GROUP group starting budget (same as
        # every other group), not the legacy global starting_budget.
        starting_budget = self._starting_budget_map(settings)[AD_TYPE_GROUP]
        roas_target = float(settings.get("group_new_roas_target", 0.0))

        # 1. current active groups
        try:
            active_groups = await self.client.get_ad_groups(only_active=True)
        except Exception as exc:  # noqa: BLE001
            logger.exception("[replenish] failed to fetch active groups: %s", exc)
            return results
        active_count = len(active_groups)
        need = target - active_count
        if need <= 0:
            logger.info(
                "[replenish] active groups %d >= target %d; nothing to do.",
                active_count, target,
            )
            return results
        need = min(need, max_per_run)

        # 2a. recommended items
        try:
            recommended = await self.client.get_recommended_items()
        except Exception as exc:  # noqa: BLE001
            logger.warning("[replenish] recommended items fetch failed: %s", exc)
            recommended = []

        # 2b. recycle good SKUs from turned-off groups
        recycled: List[Dict[str, Any]] = []
        try:
            all_groups = await self.client.get_ad_groups(only_active=False)
            off_ids: List[int] = []
            roas_by_cid: Dict[str, float] = {}
            for g in all_groups:
                cid = str(g["campaign_id"])
                st = await asyncio.to_thread(self.db.get_group_state, cid)
                if st.get("turned_off"):
                    off_ids.append(int(cid))
                    roas_by_cid[cid] = float(g.get("roas", st.get("last_roas") or 0.0))
            if off_ids:
                items_map = await self.client.harvest_group_items(off_ids)
                for cid, item_ids in items_map.items():
                    for iid in item_ids:
                        recycled.append(
                            {
                                "item_id": iid,
                                "roas": roas_by_cid.get(cid, 0.0),
                                "source_group": cid,
                            }
                        )
        except Exception as exc:  # noqa: BLE001
            logger.warning("[replenish] recycle harvest failed: %s", exc)

        # 3. rank
        recently_used = await asyncio.to_thread(self.db.recently_used_item_ids, 3)
        ranked = rank_candidates(
            recommended,
            recycled,
            exclude_item_ids=recently_used,
            min_recycled_roas=min_roas,
        )
        if not ranked:
            logger.info("[replenish] no eligible candidates found.")
            return results

        # 4. create (or suggest) the top `need`
        for cand in ranked[:need]:
            iid = int(cand["item_id"])
            try:
                created = await self.client.create_group_ad(
                    iid,
                    starting_budget,
                    roas_target=roas_target,
                )
                new_cid = created.get("campaign_id")
                await asyncio.to_thread(
                    self.db.record_created_group,
                    item_id=iid, budget=starting_budget, source=cand["source"],
                    mode="created", campaign_id=(str(new_cid) if new_cid else None),
                    roas_target=roas_target, reason=cand["reason"],
                )
                if new_cid:
                    await asyncio.to_thread(
                        self.db.upsert_group_state,
                        str(new_cid), campaign_name=f"New group (item {iid})",
                        increments_today=0, low_roas_streak=0, turned_off=0,
                    )
                    await asyncio.to_thread(
                        self.db.log_budget_change,
                        ad_type=AD_TYPE_GROUP, campaign_id=str(new_cid),
                        campaign_name=f"New group (item {iid})",
                        budget_before=0.0, budget_after=starting_budget,
                        action_taken=f"Created new group from item {iid} ({cand['source']})",
                    )
                results.append(
                    ReplenishResult(
                        item_id=iid, source=cand["source"], reason=cand["reason"],
                        budget=starting_budget, roas_target=roas_target,
                        mode="created", campaign_id=(str(new_cid) if new_cid else None),
                    )
                )
                logger.info("[replenish] created new group from item %s -> %s", iid, new_cid)
            except ShopeeAPIError as exc:
                # Create not available/permitted -> SUGGEST ONLY
                await asyncio.to_thread(
                    self.db.record_created_group,
                    item_id=iid, budget=starting_budget, source=cand["source"],
                    mode="suggested", campaign_id=None, roas_target=roas_target,
                    reason=cand["reason"],
                )
                results.append(
                    ReplenishResult(
                        item_id=iid, source=cand["source"], reason=cand["reason"],
                        budget=starting_budget, roas_target=roas_target,
                        mode="suggested", error=str(exc.error or exc),
                    )
                )
                logger.warning(
                    "[replenish] create unavailable for item %s (%s); suggested instead.",
                    iid, exc.error or exc,
                )
            except Exception as exc:  # noqa: BLE001
                results.append(
                    ReplenishResult(
                        item_id=iid, source=cand["source"], reason=cand["reason"],
                        budget=starting_budget, roas_target=roas_target,
                        mode="failed", error=str(exc),
                    )
                )
                logger.exception("[replenish] unexpected error for item %s: %s", iid, exc)

        logger.info(
            "[replenish] done. active=%d target=%d attempted=%d results=%d",
            active_count, target, need, len(results),
        )
        return results

    # =================================================================== #
    # Individual Product Ads (each ad = 1 item)
    # =================================================================== #
    async def sync_item_ads(self) -> Dict[str, int]:
        """
        Reconcile the bot's item_ads table with Shopee's live manual product ads.

        * Import any ACTIVE manual ad not yet tracked, tagging origin='manual'
          (so a user-created ad is managed exactly like a bot-created one).
        * Refresh budget/status for ads we already track.
        * Mark tracked ads that no longer appear active as closed.

        Returns {"imported": n, "updated": n, "closed": n, "active": n}.
        """
        stats = {"imported": 0, "updated": 0, "closed": 0, "active": 0}
        try:
            live = await self.client.list_manual_product_ads(only_active=True)
        except Exception as exc:  # noqa: BLE001
            logger.exception("[item] sync fetch failed: %s", exc)
            return stats

        live_by_cid = {str(a["campaign_id"]): a for a in live}
        tracked = {str(a["campaign_id"]): a for a in
                   await asyncio.to_thread(self.db.get_item_ads)}

        for cid, a in live_by_cid.items():
            existing = tracked.get(cid)
            if existing is None:
                await asyncio.to_thread(
                    self.db.upsert_item_ad, cid,
                    item_id=a.get("item_id", 0), origin="manual",
                    budget=a.get("budget", 0.0), status=a.get("status", "ongoing"),
                    roas_target=a.get("roas_target", 0.0),
                    turned_off=0,
                )
                stats["imported"] += 1
                logger.info("[item] imported manual ad %s (item %s)", cid, a.get("item_id"))
            else:
                # Refresh live budget/status/roas-target but keep origin + counters.
                await asyncio.to_thread(
                    self.db.upsert_item_ad, cid,
                    item_id=a.get("item_id", existing.get("item_id", 0)),
                    budget=a.get("budget", existing.get("budget", 0.0)),
                    roas_target=a.get("roas_target", existing.get("roas_target", 0.0)),
                    status=a.get("status", "ongoing"),
                )
                stats["updated"] += 1
            stats["active"] += 1

        # Ads we track but Shopee no longer lists as active -> mark closed.
        for cid, a in tracked.items():
            if cid not in live_by_cid and str(a.get("status", "")).lower() != "closed":
                await asyncio.to_thread(
                    self.db.upsert_item_ad, cid, status="closed"
                )
                stats["closed"] += 1

        logger.info(
            "[item] sync done: imported=%d updated=%d closed=%d active=%d",
            stats["imported"], stats["updated"], stats["closed"], stats["active"],
        )
        return stats

    async def set_item_ad_roas_target(
        self, target: str, roas_target: float
    ) -> Dict[str, Any]:
        """
        Set the ROAS target for a single individual product ad.

        `target` may be a campaign_id or an item_id; the ad is looked up in the
        item_ads table. `roas_target` is the new ROAS goal (must be > 0). On
        success the change is pushed to Shopee and persisted locally.

        Returns {"ok": bool, "campaign_id", "item_id", "roas_target", "error"}.
        """
        target = str(target or "").strip()
        try:
            new_roas = round(float(roas_target), 1)
        except (TypeError, ValueError):
            return {"ok": False, "error": "ROAS target must be a number."}
        if new_roas <= 0:
            return {"ok": False, "error": "ROAS target must be greater than 0."}

        # Resolve the ad: try campaign_id first, then item_id.
        ad = await asyncio.to_thread(self.db.get_item_ad, target)
        if not ad:
            all_ads = await asyncio.to_thread(self.db.get_item_ads)
            for a in all_ads:
                if str(a.get("item_id", "")) == target:
                    ad = a
                    break
        if not ad:
            return {
                "ok": False,
                "error": f"No tracked item ad found for '{target}'. "
                         f"Run /itemads sync first.",
            }

        cid = str(ad.get("campaign_id"))
        iid = int(ad.get("item_id", 0) or 0)
        try:
            await self.client.set_item_ad_roas_target(cid, new_roas)
        except ShopeeAPIError as exc:
            logger.error("[item] set ROAS target failed for %s: %s", cid, exc)
            return {"ok": False, "campaign_id": cid, "item_id": iid,
                    "error": str(exc)}
        except Exception as exc:  # noqa: BLE001
            logger.exception("[item] unexpected set-ROAS error for %s", cid)
            return {"ok": False, "campaign_id": cid, "item_id": iid,
                    "error": str(exc)}

        await asyncio.to_thread(
            self.db.upsert_item_ad, cid, roas_target=new_roas
        )
        logger.info("[item] ROAS target for %s (item %s) -> %.1f",
                    cid, iid, new_roas)
        return {"ok": True, "campaign_id": cid, "item_id": iid,
                "roas_target": new_roas}

    async def _rank_item_candidates(
        self, settings: Dict[str, Any], exclude_item_ids: set
    ) -> List[Dict[str, Any]]:
        """
        Build a ranked list of item candidates for new individual ads.

        Selection (default 'sales'): rank by SALES first (GMS item orders/GMV),
        then ROAS as a tiebreaker. 'roas' ranks by ROAS first. 'stock' / 'mixed'
        blend Shopee's recommended-item tags (best selling / best ROI). Items
        already backing a tracked ad (or used recently) are excluded.

        Returns [{item_id, source, sales, roas, reason}], best first.
        """
        strategy = str(settings.get("item_selection", "sales")).lower()

        # Primary signal: GMS per-item performance (orders/gmv = sales, roas).
        perf: List[Dict[str, Any]] = []
        try:
            perf = await self.client.get_gms_item_performance(days_back=7, limit=100)
        except Exception as exc:  # noqa: BLE001
            logger.warning("[item] gms item perf fetch failed: %s", exc)

        # Secondary signal: Shopee shop-level recommended items (tags).
        recommended: List[Dict[str, Any]] = []
        try:
            recommended = await self.client.get_recommended_items()
        except Exception as exc:  # noqa: BLE001
            logger.warning("[item] recommended items fetch failed: %s", exc)
        tag_by_item: Dict[int, List[str]] = {
            int(r["item_id"]): r.get("sku_tags", []) for r in recommended if r.get("item_id")
        }

        cands: Dict[int, Dict[str, Any]] = {}
        for p in perf:
            iid = int(p.get("item_id", 0) or 0)
            if iid <= 0 or iid in exclude_item_ids:
                continue
            sales = float(p.get("orders", 0) or 0) * 1000.0 + float(p.get("gmv", 0.0) or 0.0)
            roas = float(p.get("roas", 0.0) or 0.0)
            tags = tag_by_item.get(iid, [])
            reason_bits = []
            if p.get("orders"):
                reason_bits.append(f"{int(p['orders'])} orders")
            if roas:
                reason_bits.append(f"ROAS {roas:.1f}")
            if tags:
                reason_bits.append("tags: " + ", ".join(tags))
            cands[iid] = {
                "item_id": iid,
                "source": strategy if strategy in ("sales", "roas", "stock") else "mixed",
                "sales": sales,
                "roas": roas,
                "tag_score": _tag_score(tags),
                "reason": "; ".join(reason_bits) or "gms performance",
            }

        # Also allow purely recommended items with no GMS perf row (new winners).
        for iid, tags in tag_by_item.items():
            if iid in exclude_item_ids or iid in cands:
                continue
            score = _tag_score(tags)
            if score == 0:
                continue
            cands[iid] = {
                "item_id": iid,
                "source": "recommended",
                "sales": 0.0,
                "roas": 0.0,
                "tag_score": score,
                "reason": "tags: " + ", ".join(tags),
            }

        ranked = list(cands.values())
        if strategy == "roas":
            ranked.sort(key=lambda c: (c["roas"], c["sales"], c["tag_score"]), reverse=True)
        elif strategy in ("stock", "mixed"):
            ranked.sort(key=lambda c: (c["tag_score"], c["sales"], c["roas"]), reverse=True)
        else:  # 'sales' default: sales first, then ROAS
            ranked.sort(key=lambda c: (c["sales"], c["roas"], c["tag_score"]), reverse=True)
        return ranked

    async def replenish_item_ads(self, force: bool = False) -> List[ItemReplenishResult]:
        """
        Create enough NEW individual product ads to bring the active pool up to
        ``max_item_ads``. Each new ad backs exactly ONE item at the flat starting
        budget. Item selection follows ``item_selection`` (default sales-first).

        First syncs with live manual ads (so manual + turned-off ads are counted
        correctly against the cap). Respects ``item_replenish_enabled`` unless
        ``force`` (manual /replenish item) is set. Never raises.
        """
        results: List[ItemReplenishResult] = []
        settings = await asyncio.to_thread(self.db.get_settings)

        if not int(settings.get("item_ads_enabled", 1)):
            logger.info("[item] subsystem disabled; replenish skipped.")
            return results
        if not force and not int(settings.get("item_replenish_enabled", 1)):
            logger.info("[item] auto-replenish disabled; skipping.")
            return results

        # 1. sync so cap counting reflects reality (imports manual ads too).
        await self.sync_item_ads()

        cap = int(settings.get("max_item_ads", 10))
        max_per_run = int(settings.get("item_replenish_max_per_run", 5))
        active = await asyncio.to_thread(self.db.count_active_item_ads)
        need = cap - active
        if need <= 0:
            logger.info("[item] active %d >= cap %d; nothing to replenish.", active, cap)
            return results
        need = min(need, max_per_run)

        # Also respect the combined budget headroom: don't create ads we can't
        # fund at the starting budget.
        starting_budget = self._starting_budget_map(settings)[AD_TYPE_PRODUK_MANUAL]
        starting_budget = max(starting_budget, ITEM_AD_MIN_BUDGET)
        headroom = await self._combined_headroom(settings)
        affordable = int(headroom // starting_budget) if starting_budget > 0 else 0
        if affordable < need:
            logger.info(
                "[item] headroom %.0f funds only %d of %d new ads.",
                headroom, affordable, need,
            )
            need = max(affordable, 0)
        if need <= 0:
            return results

        # 2. rank candidates (exclude items already advertised + recently used).
        exclude = await asyncio.to_thread(self.db.item_ad_item_ids)
        exclude |= await asyncio.to_thread(self.db.recently_used_item_ids, 3)
        ranked = await self._rank_item_candidates(settings, exclude)
        if not ranked:
            logger.info("[item] no eligible candidates found.")
            return results

        use_reco_roi = bool(int(settings.get("item_use_recommended_roi", 0)))
        flat_roas = float(settings.get("item_new_roas_target", 0.0) or 0.0)

        # 3. create the top `need`.
        for cand in ranked[:need]:
            iid = int(cand["item_id"])
            roas_target = flat_roas
            if use_reco_roi:
                try:
                    reco = await self.client.get_item_recommended_roi(iid)
                    if reco.get("exact"):
                        roas_target = float(reco["exact"])
                except Exception:  # noqa: BLE001
                    pass
            try:
                created = await self.client.create_manual_product_ad(
                    iid, starting_budget, roas_target=roas_target,
                )
                new_cid = created.get("campaign_id")
                if new_cid:
                    await asyncio.to_thread(
                        self.db.upsert_item_ad, str(new_cid),
                        item_id=iid, origin="bot", budget=starting_budget,
                        roas_target=roas_target, status="ongoing",
                        increments_today=0, low_roas_streak=0, turned_off=0,
                    )
                    await asyncio.to_thread(
                        self.db.record_created_group,
                        item_id=iid, budget=starting_budget, source=cand["source"],
                        mode="created", campaign_id=str(new_cid),
                        roas_target=roas_target, reason=cand["reason"],
                    )
                    await asyncio.to_thread(
                        self.db.log_budget_change,
                        ad_type=AD_TYPE_PRODUK_MANUAL, campaign_id=str(new_cid),
                        campaign_name=f"Item ad {iid}",
                        budget_before=0.0, budget_after=starting_budget,
                        action_taken=f"Created individual ad from item {iid} ({cand['source']})",
                    )
                results.append(
                    ItemReplenishResult(
                        item_id=iid, source=cand["source"], reason=cand["reason"],
                        budget=starting_budget, roas_target=roas_target,
                        mode="created", campaign_id=(str(new_cid) if new_cid else None),
                    )
                )
                logger.info("[item] created individual ad from item %s -> %s", iid, new_cid)
            except ShopeeAPIError as exc:
                await asyncio.to_thread(
                    self.db.record_created_group,
                    item_id=iid, budget=starting_budget, source=cand["source"],
                    mode="suggested", campaign_id=None, roas_target=roas_target,
                    reason=cand["reason"],
                )
                results.append(
                    ItemReplenishResult(
                        item_id=iid, source=cand["source"], reason=cand["reason"],
                        budget=starting_budget, roas_target=roas_target,
                        mode="suggested", error=str(exc.error or exc),
                    )
                )
                logger.warning(
                    "[item] create unavailable for item %s (%s); suggested instead.",
                    iid, exc.error or exc,
                )
            except Exception as exc:  # noqa: BLE001
                results.append(
                    ItemReplenishResult(
                        item_id=iid, source=cand["source"], reason=cand["reason"],
                        budget=starting_budget, roas_target=roas_target,
                        mode="failed", error=str(exc),
                    )
                )
                logger.exception("[item] unexpected create error for item %s: %s", iid, exc)

        logger.info(
            "[item] replenish done. active=%d cap=%d attempted=%d results=%d",
            active, cap, need, len(results),
        )
        return results

    async def apply_item_ads_increment(
        self, total_increment: float, label: str = ""
    ) -> List[IncrementResult]:
        """
        Distribute a shared increment pool across all active individual ads,
        weighted by ROAS tier (high/mid/low), clamped to the combined cap, and
        TURN OFF ads that stay under the ROAS threshold for too many checks.

        Per-item ROAS comes from the GMS item performance report.
        """
        results: List[IncrementResult] = []
        settings = await asyncio.to_thread(self.db.get_settings)

        if not int(settings.get("item_ads_enabled", 1)):
            logger.info("[item] subsystem disabled; increment skipped.")
            return results

        # Sync first so imported manual ads participate too.
        await self.sync_item_ads()

        split_high = float(settings.get("item_split_high", 0.60))
        split_mid = float(settings.get("item_split_mid", 0.30))
        split_low = float(settings.get("item_split_low", 0.10))
        roas_off = float(settings.get("item_roas_off_threshold", 6.0))
        off_after = int(settings.get("item_off_after_checks", 2))

        ads = await asyncio.to_thread(self.db.get_item_ads, active_only=True)
        if not ads:
            logger.info("[item] no active individual ads to increment.")
            return results

        # Fetch per-ad ROAS keyed by CAMPAIGN ID (best effort) and attach.
        #
        # IMPORTANT: individual (manual) product ads must be scored with their
        # OWN campaign performance (get_product_campaign_daily_performance via
        # client.get_item_ads_roas), matched by campaign_id. The GMV-Max item
        # performance report describes items inside the GMV-Max campaign, not
        # standalone manual ads — matching it by item_id attaches the wrong ROAS
        # (usually 0.0) to each ad, which is the discrepancy we are fixing.
        roas_by_campaign: Dict[str, float] = {}
        try:
            cids = [int(a["campaign_id"]) for a in ads]
            roas_by_campaign = await self.client.get_item_ads_roas(cids)
        except Exception as exc:  # noqa: BLE001
            logger.warning("[item] roas fetch failed: %s", exc)
        for a in ads:
            cid = str(a.get("campaign_id", ""))
            a["roas"] = roas_by_campaign.get(cid, float(a.get("last_roas") or 0.0))

        # Refresh each ad's CURRENT budget from Shopee (live) so the increment
        # is applied on top of the real budget — including any change the user
        # made manually in Shopee Ads since our last sync. The formula must be
        #     new_budget = live_current_budget + increment
        # never DB_budget + increment (the DB can lag behind a manual edit).
        #
        # We also PERSIST the live value back to the DB so the combined-cap
        # headroom calculation (which sums DB budgets) reflects reality too.
        live_budget_by_campaign: Dict[str, float] = {}
        try:
            live = await self.client.list_manual_product_ads(only_active=True)
            live_budget_by_campaign = {
                str(x["campaign_id"]): float(x.get("budget", 0.0) or 0.0)
                for x in live
            }
        except Exception as exc:  # noqa: BLE001
            logger.warning("[item] live-budget fetch failed, using DB values: %s", exc)
        for a in ads:
            cid = str(a.get("campaign_id", ""))
            live_b = live_budget_by_campaign.get(cid)
            if live_b is not None and live_b > 0:
                db_b = float(a.get("budget", 0.0) or 0.0)
                if abs(live_b - db_b) > 0.01:
                    logger.info(
                        "[item] %s live budget %.0f != DB %.0f (manual change?) "
                        "— incrementing on top of live value.",
                        cid, live_b, db_b,
                    )
                    await asyncio.to_thread(
                        self.db.upsert_item_ad, cid, budget=live_b
                    )
                a["budget"] = live_b

        headroom = await self._combined_headroom(settings)
        effective_pool = min(float(total_increment), headroom)

        # Tier split (reuse the group tier splitter; expects {campaign_id, roas}).
        alloc = split_group_increment(
            [{"campaign_id": a["campaign_id"], "roas": a["roas"], "turned_off": a.get("turned_off", 0)}
             for a in ads],
            effective_pool, split_high, split_mid, split_low,
        )
        running_headroom = headroom

        for a in ads:
            cid = str(a["campaign_id"])
            iid = int(a.get("item_id", 0))
            roas = float(a.get("roas", 0.0))
            budget = float(a.get("budget", 0.0) or 0.0)
            streak = int(a.get("low_roas_streak", 0))
            inc_today = int(a.get("increments_today", 0))

            streak = streak + 1 if roas < roas_off else 0
            should_turn_off = streak >= off_after

            if should_turn_off:
                try:
                    # END the ad (status -> "berakhir"/ended), not merely pause
                    # it ("nonaktif"). Its item keeps running under the auto
                    # product ads / GMV-Max; the standalone ad is ended.
                    await self.client.stop_item_ad(cid)
                    action = (
                        f"ENDED (ROAS {roas:.2f} < {roas_off:.0f} for "
                        f"{streak} checks)"
                    )
                    if label:
                        action = f"[{label}] {action}"
                    await asyncio.to_thread(
                        self.db.upsert_item_ad, cid,
                        last_roas=roas, low_roas_streak=streak, turned_off=1,
                        status="ended",
                    )
                    await asyncio.to_thread(
                        self.db.log_budget_change,
                        ad_type=AD_TYPE_PRODUK_MANUAL, campaign_id=cid,
                        campaign_name=f"Item ad {iid}",
                        budget_before=budget, budget_after=budget,
                        roas=roas, action_taken=action,
                    )
                    results.append(
                        IncrementResult(
                            ad_type=AD_TYPE_PRODUK_MANUAL, campaign_id=cid,
                            campaign_name=f"Item ad {iid}",
                            budget_before=budget, budget_after=budget,
                            requested_increment=0.0, applied_increment=0.0,
                            capped=False, action=action, roas=roas, turned_off=True,
                        )
                    )
                except ShopeeAPIError as exc:
                    logger.error("[item] turn-off failed for %s: %s", cid, exc)
                except Exception:  # noqa: BLE001
                    logger.exception("[item] unexpected turn-off error for %s", cid)
                continue

            inc = float(alloc.get(cid, 0.0))
            try:
                per_cap = budget + running_headroom
                calc = compute_new_budget(budget, inc, per_cap)
                new_budget = calc["new_budget"]
                running_headroom = max(running_headroom - calc["applied_increment"], 0.0)
                if calc["applied_increment"] > 0.01:
                    await self.client.set_item_ad_budget(cid, new_budget)
                action = f"{calc['action']} | ROAS {roas:.2f}"
                if label:
                    action = f"[{label}] {action}"
                await asyncio.to_thread(
                    self.db.upsert_item_ad, cid,
                    budget=new_budget, last_roas=roas, low_roas_streak=streak,
                    increments_today=inc_today + 1,
                )
                await asyncio.to_thread(
                    self.db.log_budget_change,
                    ad_type=AD_TYPE_PRODUK_MANUAL, campaign_id=cid,
                    campaign_name=f"Item ad {iid}",
                    budget_before=budget, budget_after=new_budget,
                    roas=roas, action_taken=action,
                )
                results.append(
                    IncrementResult(
                        ad_type=AD_TYPE_PRODUK_MANUAL, campaign_id=cid,
                        campaign_name=f"Item ad {iid}",
                        budget_before=budget, budget_after=new_budget,
                        requested_increment=inc, applied_increment=calc["applied_increment"],
                        capped=calc["capped"], action=action, roas=roas,
                    )
                )
            except ShopeeAPIError as exc:
                logger.error("[item] increment failed for %s: %s", cid, exc)
            except Exception:  # noqa: BLE001
                logger.exception("[item] unexpected increment error for %s", cid)

        logger.info(
            "[item] increment run complete (pool %.0f IDR across %d ad(s)).",
            total_increment, len(ads),
        )
        return results
