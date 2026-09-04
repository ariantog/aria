---
name: Blade migration conventions
description: How pages are built after the Inertia/React → Blade+Alpine migration; login/permission quirks for smoke testing.
---

- All pages extend `layouts.app` (Tailwind/Alpine/Tabulator via CDN); guest pages use `layouts.guest`. See `AGENTS.md` (Blade + Alpine, no React/Vite).
- **Why:** the whole app was migrated off Inertia/React (Aug 2026); mixing paradigms breaks the shared sidebar/flash/composer setup.
- **How to apply:** new pages = Blade view + classic forms/Tabulator remote JSON; never reintroduce `Inertia::render`.
- Login uses `username` (not email) — Fortify `config/fortify.php` `'username' => 'username'`.
- Permissions must exist before pages render: `PermissionGenerator::generateAll()` creates them from model `getPermissions()`; a fresh DB has none, so every gated page 403s.
- Blade gotcha: multiline `@json([...])` inside an Alpine `@click='...'` attribute fails to compile ("Unclosed '['"); build the JSON in `@php json_encode(..., JSON_HEX_APOS|JSON_HEX_QUOT) @endphp` instead.
- `transaction_rules` config `sender_type`/`receiver_type` can be arrays — never echo them directly in Blade.
- Pre-existing broken: `/posts` (no posts table/migration exists); AdditionalFee feature has no model/routes wired.
