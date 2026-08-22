#!/usr/bin/env python3
"""
bot.py
======

Entry point for the **Shopee Ads Budget Management Bot (Indonesia)**.

Automation model (time-based fixed-IDR ramp, per ad type)
--------------------------------------------------------
Shopee has four ad types this bot manages:

  1. Iklan Toko Auto/Booster   (single ad)
  2. Iklan Toko Manual         (single ad)
  3. Iklan Produk Otomatis     (single ad)
  4. Iklan Group               (multiple ad groups, SHARED schedule)

* Each of the three single-ad types has its OWN schedules: clock time (HH:MM,
  Asia/Jakarta) + its OWN fixed IDR increment. At each time the bot ADDS that IDR
  to that ad's budget, clamped to the daily cap.
* Iklan Group uses a SHARED schedule: the increment is a TOTAL pool split across
  all ad groups weighted by ROAS (high/mid/low tiers, default 60/30/10). Ad
  groups that stay under the ROAS "off" threshold (default 6) for >= N increments
  are TURNED OFF (not paused) — they keep running under Iklan Produk Otomatis.
* Every day at 00:00/00:01 WIB everything resets to the starting budget and any
  turned-off groups are re-opened.

All scheduled times are anchored to **Asia/Jakarta (WIB, GMT+7)**.

Runs independently of the TikTok bot: own folder, own DB, own Telegram token,
own callback port. Same admin Telegram user id may be reused.
"""

from __future__ import annotations

import asyncio
import functools
import logging
import os
import re
from datetime import time as dtime
from typing import Any, Callable, Dict, List, Optional

try:
    from zoneinfo import ZoneInfo
except Exception:  # pragma: no cover
    ZoneInfo = None  # type: ignore

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.constants import ParseMode
from telegram.ext import (
    Application,
    ApplicationBuilder,
    CallbackQueryHandler,
    CommandHandler,
    ContextTypes,
)

from config import (
    AD_TYPE_GROUP,
    AD_TYPE_GMV_MAX,
    AD_TYPE_LABELS,
    AD_TYPE_PRODUK_AUTO,
    AD_TYPE_PRODUK_MANUAL,
    AD_TYPE_TOKO_AUTO,
    AD_TYPE_TOKO_MANUAL,
    ALL_AD_TYPES,
    CAPPED_AD_TYPES,
    CONFIG,
    SINGLE_AD_TYPES,
)
from database import Database, STATUS_PAUSED, STATUS_RUNNING
from engine import AutomationEngine
from oauth_server import OAuthCallbackServer
from shopee_api import ShopeeAdsClient, ShopeeAPIError

# --------------------------------------------------------------------------- #
# Logging
# --------------------------------------------------------------------------- #
logging.basicConfig(
    format="%(asctime)s | %(levelname)-7s | %(name)s | %(message)s",
    level=getattr(logging, CONFIG.log_level.upper(), logging.INFO),
)
logging.getLogger("httpx").setLevel(logging.WARNING)
logger = logging.getLogger("bot")

JOB_DAILY_RESET = "daily_reset"
JOB_SCHEDULE_PREFIX = "sched_"  # sched_<ad_type>_<HH:MM>
JOB_REPLENISH = "group_replenish"
JOB_ITEM_REPLENISH = "item_replenish"

# Short aliases used in Telegram commands so users don't type the long keys.
AD_TYPE_ALIASES = {
    "toko_auto": "iklan_toko_auto",
    "toko_booster": "iklan_toko_auto",
    "booster": "iklan_toko_auto",
    "toko_manual": "iklan_toko_manual",
    "produk_auto": "iklan_produk_auto",
    "produk_otomatis": "iklan_produk_auto",
    "group": "iklan_group",
    "gmv": "gmv_max",
    "gmv_max": "gmv_max",
    "gmvmax": "gmv_max",
    "gmv_max_roas": "gmv_max",
    "produk_gmv": "gmv_max",
    "iklan_toko_auto": "iklan_toko_auto",
    "iklan_toko_manual": "iklan_toko_manual",
    "iklan_produk_auto": "iklan_produk_auto",
    "iklan_group": "iklan_group",
    # Individual product ads (each ad = 1 item).
    "item": "iklan_produk_manual",
    "item_ads": "iklan_produk_manual",
    "itemads": "iklan_produk_manual",
    "produk_manual": "iklan_produk_manual",
    "individual": "iklan_produk_manual",
    "iklan_produk_manual": "iklan_produk_manual",
}

# --------------------------------------------------------------------------- #
# Shared resources
# --------------------------------------------------------------------------- #
DB = Database(CONFIG.db_path)
SHOPEE = ShopeeAdsClient(CONFIG)
ENGINE = AutomationEngine(DB, SHOPEE, CONFIG)

# Absolute path to the .env this process was started with (systemd
# EnvironmentFile). Used to persist OAuth tokens so they survive restarts.
ENV_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env")


def _persist_env_values(values: Dict[str, str], env_path: str = ENV_FILE) -> bool:
    """
    Update (or append) KEY=VALUE pairs in the .env file, preserving the rest of
    the file. Returns True on success. Best-effort: never raises to the caller.
    """
    try:
        try:
            with open(env_path, "r", encoding="utf-8") as fh:
                text = fh.read()
        except FileNotFoundError:
            text = ""
        for key, val in values.items():
            line = f"{key}={val}"
            pat = re.compile(rf"^{re.escape(key)}=.*$", re.MULTILINE)
            if pat.search(text):
                text = pat.sub(line, text)
            else:
                if text and not text.endswith("\n"):
                    text += "\n"
                text += line + "\n"
        tmp = env_path + ".tmp"
        with open(tmp, "w", encoding="utf-8") as fh:
            fh.write(text)
        os.replace(tmp, env_path)
        try:
            os.chmod(env_path, 0o600)
        except OSError:
            pass
        return True
    except Exception as exc:  # pragma: no cover - defensive
        logger.error("Failed to persist tokens to %s: %s", env_path, exc)
        return False


def _on_token_refreshed(access_token: str, refresh_token: str) -> None:
    """
    Callback invoked by ShopeeAdsClient after an automatic access-token refresh.
    Persists the new tokens to .env so they survive a restart. The client has
    already updated CONFIG in memory.
    """
    values = {"SHOPEE_ACCESS_TOKEN": access_token}
    if refresh_token:
        values["SHOPEE_REFRESH_TOKEN"] = refresh_token
    _persist_env_values(values)


# Wire the auto-refresh persistence into the shared client.
SHOPEE.set_token_persist_cb(_on_token_refreshed)


# --------------------------------------------------------------------------- #
# Helpers
# --------------------------------------------------------------------------- #
def _local_tz():
    if ZoneInfo is not None:
        try:
            return ZoneInfo(CONFIG.timezone)
        except Exception:  # noqa: BLE001
            logger.warning("Unknown timezone %s; falling back to UTC", CONFIG.timezone)
    return None


def _fmt_idr(amount: float) -> str:
    try:
        return f"Rp {float(amount):,.0f}"
    except Exception:  # noqa: BLE001
        return str(amount)


def _resolve_ad_type(token: str) -> Optional[str]:
    return AD_TYPE_ALIASES.get((token or "").strip().lower())


def admin_only(func: Callable) -> Callable:
    @functools.wraps(func)
    async def wrapper(update: Update, context: ContextTypes.DEFAULT_TYPE):
        user = update.effective_user
        if user is None or user.id not in CONFIG.allowed_telegram_user_ids:
            logger.warning(
                "Ignored unauthorized access id=%s username=%s",
                getattr(user, "id", None), getattr(user, "username", None),
            )
            return
        return await func(update, context)

    return wrapper


