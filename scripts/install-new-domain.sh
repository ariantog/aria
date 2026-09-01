#!/usr/bin/env bash
# Deploy helper for a brand-new subdomain database.
# Refuses to run on the current production domain — see app:install-new-domain.
set -euo pipefail

cd "$(dirname "$0")/.."

php artisan app:install-new-domain --force
