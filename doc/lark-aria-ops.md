# Lark (Feishu) + Aria — operational alerts

Aria sends **interactive cards** to Lark when stock/Jubelio rules fire. Staff **@ mentions** on those cards are driven by **Aria staff roles**, not by Lark’s own “groups” or Lark org charts.

## Architecture (Phase 1)

| Piece | Where it lives |
|--------|----------------|
| **Alert destination (chat)** | One Lark **group chat** where you added the **Custom Bot** and copied its **webhook URL** into `.env` (`LARK_BOT_WEBHOOK_URL`). Every alert goes to that chat. |
| **Who gets @ on a card** | **Aria** → **System Settings → Lark Ops** → “Event → @ mention” checkboxes + **Users → Edit** (Open ID + notify flag). |
| **Thresholds & mention rules** | **Aria → Lark Ops** (permissions `lark-ops-settings-view` / `lark-ops-settings-edit`). |
| **Secrets & master switch** | `.env`: `LARK_ENABLED`, `LARK_DRY_RUN`, webhook URL, bot secret, verification token, encrypt key. |
| **Restock “Terapkan restock” button** | Lark **app/bot** posts back to Aria `POST /webhooks/lark/events` (separate from the group webhook). |

`LARK_OPS_CHAT_ID` in config is reserved for future use; today the **webhook** defines which group receives cards.

---

## Can I have multiple Lark groups?

**Not in Phase 1 with one bot webhook.**

- One custom bot webhook → **one group chat** receives all ops cards.
- The checkboxes on the Lark Ops page (**Penjualan Online**, **Gudang & Persediaan**, etc.) are **not** Lark groups. They are **Aria operational staff roles** used only to decide **which people to @** inside that same card.

If you later need separate channels (e.g. “only gudang chat” vs “only online chat”), that would mean **multiple webhooks / bots** and code changes to route events — not something you configure inside Lark’s group list for this screen.

**Practical setup:** create one ops group, e.g. `#Aria Ops`, add finance/warehouse/online leads there, point the bot webhook at that group.

---

## What the screenshot (“Event → @ mention”) does

For each **event** (row):

1. **Mention = None**  
   Card is posted to the ops group **without** @ anyone.

2. **Mention = Roster @**  
   When that event fires, Aria adds @ tags for every user who passes **all** of:

   - Has the **staff role** whose box you checked (same roles as staff checklists: `penjualan_online`, `gudang`, `produk`, …).
   - Has **Lark Open ID** filled on their Aria user record.
   - **Aktifkan @ mention Lark** is on for that user.
   - User is **active** in Aria (not banned).
   - User is **not** superadmin (id 1 is never @’d).

You can check **multiple roles** on one event → Aria @’s the **union** of people in any of those roles (deduplicated by Open ID).

**You do not** assign these roles inside Lark. Lark only needs the bot in the group and (for @) valid Open IDs on Aria users.

---

## What to do in Lark (one-time + per person)

### 1. Ops group + custom bot (cards)

1. In Lark/Feishu, create or pick a **group chat** for operations.
2. **Group settings → Bots → Add bot → Custom bot** (custom bot / webhook bot).
3. Copy the **Webhook URL** → server `.env` as `LARK_BOT_WEBHOOK_URL`.
4. If the bot has **Sign secret**, set `LARK_BOT_SECRET`.
5. Set `LARK_ENABLED=true`, `LARK_DRY_RUN=false` when ready to send for real.

All alert cards (oversell, low stock, mismatch, etc.) appear in **this** group.

### 2. Interactive bot (restock approve button only)

Restock cards include **Terapkan restock**, which calls Aria’s URL (not the group webhook).

1. Create a **企业自建应用** (custom app) or use an existing app with **bot** capability.
2. Enable **事件订阅** (event subscription) → request URL:  
   `https://<your-aria-host>/webhooks/lark/events`
3. Set **Verification Token** / **Encrypt Key** in `.env` (`LARK_VERIFICATION_TOKEN`, `LARK_ENCRYPT_KEY` if used).
4. Subscribe to **card button callback** / message card interactions (Feishu doc: card callback).
5. Exempt route is already registered in Aria (`bootstrap/app.php`).

Without this, link buttons still work; **Terapkan restock** will not.

### 3. Per user: Open ID (for @ mentions)

Lark does **not** expose “staff role” to Aria. You only need each person’s **Open ID** (`ou_…`) in Aria.

Common ways to obtain Open ID:

- **Developer console:** Feishu/Lark Open Platform → your app → tools to resolve user ID (tenant admin).
- **Bot interaction:** User sends a message to your app bot or taps an interactive card once; your app’s event payload includes `open_id` (check Lark server logs or a temporary debug endpoint).
- **IT export:** Some tenants export member Open IDs from admin/API once.

Then in **Aria → Users → Edit** (permission `users-lark-notify-edit`):