# --------------------------------------------------------------------------- #
# Scheduling (Asia/Jakarta) — one run_daily job per (ad_type, time)
# --------------------------------------------------------------------------- #
def _reschedule_all(application: Application) -> None:
    """(Re)create one JobQueue job per schedule row, at its clock time (WIB)."""
    jq = application.job_queue
    for job in list(jq.jobs()):
        if job.name and job.name.startswith(JOB_SCHEDULE_PREFIX):
            job.schedule_removal()

    tz = _local_tz()
    schedules = DB.get_schedules(only_enabled=True)
    count = 0
    for s in schedules:
        try:
            hh, mm = (int(x) for x in s["run_time"].split(":"))
        except Exception:  # noqa: BLE001
            logger.warning("Bad schedule time %r; skipping", s.get("run_time"))
            continue
        run_at = dtime(hour=hh, minute=mm, tzinfo=tz) if tz else dtime(hour=hh, minute=mm)
        jq.run_daily(
            _job_increment,
            time=run_at,
            name=f"{JOB_SCHEDULE_PREFIX}{s['ad_type']}_{s['run_time']}",
            data={
                "ad_type": s["ad_type"],
                "increment_idr": s["increment_idr"],
                "run_time": s["run_time"],
            },
        )
        count += 1
    logger.info("Scheduled %d increment job(s) (Asia/Jakarta).", count)


async def _job_increment(context: ContextTypes.DEFAULT_TYPE) -> None:
    """Fired at a scheduled WIB time; applies that ad type's increment."""
    data = context.job.data or {}
    ad_type = data.get("ad_type")
    increment = float(data.get("increment_idr", 0.0))
    run_time = data.get("run_time", "")
    label = run_time

    settings = await asyncio.to_thread(DB.get_settings)
    if settings.get("status") != STATUS_RUNNING:
        logger.info("[%s@%s] skipped: bot paused.", ad_type, run_time)
        return
    if not CONFIG.is_shopee_configured():
        logger.warning("[%s@%s] skipped: Shopee not authorized yet.", ad_type, run_time)
        return

    try:
        if ad_type == AD_TYPE_GMV_MAX:
            results = await ENGINE.apply_gmv_max_increment(increment, label=label)
        elif ad_type == AD_TYPE_PRODUK_MANUAL:
            results = await ENGINE.apply_item_ads_increment(increment, label=label)
        elif ad_type == AD_TYPE_GROUP:
            results = await ENGINE.apply_group_increment(increment, label=label)
        else:
            results = await ENGINE.apply_single_increment(ad_type, increment, label=label)
    except Exception:  # noqa: BLE001
        logger.exception("[%s@%s] increment job crashed", ad_type, run_time)
        return

    if results:
        await _notify_admins(context.application, _format_increment_summary(ad_type, run_time, results))


async def _job_daily_reset(context: ContextTypes.DEFAULT_TYPE) -> None:
    settings = await asyncio.to_thread(DB.get_settings)
    if not CONFIG.is_shopee_configured():
        logger.warning("Daily reset skipped: Shopee not authorized.")
        return
    try:
        results = await ENGINE.run_daily_reset()
    except Exception:  # noqa: BLE001
        logger.exception("Daily reset job crashed")
        return
    if not results:
        await _notify_admins(context.application, "🔄 *Daily reset done* — no ads to reset.")
        return
    # Build a per-ad-type breakdown from the reset results. Each type resets to
    # its OWN starting budget (GMV-Max → starting_budget_gmv_max, individual
    # product ads → item_ad_starting_budget), so report them separately instead
    # of a single flat number.
    by_type: Dict[str, List[Dict[str, Any]]] = {}
    for r in results:
        by_type.setdefault(r["ad_type"], []).append(r)
    lines = [f"🔄 *Daily reset done* — {len(results)} ad(s) reset."]
    for ad_type, rows in by_type.items():
        label = AD_TYPE_LABELS.get(ad_type, ad_type)
        # All rows of one type share the same starting budget.
        amount = rows[0].get("budget_after", 0)
        count = len(rows)
        suffix = " each" if count > 1 else ""
        lines.append(f"• *{label}*: {count} → {_fmt_idr(amount)}{suffix}")
    await _notify_admins(context.application, "\n".join(lines))


def _schedule_daily_reset(application: Application) -> None:
    tz = _local_tz()
    hh = CONFIG.daily_reset_hour
    mm = CONFIG.daily_reset_minute
    application.job_queue.run_daily(
        _job_daily_reset,
        time=dtime(hour=hh, minute=mm, tzinfo=tz) if tz else dtime(hour=hh, minute=mm),
        name=JOB_DAILY_RESET,
    )
    logger.info("Daily reset scheduled for %02d:%02d %s.", hh, mm, CONFIG.timezone)


async def _run_replenish_and_report(application: Application) -> str:
    """Run replenishment and return a human summary (also notifies admins)."""
    if not CONFIG.is_shopee_configured():
        return "⚠️ Shopee not authorized yet — use /authorize first."
    try:
        results = await ENGINE.replenish_groups()
    except Exception:  # noqa: BLE001
        logger.exception("Replenish run crashed")
        return "❌ Replenishment failed (see logs)."
    summary = _format_replenish_summary(results)
    await _notify_admins(application, summary)
    return summary


async def _job_replenish(context: ContextTypes.DEFAULT_TYPE) -> None:
    settings = await asyncio.to_thread(DB.get_settings)
    if not int(settings.get("group_replenish_enabled", 1)):
        logger.info("Replenish job skipped: disabled in settings.")
        return
    await _run_replenish_and_report(context.application)


def _schedule_replenish(application: Application) -> None:
    raw = (CONFIG.group_replenish_time or "").strip()
    if not raw or ":" not in raw:
        logger.info("Group replenish auto-schedule disabled (no time set).")
        return
    try:
        hh, mm = (int(x) for x in raw.split(":", 1))
    except ValueError:
        logger.warning("Invalid GROUP_REPLENISH_TIME %r; skipping schedule.", raw)
        return
    tz = _local_tz()
    application.job_queue.run_daily(
        _job_replenish,
        time=dtime(hour=hh, minute=mm, tzinfo=tz) if tz else dtime(hour=hh, minute=mm),
        name=JOB_REPLENISH,
    )
    logger.info("Group replenish scheduled for %02d:%02d %s.", hh, mm, CONFIG.timezone)


async def _run_item_replenish_and_report(application: Application, force: bool = False) -> str:
    """Run individual-ad replenishment and return a human summary."""
    if not CONFIG.is_shopee_configured():
        return "⚠️ Shopee not authorized yet — use /authorize first."
    try:
        results = await ENGINE.replenish_item_ads(force=force)
    except Exception:  # noqa: BLE001
        logger.exception("Item replenish run crashed")
        return "❌ Individual-ad replenishment failed (see logs)."
    summary = _format_item_replenish_summary(results)
    await _notify_admins(application, summary)
    return summary


async def _job_item_replenish(context: ContextTypes.DEFAULT_TYPE) -> None:
    settings = await asyncio.to_thread(DB.get_settings)
    if not int(settings.get("item_ads_enabled", 1)):
        logger.info("Item replenish job skipped: subsystem disabled.")
        return
    if not int(settings.get("item_replenish_enabled", 1)):
        logger.info("Item replenish job skipped: auto-replenish disabled.")
        return
    await _run_item_replenish_and_report(context.application, force=False)


def _schedule_item_replenish(application: Application) -> None:
    raw = (CONFIG.item_replenish_time or "").strip()
    if not raw or ":" not in raw:
        logger.info("Item replenish auto-schedule disabled (no time set).")
        return
    try:
        hh, mm = (int(x) for x in raw.split(":", 1))
    except ValueError:
        logger.warning("Invalid ITEM_REPLENISH_TIME %r; skipping schedule.", raw)
        return
    tz = _local_tz()
    application.job_queue.run_daily(
        _job_item_replenish,
        time=dtime(hour=hh, minute=mm, tzinfo=tz) if tz else dtime(hour=hh, minute=mm),
        name=JOB_ITEM_REPLENISH,
    )
    logger.info("Item replenish scheduled for %02d:%02d %s.", hh, mm, CONFIG.timezone)


