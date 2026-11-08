# Team debug keystore (sideload / CI only)

This **debug** keystore is committed so every GitHub Actions build and developers who run `tool/apply_android_overlay.sh` sign debug APKs with the **same** key. That lets you install a new CI artifact **over** an older one without uninstalling.

| Field | Value |
|-------|--------|
| File | `debug.keystore` |
| Store password | `android` |
| Key alias | `androiddebugkey` |
| Key password | `android` |

These match the usual Android SDK debug defaults (passwords only; the **certificate** is team-specific).

**Not for Play Store release.** Release / upload keys stay out of the repo.

If you build locally without applying the overlay, Gradle uses your machine’s `~/.android/debug.keystore` — signatures will **not** match CI until you run `bash tool/apply_android_overlay.sh` after `flutter create`.