- **Lark Open ID** = `ou_…`
- **Aktifkan @ mention Lark** = on (off = still stored but excluded from roster @)

### 4. Nothing to click in Lark for the role checkboxes

Checking **Gudang & Persediaan** on the Lark Ops page **only updates Aria’s database**. No Lark admin screen mirrors those boxes. After saving in Aria, the next alert of that type will @ the matching Aria users who have Open IDs.

---

## What to do in Aria

### Permissions (after deploy)

Run permission generate (or assign manually):

| Permission | Purpose |
|------------|---------|
| `lark-ops-settings-view` | Open Lark Ops page |
| `lark-ops-settings-edit` | Save thresholds & event @ rules |
| `users-lark-notify-edit` | Edit Lark Open ID / notify on user form |

### Map people to roles (same as checklists)

**Users → Edit → Staff roles** (permission `users-staff-roles-edit`):

- Example: warehouse supervisor → **Gudang & Persediaan**
- Online lead → **Penjualan Online**
- Product manager → **Produk & Desain**

A person can have **multiple** staff roles; they will match any event that checks any of those roles.

### Tune Lark Ops page

**System Settings → Lark Ops**

- **Threshold stok:** cover days, re-arm buffer, Aria vs Jubelio mismatch delta.
- **Jubelio:** backlog threshold for `lark:scan-jubelio-error-backlog`, order URL template for “Buka di Jubelio” buttons.
- **Event → @ mention:** per event, toggle **Kirim Lark** (disable cards without stopping scanners), mention mode, and staff roles — e.g. keep webhook oversell as **None** (silent card), use **Roster @** for blocked oversell and stock mismatch.

### Cron

Scheduler runs `lark:scan-low-cover` (daily) and `lark:scan-jubelio-error-backlog` (hourly when error count ≥ threshold). Ensure Laravel scheduler is running on the server.

---

## Event keys (reference)

| Event key | Typical use | Default mention idea |
|-----------|-------------|----------------------|
| `jubelio.oversell.webhook` | Early warning on webhook before post | None (card only) |
| `stock.low_cover` | One channel low on cover | None |
| `stock.low_cover.both` | Aria & Jubelio both low | Roster: produk, gudang, penjualan_online |
| `stock.mismatch` | Qty delta Aria vs Jubelio | Roster: penjualan_online, gudang |
| `jubelio.oversell.blocked` | Post failed stock check | Roster: penjualan_online, gudang |
| `jubelio.error.backlog` | Many Jubelio sync errors (when scanner enabled) | Roster: penjualan_online |
| `stock.restock.pending` | Restock approval card | None |

---

## Example workflow (your screenshot)

1. You set **Selisih stok** to **Roster @** with **Penjualan Online** + **Gudang & Persediaan** checked → **Save** in Aria.
2. In Aria, user “Budi” has staff role **Gudang**, Open ID `ou_abc`, notify **on**.
3. User “Sari” has **Penjualan Online**, Open ID `ou_def`, notify **on**.
4. When a mismatch alert fires, the card appears in the **ops group** and shows @Budi @Sari (Lark renders `<at id="ou_…">` from the card JSON).
5. User “Andi” has **Gudang** but **no Open ID** → not @’d until you fill ID in Users → Edit.

---

## Environment variables (quick reference)

```env
LARK_ENABLED=true
LARK_DRY_RUN=false
LARK_BOT_WEBHOOK_URL=https://open.feishu.cn/open-apis/bot/v2/hook/...
LARK_BOT_SECRET=...
LARK_VERIFICATION_TOKEN=...
LARK_ENCRYPT_KEY=...
LARK_JUBELIO_ORDER_URL_TEMPLATE=https://.../{invoice}
LARK_SYSTEM_USER_ID=1
```

Card link buttons append `lk_jump_to_browser=true` and `lk_mobile_jump_to_browser=true` so Aria/Jubelio links prefer the system browser when Lark allows it.

---

## Troubleshooting

| Symptom | Check |
|---------|--------|
| No cards at all | `LARK_ENABLED`, webhook URL, bot still in group, `LARK_DRY_RUN`, `notification_log` / Laravel log |
| Card but no @ | Event set to **Roster @**? Role checkboxes match user’s **staff roles**? Open ID set? Notify enabled? |
| @ wrong people | Staff roles on user record; trim notify-off users |
| Restock button does nothing | App event URL, verification token, app published, user mappable via Open ID |
| Too many alerts | Lark Ops thresholds; re-arm buffer; dedup via `operational_alerts` fingerprints |

---

## Related code

- Config defaults: `config/lark.php`
- DB overrides: `App\Services\Lark\LarkSettingsService` (`settings.slug = lark_ops_settings`)
- UI: `resources/views/lark-settings/index.blade.php`
- Roster: `App\Services\Lark\LarkRosterService`
- Staff role catalog: `App\Support\StaffChecklistCatalog`
