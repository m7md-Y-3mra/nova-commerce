#!/usr/bin/env bash
# Runs ON the alwaysdata server (no Docker there; WordPress runs on their PHP).
# Installs WordPress + the pinned WooCommerce, the Arabic language packs, the
# nova-store theme and nova-store-core plugin, then seeds the demo catalog.
#
# Usage (normally called by deploy/alwaysdata-push.sh from your machine):
#   ALWAYSDATA_ACCOUNT=myaccount ADMIN_EMAIL=you@example.com bash ~/nova-install.sh
#
# Secrets are read from files (mode 600), never from argv or the repo:
#   ~/.dbpass      MySQL password of the alwaysdata database user
#   ~/.adminpass   WordPress admin password (only needed for the first install)
#
# Re-runnable: each step checks current state. The catalog is only seeded on
# the first run; RESEED=1 forces it (this resets prices and stock).
set -euo pipefail

ACCOUNT="${ALWAYSDATA_ACCOUNT:?set ALWAYSDATA_ACCOUNT to your alwaysdata account name}"
SITE_URL="${SITE_URL:-https://${ACCOUNT}.alwaysdata.net}"
DB_NAME="${DB_NAME:-${ACCOUNT}_nova}"
DB_USER="${DB_USER:-${ACCOUNT}}"
DB_HOST="${DB_HOST:-mysql-${ACCOUNT}.alwaysdata.net}"
ADMIN_USER="${ADMIN_USER:-admin}"
DOCROOT="${DOCROOT:-www}" # site root directory, relative to $HOME
WWW="${WWW:-$HOME/$DOCROOT}"
SRC="${SRC:-$HOME/nova-src}"
WP_BIN="${WP_BIN:-$HOME/bin/wp}"

WP_VERSION="7.1.3"
WOOCOMMERCE_VERSION="11.2.1"

# Seeding generates thumbnails for 19 images; the free plan's PHP default is tight.
wp() {
	php -d memory_limit=512M "$WP_BIN" --path="$WWW" "$@"
}

[[ -s "$HOME/.dbpass" ]] || { echo "Missing ~/.dbpass (database password)." >&2; exit 1; }
[[ -d "$SRC/wp-content/themes/nova-store" && -d "$SRC/wp-content/plugins/nova-store-core" && -f "$SRC/seed/seed.php" ]] \
	|| { echo "Missing code in $SRC - upload it with deploy/alwaysdata-push.sh." >&2; exit 1; }