# --------------------------------------------------------------------------- #
# Formatting
# --------------------------------------------------------------------------- #
def _format_replenish_summary(results) -> str:
    if not results:
        return (
            "🌱 *Group replenishment* — no action needed.\n"
            "Active groups already at/above the target, or no eligible "
            "high-performing candidates were found."
        )
    created = [r for r in results if r.mode == "created"]
    suggested = [r for r in results if r.mode == "suggested"]
    failed = [r for r in results if r.mode == "failed"]
    lines = ["🌱 *Group replenishment*"]
    if created:
        lines.append(f"\n✅ *Created {len(created)} new group(s)* at {_fmt_idr(created[0].budget)}:")
        for r in created:
            cid = f" → campaign `{r.campaign_id}`" if r.campaign_id else ""
            lines.append(f"• item `{r.item_id}` ({r.source}){cid}\n   _{r.reason}_")
    if suggested:
        lines.append(
            f"\n💡 *Suggested {len(suggested)} group(s)* "
            "(auto-create unavailable — create these manually in Shopee Ads):"
        )
        for r in suggested:
            lines.append(
                f"• item `{r.item_id}` ({r.source}) @ {_fmt_idr(r.budget)}\n   _{r.reason}_"
            )
    if failed:
        lines.append(f"\n❌ *{len(failed)} failed* (see logs):")
        for r in failed:
            lines.append(f"• item `{r.item_id}`: {r.error}")
    return "\n".join(lines)


def _format_item_replenish_summary(results) -> str:
    if not results:
        return (
            "🧩 *Individual-ad replenishment* — no action needed.\n"
            "Active individual ads already at the cap, no budget headroom, or "
            "no eligible candidate items were found."
        )
    created = [r for r in results if r.mode == "created"]
    suggested = [r for r in results if r.mode == "suggested"]
    failed = [r for r in results if r.mode == "failed"]
    lines = ["🧩 *Individual product ads*"]
    if created:
        lines.append(f"\n✅ *Created {len(created)} new ad(s)* at {_fmt_idr(created[0].budget)} each:")
        for r in created:
            cid = f" → campaign `{r.campaign_id}`" if r.campaign_id else ""
            roas = f", ROAS target {r.roas_target:.1f}" if r.roas_target else ""
            lines.append(f"• item `{r.item_id}` ({r.source}{roas}){cid}\n   _{r.reason}_")
    if suggested:
        lines.append(
            f"\n💡 *Suggested {len(suggested)} ad(s)* "
            "(auto-create unavailable — create manually in Shopee Ads):"
        )
        for r in suggested:
            lines.append(
                f"• item `{r.item_id}` ({r.source}) @ {_fmt_idr(r.budget)}\n   _{r.reason}_"
            )
    if failed:
        lines.append(f"\n❌ *{len(failed)} failed* (see logs):")
        for r in failed:
            lines.append(f"• item `{r.item_id}`: {r.error}")
    return "\n".join(lines)


def _format_increment_summary(ad_type: str, run_time: str, results) -> str:
    label = AD_TYPE_LABELS.get(ad_type, ad_type)
    lines = [f"⏱ *{label}* increment @ {run_time} WIB"]
    for r in results[:12]:
        if getattr(r, "turned_off", False):
            lines.append(f"• 🔴 {r.campaign_name}: {r.action}")
        else:
            lines.append(
                f"• {r.campaign_name}: {_fmt_idr(r.budget_before)} → "
                f"{_fmt_idr(r.budget_after)}"
                + (f" (ROAS {r.roas:.2f})" if r.roas is not None else "")
            )
    if len(results) > 12:
        lines.append(f"…and {len(results) - 12} more")
    return "\n".join(lines)


async def _notify_admins(application: Application, text: str) -> None:
    for uid in CONFIG.allowed_telegram_user_ids:
        try:
            await application.bot.send_message(
                chat_id=uid, text=text, parse_mode=ParseMode.MARKDOWN
            )
        except Exception:  # noqa: BLE001
            logger.debug("Could not notify admin %s", uid)


def _dashboard_markup() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [
                InlineKeyboardButton("📊 Status", callback_data="status"),
                InlineKeyboardButton("🗓 Schedule", callback_data="schedule"),
            ],
            [
                InlineKeyboardButton("📜 History", callback_data="history"),
                InlineKeyboardButton("⚙️ Settings", callback_data="settings"),
            ],
            [
                InlineKeyboardButton("⏸ Pause", callback_data="pause"),
                InlineKeyboardButton("▶️ Resume", callback_data="resume"),
            ],
            [InlineKeyboardButton("🔁 Refresh", callback_data="status")],
        ]
    )


# --------------------------------------------------------------------------- #
# Command handlers
# --------------------------------------------------------------------------- #
@admin_only
async def cmd_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    settings = await asyncio.to_thread(DB.get_settings)
    text = (
        "🛍 *Shopee Ads Budget Bot* (Indonesia)\n"
        f"Status: *{settings.get('status')}*\n"
        f"Timezone: *{CONFIG.timezone}* (WIB, GMT+7)\n\n"
        "Manages 4 ad types with time-based IDR increments:\n"
        "• Iklan Toko Auto/Booster\n• Iklan Toko Manual\n"
        "• Iklan Produk Otomatis\n• Iklan Group (ROAS-weighted split)\n\n"
        "🧩 *Individual Product Ads* (each ad = 1 item): creates a capped pool, "
        "tiers budgets by ROAS, turns off bad performers, and replenishes daily "
        "— `/itemads`.\n\n"
        "Use the buttons below, `/schedule`, or `/itemads` to configure."
    )
    await update.effective_message.reply_text(
        text, reply_markup=_dashboard_markup(), parse_mode=ParseMode.MARKDOWN
    )


def _ad_type_help() -> str:
    return (
        "*Ad types* (use these keys):\n"
        "• `gmv` — Iklan Produk GMV Max  ✅ *the live, controllable type*\n"
        "• `item` — Individual Product Ads  ✅ *managed via* `/itemads` *(auto-split by ROAS tier)*\n"
    )


