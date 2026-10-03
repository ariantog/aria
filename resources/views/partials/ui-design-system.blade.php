{{--
    Refactoring UI–inspired design tokens and utility classes for Blade pages.
    Included from layouts/app.blade.php and layouts/guest.blade.php.
    Prefer these classes on new/edited markup over one-off Tailwind combinations.
--}}
<style>
    :root {
        --ui-bg: 220 20% 97%;
        --ui-surface: 0 0% 100%;
        --ui-surface-muted: 220 16% 96%;
        --ui-border: 220 13% 91%;
        --ui-border-strong: 220 10% 84%;
        --ui-text: 222 47% 11%;
        --ui-text-muted: 215 16% 47%;
        --ui-text-subtle: 215 14% 57%;
        --ui-accent: 221 83% 53%;
        --ui-accent-hover: 221 83% 48%;
        --ui-accent-muted: 214 95% 96%;
        --ui-accent-foreground: 221 83% 40%;
        --ui-radius: 0.625rem;
        --ui-radius-lg: 0.875rem;
        --ui-shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
        --ui-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
        --ui-shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.05);
        --ui-shadow-panel: 0 1px 2px rgba(15, 23, 42, 0.04), 0 0 0 1px hsl(var(--ui-border));
        --ui-focus-ring: 0 0 0 3px hsl(221 83% 53% / 0.25);
    }

    .dark {
        --ui-bg: 222 47% 7%;
        --ui-surface: 222 47% 9%;
        --ui-surface-muted: 217 33% 12%;
        --ui-border: 217 19% 18%;
        --ui-border-strong: 217 15% 24%;
        --ui-text: 210 40% 98%;
        --ui-text-muted: 215 20% 65%;
        --ui-text-subtle: 215 16% 55%;
        --ui-accent-muted: 221 50% 15%;
        --ui-accent-foreground: 214 95% 85%;
        --ui-shadow-panel: 0 0 0 1px hsl(var(--ui-border));
    }

    /* ── Page layout ─────────────────────────────────────────────── */
    .ui-page {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        padding: 1rem 1.25rem 1.5rem;
    }
    @media (min-width: 640px) {
        .ui-page { padding: 1.25rem 1.5rem 2rem; }
    }

    .ui-page-header {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }
    @media (min-width: 640px) {
        .ui-page-header {
            flex-direction: row;
            align-items: center;
        }
    }

    .ui-page-title {
        font-size: 1.375rem;
        font-weight: 600;
        letter-spacing: -0.025em;
        line-height: 1.25;
        color: hsl(var(--ui-text));
    }
    @media (min-width: 640px) {
        .ui-page-title { font-size: 1.5rem; }
    }

    .ui-page-lead {
        margin-top: 0.25rem;
        font-size: 0.875rem;
        line-height: 1.4;
        color: hsl(var(--ui-text-muted));
        max-width: 42rem;
    }

    .ui-page-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    /* ── Surfaces & panels ───────────────────────────────────────── */
    .ui-panel {
        overflow: hidden;
        border-radius: var(--ui-radius-lg);
        background: hsl(var(--ui-surface));
        box-shadow: var(--ui-shadow-panel);
    }

    .ui-panel-toolbar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
        border-bottom: 1px solid hsl(var(--ui-border));
        padding: 0.5rem 0.75rem;
        background: hsl(var(--ui-surface));
    }

    .ui-panel-body { padding: 1rem 1.25rem; }
    @media (min-width: 640px) {
        .ui-panel-body { padding: 1.25rem 1.5rem; }
    }

    .ui-chip-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem 1rem;
        border-radius: var(--ui-radius);
        background: hsl(var(--ui-surface-muted));
        padding: 0.5rem 0.75rem;
        box-shadow: inset 0 0 0 1px hsl(var(--ui-border));
    }

    .ui-chip-bar-label {
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: hsl(var(--ui-text-subtle));
    }

    /* ── Buttons ─────────────────────────────────────────────────── */
    .ui-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        border-radius: var(--ui-radius);
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.25;
        transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        white-space: nowrap;
    }

    .ui-btn-primary {
        background: hsl(var(--ui-accent));
        color: white;
        box-shadow: var(--ui-shadow-xs), 0 1px 0 hsl(221 83% 45% / 0.15);
    }
    .ui-btn-primary:hover {
        background: hsl(var(--ui-accent-hover));
    }
    .ui-btn-primary:active {
        box-shadow: none;
    }

    .ui-btn-secondary {
        background: hsl(var(--ui-surface));
        color: hsl(var(--ui-text));
        box-shadow: inset 0 0 0 1px hsl(var(--ui-border-strong));
    }
    .ui-btn-secondary:hover {
        background: hsl(var(--ui-surface-muted));
    }

    .ui-btn-ghost {
        color: hsl(var(--ui-text-muted));
        padding: 0.375rem 0.625rem;
    }
    .ui-btn-ghost:hover {
        background: hsl(var(--ui-surface-muted));
        color: hsl(var(--ui-text));
    }

    .ui-btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
    }

    /* ── Form controls ───────────────────────────────────────────── */
    .ui-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 500;
        color: hsl(var(--ui-text));
        margin-bottom: 0.375rem;
    }

    .ui-label-muted {
        font-size: 0.75rem;
        font-weight: 500;
        color: hsl(var(--ui-text-muted));
    }

    .ui-input,
    .ui-select,
    .ui-textarea {
        width: 100%;
        border-radius: var(--ui-radius);
        border: 1px solid hsl(var(--ui-border-strong));
        background: hsl(var(--ui-surface));
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        color: hsl(var(--ui-text));
        box-shadow: var(--ui-shadow-xs);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .ui-input::placeholder,
    .ui-textarea::placeholder {
        color: hsl(var(--ui-text-subtle));
    }
    .ui-input:focus,
    .ui-select:focus,
    .ui-textarea:focus {
        outline: none;
        border-color: hsl(var(--ui-accent));
        box-shadow: var(--ui-focus-ring);
    }

    .ui-field-hint {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: hsl(var(--ui-text-muted));
    }

    /* ── Data tables (Refactoring UI: light header, row hover, no grid) ─ */
    .ui-data-table {
        width: 100%;
        font-size: 0.875rem;
        border-collapse: collapse;
    }

    .ui-data-table thead {
        background: hsl(var(--ui-surface));
        border-bottom: 1px solid hsl(var(--ui-border));
    }

    .ui-data-table thead th {
        padding: 0.625rem 0.75rem;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: hsl(var(--ui-text-subtle));
        white-space: nowrap;
    }

    .ui-data-table tbody tr {
        border-bottom: 1px solid hsl(var(--ui-border) / 0.65);
        transition: background-color 0.12s ease;
    }

    .ui-data-table tbody tr:last-child {
        border-bottom: none;
    }

    .ui-data-table tbody tr:hover {
        background: hsl(var(--ui-surface-muted) / 0.85);
    }

    .ui-data-table tbody td {
        padding: 0.625rem 0.75rem;
        vertical-align: middle;
        color: hsl(var(--ui-text));
    }

    .ui-data-table a:not(.ui-btn):not(.ui-btn-primary):not(.ui-btn-secondary) {
        color: hsl(var(--ui-accent));
        font-weight: 500;
    }
    .ui-data-table a:not(.ui-btn):not(.ui-btn-primary):not(.ui-btn-secondary):hover {
        text-decoration: underline;
    }

    /* ── Badges & pills ──────────────────────────────────────────── */
    .ui-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border-radius: 9999px;
        padding: 0.125rem 0.5rem;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.25;
    }

    /* ── App shell refinements ───────────────────────────────────── */
    .ui-app-bg {
        background: hsl(var(--ui-bg));
        color: hsl(var(--ui-text));
    }

    .ui-sidebar {
        background: hsl(var(--ui-surface));
        border-right: 1px solid hsl(var(--ui-border));
        box-shadow: 2px 0 8px rgba(15, 23, 42, 0.03);
    }

    .ui-topbar {
        background: hsl(var(--ui-surface) / 0.92);
        backdrop-filter: blur(8px);
        border-bottom: 1px solid hsl(var(--ui-border));
    }

    #sidebar nav a.bg-blue-50,
    #sidebar nav button.bg-blue-50 {
        background: hsl(var(--ui-accent-muted)) !important;
        color: hsl(var(--ui-accent-foreground)) !important;
        box-shadow: inset 3px 0 0 hsl(var(--ui-accent));
    }

    #sidebar nav .ml-6 a.bg-blue-50 {
        box-shadow: inset 2px 0 0 hsl(var(--ui-accent));
    }

    /* Combobox (Refactoring UI: soft panel, accent highlight) */
    .combobox-options {
        background: hsl(var(--ui-surface));
        border: 1px solid hsl(var(--ui-border));
        border-radius: var(--ui-radius);
        box-shadow: var(--ui-shadow-md);
    }
    .combobox-option {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        color: hsl(var(--ui-text));
    }
    .combobox-option:hover,
    .combobox-option.active {
        background: hsl(var(--ui-accent));
        color: white;
    }

    /* Legacy pages: soften default focus without fighting .ui-input */
    input:not(.ui-input):focus,
    select:not(.ui-select):focus,
    textarea:not(.ui-textarea):focus {
        outline: none;
        border-color: hsl(var(--ui-accent));
        box-shadow: var(--ui-focus-ring);
    }

    .ui-stat-card {
        border-radius: var(--ui-radius-lg);
        background: hsl(var(--ui-surface));
        padding: 1rem 1.25rem;
        box-shadow: var(--ui-shadow-panel);
    }

    .ui-stat-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: hsl(var(--ui-text-muted));
    }

    .ui-stat-value {
        margin-top: 0.25rem;
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: -0.02em;
        color: hsl(var(--ui-text));
        font-variant-numeric: tabular-nums;
    }

    /* Guest auth card */
    .ui-auth-card {
        border-radius: var(--ui-radius-lg);
        background: hsl(var(--ui-surface));
        padding: 2rem;
        box-shadow: var(--ui-shadow-md), 0 0 0 1px hsl(var(--ui-border));
    }

    .ui-brand-mark {
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--ui-radius);
        background: linear-gradient(145deg, hsl(221 83% 48%), hsl(221 83% 42%));
        color: white;
        font-weight: 700;
        box-shadow: var(--ui-shadow-sm);
    }

    .ui-brand-mark-sm {
        height: 2rem;
        width: 2rem;
        font-size: 0.75rem;
    }

    .ui-auth-logo.ui-brand-mark,
    .ui-auth-logo {
        height: 2.75rem;
        width: 2.75rem;
        font-size: 0.875rem;
    }
</style>
