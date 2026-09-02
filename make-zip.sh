#!/usr/bin/env bash
#
# make-zip.sh - Build the installable Akeeba Engage package (community fork).
#
# Standalone convenience builder (no phing / Akeeba buildfiles needed). It
# assembles each sub-extension zip and wraps them into the Joomla package
# installer pkg_engage-<version>.zip under dist/.
#
# The canonical Akeeba build is build.xml (phing); this script reproduces the
# package layout so anyone can build an installable without that toolchain.
#
# Usage:  ./make-zip.sh
# Output: dist/pkg_engage-<version>.zip
#
set -euo pipefail
cd "$(dirname "$0")"
ROOT="$(pwd)"

VERSION="$(grep -m1 '<version>' component/engage.xml | sed -E 's|.*<version>(.*)</version>.*|\1|')"
DATE="$(date +%Y-%m-%d)"
[ -n "$VERSION" ] || { echo "Could not read <version> from component/engage.xml" >&2; exit 1; }

# Composer dependencies (htmlpurifier) live under component/backend/vendor,
# which is gitignored. Generate them if missing so the build is self-contained.
if [ ! -d component/backend/vendor ]; then
    echo "Composer dependencies missing -> running composer install ..."
    composer install --no-dev --no-progress --ignore-platform-reqs
fi

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
PKG="$STAGE/pkg"
mkdir -p "$PKG/language" "$ROOT/dist"

# zip_dir <srcdir> <absolute-zip-path> [extra -x patterns...]
zip_dir() {
    local src="$1" out="$2"; shift 2
    ( cd "$src" && zip -rq "$out" . -x '*.DS_Store' '.git/*' '.idea/*' "$@" )
}

echo "Building Akeeba Engage package $VERSION ..."

# --- Component (engage.xml sits at the zip root; drop dev-only bits) ---
zip_dir component "$PKG/com_engage.zip" \
    'debug/*' 'LICENSE.txt' 'README.php' 'script.engage.php' \
    '*/.sass-cache/*' \
    'backend/vendor/ezyang/htmlpurifier/tests/*' \
    'backend/vendor/ezyang/htmlpurifier/docs/*' \
    'backend/vendor/ezyang/htmlpurifier/extras/*' \
    'backend/vendor/ezyang/htmlpurifier/maintenance/*' \
    'backend/vendor/ezyang/htmlpurifier/plugins/*'
echo "  + com_engage.zip"

# --- Module ---
zip_dir modules/site/engage_latest "$PKG/mod_engage_latest.zip"
echo "  + mod_engage_latest.zip"

# --- Plugins (dir:zipname) ---
PLUGINS="
plugins/actionlog/engage:plg_actionlog_engage.zip
plugins/console/engage:plg_console_engage.zip
plugins/content/engage:plg_content_engage.zip
plugins/datacompliance/engage:plg_datacompliance_engage.zip
plugins/engage/akismet:plg_engage_akismet.zip
plugins/engage/email:plg_engage_email.zip
plugins/engage/gravatar:plg_engage_gravatar.zip
plugins/privacy/engage:plg_privacy_engage.zip
plugins/system/engagecache:plg_system_engagecache.zip
plugins/user/engage:plg_user_engage.zip
"
while IFS=: read -r dir name; do
    [ -n "$dir" ] || continue
    zip_dir "$dir" "$PKG/$name"
    echo "  + $name"
done <<< "$PLUGINS"

# --- Package manifest, install script and package languages ---
sed -e "s/##VERSION##/$VERSION/g" -e "s/##DATE##/$DATE/g" \
    build/templates/pkg_engage.xml > "$PKG/pkg_engage.xml"
cp component/script.engage.php "$PKG/script.engage.php"
cp -R build/templates/language/. "$PKG/language/"

# --- Wrap the package ---
OUT="$ROOT/dist/pkg_engage-$VERSION.zip"
rm -f "$OUT"
( cd "$PKG" && zip -rq "$OUT" . -x '*.DS_Store' )

echo "Created: dist/pkg_engage-$VERSION.zip"
echo "Size:    $(du -h "$OUT" | cut -f1)"
