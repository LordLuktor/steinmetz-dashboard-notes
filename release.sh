#!/usr/bin/env bash
# Tag + publish a GitHub release with an installable zip for the built-in updater.
# Usage: ./release.sh "Release notes"   (version is read from the plugin header)
set -euo pipefail
cd "$(dirname "$0")"
SLUG="steinmetz-dashboard-notes"
VER="$(grep -m1 -oP '^\s*\*\s*Version:\s*\K[0-9.]+' ${SLUG}.php)"
NOTES="${1:-Release v${VER}}"
[[ -n "$(git status --porcelain)" ]] && { echo "Commit your changes first." >&2; exit 1; }
git rev-parse "v${VER}" >/dev/null 2>&1 && { echo "Tag v${VER} already exists — bump the version." >&2; exit 1; }
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/$SLUG"
git archive HEAD | tar -x -C "$TMP/$SLUG"
rm -f "$TMP/$SLUG"/{release.sh,README.md,.gitignore}
(cd "$TMP" && zip -qr "${SLUG}.zip" "$SLUG")
git -c user.email="steinmetz.scott@gmail.com" -c user.name="Scott Steinmetz" tag -a "v${VER}" -m "v${VER}"
git push origin main "v${VER}"
gh release create "v${VER}" "$TMP/${SLUG}.zip" --title "v${VER}" --notes "$NOTES"
echo "Released v${VER}"
