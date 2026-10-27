#!/usr/bin/env bash
# Runs on YOUR machine. Uploads the theme, plugin and seed data to alwaysdata
# over SSH, then runs deploy/alwaysdata-install.sh there.
#
#   ALWAYSDATA_ACCOUNT=myaccount ADMIN_EMAIL=you@example.com ./deploy/alwaysdata-push.sh
#
# Beside an existing site in ~/www (free plan: one account, one address):
#   ALWAYSDATA_ACCOUNT=myaccount DOCROOT=www/nova SITE_URL=https://myaccount.alwaysdata.net/nova ADMIN_EMAIL=... ./deploy/alwaysdata-push.sh
#
# One-time, before the first push (passwords go into mode-600 files on the server):
#   printf '%s' 'DB_PASSWORD'    | ssh myaccount@ssh-myaccount.alwaysdata.net 'umask 077; cat > ~/.dbpass'
#   printf '%s' 'ADMIN_PASSWORD' | ssh myaccount@ssh-myaccount.alwaysdata.net 'umask 077; cat > ~/.adminpass'
set -euo pipefail

cd "$(dirname "$0")/.."

ACCOUNT="${ALWAYSDATA_ACCOUNT:?set ALWAYSDATA_ACCOUNT to your alwaysdata account name}"
HOST="${SSH_HOST:-${ACCOUNT}@ssh-${ACCOUNT}.alwaysdata.net}"

echo "==> Uploading code to ${HOST}:~/nova-src"
tar -czf - wp-content/themes/nova-store wp-content/plugins/nova-store-core seed \
	| ssh "$HOST" 'rm -rf ~/nova-src && mkdir -p ~/nova-src && tar -xzf - -C ~/nova-src'
scp -q deploy/alwaysdata-install.sh "${HOST}:~/nova-install.sh"

echo "==> Running installer"
# Only non-secret settings are passed; passwords stay in the server's files.
ssh "$HOST" "ALWAYSDATA_ACCOUNT='${ACCOUNT}' ADMIN_EMAIL='${ADMIN_EMAIL:-}' ADMIN_USER='${ADMIN_USER:-admin}' \
	SITE_URL='${SITE_URL:-}' DB_NAME='${DB_NAME:-}' DOCROOT='${DOCROOT:-www}' RESEED='${RESEED:-0}' \
	bash -c 'for v in SITE_URL DB_NAME; do [ -n \"\${!v}\" ] || unset \$v; done; bash ~/nova-install.sh'"
