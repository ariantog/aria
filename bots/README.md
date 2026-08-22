# Shopee Ads Budget Management — Indonesia 🇮🇩🛍

> **L12 web UI:** This bot is now integrated into Aria Core (Laravel). Manage schedules,
> settings, OAuth, and history at **`/shopee-ads`** in the app. Automation runs via
> `shopee-ads:process` every minute through `/cron-manager` (not a standalone Python
> systemd service). OAuth relay: `public/shopeebot.php` → `shopee-ads/oauth/callback`.

A production-ready system that ramps **Shopee Ads (Indonesia)** budgets on a
**time-based, fixed-IDR schedule**, anchored to **Asia/Jakarta (WIB, GMT+7)**.

It talks to the **Shopee Open Platform API v2**
(`https://partner.shopeemobile.com`), persists to a local **SQLite** database,
and is controlled entirely through an **admin-only Telegram** interface.

---

## Ad types managed

| # | Ad type | Model |
|---|---------|-------|
| 1 | **Iklan Toko Auto/Booster** | single ad — its own schedules (time + fixed IDR) |
| 2 | **Iklan Toko Manual** | single ad — its own schedules |
| 3 | **Iklan Produk Otomatis** | single ad — its own schedules |
| 4 | **Iklan Group** | many ad groups — **one shared schedule**; the increment is a **total pool split by ROAS** |

Each ad type has **independent** schedule times and increment amounts. Budgets
are always clamped to the per-ad **daily cap**.

---

## How the automation works

### Single-ad types (1–3)
At each configured clock time (WIB), the bot **adds that schedule's fixed IDR**
to the ad's budget, clamped to `daily_max_budget`. Same behaviour as the TikTok
bot's per-campaign ramp: multiple times per day, each with its own amount.

### Iklan Group (4) — ROAS-weighted split
The group schedule carries **one total increment pool** (e.g. `1,000,000` IDR).
At the scheduled time the bot:

1. Fetches all active ad groups and each group's ROAS.
2. Sorts by ROAS and splits into three tiers (high / mid / low) as evenly as
   possible.
3. Distributes the pool by weight — default **60% / 30% / 10%** — so the
   **highest-ROAS groups get the largest increment**, medium get the middle
   share, lowest get the least (each clamped per-group to the daily cap).
4. **Turns OFF** (not pauses) any group whose ROAS stays **below the threshold**
   (default `6`) for **N increments in a row** (default `2`). A turned-off group
   keeps running under **Iklan Produk Otomatis**; pausing (which would hide it
   entirely) is deliberately *not* used.

> Example: pool `1,000,000` across 6 groups → high tier `g1,g2` get `300k` each
> (60% ÷ 2), mid `g3,g4` get `150k` each (30% ÷ 2), low `g5,g6` get `50k` each
> (10% ÷ 2).

### Group replenishment (auto-create new high-performing groups)

Over time some groups get **exhausted** — their ROAS drops and the turn-off rule
disables them, so the pool of active high-performing groups shrinks. To stop that
draining your budget, **replenishment tops the pool back up** by creating brand-new
group campaigns from currently high-performing products, running **alongside** your
existing groups.

Once a day (default **02:00 WIB**, or on demand via `/replenish`) the bot:

1. Counts **active** groups. If `active >= GROUP_TARGET_ACTIVE_COUNT` → does nothing.
2. Gathers candidate products from two sources:
   - **(a)** Shopee's shop-level **recommended items** — tagged *best ROI* /
     *best selling* / *top search* (`get_recommended_item_list`);
   - **(b)** the **good SKUs recycled** from turned-off / exhausted groups
     (only those whose ROAS ≥ `GROUP_REPLENISH_MIN_ROAS`).
3. Ranks + de-duplicates them (best ROI first; recommended above recycled),
   skipping any item already used in the last few days.
4. Creates up to `min(target − active, GROUP_REPLENISH_MAX_PER_RUN)` new groups
   via `create_manual_product_ads` at the **same starting budget** as other
   groups, with the configured ROAS target (`0` = Shopee GMV-Max auto bidding).
   Each new group then behaves like any other group (ROAS-weighted increments,
   turn-off, daily reset).

**Graceful fallback:** if your Shopee app doesn't have the create-ads permission
(or the endpoint is unavailable), the bot switches to **suggest-only** — it
messages you the exact items (+ suggested budget) to add manually in Shopee Ads,
and records them so it won't spam the same suggestion daily.

### Daily reset (00:00 / 00:01 WIB)
Every day at the configured reset time (default **00:01 WIB**) every managed ad
is reset to `starting_budget`, all turned-off groups are **re-opened**, and the
per-day group counters are zeroed. Set `DAILY_RESET_HOUR=0` / `DAILY_RESET_MINUTE=0`
for exactly midnight `00:00`.

**All schedule times and the daily reset are in Asia/Jakarta (WIB, GMT+7)**,
verified to fire at Jakarta wall-clock time regardless of the server's own
timezone.

---

## Project structure

