#!/bin/sh
# CellCraze — one-command store bootstrap.
# Runs inside the `wpcli` container:
#   docker compose run --rm wpcli /scripts/setup.sh
#
# Idempotent: safe to re-run. Already-done steps are skipped.
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "==> [1/5] Install & configure WordPress core"
sh "$SCRIPT_DIR/01-install-wp.sh"

echo "==> [2/5] Install & activate plugins"
sh "$SCRIPT_DIR/02-install-plugins.sh"

echo "==> [3/5] Configure WooCommerce (Sri Lanka / LKR, payments, shipping)"
sh "$SCRIPT_DIR/03-configure-woo.sh"

echo "==> [4/5] Create product categories"
sh "$SCRIPT_DIR/04-create-categories.sh"

echo "==> [5/5] Import sample products"
sh "$SCRIPT_DIR/05-import-products.sh"

echo ""
echo "============================================================"
echo " CellCraze is ready."
echo "   Store:  ${SITE_URL}"
echo "   Admin:  ${SITE_URL}/wp-admin  (user: ${WP_ADMIN_USER})"
echo "============================================================"
