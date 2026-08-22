"""
database.py
===========

SQLite persistence for the **Shopee Ads Budget Management Bot**.

Tables
------
* `settings`          - single row (id=1): Running/Paused status, starting_budget,
                        daily_max_budget, group ROAS split & turn-off thresholds,
                        last_daily_reset.
* `schedules`         - time-based increment schedules. Each row is scoped to an
                        ad_type. For the three single-ad types each schedule adds
                        a fixed IDR to THAT ad. For `iklan_group` the schedule's
                        increment is the TOTAL pool split across ad groups by ROAS.
* `group_state`       - per ad-group runtime state used by the ROAS turn-off rule
                        (how many increments a group has received today and how
                        many consecutive times it stayed under the ROAS threshold).
* `budget_history`    - append-only audit log of every budget/status change.

Synchronous sqlite3 (fast, local) called from async code via
`asyncio.to_thread(...)`. WAL mode + per-op short-lived connections keep it
thread-safe.
"""

from __future__ import annotations

import logging
import sqlite3
from contextlib import contextmanager
from datetime import datetime
from typing import Any, Dict, Iterator, List, Optional

from config import ALL_AD_TYPES

logger = logging.getLogger(__name__)

STATUS_RUNNING = "Running"
STATUS_PAUSED = "Paused"


