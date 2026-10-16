#!/bin/sh
# Copies the PHP part of Supertext Plugin Health (version reports, API key check result and
# ping endpoint for the Supertext Commando Center) from the Commando Center's repository into
# lib/supertext-plugin-health, with its namespace changed from Supertext\PluginHealth to
# Supertext\Typo3Translation\PluginHealth. The extension ships this copy, like the Supertext
# SDK, so sites install nothing besides the extension.
#
#   scripts/vendor-plugin-health.sh [commit-or-branch]   (default: main)
#   PLUGIN_HEALTH_DIR=/path/to/clone scripts/vendor-plugin-health.sh   (use a local clone as it is)
set -eu
REF="${1:-main}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

if [ -n "${PLUGIN_HEALTH_DIR:-}" ]; then
    SRC="$PLUGIN_HEALTH_DIR"
else
    git clone --quiet https://github.com/Supertext/Commando-Center-Supertext-Translation "$TMP/center"
    git -C "$TMP/center" checkout --quiet "$REF"
    SRC="$TMP/center"
fi
COMMIT="$(git -C "$SRC" rev-parse HEAD)"

DEST="$ROOT/lib/supertext-plugin-health"
rm -rf "$DEST"
mkdir -p "$DEST"
cp -R "$SRC/plugin-health/php/src" "$DEST/src"
cp "$SRC/plugin-health/php/LICENSE" "$DEST/LICENSE"
find "$DEST/src" -name '*.php' -exec sed -i 's/Supertext\\PluginHealth\\/Supertext\\Typo3Translation\\PluginHealth\\/g; s/Supertext\\PluginHealth;/Supertext\\Typo3Translation\\PluginHealth;/g' {} +
printf '%s\n' "$COMMIT" > "$DEST/COMMIT"
VERSION="$(sed -n "s/.*PLUGIN_HEALTH_VERSION = '\([^']*\)'.*/\1/p" "$DEST/src/PluginHealth.php")"
printf '%s\n' "$VERSION" > "$DEST/VERSION"
cat > "$DEST/README.md" <<EOT
# Supertext Plugin Health (bundled copy)

PHP part of [Supertext Plugin Health](https://github.com/Supertext/Commando-Center-Supertext-Translation/tree/main/plugin-health),
version $VERSION, commit \`$COMMIT\`, namespace \`Supertext\\Typo3Translation\\PluginHealth\` instead of \`Supertext\\PluginHealth\`.

Don't edit these files: fix it in Commando-Center-Supertext-Translation/plugin-health, then run
\`scripts/vendor-plugin-health.sh\`.
EOT
if grep -rq 'Supertext\\PluginHealth' "$DEST/src"; then
    echo "vendor-plugin-health: unprefixed Supertext\\PluginHealth reference left in $DEST/src" >&2
    exit 1
fi
echo "lib/supertext-plugin-health: Supertext Plugin Health (PHP) $VERSION from commit $COMMIT"
