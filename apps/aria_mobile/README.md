# Aria Mobile (Flutter)

Native client for Aria ERP. Talks to the same Laravel host via `/api/mobile/v1` (Bearer Sanctum token).

## One-time setup

Install [Flutter](https://docs.flutter.dev/get-started/install) and Android SDK (or Xcode for iOS). From this directory:

```bash
cd apps/aria_mobile
flutter create --platforms=android,ios .
flutter pub get
```

The `flutter create` step adds the `android/` and `ios/` runners that are gitignored until generated locally.

## Configure API base URL

On first launch, enter your Aria base URL (no trailing slash), for example:

- Development: `http://127.0.0.1:8000` (web) or `http://10.0.2.2:8000` (Android emulator → host loopback)
- Production: `https://your-aria-domain.example`

The app calls `{baseUrl}/api/mobile/v1/...`.

## Build APK

```bash
flutter build apk --debug     # quick test install
flutter build apk --release   # needs signing for Play/internal distribution
```

Release signing: set up `key.properties` and Gradle signing config per [Flutter Android deployment](https://docs.flutter.dev/deployment/android).

## GitHub Actions (no local Flutter)

The workflow **Flutter debug APK** (`.github/workflows/flutter-apk.yml`) builds a **debug** APK on GitHub and uploads it as an artifact named `aria-mobile-debug-apk`.

- **Manual run:** repo → **Actions** → **Flutter debug APK** → **Run workflow**.
- **Automatic run:** pushes to `main` / `develop` / `master` that touch `apps/aria_mobile/**`.

Download the artifact from the finished run’s **Summary** page. Install with `adb install app-debug.apk` or share the file; debug builds are for testing, not Play Store.

For a **signed release** APK/AAB, add keystore secrets and a separate job (Gradle signing or `flutter build apk --release` with env vars) — ask the maintainer before storing signing keys in GitHub.

## Authentication

Uses username + password (same as web Fortify). If the account has two-factor authentication enabled, the app prompts for a TOTP code or a recovery code.

## Permissions

Available actions follow Spatie permissions (`transactions-type-sell`, `transactions-type-cash-in`, etc.), same as the web UI.
