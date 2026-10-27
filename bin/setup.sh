#!/usr/bin/env bash
# Idempotent bootstrap for the NOVA Store demo.
#
#   1. Starts the Docker stack (MariaDB + WordPress/Apache).
#   2. Installs WordPress core and the pinned WooCommerce release via WP-CLI.
#   3. Activates the nova-store theme and nova-store-core plugin.
#   4. Configures WooCommerce (store details, COD only, shipping) and seeds
#      categories, products, pages and the primary menu (seed/seed.php).
#
# Safe to re-run: every step checks current state before changing anything.
set -euo pipefail

cd "$(dirname "$0")/.."

WOOCOMMERCE_VERSION="11.2.1"

if [[ ! -f .env ]]; then
	echo "Missing .env - copy .env.example to .env and set real values first." >&2
	exit 1
fi
set -a
# shellcheck disable=SC1091
source .env
set +a

wp() {
	docker compose run --rm -T wpcli "$@"
}

echo "==> Starting containers"
docker compose up -d db wordpress

echo "==> Waiting for WordPress files and database"
for _ in $(seq 1 60); do
	if docker compose exec -T wordpress test -f /var/www/html/wp-config.php \
		&& wp db check --quiet >/dev/null 2>&1; then
		break
	fi
	sleep 2
done
wp db check --quiet >/dev/null

if ! wp core is-installed >/dev/null 2>&1; then
	echo "==> Installing WordPress"
	wp core install \
		--url="${WP_URL}" \
		--title="NOVA Store" \
		--admin_user="${WP_ADMIN_USER}" \
		--admin_password="${WP_ADMIN_PASSWORD}" \
		--admin_email="${WP_ADMIN_EMAIL}" \
		--skip-email
else
	echo "==> WordPress already installed"
fi

echo "==> Site options"
wp option update blogdescription "Premium tech accessories for focused work" >/dev/null
wp option update timezone_string "UTC" >/dev/null
wp rewrite structure '/%postname%/' >/dev/null

echo "==> Removing bundled plugins the store does not need"
for p in hello akismet; do
	if wp plugin is-installed "$p" >/dev/null 2>&1; then
		wp plugin delete "$p" >/dev/null
	fi
done

echo "==> WooCommerce ${WOOCOMMERCE_VERSION}"
if ! wp plugin is-installed woocommerce >/dev/null 2>&1; then
	wp plugin install woocommerce --version="${WOOCOMMERCE_VERSION}" --activate
else
	wp plugin activate woocommerce >/dev/null
fi

echo "==> Theme and custom plugin"
wp theme activate nova-store
wp plugin activate nova-store-core

echo "==> Seeding store configuration and catalog"
wp eval-file /seed/seed.php --user="${WP_ADMIN_USER}"

wp rewrite flush --hard >/dev/null
wp cache flush >/dev/null

echo
echo "Done. Storefront: ${WP_URL}"
echo "      Admin:      ${WP_URL}/wp-admin  (user: ${WP_ADMIN_USER}, password in .env)"