class Database:
    """SQLite wrapper with the schema this bot needs."""

    def __init__(self, db_path: str) -> None:
        self.db_path = db_path

    @contextmanager
    def _connect(self) -> Iterator[sqlite3.Connection]:
        conn = sqlite3.connect(self.db_path, timeout=30, isolation_level=None)
        try:
            conn.row_factory = sqlite3.Row
            conn.execute("PRAGMA journal_mode=WAL;")
            conn.execute("PRAGMA foreign_keys=ON;")
            yield conn
        finally:
            conn.close()

    # ------------------------------------------------------------------ #
    # Schema + seeding
    # ------------------------------------------------------------------ #
    @staticmethod
    def _ensure_columns(conn, table: str, columns: Dict[str, str]) -> None:
        """
        Idempotently ALTER TABLE ADD COLUMN any of `columns` (name -> SQL def)
        that don't yet exist. Lets an already-created live DB pick up new
        settings columns without a destructive rebuild.
        """
        existing = {
            r[1] for r in conn.execute(f"PRAGMA table_info({table});").fetchall()
        }
        for name, ddl in columns.items():
            if name not in existing:
                conn.execute(f"ALTER TABLE {table} ADD COLUMN {name} {ddl};")

    @staticmethod
    def _seed_per_type_starting_budgets(conn, defaults: Dict[str, Any]) -> None:
        """
        For an EXISTING settings row that just gained the per-type starting-budget
        columns via migration, copy the legacy global ``starting_budget`` into any
        per-type column still holding the bare 100000 ALTER-default, so upgraded
        installs don't silently reset. Provided defaults win over the legacy value.
        New installs (no row yet) are handled by the INSERT and are untouched here.
        """
        row = conn.execute("SELECT * FROM settings WHERE id = 1;").fetchone()
        if row is None:
            return
        row = dict(row)
        legacy = float(row.get("starting_budget", 100000.0) or 100000.0)
        mapping = {
            "starting_budget_toko_auto": "default_starting_budget_toko_auto",
            "starting_budget_toko_manual": "default_starting_budget_toko_manual",
            "starting_budget_produk_auto": "default_starting_budget_produk_auto",
            "starting_budget_group": "default_starting_budget_group",
            "starting_budget_gmv_max": "default_starting_budget_gmv_max",
        }
        for col, _dkey in mapping.items():
            current = row.get(col)
            # Only seed when the column is still at the raw ALTER default (100000)
            # AND the legacy global budget differs — that's the tell-tale of a
            # freshly-migrated column that was never explicitly set by the user.
            if current is not None and abs(float(current) - 100000.0) < 0.001 and abs(legacy - 100000.0) >= 0.001:
                conn.execute(
                    f"UPDATE settings SET {col} = ? WHERE id = 1;", (legacy,)
                )

    def init_schema(self, defaults: Optional[Dict[str, Any]] = None) -> None:
        defaults = defaults or {}
        with self._connect() as conn:
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS settings (
                    id                       INTEGER PRIMARY KEY CHECK (id = 1),
                    status                   TEXT NOT NULL DEFAULT 'Running',
                    starting_budget          REAL NOT NULL DEFAULT 100000,
                    starting_budget_toko_auto   REAL NOT NULL DEFAULT 100000,
                    starting_budget_toko_manual REAL NOT NULL DEFAULT 100000,
                    starting_budget_produk_auto REAL NOT NULL DEFAULT 100000,
                    starting_budget_group       REAL NOT NULL DEFAULT 100000,
                    starting_budget_gmv_max     REAL NOT NULL DEFAULT 100000,
                    daily_max_budget         REAL NOT NULL DEFAULT 1000000,
                    -- GMV-Max campaign the bot manages + the daily budget it
                    -- currently owns (there is no GMS "get budget" endpoint, so
                    -- the bot tracks the value it last set here).
                    gms_campaign_id          TEXT,
                    gms_current_budget       REAL NOT NULL DEFAULT 0,
                    group_split_high         REAL NOT NULL DEFAULT 0.60,
                    group_split_mid          REAL NOT NULL DEFAULT 0.30,
                    group_split_low          REAL NOT NULL DEFAULT 0.10,
                    group_roas_off_threshold REAL NOT NULL DEFAULT 6.0,
                    group_off_after_increments INTEGER NOT NULL DEFAULT 2,
                    group_replenish_enabled  INTEGER NOT NULL DEFAULT 1,
                    group_target_active_count INTEGER NOT NULL DEFAULT 5,
                    group_replenish_max_per_run INTEGER NOT NULL DEFAULT 3,
                    group_replenish_min_roas REAL NOT NULL DEFAULT 6.0,
                    group_new_roas_target    REAL NOT NULL DEFAULT 0.0,
                    -- Individual Product Ads (each ad = 1 item)
                    item_ads_enabled         INTEGER NOT NULL DEFAULT 1,
                    max_item_ads             INTEGER NOT NULL DEFAULT 10,
                    item_ad_starting_budget  REAL NOT NULL DEFAULT 25000,
                    item_replenish_enabled   INTEGER NOT NULL DEFAULT 1,
                    item_replenish_max_per_run INTEGER NOT NULL DEFAULT 5,
                    item_roas_off_threshold  REAL NOT NULL DEFAULT 6.0,
                    item_off_after_checks    INTEGER NOT NULL DEFAULT 2,
                    item_new_roas_target     REAL NOT NULL DEFAULT 0.0,
                    item_use_recommended_roi INTEGER NOT NULL DEFAULT 0,
                    item_split_high          REAL NOT NULL DEFAULT 0.60,
                    item_split_mid           REAL NOT NULL DEFAULT 0.30,
                    item_split_low           REAL NOT NULL DEFAULT 0.10,
                    item_selection           TEXT NOT NULL DEFAULT 'sales',
                    last_daily_reset         TEXT,
                    updated_at               TEXT NOT NULL DEFAULT (datetime('now'))
                );
                """
            )
            # Migrate older DBs that predate the replenishment columns.
            self._ensure_columns(
                conn,
                "settings",
                {
                    "group_replenish_enabled": "INTEGER NOT NULL DEFAULT 1",
                    "group_target_active_count": "INTEGER NOT NULL DEFAULT 5",
                    "group_replenish_max_per_run": "INTEGER NOT NULL DEFAULT 3",
                    "group_replenish_min_roas": "REAL NOT NULL DEFAULT 6.0",
                    "group_new_roas_target": "REAL NOT NULL DEFAULT 0.0",
                    # Per-ad-type starting budgets (added later; migrate old DBs).
                    "starting_budget_toko_auto": "REAL NOT NULL DEFAULT 100000",
                    "starting_budget_toko_manual": "REAL NOT NULL DEFAULT 100000",
                    "starting_budget_produk_auto": "REAL NOT NULL DEFAULT 100000",
                    "starting_budget_group": "REAL NOT NULL DEFAULT 100000",
                    # GMV-Max: starting budget + campaign/budget tracking.
                    "starting_budget_gmv_max": "REAL NOT NULL DEFAULT 100000",
                    "gms_campaign_id": "TEXT",
                    "gms_current_budget": "REAL NOT NULL DEFAULT 0",
                    # Individual Product Ads settings (added later; migrate old DBs).
                    "item_ads_enabled": "INTEGER NOT NULL DEFAULT 1",
                    "max_item_ads": "INTEGER NOT NULL DEFAULT 10",
                    "item_ad_starting_budget": "REAL NOT NULL DEFAULT 25000",
                    "item_replenish_enabled": "INTEGER NOT NULL DEFAULT 1",
                    "item_replenish_max_per_run": "INTEGER NOT NULL DEFAULT 5",
                    "item_roas_off_threshold": "REAL NOT NULL DEFAULT 6.0",
                    "item_off_after_checks": "INTEGER NOT NULL DEFAULT 2",
                    "item_new_roas_target": "REAL NOT NULL DEFAULT 0.0",
                    "item_use_recommended_roi": "INTEGER NOT NULL DEFAULT 0",
                    "item_split_high": "REAL NOT NULL DEFAULT 0.60",
                    "item_split_mid": "REAL NOT NULL DEFAULT 0.30",
                    "item_split_low": "REAL NOT NULL DEFAULT 0.10",
                    "item_selection": "TEXT NOT NULL DEFAULT 'sales'",
                },
            )
            # Seed the new per-type columns from the legacy global starting_budget
            # (and from provided defaults) the first time they appear, so existing
            # installs keep behaving sensibly instead of snapping to 100000.
            self._seed_per_type_starting_budgets(conn, defaults)

            # Per-ad-type, time-based increment schedules.
            #  - For single ad types: increment_idr is added to that one ad.
            #  - For iklan_group: increment_idr is the TOTAL pool for all groups.
            # Unique per (ad_type, run_time) so the same time can exist across
            # different ad types but not twice within one ad type.
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS schedules (
                    id             INTEGER PRIMARY KEY AUTOINCREMENT,
                    ad_type        TEXT    NOT NULL,
                    run_time       TEXT    NOT NULL,          -- 'HH:MM' 24h WIB
                    increment_idr  REAL    NOT NULL,
                    enabled        INTEGER NOT NULL DEFAULT 1,
                    created_at     TEXT    NOT NULL DEFAULT (datetime('now')),
                    UNIQUE (ad_type, run_time)
                );
                """
            )

            # Per ad-group runtime state (reset daily). Tracks how many
            # increments a group received today and its consecutive
            # under-threshold ROAS count, so we can TURN OFF persistent
            # low-ROAS groups after N increments.
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS group_state (
                    campaign_id        TEXT PRIMARY KEY,
                    campaign_name      TEXT,
                    increments_today   INTEGER NOT NULL DEFAULT 0,
                    low_roas_streak    INTEGER NOT NULL DEFAULT 0,
                    last_roas          REAL,
                    turned_off         INTEGER NOT NULL DEFAULT 0,
                    updated_at         TEXT NOT NULL DEFAULT (datetime('now'))
                );
                """
            )

            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS budget_history (
                    id             INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp      TEXT NOT NULL,
                    ad_type        TEXT NOT NULL,
                    campaign_id    TEXT NOT NULL,
                    campaign_name  TEXT NOT NULL,
                    budget_before  REAL NOT NULL,
                    budget_after   REAL NOT NULL,
                    roas           REAL,
                    action_taken   TEXT NOT NULL
                );
                """
            )
            conn.execute(
                "CREATE INDEX IF NOT EXISTS idx_hist_ts ON budget_history(timestamp DESC);"
            )
            # Groups the bot has CREATED via replenishment (or suggested when the
            # create API is unavailable). Lets us audit and avoid re-suggesting
            # the same item repeatedly.
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS created_groups (
                    id             INTEGER PRIMARY KEY AUTOINCREMENT,
                    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
                    item_id        INTEGER NOT NULL,
                    campaign_id    TEXT,                     -- NULL when suggest-only
                    budget         REAL NOT NULL,
                    roas_target    REAL NOT NULL DEFAULT 0,
                    source         TEXT NOT NULL,            -- 'recommended' | 'recycled'
                    reason         TEXT,                     -- tags / originating group
                    mode           TEXT NOT NULL             -- 'created' | 'suggested'
                );
                """
            )
            conn.execute(
                "CREATE INDEX IF NOT EXISTS idx_cg_created ON created_groups(created_at DESC);"
            )
            conn.execute(
                "CREATE INDEX IF NOT EXISTS idx_cg_item ON created_groups(item_id);"
            )

            # Individual Product Ads the bot manages. One row per manual product
            # ad campaign (each backs exactly ONE item). `origin` records whether
            # the bot created it ('bot') or it was created manually in Seller
            # Centre and imported ('manual') -- imported ads are treated exactly
            # like bot-created ones (budget tiering + ROAS turn-off apply).
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS item_ads (
                    campaign_id        TEXT PRIMARY KEY,
                    item_id            INTEGER NOT NULL,
                    origin             TEXT NOT NULL DEFAULT 'bot',   -- 'bot' | 'manual'
                    budget             REAL NOT NULL DEFAULT 0,
                    roas_target        REAL NOT NULL DEFAULT 0,
                    status             TEXT NOT NULL DEFAULT 'ongoing',
                    increments_today   INTEGER NOT NULL DEFAULT 0,
                    low_roas_streak    INTEGER NOT NULL DEFAULT 0,
                    last_roas          REAL,
                    turned_off         INTEGER NOT NULL DEFAULT 0,
                    created_at         TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at         TEXT NOT NULL DEFAULT (datetime('now'))
                );
                """
            )
            conn.execute(
                "CREATE INDEX IF NOT EXISTS idx_ia_item ON item_ads(item_id);"
            )
            conn.execute(
                "CREATE INDEX IF NOT EXISTS idx_ia_status ON item_ads(turned_off, status);"
            )

            existing = conn.execute("SELECT id FROM settings WHERE id = 1;").fetchone()
            if existing is None:
                conn.execute(
                    """
                    INSERT INTO settings (
                        id, status, starting_budget,
                        starting_budget_toko_auto, starting_budget_toko_manual,
                        starting_budget_produk_auto, starting_budget_group,
                        starting_budget_gmv_max,
                        daily_max_budget,
                        group_split_high, group_split_mid, group_split_low,
                        group_roas_off_threshold, group_off_after_increments,
                        group_replenish_enabled, group_target_active_count,
                        group_replenish_max_per_run, group_replenish_min_roas,
                        group_new_roas_target,
                        item_ads_enabled, max_item_ads, item_ad_starting_budget,
                        item_replenish_enabled, item_replenish_max_per_run,
                        item_roas_off_threshold, item_off_after_checks,
                        item_new_roas_target, item_use_recommended_roi,
                        item_split_high, item_split_mid, item_split_low,
                        item_selection,
                        last_daily_reset, updated_at
                    ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                              ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, datetime('now'));
                    """,
                    (
                        STATUS_RUNNING,
                        float(defaults.get("starting_budget", 100000.0)),
                        float(defaults.get("starting_budget_toko_auto",
                                           defaults.get("starting_budget", 100000.0))),
                        float(defaults.get("starting_budget_toko_manual",
                                           defaults.get("starting_budget", 100000.0))),
                        float(defaults.get("starting_budget_produk_auto",
                                           defaults.get("starting_budget", 100000.0))),
                        float(defaults.get("starting_budget_group",
                                           defaults.get("starting_budget", 100000.0))),
                        float(defaults.get("starting_budget_gmv_max",
                                           defaults.get("starting_budget", 100000.0))),
                        float(defaults.get("daily_max_budget", 1000000.0)),
                        float(defaults.get("group_split_high", 0.60)),
                        float(defaults.get("group_split_mid", 0.30)),
                        float(defaults.get("group_split_low", 0.10)),
                        float(defaults.get("group_roas_off_threshold", 6.0)),
                        int(defaults.get("group_off_after_increments", 2)),
                        int(bool(defaults.get("group_replenish_enabled", True))),
                        int(defaults.get("group_target_active_count", 5)),
                        int(defaults.get("group_replenish_max_per_run", 3)),
                        float(defaults.get("group_replenish_min_roas", 6.0)),
                        float(defaults.get("group_new_roas_target", 0.0)),
                        int(bool(defaults.get("item_ads_enabled", True))),
                        int(defaults.get("max_item_ads", 10)),
                        float(defaults.get("item_ad_starting_budget", 25000.0)),
                        int(bool(defaults.get("item_replenish_enabled", True))),
                        int(defaults.get("item_replenish_max_per_run", 5)),
                        float(defaults.get("item_roas_off_threshold", 6.0)),
                        int(defaults.get("item_off_after_checks", 2)),
                        float(defaults.get("item_new_roas_target", 0.0)),
                        int(bool(defaults.get("item_use_recommended_roi", False))),
                        float(defaults.get("item_split_high", 0.60)),
                        float(defaults.get("item_split_mid", 0.30)),
                        float(defaults.get("item_split_low", 0.10)),
                        str(defaults.get("item_selection", "sales")),
                    ),
                )
        logger.info("Database schema ready at %s", self.db_path)

    # ------------------------------------------------------------------ #
    # Settings
    # ------------------------------------------------------------------ #
    def get_settings(self) -> Dict[str, Any]:
        with self._connect() as conn:
            row = conn.execute("SELECT * FROM settings WHERE id = 1;").fetchone()
            if row is None:
                self.init_schema()
                row = conn.execute("SELECT * FROM settings WHERE id = 1;").fetchone()
            return dict(row)

    def update_setting(self, **fields: Any) -> Dict[str, Any]:
        allowed = {
            "status",
            "starting_budget",
            "starting_budget_toko_auto",
            "starting_budget_toko_manual",
            "starting_budget_produk_auto",
            "starting_budget_group",
            "starting_budget_gmv_max",
            "gms_campaign_id",
            "gms_current_budget",
            "daily_max_budget",
            "group_split_high",
            "group_split_mid",
            "group_split_low",
            "group_roas_off_threshold",
            "group_off_after_increments",
            "group_replenish_enabled",
            "group_target_active_count",
            "group_replenish_max_per_run",
            "group_replenish_min_roas",
            "group_new_roas_target",
            "item_ads_enabled",
            "max_item_ads",
            "item_ad_starting_budget",
            "item_replenish_enabled",
            "item_replenish_max_per_run",
            "item_roas_off_threshold",
            "item_off_after_checks",
            "item_new_roas_target",
            "item_use_recommended_roi",
            "item_split_high",
            "item_split_mid",
            "item_split_low",
            "item_selection",
            "last_daily_reset",
        }
        updates = {k: v for k, v in fields.items() if k in allowed}
        if not updates:
            return self.get_settings()
        set_clause = ", ".join(f"{k} = ?" for k in updates)
        values = list(updates.values())
        with self._connect() as conn:
            conn.execute(
                f"UPDATE settings SET {set_clause}, updated_at = datetime('now') WHERE id = 1;",
                values,
            )
        return self.get_settings()

    def set_status(self, status: str) -> Dict[str, Any]:
        if status not in (STATUS_RUNNING, STATUS_PAUSED):
            raise ValueError("status must be 'Running' or 'Paused'")
        return self.update_setting(status=status)

    # ------------------------------------------------------------------ #
    # Schedules (per ad type)
    # ------------------------------------------------------------------ #
    @staticmethod
    def _normalize_time(run_time: str) -> str:
        raw = (run_time or "").strip()
        if ":" not in raw:
            raise ValueError("Time must be in HH:MM 24-hour format, e.g. 09:00.")
        hh_s, mm_s = raw.split(":", 1)
        try:
            hh, mm = int(hh_s), int(mm_s)
        except ValueError as exc:
            raise ValueError("Time must be numeric HH:MM, e.g. 09:00.") from exc
        if not (0 <= hh <= 23 and 0 <= mm <= 59):
            raise ValueError("Hour must be 0-23 and minute 0-59.")
        return f"{hh:02d}:{mm:02d}"

    @staticmethod
    def _validate_ad_type(ad_type: str) -> str:
        if ad_type not in ALL_AD_TYPES:
            raise ValueError(f"Unknown ad type: {ad_type}")
        return ad_type

    def add_schedule(self, ad_type: str, run_time: str, increment_idr: float) -> Dict[str, Any]:
        ad_type = self._validate_ad_type(ad_type)
        rt = self._normalize_time(run_time)
        inc = float(increment_idr)
        if inc <= 0:
            raise ValueError("Increment must be a positive IDR amount.")
        with self._connect() as conn:
            conn.execute(
                """
                INSERT INTO schedules (ad_type, run_time, increment_idr, enabled)
                VALUES (?, ?, ?, 1)
                ON CONFLICT(ad_type, run_time)
                DO UPDATE SET increment_idr = excluded.increment_idr, enabled = 1;
                """,
                (ad_type, rt, inc),
            )
            row = conn.execute(
                "SELECT * FROM schedules WHERE ad_type = ? AND run_time = ?;",
                (ad_type, rt),
            ).fetchone()
            return dict(row)

    def delete_schedule(self, ad_type: str, run_time: str) -> bool:
        ad_type = self._validate_ad_type(ad_type)
        rt = self._normalize_time(run_time)
        with self._connect() as conn:
            cur = conn.execute(
                "DELETE FROM schedules WHERE ad_type = ? AND run_time = ?;", (ad_type, rt)
            )
            return cur.rowcount > 0

    def clear_schedules(self, ad_type: Optional[str] = None) -> int:
        with self._connect() as conn:
            if ad_type:
                ad_type = self._validate_ad_type(ad_type)
                cur = conn.execute("DELETE FROM schedules WHERE ad_type = ?;", (ad_type,))
            else:
                cur = conn.execute("DELETE FROM schedules;")
            return cur.rowcount

    def get_schedules(
        self, ad_type: Optional[str] = None, only_enabled: bool = False
    ) -> List[Dict[str, Any]]:
        q = "SELECT * FROM schedules"
        conds = []
        args: List[Any] = []
        if ad_type:
            conds.append("ad_type = ?")
            args.append(self._validate_ad_type(ad_type))
        if only_enabled:
            conds.append("enabled = 1")
        if conds:
            q += " WHERE " + " AND ".join(conds)
        q += " ORDER BY ad_type, run_time;"
        with self._connect() as conn:
            return [dict(r) for r in conn.execute(q, args).fetchall()]

    def total_scheduled_increment(self, ad_type: Optional[str] = None) -> float:
        q = "SELECT COALESCE(SUM(increment_idr), 0) AS total FROM schedules WHERE enabled = 1"
        args: List[Any] = []
        if ad_type:
            q += " AND ad_type = ?"
            args.append(self._validate_ad_type(ad_type))
        with self._connect() as conn:
            return float(conn.execute(q, args).fetchone()["total"])

    # ------------------------------------------------------------------ #
    # Group runtime state (for ROAS-based turn-off)
    # ------------------------------------------------------------------ #
    def get_group_state(self, campaign_id: str) -> Dict[str, Any]:
        with self._connect() as conn:
            row = conn.execute(
                "SELECT * FROM group_state WHERE campaign_id = ?;", (str(campaign_id),)
            ).fetchone()
            if row is None:
                return {
                    "campaign_id": str(campaign_id),
                    "campaign_name": None,
                    "increments_today": 0,
                    "low_roas_streak": 0,
                    "last_roas": None,
                    "turned_off": 0,
                }
            return dict(row)

    def upsert_group_state(
        self,
        campaign_id: str,
        *,
        campaign_name: Optional[str] = None,
        increments_today: Optional[int] = None,
        low_roas_streak: Optional[int] = None,
        last_roas: Optional[float] = None,
        turned_off: Optional[int] = None,
    ) -> None:
        cur = self.get_group_state(campaign_id)
        name = campaign_name if campaign_name is not None else cur.get("campaign_name")
        inc = increments_today if increments_today is not None else cur.get("increments_today", 0)
        streak = low_roas_streak if low_roas_streak is not None else cur.get("low_roas_streak", 0)
        roas = last_roas if last_roas is not None else cur.get("last_roas")
        off = turned_off if turned_off is not None else cur.get("turned_off", 0)
        with self._connect() as conn:
            conn.execute(
                """
                INSERT INTO group_state (campaign_id, campaign_name, increments_today,
                                         low_roas_streak, last_roas, turned_off, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
                ON CONFLICT(campaign_id) DO UPDATE SET
                    campaign_name = excluded.campaign_name,
                    increments_today = excluded.increments_today,
                    low_roas_streak = excluded.low_roas_streak,
                    last_roas = excluded.last_roas,
                    turned_off = excluded.turned_off,
                    updated_at = datetime('now');
                """,
                (str(campaign_id), name, int(inc), int(streak), roas, int(off)),
            )

    def reset_group_states(self) -> None:
        """Zero out per-day counters (called by the daily reset)."""
        with self._connect() as conn:
            conn.execute(
                "UPDATE group_state SET increments_today = 0, low_roas_streak = 0, "
                "turned_off = 0, updated_at = datetime('now');"
            )

    # ------------------------------------------------------------------ #
    # History
    # ------------------------------------------------------------------ #
    def log_budget_change(
        self,
        *,
        ad_type: str,
        campaign_id: str,
        campaign_name: str,
        budget_before: float,
        budget_after: float,
        action_taken: str,
        roas: Optional[float] = None,
    ) -> None:
        with self._connect() as conn:
            conn.execute(
                """
                INSERT INTO budget_history (timestamp, ad_type, campaign_id, campaign_name,
                                            budget_before, budget_after, roas, action_taken)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?);
                """,
                (
                    datetime.now().isoformat(timespec="seconds"),
                    ad_type,
                    str(campaign_id),
                    campaign_name,
                    float(budget_before),
                    float(budget_after),
                    roas,
                    action_taken,
                ),
            )

    def get_recent_history(self, limit: int = 15) -> List[Dict[str, Any]]:
        with self._connect() as conn:
            rows = conn.execute(
                "SELECT * FROM budget_history ORDER BY id DESC LIMIT ?;", (int(limit),)
            ).fetchall()
            return [dict(r) for r in rows]

    # ------------------------------------------------------------------ #
    # Created / suggested groups (replenishment audit trail)
    # ------------------------------------------------------------------ #
    def record_created_group(
        self,
        *,
        item_id: int,
        budget: float,
        source: str,
        mode: str,
        campaign_id: Optional[str] = None,
        roas_target: float = 0.0,
        reason: Optional[str] = None,
    ) -> int:
        """
        Record that a new group was created ('created') or recommended
        ('suggested', when the create API is unavailable). Returns row id.
        """
        with self._connect() as conn:
            cur = conn.execute(
                """
                INSERT INTO created_groups
                    (item_id, campaign_id, budget, roas_target, source, reason, mode)
                VALUES (?, ?, ?, ?, ?, ?, ?);
                """,
                (
                    int(item_id),
                    (str(campaign_id) if campaign_id is not None else None),
                    float(budget),
                    float(roas_target),
                    str(source),
                    (str(reason) if reason is not None else None),
                    str(mode),
                ),
            )
            return int(cur.lastrowid)

    def recently_used_item_ids(self, within_days: int = 3) -> set:
        """
        Item ids created OR suggested within the last `within_days` days, so
        replenishment doesn't keep proposing the same SKU every run.
        """
        with self._connect() as conn:
            rows = conn.execute(
                "SELECT DISTINCT item_id FROM created_groups "
                "WHERE created_at >= datetime('now', ?);",
                (f"-{int(within_days)} days",),
            ).fetchall()
            return {int(r[0]) for r in rows}

    def get_created_groups(self, limit: int = 15) -> List[Dict[str, Any]]:
        with self._connect() as conn:
            rows = conn.execute(
                "SELECT * FROM created_groups ORDER BY id DESC LIMIT ?;",
                (int(limit),),
            ).fetchall()
            return [dict(r) for r in rows]

    # ------------------------------------------------------------------ #
    # Individual Product Ads (item_ads table)
    # ------------------------------------------------------------------ #
    def get_item_ad(self, campaign_id: str) -> Optional[Dict[str, Any]]:
        with self._connect() as conn:
            row = conn.execute(
                "SELECT * FROM item_ads WHERE campaign_id = ?;", (str(campaign_id),)
            ).fetchone()
            return dict(row) if row else None

    def get_item_ads(
        self, *, active_only: bool = False, include_turned_off: bool = True
    ) -> List[Dict[str, Any]]:
        """
        Return tracked individual product ads.

        active_only=True  -> only rows the bot still counts toward the cap
                             (not turned_off, status ongoing/scheduled).
        include_turned_off=False -> exclude rows we turned off.
        """
        with self._connect() as conn:
            rows = conn.execute(
                "SELECT * FROM item_ads ORDER BY last_roas DESC;"
            ).fetchall()
        out = [dict(r) for r in rows]
        if active_only:
            out = [
                r for r in out
                if not int(r.get("turned_off", 0))
                and str(r.get("status", "")).lower()
                in ("", "ongoing", "running", "active", "scheduled")
            ]
        elif not include_turned_off:
            out = [r for r in out if not int(r.get("turned_off", 0))]
        return out

    def count_active_item_ads(self) -> int:
        """Number of individual ads currently counting toward the cap."""
        return len(self.get_item_ads(active_only=True))

    def upsert_item_ad(
        self,
        campaign_id: str,
        *,
        item_id: Optional[int] = None,
        origin: Optional[str] = None,
        budget: Optional[float] = None,
        roas_target: Optional[float] = None,
        status: Optional[str] = None,
        increments_today: Optional[int] = None,
        low_roas_streak: Optional[int] = None,
        last_roas: Optional[float] = None,
        turned_off: Optional[int] = None,
    ) -> None:
        cur = self.get_item_ad(campaign_id) or {}
        iid = item_id if item_id is not None else cur.get("item_id", 0)
        org = origin if origin is not None else cur.get("origin", "bot")
        bud = budget if budget is not None else cur.get("budget", 0.0)
        rt = roas_target if roas_target is not None else cur.get("roas_target", 0.0)
        st = status if status is not None else cur.get("status", "ongoing")
        inc = increments_today if increments_today is not None else cur.get("increments_today", 0)
        streak = low_roas_streak if low_roas_streak is not None else cur.get("low_roas_streak", 0)
        roas = last_roas if last_roas is not None else cur.get("last_roas")
        off = turned_off if turned_off is not None else cur.get("turned_off", 0)
        with self._connect() as conn:
            conn.execute(
                """
                INSERT INTO item_ads (campaign_id, item_id, origin, budget,
                                      roas_target, status, increments_today,
                                      low_roas_streak, last_roas, turned_off,
                                      created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                ON CONFLICT(campaign_id) DO UPDATE SET
                    item_id = excluded.item_id,
                    origin = excluded.origin,
                    budget = excluded.budget,
                    roas_target = excluded.roas_target,
                    status = excluded.status,
                    increments_today = excluded.increments_today,
                    low_roas_streak = excluded.low_roas_streak,
                    last_roas = excluded.last_roas,
                    turned_off = excluded.turned_off,
                    updated_at = datetime('now');
                """,
                (str(campaign_id), int(iid or 0), str(org), float(bud or 0.0),
                 float(rt or 0.0), str(st), int(inc or 0), int(streak or 0),
                 roas, int(off or 0)),
            )

    def reset_item_ad_counters(self) -> None:
        """Zero per-day counters + re-open turned-off item ads (daily reset)."""
        with self._connect() as conn:
            conn.execute(
                "UPDATE item_ads SET increments_today = 0, low_roas_streak = 0, "
                "turned_off = 0, updated_at = datetime('now');"
            )

    def item_ad_item_ids(self) -> set:
        """
        Item_ids backing a *currently active* individual ad.

        Only ACTIVE ads (not turned_off, status ongoing/running/active/
        scheduled) exclude their item from replenishment. Items whose ad was
        closed/ended become eligible for re-picking again (subject to the
        separate recency cooldown), which is what daily rotation needs.
        """
        with self._connect() as conn:
            rows = conn.execute(
                "SELECT DISTINCT item_id FROM item_ads "
                "WHERE COALESCE(turned_off, 0) = 0 "
                "AND LOWER(COALESCE(status, '')) "
                "IN ('', 'ongoing', 'running', 'active', 'scheduled');"
            ).fetchall()
            return {int(r[0]) for r in rows if r[0] is not None}
