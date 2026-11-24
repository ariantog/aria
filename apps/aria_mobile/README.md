# Aria Mobile (Flutter)

## POS v1 (sell)

Counter **POS — Sell** (`lib/screens/pos_sell_screen.dart`): scanner-first sell, per-user defaults (`preferences.pos`), 58mm receipt via **PDF** (`printing` package) or **Bluetooth ESC/POS** (native thermal), optional WhatsApp link, Jubelio push reminder (no reserve). See `doc/pos-mobile-kickoff.md` and `doc/mobile-api.md`.

## Bluetooth thermal print (58mm, Android-first)

**Packages:** [`print_bluetooth_thermal`](https://pub.dev/packages/print_bluetooth_thermal) (RFCOMM transport) + [`esc_pos_utils_plus`](https://pub.dev/packages/esc_pos_utils_plus) (ESC/POS bytes). Receipt text follows the same layout as `resources/views/transactions/pdf/receipt.blade.php` (18|3|9 monospace columns, branding, subtotal, disc/adj/ppn, total).

1. Pair the 58mm printer in **Android Settings → Bluetooth** (SPP / classic Bluetooth, not BLE-only gadgets without a serial profile).
2. In Aria Mobile, open **Bluetooth printer** (print icon on the shell app bar, or bluetooth icon on transaction detail).
3. Select the paired device (MAC is saved in SharedPreferences), tap **Test print**.
4. From **Transaction detail** or POS success sheet, use **BT print** / **Bluetooth ESC/POS print**.

**iOS:** `print_bluetooth_thermal` lists iOS support, but this project has **not** validated thermal printing on iOS yet (no committed `Info.plist` Bluetooth usage strings). Use PDF print or web for v1 on iOS until tested.

Data comes from `GET /api/mobile/v1/transactions/{id}` (`receipt_branding`, `grand_total`, line `display_name`).

Native client for Aria ERP. Talks to the same Laravel host via `/api/mobile/v1` (Bearer Sanctum token).

**Android package name (applicationId):** `com.ariantog.aria_mobile` — fixed via `flutter create --org com.ariantog --project-name aria_mobile` plus the tracked Gradle overlay. Do not change casually; a different id is a different app on the device.

## One-time setup

Install [Flutter](https://docs.flutter.dev/get-started/install) and Android SDK (or Xcode for iOS). From this directory:

```bash
cd apps/aria_mobile
flutter pub get
flutter create --org com.ariantog --project-name aria_mobile --platforms=android,ios .
bash tool/apply_android_overlay.sh
```

`apply_android_overlay.sh` copies the camera **AndroidManifest** and **app/build.gradle.kts** (team debug signing). Without it, local debug builds use your personal `~/.android/debug.keystore` and will **not** match CI signatures.

Use **Theme** (palette icon on home) to set light / dark / system; it calls `PATCH /api/mobile/v1/preferences/appearance` so it stays in sync with web settings.

## Configure API base URL

On first launch, enter your Aria base URL (no trailing slash), for example:

- Development: `http://127.0.0.1:8000` (web) or `http://10.0.2.2:8000` (Android emulator → host loopback)
- Production: `https://your-aria-domain.example`

The app calls `{baseUrl}/api/mobile/v1/...`.

The default production host is baked in (`https://aria3.corenationactive.com`). That fills the base URL field when nothing is saved yet; the user can still change it (stored in SharedPreferences). Override for local dev:

```bash
flutter run --dart-define=ARIA_DEFAULT_BASE_URL=http://10.0.2.2:5000
```

**Why overlays instead of committing `android/`:** The generated `android/` tree is gitignored so clones stay small. Tracked pieces live under `android_manifests/`, `android_overlay/`, and `android_keystore/`; CI and local dev run `flutter create` then `tool/apply_android_overlay.sh` (see `.github/workflows/flutter-apk.yml`).

## Build APK (local)

Use the same overlay as CI so signatures match:

```bash
flutter create --org com.ariantog --project-name aria_mobile --platforms=android .
bash tool/apply_android_overlay.sh
flutter build apk --debug
```

Bump the installable version when testing upgrades locally (CI uses `GITHUB_RUN_NUMBER` automatically):

```bash
flutter build apk --debug --build-number=42
```

Release signing for Play Store is separate — see [Flutter Android deployment](https://docs.flutter.dev/deployment/android). This repo’s committed keystore is **debug-only** for internal sideload.

## Install / upgrade on a device

| Symptom | Cause | Fix |
|---------|--------|-----|
| “App not installed” or must uninstall first | APK signed with a **different** key than the installed app | Use CI artifacts or a local build **after** `apply_android_overlay.sh` (team keystore). |
| “App not installed” with same signing | **versionCode** not higher than installed | Install a newer CI run (each build uses `--build-number=$GITHUB_RUN_NUMBER`) or pass `--build-number` locally. |
| Wrong app / duplicate icon | Different **applicationId** | Only `com.ariantog.aria_mobile` from this project; uninstall old experimental packages once. |

```bash
adb install -r build/app/outputs/flutter-apk/app-debug.apk
```

`-r` replaces the existing app when the signature and package name match and versionCode increases.

## GitHub Actions (no local Flutter)

The workflow **Flutter debug APK** (`.github/workflows/flutter-apk.yml`) builds a **debug** APK on GitHub and uploads it as an artifact named `aria-mobile-debug-apk`.

- **On PRs** that touch `apps/aria_mobile/**` (compile check + APK artifact).
- **On push to `main`** when mobile files change.
- **Manual run:** repo → **Actions** → **Flutter debug APK** → **Run workflow** → pick your **branch** (e.g. a PR branch) → Run.

Commit `apps/aria_mobile/pubspec.lock` whenever `pubspec.yaml` dependencies change so CI and teammates resolve the same versions.
- **Automatic run:** pushes to `main` / `develop` / `master` that touch `apps/aria_mobile/**`.

Each run sets `versionCode` from `GITHUB_RUN_NUMBER` so newer artifacts upgrade in place. Download the artifact from the finished run’s **Summary** page.

**Pre-fill API URL in CI builds:** add a repository variable `ARIA_MOBILE_BASE_URL` (Settings → Secrets and variables → Actions → Variables), e.g. `https://staging.example.com`. The workflow passes it as `--dart-define=ARIA_DEFAULT_BASE_URL=...` when building. On a manual **Run workflow** you can pass **base_url** instead to override for that run only.

For a **signed release** APK/AAB, add keystore secrets and a separate job (Gradle signing or `flutter build apk --release` with env vars) — ask the maintainer before storing signing keys in GitHub.

## UX & design docs

- **App shell / home (Material 3, mockup-based):** [`doc/mobile-app-design-direction.md`](doc/mobile-app-design-direction.md)
- **Transaction forms:** [`doc/mobile-transaction-form-ux.md`](doc/mobile-transaction-form-ux.md)

## Authentication

Uses username + password (same as web Fortify). If the account has two-factor authentication enabled, the app prompts for a TOTP code or a recovery code.

## Permissions

Available actions follow Spatie permissions (`transactions-type-sell`, `transactions-type-cash-in`, etc.), same as the web UI.
