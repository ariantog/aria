# Lark Phase 2 — cloud agent kickoff prompt

Copy everything in the **Prompt** section into a new Cursor cloud chat (branch off `main` after Phase 1 + follow-up PRs are merged). Adjust acceptance criteria if production feedback changed.

---

## Prompt

```
Goal: Lark Phase 2 — finish operational alerting, staff-facing config, and tie alerts to staff checklists/KPIs (Laravel source of truth, Lark delivery only; no Grok).

Context (already shipped on main — do not rebuild):
- Lark ops: custom bot webhook → ops group; interactive restock callback → POST /webhooks/lark/events
- Events: jubelio.oversell.webhook, jubelio.oversell.blocked, stock.low_cover / stock.low_cover.both, stock.mismatch, stock.restock.pending; optional jubelio.error.backlog (config only until scanner exists)
- operational_alerts + notification_log dedupe; Lark Ops settings UI (thresholds, @ mention roster, low-cover item types — default asset lancar only; service excluded)
- Oversell webhook fixes: stable fingerprint, skip SHIPPED/COMPLETED/RETURNED proactive compare, skip when sell already in Aria
- Docs: doc/lark-aria-ops.md

Package choices (keep unless user says otherwise):
- A3: one ops group (single webhook URL)
- B0: silent cards by default; roster @ on critical events via config
- D1: @ via Aria staff roles + users.lark_open_id + lark_notify_enabled

Phase 2 scope (pick logical PR clusters; one chat per cluster):

1) Jubelio error backlog scanner
   - Implement cron/command (e.g. lark:scan-jubelio-error-backlog) using threshold from Lark Ops settings
   - Count jubelioorders in error state (align with /jubelio index “Error SKU” / attention error types)
   - Dispatch jubelio.error.backlog when count >= threshold; dedupe fingerprint per day or rolling window
   - Pest coverage; no per-row Jubelio API on index

2) Per-event enable/disable in Lark Ops (user asked to customize what Lark sends)
   - Extend lark_ops_settings: toggles per event_key (send card yes/no), keep mention/threshold sections
   - OperationalAlertDispatcher / scanners respect toggles before send
   - Default: match current production behavior (low cover asset lancar only; oversell as now)

3) Staff checklist + KPI from operational facts (original product goal)
   - Metric-backed checklist items (examples): clear N Jubelio errors, resolve N stock discrepancies, apply N restock from Lark callback
   - Record staff_activity_events or reuse notification_log + resolver queries; show actual vs target on /staff-checklists overview
   - Permissions: extend staff-checklist roles; do not replace manual templates entirely in v1
   - Link KPI definitions to staff roles (penjualan_online, gudang, produk, …)

4) Optional polish (only if time in same PR or separate chat)
   - Low-cover: manufactured item path if user enables in Lark Ops later
   - Daily digest card (one summary vs many cards) — still B0 for non-critical
   - Lark Ops “roster preview” (who would be @’d per event)
   - jubelio_stock_discrepancies resolved_* → KPI + optional Lark when backlog high

Where:
- app/Services/Lark/*, LowCoverScanService, JubelioWebhookOversellScanner, ProcessJubelioOrder
- app/Services/StaffChecklist* , ChecklistTemplate, staff-checklists views
- config/lark.php, LarkSettingsService, resources/views/lark-settings/*
- database: settings JSON only unless user explicitly requests new tables for KPI (prefer additive columns on new L12 tables with production-safe migrations)

Acceptance:
- ./vendor/bin/pest for touched areas; BladePagesRenderTest if Blade changes
- No demo videos (AGENTS.md)
- Production-safe migrations only (additive / guarded)
- Do not change signed transactions.total, warehouse_item rebuild, or React/Vite

Notes:
- User timezone Asia/Jakarta; report times in WIB when relevant
- Low cover: both Aria physical + Jubelio available for cover days; re-arm rules unchanged
- Service items: never low-cover Lark (unlimited stock)
- Maintainer tests Lark manually after deploy; include “after deploy” steps in PR body

Branch: cursor/lark-phase2-<short-name>-9ce3
Start by reading doc/lark-aria-ops.md and AGENTS.md Jubelio/Lark sections; confirm which Phase 2 cluster this chat owns before coding.
```

---

## Suggested chat split

| Chat | Focus |
|------|--------|
| 1 | Error backlog scanner + event enable toggles |
| 2 | Checklist KPI metrics + overview UI |
| 3 | Discrepancy resolve tracking + digest/preview (optional) |

---

## Pre-merge checklist for maintainer (Phase 1)

- [ ] PRs merged: oversell spam/SHIPPED (#949/#951), low-cover item types (#952)
- [ ] Permissions: lark-ops-settings-*, users-lark-notify-edit
- [ ] Lark Ops: item types = asset lancar only; events @ as desired
- [ ] Users: Lark Open ID + notify for roster staff
- [ ] `LARK_ENABLED=true`, cron `lark:scan-low-cover` running
