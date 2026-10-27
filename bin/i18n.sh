#!/usr/bin/env bash
# Rebuild translation files for the theme and plugin.
#
#   1. Extract strings into .pot files with WP-CLI `i18n make-pot` (in Docker).
#   2. Merge Arabic translations from seed/translations-strings-ar.py into .po files.
#   3. Compile .mo files with GNU gettext `msgfmt -c` (validates plural forms and placeholders).
#
# Requires: the stack running (`docker compose up -d`), python3 and gettext on the host.
set -euo pipefail

cd "$(dirname "$0")/.."

THEME_DIR=wp-content/themes/nova-store/languages
PLUGIN_DIR=wp-content/plugins/nova-store-core/languages
STRINGS=seed/translations-strings-ar.py

pot() {
	# $1 = source dir inside the container, $2 = text domain. Writes the POT to stdout.
	docker compose run --rm -T --entrypoint sh wpcli -c \
		"wp i18n make-pot '$1' /tmp/out.pot --domain='$2' --skip-js --quiet && cat /tmp/out.pot" 2>/dev/null
}

echo "==> Extracting strings"
pot wp-content/themes/nova-store nova-store > "$THEME_DIR/nova-store.pot"
pot wp-content/plugins/nova-store-core nova-store-core > "$PLUGIN_DIR/nova-store-core.pot"

echo "==> Writing Arabic .po files"
python3 -I bin/po-fill.py "$THEME_DIR/nova-store.pot" "$THEME_DIR/ar.po" "$STRINGS" THEME
python3 -I bin/po-fill.py "$PLUGIN_DIR/nova-store-core.pot" "$PLUGIN_DIR/nova-store-core-ar.po" "$STRINGS" PLUGIN

echo "==> Compiling .mo files"
msgfmt -c --statistics -o "$THEME_DIR/ar.mo" "$THEME_DIR/ar.po" 2>&1 | grep -v "header field"
msgfmt -c --statistics -o "$PLUGIN_DIR/nova-store-core-ar.mo" "$PLUGIN_DIR/nova-store-core-ar.po" 2>&1 | grep -v "header field"