```
.
├── bot.py                       # Telegram UI + per-ad-type scheduling (WIB) + wiring
├── config.py                    # Env-based configuration (no hard-coded secrets)
├── database.py                  # SQLite (settings + schedules + group_state + history)
├── shopee_api.py                # Async Shopee Open Platform v2 client (HMAC-SHA256 signing)
├── engine.py                    # Increment engine: fixed-IDR + ROAS-weighted split + turn-off
├── oauth_server.py              # Shopee OAuth callback HTTP server (code + shop_id)
├── shopeebot.php                # HTTPS OAuth relay (forwards code+shop_id to the VPS)
├── requirements.txt
├── .env.example                 # Copy to .env and fill in
├── shopee-budget-bot.service    # systemd unit
└── README.md
```

---

## Telegram commands

| Command | Description |
|---------|-------------|
| `/start`, `/menu` | Inline dashboard. |
| `/schedule` | List all schedules per ad type. |
| `/schedule add <type> <HH:MM> <IDR>` | Add/update a schedule. e.g. `/schedule add toko_manual 09:00 50000`. For `group`, the IDR is the **total pool**. |
| `/schedule del <type> <HH:MM>` | Remove a schedule. |
| `/schedule clear [type]` | Clear all schedules (or one type's). |
| `/status` | Per-ad-type planned end-of-day (start + Σ increments) vs daily cap. |
| `/history` | Recent budget/status changes. |
| `/settings` | View settings. `/settings <key> <value>` to edit. |
| `/pause` / `/resume` | Toggle automation. |
| `/replenish` | Run group replenishment now (create/suggest new groups from top products). |
| `/replenish status` | Show replenish config + recent created/suggested groups. |
| `/replenish on` / `off` | Enable/disable auto replenishment. |
| `/replenish target <N>` | Set target active-group count. |
| `/replenish max <N>` | Set max new groups created per run. |
| `/replenish minroas <X>` | Min ROAS for recycled SKUs to qualify. |
| `/replenish roastarget <X>` | ROAS target for new groups (0 = GMV-Max auto). |
| `/authorize` | Shopee seller authorization URL (OAuth). |

**Ad type keys:** `toko_auto` (or `booster`), `toko_manual`, `produk_auto`,
`group`.

**Editable settings keys:** `starting_budget`, `daily_max_budget`,
`group_split_high`, `group_split_mid`, `group_split_low`,
`group_roas_off_threshold`, `group_off_after_increments`.

---

## Shopee Developer setup

1. Create an app on the **Shopee Open Platform** and enable the **Ads** module
   (requires special permission from Shopee).
2. Note your **Partner ID** and **Partner Key**.
3. Shopee **rejects raw-IP redirect URLs**, so register an HTTPS relay
   (`shopeebot.php` on your domain) as the app's **Redirect URL**:
   ```
   https://<your-domain>/shopeebot.php
   ```
   The relay forwards `code` + `shop_id` to the VPS callback:
   ```
   http://<VPS_PUBLIC_IP>:8090/shopee/callback
   ```
4. Authorize via `/authorize` in Telegram → approve for your shop → the bot
   exchanges the code for an access + refresh token.

### Signature (v2, implemented in `shopee_api.py`)
* Public calls: `HMAC_SHA256(partner_id + api_path + timestamp, partner_key)`
* Shop calls: `HMAC_SHA256(partner_id + api_path + timestamp + access_token + shop_id, partner_key)`

---

## Deploy on the VPS (Ubuntu)

```bash
sudo mkdir -p /root/shopee-budget-bot
cd /root/shopee-budget-bot
# copy the code here

python3 -m venv venv
./venv/bin/pip install --upgrade pip
./venv/bin/pip install -r requirements.txt

cp .env.example .env
nano .env            # TELEGRAM_BOT_TOKEN (NEW bot), ALLOWED_TELEGRAM_USER_ID,
                     # SHOPEE_PARTNER_ID / SHOPEE_PARTNER_KEY, redirect override
chmod 600 .env

sudo ufw allow 8090/tcp   # Shopee callback port (TikTok bot uses 8080)

sudo cp shopee-budget-bot.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now shopee-budget-bot
journalctl -u shopee-budget-bot -f
```

---

## Database schema (SQLite)

* **`settings`** (id=1): `status`, `starting_budget`, `daily_max_budget`,
  `group_split_high/mid/low`, `group_roas_off_threshold`,
  `group_off_after_increments`, `last_daily_reset`.
* **`schedules`**: `ad_type`, `run_time` (`HH:MM`), `increment_idr`, `enabled`
  — unique per `(ad_type, run_time)`.
* **`group_state`**: per ad group — `increments_today`, `low_roas_streak`,
  `last_roas`, `turned_off` (drives the ROAS turn-off rule; reset daily).
* **`budget_history`**: append-only audit log.

---

## Independence from the TikTok bot

| | TikTok bot | Shopee bot |
|--|-----------|-----------|
| Folder | `/root/tiktok-budget-bot` | `/root/shopee-budget-bot` |
| Telegram token | own | **separate** (new @BotFather bot) |
| Admin user id | same | **same allowed** |
| DB file | `bot_data.db` | `shopee_bot_data.db` |
| Callback port | `8080` | `8090` |
| systemd unit | `tiktok-budget-bot` | `shopee-budget-bot` |

The TikTok bot is left completely unchanged.

---

## Local test

```bash
PYTHONPATH=. ./venv/bin/python test_shopee.py
```
Covers cap clamping, ROAS-weighted split, per-ad-type schedule CRUD, single-ad
increment, and the group increment + auto-turn-off + daily-reset re-open flow.

Python **3.10+** required (uses `zoneinfo`, modern typing).