@admin_only
async def cmd_schedule(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """
    /schedule                                  -> list all schedules
    /schedule add <type> <HH:MM> <IDR>         -> add/update a schedule
    /schedule del <type> <HH:MM>               -> remove a schedule
    /schedule clear [type]                     -> clear all (or one type's)
    """
    msg = update.effective_message
    args = context.args or []

    if not args:
        await _send_schedule(update, context)
        return

    sub = args[0].lower()

    if sub == "add":
        if len(args) < 4:
            await msg.reply_text(
                "Usage: `/schedule add <type> <HH:MM> <IDR>`\n"
                "Example: `/schedule add gmv 11:00 150000`\n\n" + _ad_type_help(),
                parse_mode=ParseMode.MARKDOWN,
            )
            return
        ad_type = _resolve_ad_type(args[1])
        if not ad_type:
            await msg.reply_text("Unknown ad type.\n\n" + _ad_type_help(), parse_mode=ParseMode.MARKDOWN)
            return
        run_time = args[2]
        try:
            increment = float(args[3].replace(",", "").replace(".", ""))
        except ValueError:
            await msg.reply_text("Increment must be a number (IDR), e.g. 50000.")
            return
        try:
            row = await asyncio.to_thread(DB.add_schedule, ad_type, run_time, increment)
        except ValueError as exc:
            await msg.reply_text(f"❌ {exc}")
            return
        _reschedule_all(context.application)
        note = ""
        if ad_type == AD_TYPE_GMV_MAX:
            note = "\n_(Adds this IDR to the single GMV-Max daily budget, clamped to the combined cap.)_"
        elif ad_type == AD_TYPE_GROUP:
            note = "\n_(Legacy: TOTAL pool split across ad groups by ROAS.)_"
        elif ad_type in (AD_TYPE_TOKO_AUTO, AD_TYPE_TOKO_MANUAL):
            note = "\n⚠️ _Iklan Toko has no budget-set API — this schedule won't change Shopee. Set it manually._"
        await msg.reply_text(
            f"✅ Scheduled *{AD_TYPE_LABELS[ad_type]}* at *{row['run_time']}* WIB: "
            f"+{_fmt_idr(row['increment_idr'])}.{note}",
            parse_mode=ParseMode.MARKDOWN,
        )
        return

    if sub == "del":
        if len(args) < 3:
            await msg.reply_text("Usage: `/schedule del <type> <HH:MM>`", parse_mode=ParseMode.MARKDOWN)
            return
        ad_type = _resolve_ad_type(args[1])
        if not ad_type:
            await msg.reply_text("Unknown ad type.\n\n" + _ad_type_help(), parse_mode=ParseMode.MARKDOWN)
            return
        try:
            ok = await asyncio.to_thread(DB.delete_schedule, ad_type, args[2])
        except ValueError as exc:
            await msg.reply_text(f"❌ {exc}")
            return
        _reschedule_all(context.application)
        await msg.reply_text("✅ Removed." if ok else "No matching schedule found.")
        return

    if sub == "clear":
        ad_type = _resolve_ad_type(args[1]) if len(args) > 1 else None
        n = await asyncio.to_thread(DB.clear_schedules, ad_type)
        _reschedule_all(context.application)
        scope = AD_TYPE_LABELS.get(ad_type, "all ad types") if ad_type else "all ad types"
        await msg.reply_text(f"✅ Cleared {n} schedule(s) for {scope}.")
        return

    await msg.reply_text(
        "Unknown sub-command.\n"
        "`/schedule` | `/schedule add <type> <HH:MM> <IDR>` | "
        "`/schedule del <type> <HH:MM>` | `/schedule clear [type]`",
        parse_mode=ParseMode.MARKDOWN,
    )


async def _send_schedule(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    schedules = await asyncio.to_thread(DB.get_schedules)
    settings = await asyncio.to_thread(DB.get_settings)
    lines = ["🗓 *Increment Schedule* (Asia/Jakarta, WIB)\n"]
    if not schedules:
        lines.append("_No schedules yet._\n")
    else:
        by_type: Dict[str, List[Dict[str, Any]]] = {}
        for s in schedules:
            by_type.setdefault(s["ad_type"], []).append(s)
        for ad_type in CAPPED_AD_TYPES:
            rows = by_type.get(ad_type)
            if not rows:
                continue
            lines.append(f"*{AD_TYPE_LABELS[ad_type]}:*")
            for s in rows:
                tag = " (total pool)" if ad_type == AD_TYPE_GROUP else ""
                state = "" if s["enabled"] else " _(disabled)_"
                lines.append(f"  • {s['run_time']} → +{_fmt_idr(s['increment_idr'])}{tag}{state}")
            lines.append("")

    # Expected daily spend summary (start + Σ increments, clamped by the cap).
    enabled = [s for s in schedules if s.get("enabled", 1)]
    est = ENGINE.estimate_daily_spend(settings, enabled)
    gmv = est["gmv_max"]
    lines.append("*💰 Expected daily spend (GMV-Max):*")
    lines.append(
        f"  start {_fmt_idr(gmv['start'])} + increments {_fmt_idr(gmv['increments'])} "
        f"= {_fmt_idr(gmv['planned'])}"
    )
    if est["over_cap"]:
        lines.append(
            f"  ⚠️ clamped to cap → *{_fmt_idr(est['capped_total'])}*"
        )
    else:
        lines.append(f"  ✅ expected ≈ *{_fmt_idr(est['capped_total'])}* (cap {_fmt_idr(est['cap'])})")
    lines.append("")

    lines.append(
        "Add: `/schedule add <type> <HH:MM> <IDR>`\n"
        "Del: `/schedule del <type> <HH:MM>`\n"
        "Clear: `/schedule clear [type]`\n\n" + _ad_type_help()
    )
    await update.effective_message.reply_text("\n".join(lines), parse_mode=ParseMode.MARKDOWN)


@admin_only
async def cmd_status(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _send_status(update, context)


def _tier_breakdown(items: list) -> Dict[str, Dict[str, float]]:
    """
    Rank GMV-Max items by ROAS and split into high/mid/low tiers (as evenly as
    possible). Returns {tier: {count, spend, avg_roas}} for reporting.
    """
    out = {t: {"count": 0, "spend": 0.0, "roas_sum": 0.0} for t in ("high", "mid", "low")}
    n = len(items)
    if n == 0:
        return {t: {"count": 0, "spend": 0.0, "avg_roas": 0.0} for t in out}
    ordered = sorted(items, key=lambda x: float(x.get("roas", 0.0)), reverse=True)
    base = n // 3
    rem = n % 3
    high_n = base + (1 if rem >= 1 else 0)
    mid_n = base + (1 if rem == 2 else 0)
    slices = {
        "high": ordered[:high_n],
        "mid": ordered[high_n:high_n + mid_n],
        "low": ordered[high_n + mid_n:],
    }
    result: Dict[str, Dict[str, float]] = {}
    for tier, grp in slices.items():
        spend = sum(float(i.get("expense", 0.0)) for i in grp)
        roas_sum = sum(float(i.get("roas", 0.0)) for i in grp)
        result[tier] = {
            "count": len(grp),
            "spend": spend,
            "avg_roas": (roas_sum / len(grp)) if grp else 0.0,
        }
    return result


async def _send_status(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    settings = await asyncio.to_thread(DB.get_settings)
    schedules = await asyncio.to_thread(DB.get_schedules, None, True)
    # Individual product ads: current active budgets + count for the estimate.
    active_item_ads = await asyncio.to_thread(DB.get_item_ads, active_only=True)
    item_current = sum(float(a.get("budget", 0.0) or 0.0) for a in active_item_ads)
    est = ENGINE.estimate_daily_spend(
        settings, schedules,
        item_ads_current=item_current, item_ads_count=len(active_item_ads),
    )
    cap = est["cap"]
    gmv = est["gmv_max"]
    item = est["item_ads"]

    lines = [
        "📊 *Shopee Ads Status*",
        f"Status: *{settings.get('status')}*  |  TZ: {CONFIG.timezone} (GMT+7)",
        f"🧱 *Combined daily cap: {_fmt_idr(cap)}*",
        "",
        "*💰 Expected spend in 1 day — Iklan Produk GMV Max:*",
        f"• Starting budget: {_fmt_idr(gmv['start'])}",
        f"• + Scheduled increments: {_fmt_idr(gmv['increments'])}",
        f"• = *Planned daily budget: {_fmt_idr(gmv['planned'])}*",
        "",
        f"*🧩 Individual Product Ads ({item['count']}/{int(settings.get('max_item_ads', 10))} running):*",
        f"• Current budgets: {_fmt_idr(item['start'])}",
        f"• + Scheduled increments: {_fmt_idr(item['increments'])}",
        f"• = *Planned: {_fmt_idr(item['planned'])}*",
        f"• Auto-replenish: {'ON' if int(settings.get('item_replenish_enabled', 1)) else 'OFF'}"
        f"  |  Subsystem: {'ON' if int(settings.get('item_ads_enabled', 1)) else 'OFF'}",
    ]
    if est["over_cap"]:
        lines.append(
            f"⚠️ Planned {_fmt_idr(est['planned_total'])} exceeds the cap "
            f"{_fmt_idr(cap)} → the bot clamps at *{_fmt_idr(est['capped_total'])}*."
        )
    else:
        lines.append(
            f"✅ Within cap. *Expected daily spend ≈ {_fmt_idr(est['capped_total'])}*."
        )

    # Live GMV-Max campaign + ROAS tier breakdown (read-only reporting).
    if CONFIG.is_shopee_configured():
        try:
            camp = await SHOPEE.get_gms_campaign()
        except Exception:  # noqa: BLE001
            camp = None
        if camp:
            lines += [
                "",
                "*📈 Live GMV-Max (today):*",
                f"• Campaign id: `{camp['campaign_id']}`",
                f"• Spend: {_fmt_idr(camp['expense'])}  |  GMV: {_fmt_idr(camp['gmv'])}  "
                f"|  ROAS: {camp['roas']:.2f}",
            ]
            tracked = float(settings.get("gms_current_budget", 0.0) or 0.0)
            if tracked > 0:
                lines.append(f"• Bot-set daily budget now: {_fmt_idr(tracked)}")
            try:
                items = await SHOPEE.get_gms_item_performance()
            except Exception:  # noqa: BLE001
                items = []
            if items:
                tb = _tier_breakdown(items)
                lines.append("")
                lines.append("*🏷 Where it's spending (ROAS tiers of items):*")
                for tier, label in (("high", "High"), ("mid", "Mid"), ("low", "Low")):
                    d = tb[tier]
                    lines.append(
                        f"• {label}: {d['count']} item(s) · spend {_fmt_idr(d['spend'])} "
                        f"· avg ROAS {d['avg_roas']:.2f}"
                    )
                lines.append(
                    "_Tiers are a live ROAS ranking of the items inside the single "
                    "GMV-Max campaign (budget is set campaign-wide by Shopee)._"
                )
    else:
        lines.append("\n⚠️ _Shopee not authorized yet — run /authorize._")

    target = update.effective_message
    await target.reply_text("\n".join(lines), parse_mode=ParseMode.MARKDOWN)


async def _send_history(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    rows = await asyncio.to_thread(DB.get_recent_history, 15)
    if not rows:
        await update.effective_message.reply_text("No history yet.")
        return
    lines = ["📜 *Recent budget changes*\n"]
    for r in rows:
        label = AD_TYPE_LABELS.get(r["ad_type"], r["ad_type"])
        ts = r["timestamp"].replace("T", " ")
        lines.append(f"`{ts}` [{label}] {r['campaign_name']}: {r['action_taken']}")
    await update.effective_message.reply_text("\n".join(lines), parse_mode=ParseMode.MARKDOWN)


@admin_only
async def cmd_history(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _send_history(update, context)


@admin_only
async def cmd_settings(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    args = context.args or []
    if len(args) >= 2:
        key = args[0].lower()
        valid = {
            "starting_budget", "daily_max_budget",
            "starting_budget_toko_auto", "starting_budget_toko_manual",
            "starting_budget_produk_auto", "starting_budget_group",
            "starting_budget_gmv_max",
            "group_split_high", "group_split_mid", "group_split_low",
            "group_roas_off_threshold", "group_off_after_increments",
            # Individual product ads (most also have friendlier /itemads shortcuts)
            "max_item_ads", "item_ad_starting_budget",
            "item_replenish_max_per_run", "item_roas_off_threshold",
            "item_off_after_checks", "item_new_roas_target",
            "item_split_high", "item_split_mid", "item_split_low",
        }
        int_keys = {
            "group_off_after_increments", "max_item_ads",
            "item_replenish_max_per_run", "item_off_after_checks",
        }
        if key not in valid:
            await update.effective_message.reply_text(
                "Editable keys: " + ", ".join(sorted(valid))
            )
            return
        try:
            if key in int_keys:
                value: Any = int(args[1])
            else:
                value = float(args[1].replace(",", ""))
        except ValueError:
            await update.effective_message.reply_text("Value must be numeric.")
            return
        # group_split_* are fractions of the pool (0-1). Reject > 1 (and < 0)
        # so a typo like 60 (meant 0.6) can't blow up the split.
        if key in ("group_split_high", "group_split_mid", "group_split_low",
                   "item_split_high", "item_split_mid", "item_split_low"):
            if value < 0 or value > 1:
                await update.effective_message.reply_text(
                    f"❌ <code>{key}</code> must be a fraction between 0 and 1 "
                    f"(e.g. <code>0.6</code> for 60%). You entered <code>{value}</code>.",
                    parse_mode=ParseMode.HTML,
                )
                return
        await asyncio.to_thread(DB.update_setting, **{key: value})
        await update.effective_message.reply_text(
            f"✅ Set <code>{key}</code> = <code>{value}</code>.", parse_mode=ParseMode.HTML
        )
        return
    await _send_settings(update, context)


async def _send_settings(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    s = await asyncio.to_thread(DB.get_settings)

    def _sb(key: str) -> float:
        v = s.get(key)
        try:
            v = float(v)
        except (TypeError, ValueError):
            v = 0.0
        return v if v > 0 else float(s.get("starting_budget", 0) or 0)

    gmv_start = _sb("starting_budget_gmv_max")
    gmv_current = float(s.get("gms_current_budget", 0.0) or 0.0)

    # NOTE: rendered as HTML (not Markdown) because setting keys contain
    # underscores, which Telegram Markdown treats as italic markers and would
    # raise "Can't parse entities". In HTML underscores are literal.
    text = (
        "⚙️ <b>Settings</b>\n"
        f"🧱 <b>daily_max_budget (COMBINED cap, all ad types): {_fmt_idr(s['daily_max_budget'])}</b>\n\n"
        "<b>📈 GMV-Max</b>\n"
        f"• starting_budget_gmv_max (daily reset): {_fmt_idr(gmv_start)}\n"
        f"• current budget (tracked): {_fmt_idr(gmv_current)}\n"
        "  <i>Add budget through the day with </i><code>/schedule add gmv HH:MM IDR</code>\n\n"
        "<b>🧩 Individual Product Ads</b>\n"
        f"• subsystem: {'ON' if int(s.get('item_ads_enabled', 1)) else 'OFF'}"
        f"  |  auto-replenish: {'ON' if int(s.get('item_replenish_enabled', 1)) else 'OFF'}\n"
        f"• max_item_ads (cap): {int(s.get('max_item_ads', 10))}\n"
        f"• item_ad_starting_budget: {_fmt_idr(s.get('item_ad_starting_budget', 25000))}\n"
        f"• item_roas_off_threshold: {float(s.get('item_roas_off_threshold', 6.0)):.1f}"
        f"  after {int(s.get('item_off_after_checks', 2))} checks\n"
        f"• selection: {s.get('item_selection', 'sales')}\n"
        f"• ROAS-tier split high/mid/low: "
        f"{float(s.get('item_split_high', 0.6)):.0%}/"
        f"{float(s.get('item_split_mid', 0.3)):.0%}/"
        f"{float(s.get('item_split_low', 0.1)):.0%}\n"
        "  <i>Manage these with </i><code>/itemads</code>\n\n"
        "Edit with <code>/settings &lt;key&gt; &lt;value&gt;</code>, e.g.:\n"
        "<code>/settings daily_max_budget 5000000</code>\n"
        "<code>/settings starting_budget_gmv_max 950000</code>\n"
        "<code>/settings item_split_high 0.6</code>\n\n"
        "Manage increment times: <code>/schedule</code>\n"
        "<i>Only GMV-Max and Individual Product Ads are live. "
        "toko_auto / toko_manual / produk_auto / group are legacy.</i>"
    )
    await update.effective_message.reply_text(text, parse_mode=ParseMode.HTML)


@admin_only
async def cmd_pause(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await asyncio.to_thread(DB.set_status, STATUS_PAUSED)
    await update.effective_message.reply_text("⏸ Automation *paused*.", parse_mode=ParseMode.MARKDOWN)


@admin_only
async def cmd_resume(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await asyncio.to_thread(DB.set_status, STATUS_RUNNING)
    await update.effective_message.reply_text("▶️ Automation *resumed*.", parse_mode=ParseMode.MARKDOWN)


@admin_only
async def cmd_replenish(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """
    Manually trigger group replenishment now, or manage its settings.

      /replenish                 -> run replenishment now
      /replenish status          -> show replenish config + recent created/suggested
      /replenish on | off        -> enable/disable auto replenishment
      /replenish target <N>      -> set target active-group count
      /replenish max <N>         -> set max new groups per run
      /replenish minroas <X>     -> set min ROAS for recycled SKUs
      /replenish roastarget <X>  -> ROAS target for new groups (0 = auto bidding)
    """
    args = [a.lower() for a in (context.args or [])]
    msg = update.effective_message

    if not args:
        await msg.reply_text("🌱 Running group replenishment now…")
        summary = await _run_replenish_and_report(context.application)
        await msg.reply_text(summary, parse_mode=ParseMode.MARKDOWN)
        return

    sub = args[0]
    if sub == "status":
        s = await asyncio.to_thread(DB.get_settings)
        created = await asyncio.to_thread(DB.get_created_groups, 10)
        lines = [
            "🌱 *Group replenishment settings*",
            f"• Auto: {'ON' if int(s.get('group_replenish_enabled', 1)) else 'OFF'}"
            f" @ {CONFIG.group_replenish_time or '(disabled)'} WIB",
            f"• Target active groups: *{int(s.get('group_target_active_count', 5))}*",
            f"• Max new per run: *{int(s.get('group_replenish_max_per_run', 3))}*",
            f"• Min recycled ROAS: *{float(s.get('group_replenish_min_roas', 6.0)):.1f}*",
            f"• New-group ROAS target: *{float(s.get('group_new_roas_target', 0.0)):.1f}*"
            " (0 = GMV-Max auto)",
            f"• New-group starting budget: *{_fmt_idr(s.get('starting_budget', 0))}*",
        ]
        if created:
            lines.append("\n*Recent created / suggested:*")
            for c in created:
                tag = "✅ created" if c["mode"] == "created" else (
                    "💡 suggested" if c["mode"] == "suggested" else "❌ " + c["mode"]
                )
                cid = f" `{c['campaign_id']}`" if c.get("campaign_id") else ""
                lines.append(f"• {tag} item `{c['item_id']}` ({c['source']}){cid}")
        await msg.reply_text("\n".join(lines), parse_mode=ParseMode.MARKDOWN)
        return

    try:
        if sub in ("on", "off"):
            await asyncio.to_thread(
                DB.update_setting, group_replenish_enabled=1 if sub == "on" else 0
            )
            await msg.reply_text(f"🌱 Auto replenishment *{sub.upper()}*.", parse_mode=ParseMode.MARKDOWN)
            return
        if len(args) < 2:
            await msg.reply_text("Missing value. See /replenish (no args) usage.")
            return
        val = args[1]
        if sub == "target":
            await asyncio.to_thread(DB.update_setting, group_target_active_count=int(val))
            await msg.reply_text(f"🎯 Target active groups set to *{int(val)}*.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "max":
            await asyncio.to_thread(DB.update_setting, group_replenish_max_per_run=int(val))
            await msg.reply_text(f"🔢 Max new groups per run set to *{int(val)}*.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "minroas":
            await asyncio.to_thread(DB.update_setting, group_replenish_min_roas=float(val))
            await msg.reply_text(f"📉 Min recycled ROAS set to *{float(val):.1f}*.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "roastarget":
            await asyncio.to_thread(DB.update_setting, group_new_roas_target=float(val))
            await msg.reply_text(f"🎯 New-group ROAS target set to *{float(val):.1f}*.", parse_mode=ParseMode.MARKDOWN)
        else:
            await msg.reply_text("Unknown option. See /replenish (no args) for usage.")
    except ValueError:
        await msg.reply_text("Invalid number.")


@admin_only
async def cmd_itemads(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """
    Manage the Individual Product Ads subsystem (each ad = 1 item).

      /itemads                    -> status: active vs cap, ROAS tiers, turned-off
      /itemads replenish          -> create ads now to fill up to the cap
      /itemads sync               -> import/refresh live manual ads
      /itemads on | off           -> master subsystem on/off
      /itemads auto on | off      -> daily auto-replenish on/off toggle
      /itemads cap <N>            -> max number of running item ads
      /itemads budget <IDR>       -> starting budget per new ad (min 25,000)
      /itemads roasoff <X>        -> ROAS turn-off threshold
      /itemads offafter <N>       -> low-ROAS checks before turn-off
      /itemads roastarget <X>     -> default ROAS target for NEW ads (0 = auto)
      /itemads adroas <id> <X>    -> set ROAS target of ONE ad (campaign or item id)
      /itemads maxperrun <N>      -> max new ads created per replenish run
      /itemads selection sales|roas|stock|mixed  -> candidate ranking
      /itemads recroi on | off    -> use Shopee's recommended ROI for new ads
    """
    args = [a.lower() for a in (context.args or [])]
    msg = update.effective_message

    # ---- no args: status ----
    if not args:
        s = await asyncio.to_thread(DB.get_settings)
        all_ads = await asyncio.to_thread(DB.get_item_ads)
        active = [a for a in all_ads
                  if not int(a.get("turned_off", 0))
                  and str(a.get("status", "")).lower()
                  in ("", "ongoing", "running", "active", "scheduled")]
        off = [a for a in all_ads if int(a.get("turned_off", 0))]
        cap = int(s.get("max_item_ads", 10))
        total_budget = sum(float(a.get("budget", 0.0) or 0.0) for a in active)

        # Enrich with LIVE per-item ROAS + GMV/orders (best-effort) so the
        # display shows real performance, not just the last stored value.
        live_roas: dict = {}
        live_gmv: dict = {}
        live_orders: dict = {}
        if CONFIG.is_shopee_configured() and all_ads:
            try:
                perf = await SHOPEE.get_gms_item_performance(days_back=1, limit=100)
                for p in perf:
                    iid = int(p.get("item_id", 0) or 0)
                    live_roas[iid] = float(p.get("roas", 0.0) or 0.0)
                    live_gmv[iid] = float(p.get("gmv", 0.0) or 0.0)
                    live_orders[iid] = int(p.get("orders", 0) or 0)
            except Exception:  # noqa: BLE001
                pass

        def _roas_of(a: dict) -> float:
            iid = int(a.get("item_id", 0) or 0)
            return live_roas.get(iid, float(a.get("last_roas") or 0.0))

        lines = [
            "🧩 <b>Individual Product Ads</b>",
            f"• Subsystem: <b>{'ON' if int(s.get('item_ads_enabled', 1)) else 'OFF'}</b>"
            f"  |  Auto-replenish: <b>{'ON' if int(s.get('item_replenish_enabled', 1)) else 'OFF'}</b>"
            f" @ {CONFIG.item_replenish_time or '(off)'} WIB",
            f"• Running: <b>{len(active)} / {cap}</b> (cap)"
            f"  |  Total budget: <b>{_fmt_idr(total_budget)}</b>",
            f"• Starting budget/ad: <b>{_fmt_idr(s.get('item_ad_starting_budget', 25000))}</b>"
            f"  |  Turn-off: ROAS &lt; <b>{float(s.get('item_roas_off_threshold', 6.0)):.1f}</b>"
            f" ×<b>{int(s.get('item_off_after_checks', 2))}</b>",
            f"• Selection: <b>{s.get('item_selection', 'sales')}</b> (sales→ROAS)"
            f"  |  New-ad ROAS target: <b>{float(s.get('item_new_roas_target', 0.0)):.1f}</b>"
            f"{' (recommended)' if int(s.get('item_use_recommended_roi', 0)) else ' (0=auto)'}",
        ]

        def _fmt_roas_target(rt: float) -> str:
            rt = float(rt or 0.0)
            return "auto" if rt <= 0 else f"{rt:.1f}"

        def _ad_line(a: dict) -> str:
            iid = int(a.get("item_id", 0) or 0)
            roas = _roas_of(a)
            rt = _fmt_roas_target(a.get("roas_target"))
            origin = "👤 manual" if str(a.get("origin")) == "manual" else "🤖 bot"
            extras = []
            if live_orders.get(iid):
                extras.append(f"{live_orders[iid]} ord")
            if live_gmv.get(iid):
                extras.append(f"GMV {_fmt_idr(live_gmv[iid])}")
            streak = int(a.get("low_roas_streak", 0) or 0)
            if streak:
                extras.append(f"⚠️{streak} low-ROAS")
            extra = ("  ·  " + " · ".join(extras)) if extras else ""
            return (
                f"• <code>{iid}</code> [{origin}]\n"
                f"   budget <b>{_fmt_idr(a.get('budget', 0.0))}</b>  ·  "
                f"set ROAS <b>{rt}</b>  ·  live ROAS <b>{roas:.2f}</b>{extra}"
            )

        if active:
            lines.append(f"\n<b>▶️ Active ads ({len(active)}):</b>")
            # Show best ROAS first.
            for a in sorted(active, key=_roas_of, reverse=True):
                lines.append(_ad_line(a))

            # Compact ROAS-tier rollup (how budget increments will be weighted).
            tiers = _tier_breakdown(
                [{"item_id": a.get("item_id"), "roas": _roas_of(a),
                  "expense": float(a.get("budget", 0.0) or 0.0)} for a in active]
            )
            tier_bits = []
            for tname in ("high", "mid", "low"):
                t = tiers.get(tname, {})
                if int(t.get("count", 0)):
                    tier_bits.append(
                        f"{tname} {int(t['count'])} (avg {float(t.get('avg_roas', 0.0)):.2f})"
                    )
            if tier_bits:
                lines.append("\n<b>ROAS tiers</b> (budget weight "
                             f"{float(s.get('item_split_high', 0.6)):.0%}/"
                             f"{float(s.get('item_split_mid', 0.3)):.0%}/"
                             f"{float(s.get('item_split_low', 0.1)):.0%}): "
                             + "  ·  ".join(tier_bits))

        if off:
            lines.append(f"\n<b>⛔ Turned off today ({len(off)}):</b>")
            for a in sorted(off, key=_roas_of, reverse=True)[:10]:
                iid = int(a.get("item_id", 0) or 0)
                lines.append(
                    f"• <code>{iid}</code>  live ROAS {_roas_of(a):.2f}  "
                    f"(set {_fmt_roas_target(a.get('roas_target'))})"
                )

        if not active and not off:
            lines.append("\n<i>No individual ads tracked yet. Run /itemads sync to "
                         "import manual ads, or /itemads replenish to create some.</i>")

        if not live_roas and all_ads:
            lines.append("\n<i>ℹ️ Live ROAS unavailable right now (rate-limit or new "
                         "ad with no data yet) — showing last stored values.</i>")
        await msg.reply_text("\n".join(lines), parse_mode=ParseMode.HTML)
        return

    sub = args[0]

    if sub == "replenish":
        await msg.reply_text("🧩 Creating individual ads now…")
        summary = await _run_item_replenish_and_report(context.application, force=True)
        await msg.reply_text(summary, parse_mode=ParseMode.MARKDOWN)
        return

    if sub == "sync":
        await msg.reply_text("🔄 Syncing with live manual ads…")
        try:
            stats = await ENGINE.sync_item_ads()
            await msg.reply_text(
                f"✅ Sync done — imported {stats['imported']}, updated {stats['updated']}, "
                f"closed {stats['closed']}, active {stats['active']}.",
            )
        except Exception:  # noqa: BLE001
            logger.exception("item sync failed")
            await msg.reply_text("❌ Sync failed (see logs).")
        return

    if sub in ("on", "off"):
        await asyncio.to_thread(DB.update_setting, item_ads_enabled=1 if sub == "on" else 0)
        await msg.reply_text(
            f"🧩 Individual-ads subsystem *{sub.upper()}*.", parse_mode=ParseMode.MARKDOWN
        )
        return

    if sub == "auto":
        if len(args) < 2 or args[1] not in ("on", "off"):
            await msg.reply_text("Usage: /itemads auto on | off")
            return
        await asyncio.to_thread(
            DB.update_setting, item_replenish_enabled=1 if args[1] == "on" else 0
        )
        await msg.reply_text(
            f"♻️ Auto-replenish *{args[1].upper()}*.", parse_mode=ParseMode.MARKDOWN
        )
        return

    if sub in ("adroas", "setroas"):
        # Change the ROAS target of ONE specific ad.
        #   /itemads adroas <campaign_id|item_id> <roas>
        if len(args) < 3:
            await msg.reply_text(
                "Usage: `/itemads adroas <campaign_id|item_id> <roas>`\n"
                "Example: `/itemads adroas 49910363389 5.0`\n"
                "_Find the ids with_ `/itemads`.",
                parse_mode=ParseMode.MARKDOWN,
            )
            return
        await msg.reply_text("🎯 Updating ad ROAS target…")
        try:
            res = await ENGINE.set_item_ad_roas_target(args[1], args[2])
        except Exception:  # noqa: BLE001
            logger.exception("set ad ROAS target failed")
            await msg.reply_text("❌ Failed to set ROAS target (see logs).")
            return
        if res.get("ok"):
            await msg.reply_text(
                f"✅ ROAS target for item `{res.get('item_id')}` "
                f"(ad `{res.get('campaign_id')}`) set to *{res.get('roas_target'):.1f}*.",
                parse_mode=ParseMode.MARKDOWN,
            )
        else:
            await msg.reply_text(f"❌ {res.get('error', 'Could not set ROAS target.')}")
        return

    if len(args) < 2:
        await msg.reply_text("Missing value. See /itemads (no args) for usage.")
        return
    val = args[1]
    try:
        if sub == "cap":
            await asyncio.to_thread(DB.update_setting, max_item_ads=int(val))
            await msg.reply_text(f"🔢 Max item ads set to *{int(val)}*.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "budget":
            b = max(float(val), 25000.0)
            await asyncio.to_thread(DB.update_setting, item_ad_starting_budget=b)
            note = " (raised to 25,000 minimum)" if b != float(val) else ""
            await msg.reply_text(
                f"💰 Starting budget/ad set to *{_fmt_idr(b)}*{note}.", parse_mode=ParseMode.MARKDOWN
            )
        elif sub == "roasoff":
            await asyncio.to_thread(DB.update_setting, item_roas_off_threshold=float(val))
            await msg.reply_text(f"📉 Turn-off ROAS set to *{float(val):.1f}*.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "offafter":
            await asyncio.to_thread(DB.update_setting, item_off_after_checks=int(val))
            await msg.reply_text(f"🔁 Turn off after *{int(val)}* low-ROAS checks.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "roastarget":
            await asyncio.to_thread(DB.update_setting, item_new_roas_target=float(val))
            await msg.reply_text(f"🎯 New-ad ROAS target set to *{float(val):.1f}*.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "maxperrun":
            await asyncio.to_thread(DB.update_setting, item_replenish_max_per_run=int(val))
            await msg.reply_text(f"🔢 Max new ads per run set to *{int(val)}*.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "selection":
            if val not in ("sales", "roas", "stock", "mixed"):
                await msg.reply_text("Selection must be: sales | roas | stock | mixed")
                return
            await asyncio.to_thread(DB.update_setting, item_selection=val)
            await msg.reply_text(f"🧮 Selection strategy set to *{val}*.", parse_mode=ParseMode.MARKDOWN)
        elif sub == "recroi":
            await asyncio.to_thread(DB.update_setting, item_use_recommended_roi=1 if val in ("on", "1", "true") else 0)
            await msg.reply_text(f"🎯 Use recommended ROI: *{val.upper()}*.", parse_mode=ParseMode.MARKDOWN)
        else:
            await msg.reply_text("Unknown option. See /itemads (no args) for usage.")
    except ValueError:
        await msg.reply_text("Invalid number.")


@admin_only
async def cmd_authorize(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if not (CONFIG.shopee_partner_id and CONFIG.shopee_partner_key):
        await update.effective_message.reply_text(
            "❌ SHOPEE_PARTNER_ID / SHOPEE_PARTNER_KEY not set in environment."
        )
        return
    url = SHOPEE.build_authorization_url(state="shopeebudgetbot")
    # NOTE: send the URL WITHOUT Markdown — the link contains underscores
    # (auth_partner, partner_id, shop_id) which Telegram's Markdown parser would
    # otherwise strip, breaking the link (error_not_found).
    await update.effective_message.reply_text(
        "🔑 Authorize Shopee\n\n"
        "⚠️ This link is time-limited (~5 min). TIP: log into Shopee Seller in "
        "your browser FIRST, then open the link and approve immediately. If you "
        "see \"invalid timestamp\", just run /authorize again for a fresh link.\n\n"
        "1. Open this link and approve the app for your shop:\n\n"
        f"{url}\n\n"
        "2. Shopee will redirect back with code + shop_id; the bot exchanges "
        "them for an access token automatically.\n\n"
        f"Redirect URI in use:\n{CONFIG.oauth_redirect_uri}",
        disable_web_page_preview=True,
    )


# --------------------------------------------------------------------------- #
# Callback buttons
# --------------------------------------------------------------------------- #
@admin_only
async def on_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    query = update.callback_query
    await query.answer()
    data = query.data
    if data == "status":
        await _send_status(update, context)
    elif data == "schedule":
        await _send_schedule(update, context)
    elif data == "history":
        await _send_history(update, context)
    elif data == "settings":
        await _send_settings(update, context)
    elif data == "pause":
        await asyncio.to_thread(DB.set_status, STATUS_PAUSED)
        await query.edit_message_text("⏸ Automation *paused*.", parse_mode=ParseMode.MARKDOWN)
    elif data == "resume":
        await asyncio.to_thread(DB.set_status, STATUS_RUNNING)
        await query.edit_message_text("▶️ Automation *resumed*.", parse_mode=ParseMode.MARKDOWN)


# --------------------------------------------------------------------------- #
# OAuth callback wiring
# --------------------------------------------------------------------------- #
def _build_oauth_server() -> OAuthCallbackServer:
    main_loop = asyncio.get_running_loop()

    def handle_auth_code(code: str, shop_id: int, state) -> str:
        async def _exchange():
            tok = await SHOPEE.exchange_code_for_token(code, shop_id)
            return tok

        fut = asyncio.run_coroutine_threadsafe(_exchange(), main_loop)
        tok = fut.result(timeout=30)
        access = tok.get("access_token", "")
        refresh = tok.get("refresh_token", "")
        if access:
            # Update the in-memory config so subsequent calls are authorized.
            object.__setattr__(CONFIG, "shopee_access_token", access)
            object.__setattr__(CONFIG, "shopee_refresh_token", refresh)
            object.__setattr__(CONFIG, "shopee_shop_id", int(shop_id))
            # Persist to .env so the tokens survive a restart (no manual step).
            persisted = _persist_env_values(
                {
                    "SHOPEE_SHOP_ID": str(int(shop_id)),
                    "SHOPEE_ACCESS_TOKEN": access,
                    "SHOPEE_REFRESH_TOKEN": refresh,
                }
            )
            logger.info(
                "Shopee OAuth complete for shop_id=%s (persisted_to_env=%s).",
                shop_id,
                persisted,
            )
            if persisted:
                return (
                    f"Authorization complete for shop_id {shop_id}. "
                    "Tokens have been saved to .env automatically — you're all set."
                )
            return (
                f"Access token obtained for shop_id {shop_id}, but writing to .env "
                "failed. Add SHOPEE_ACCESS_TOKEN, SHOPEE_REFRESH_TOKEN and "
                "SHOPEE_SHOP_ID to your .env manually so it survives restarts."
            )
        return "No access token returned by Shopee."

    return OAuthCallbackServer(
        host=CONFIG.oauth_callback_host,
        port=CONFIG.oauth_callback_port,
        redirect_path=CONFIG.oauth_redirect_path,
        on_auth_code=handle_auth_code,
    )


# --------------------------------------------------------------------------- #
# Lifecycle
# --------------------------------------------------------------------------- #
async def _post_init(application: Application) -> None:
    DB.init_schema(
        defaults={
            "starting_budget": CONFIG.default_starting_budget,
            "starting_budget_toko_auto": CONFIG.default_starting_budget_toko_auto,
            "starting_budget_toko_manual": CONFIG.default_starting_budget_toko_manual,
            "starting_budget_produk_auto": CONFIG.default_starting_budget_produk_auto,
            "starting_budget_group": CONFIG.default_starting_budget_group,
            "starting_budget_gmv_max": CONFIG.default_starting_budget_gmv_max,
            "daily_max_budget": CONFIG.default_daily_max_budget,
            "group_split_high": CONFIG.group_split_high,
            "group_split_mid": CONFIG.group_split_mid,
            "group_split_low": CONFIG.group_split_low,
            "group_roas_off_threshold": CONFIG.group_roas_off_threshold,
            "group_off_after_increments": CONFIG.group_off_after_increments,
            "group_replenish_enabled": CONFIG.group_replenish_enabled,
            "group_target_active_count": CONFIG.group_target_active_count,
            "group_replenish_max_per_run": CONFIG.group_replenish_max_per_run,
            "group_replenish_min_roas": CONFIG.group_replenish_min_roas,
            "group_new_roas_target": CONFIG.group_new_roas_target,
            # Individual product ads
            "item_ads_enabled": CONFIG.item_ads_enabled,
            "max_item_ads": CONFIG.max_item_ads,
            "item_ad_starting_budget": CONFIG.item_ad_starting_budget,
            "item_replenish_enabled": CONFIG.item_replenish_enabled,
            "item_replenish_max_per_run": CONFIG.item_replenish_max_per_run,
            "item_roas_off_threshold": CONFIG.item_roas_off_threshold,
            "item_off_after_checks": CONFIG.item_off_after_checks,
            "item_new_roas_target": CONFIG.item_new_roas_target,
            "item_use_recommended_roi": CONFIG.item_use_recommended_roi,
            "item_split_high": CONFIG.item_split_high,
            "item_split_mid": CONFIG.item_split_mid,
            "item_split_low": CONFIG.item_split_low,
            "item_selection": CONFIG.item_selection,
        }
    )
    _reschedule_all(application)
    _schedule_daily_reset(application)
    _schedule_replenish(application)
    _schedule_item_replenish(application)

    server = _build_oauth_server()
    server.start()
    application.bot_data["oauth_server"] = server


async def _post_shutdown(application: Application) -> None:
    server = application.bot_data.get("oauth_server")
    if server:
        server.stop()
    await SHOPEE.aclose()


def main() -> None:
    problems = CONFIG.validate()
    for p in problems:
        logger.error("CONFIG: %s", p)
    if not CONFIG.is_shopee_configured():
        logger.warning("Starting with incomplete Shopee config; use /authorize to finish OAuth.")

    application = (
        ApplicationBuilder()
        .token(CONFIG.telegram_bot_token)
        .post_init(_post_init)
        .post_shutdown(_post_shutdown)
        .build()
    )

    application.add_handler(CommandHandler(["start", "menu"], cmd_start))
    application.add_handler(CommandHandler("schedule", cmd_schedule))
    application.add_handler(CommandHandler("status", cmd_status))
    application.add_handler(CommandHandler("history", cmd_history))
    application.add_handler(CommandHandler("settings", cmd_settings))
    application.add_handler(CommandHandler("pause", cmd_pause))
    application.add_handler(CommandHandler("resume", cmd_resume))
    application.add_handler(CommandHandler("replenish", cmd_replenish))
    application.add_handler(CommandHandler("itemads", cmd_itemads))
    application.add_handler(CommandHandler("authorize", cmd_authorize))
    application.add_handler(CallbackQueryHandler(on_callback))

    logger.info("Starting Telegram polling…")
    application.run_polling(allowed_updates=Update.ALL_TYPES)


if __name__ == "__main__":
    main()
