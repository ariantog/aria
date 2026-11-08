#!/usr/bin/env bash
# Apply tracked Android snippets after `flutter create` (android/ is gitignored).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -d android/app ]]; then
  echo "error: run from apps/aria_mobile after 'flutter create --platforms=android' (missing android/app)" >&2
  exit 1
fi

cp android_manifests/main/AndroidManifest.xml android/app/src/main/AndroidManifest.xml
cp android_overlay/app/build.gradle.kts android/app/build.gradle.kts

if [[ ! -f android_keystore/debug.keystore ]]; then
  echo "error: missing android_keystore/debug.keystore (team debug key for consistent sideload signing)" >&2
  exit 1
fi

echo "Applied Android manifest + app/build.gradle.kts (applicationId com.ariantog.aria_mobile, team debug keystore)."
