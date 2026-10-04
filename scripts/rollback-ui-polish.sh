#!/usr/bin/env bash
# Revert Refactoring UI polish (PR #825) on the current branch.
# Usage:
#   ./scripts/rollback-ui-polish.sh          # create revert commits (default)
#   ./scripts/rollback-ui-polish.sh --dry-run
#
# Run from a clean working tree on main (or branch that contains the UI commits).

set -euo pipefail

DRY_RUN=0
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
fi

# Newest-first revert order for the feature branch commits
COMMITS=(
  8376ca05
  172ada43
  6fcd99c2
)

echo "This will revert Refactoring UI polish in order:"
printf '  %s\n' "${COMMITS[@]}"
echo

if [[ "$DRY_RUN" == 1 ]]; then
  echo "Dry run: would run: git revert --no-edit ${COMMITS[*]}"
  exit 0
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: working tree not clean. Commit or stash changes first." >&2
  exit 1
fi

for sha in "${COMMITS[@]}"; do
  if ! git cat-file -e "${sha}^{commit}" 2>/dev/null; then
    echo "Commit ${sha} not found in this repo." >&2
    echo "If main was squash-merged, use instead:" >&2
    echo "  git revert <squash-merge-commit-on-main> --no-edit" >&2
    exit 1
  fi
done

git revert --no-edit "${COMMITS[@]}"

echo
echo "Revert complete. Run tests, then push:"
echo "  ./vendor/bin/pest tests/Feature/BladePagesRenderTest.php tests/Feature/ItemCreateFormTest.php"
echo "  git push origin main"