# ----------------------------------------------------------------------
# Never overwrite a different site living in the same docroot.
# ----------------------------------------------------------------------
if [[ -d "$WWW/wp-content/themes" ]]; then
	for theme in "$WWW"/wp-content/themes/*/; do
		name="$(basename "$theme")"
		case "$name" in
			nova-store | twentytwentyfive) ;;
			*) echo "Refusing: $WWW already hosts another site (theme '$name'). Use a separate account or site." >&2; exit 1 ;;
		esac
	done
fi
if [[ -f "$WWW/wp-config.php" ]] && ! grep -q "'DB_NAME', '${DB_NAME}'" "$WWW/wp-config.php"; then
	echo "Refusing: $WWW/wp-config.php points at a different database than ${DB_NAME}." >&2
	exit 1
fi

echo "==> WP-CLI"
if [[ ! -x "$WP_BIN" ]]; then
	mkdir -p "$(dirname "$WP_BIN")"
	curl -fsSL -o "$WP_BIN" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +x "$WP_BIN"
fi
wp --version

echo "==> WordPress core ${WP_VERSION}"
mkdir -p "$WWW"
if [[ ! -f "$WWW/wp-load.php" ]]; then
	wp core download --version="$WP_VERSION" --skip-content
fi

if [[ ! -f "$WWW/wp-config.php" ]]; then
	echo "==> wp-config.php"
	# Password via stdin so it never shows up in the process list. --prompt echoes
	# the value and the rebuilt command to stdout, so stdout is discarded.
	wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbhost="$DB_HOST" \
		--prompt=dbpass < "$HOME/.dbpass" >/dev/null
	wp config set WP_HOME "$SITE_URL"
	wp config set WP_SITEURL "$SITE_URL"
	wp config set WP_DEBUG false --raw
	wp config set WP_DEBUG_DISPLAY false --raw
	wp config set DISALLOW_FILE_EDIT true --raw
	wp config set WP_MEMORY_LIMIT '256M'
	wp config set FS_METHOD 'direct'

	# TLS ends at alwaysdata's proxy, so PHP sees plain HTTP. Trust its header so
	# is_ssl() is true: secure cookies (cart session, nova_lang) and https URLs.
	php -r '
		$file = $argv[1];
		$snippet = "if ( isset( \$_SERVER[\"HTTP_X_FORWARDED_PROTO\"] ) && \"https\" === \$_SERVER[\"HTTP_X_FORWARDED_PROTO\"] ) {\n\t\$_SERVER[\"HTTPS\"] = \"on\";\n}\n\n";
		$config = file_get_contents( $file );
		$marker = "/* That'"'"'s all, stop editing!";
		if ( false === strpos( $config, "HTTP_X_FORWARDED_PROTO" ) ) {
			$pos = strpos( $config, $marker );
			if ( false === $pos ) { fwrite( STDERR, "wp-config marker not found\n" ); exit( 1 ); }
			file_put_contents( $file, substr_replace( $config, $snippet, $pos, 0 ) );
		}
	' "$WWW/wp-config.php"
	php -l "$WWW/wp-config.php" >/dev/null
	chmod 600 "$WWW/wp-config.php"
fi

if [[ ! -f "$WWW/.htaccess" ]]; then
	echo "==> .htaccess"
	# `wp rewrite flush --hard` cannot write it here, and without it every permalink 404s.
	cat > "$WWW/.htaccess" <<'HTACCESS'
# Force HTTPS. TLS ends at the proxy, so test the forwarded header, not %{HTTPS}.
RewriteEngine On
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS
fi

if ! wp core is-installed >/dev/null 2>&1; then
	echo "==> Installing WordPress"
	[[ -s "$HOME/.adminpass" ]] || { echo "Missing ~/.adminpass (admin password for the first install)." >&2; exit 1; }
	: "${ADMIN_EMAIL:?set ADMIN_EMAIL for the first install}"
	wp core install --url="$SITE_URL" --title="NOVA Store" \
		--admin_user="$ADMIN_USER" --admin_email="$ADMIN_EMAIL" --skip-email \
		--prompt=admin_password < "$HOME/.adminpass" >/dev/null # stdout echoes the password
fi

echo "==> Site options"
wp option update blogdescription "Premium tech accessories for focused work" >/dev/null
wp option update timezone_string "UTC" >/dev/null
wp rewrite structure '/%postname%/' >/dev/null

echo "==> Fallback theme (not activated; keeps /wp-admin reachable if nova-store fatals)"
wp theme is-installed twentytwentyfive >/dev/null 2>&1 || wp theme install twentytwentyfive >/dev/null

echo "==> WooCommerce ${WOOCOMMERCE_VERSION}"
if ! wp plugin is-installed woocommerce >/dev/null 2>&1; then
	wp plugin install woocommerce --version="$WOOCOMMERCE_VERSION"
fi
wp plugin activate woocommerce >/dev/null

echo "==> Arabic language packs"
wp language core install ar >/dev/null
wp language plugin install woocommerce ar >/dev/null

echo "==> Theme and plugin code"
for dir in themes/nova-store plugins/nova-store-core; do
	mkdir -p "$WWW/wp-content/$(dirname "$dir")"
	rm -rf "$WWW/wp-content/$dir.new"
	cp -a "$SRC/wp-content/$dir" "$WWW/wp-content/$dir.new"
	rm -rf "$WWW/wp-content/$dir"
	mv "$WWW/wp-content/$dir.new" "$WWW/wp-content/$dir"
done
wp theme activate nova-store
wp plugin activate nova-store-core

products="$(wp post list --post_type=product --post_status=any --format=count)"
if [[ "$products" == "0" || "${RESEED:-0}" == "1" ]]; then
	echo "==> Seeding catalog (slow on the free plan)"
	wp eval-file "$SRC/seed/seed.php" --user="$ADMIN_USER"
else
	echo "==> Catalog already seeded ($products products); RESEED=1 to reset it"
fi

echo "==> Public demo: no order e-mails to shoppers"
# Shoppers type addresses that may belong to real people. The admin new-order
# e-mail and password resets stay on.
wp eval '
	foreach ( array( "customer_on_hold_order", "customer_processing_order", "customer_completed_order", "customer_invoice", "customer_note", "customer_refunded_order", "customer_new_account" ) as $id ) {
		$key      = "woocommerce_" . $id . "_settings";
		$settings = get_option( $key, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$settings["enabled"] = "no";
		update_option( $key, $settings );
	}
'

wp rewrite flush >/dev/null
wp cache flush >/dev/null || true

echo "--- state ---"
wp option get home
wp theme list --status=active --field=name
wp plugin list --status=active --field=name
echo "products: $(wp post list --post_type=product --format=count)"
echo "Done: ${SITE_URL}  (Arabic: ${SITE_URL}/?lang=ar)"
