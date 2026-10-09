#!/bin/sh
# Install & activate all CellCraze plugins (idempotent — wp skips installed ones).
set -eu

# Requirement -> plugin (wordpress.org slugs), per the implementation guide.
PLUGINS="
woocommerce
atum-stock-manager-for-woocommerce
woocommerce-pdf-invoices-packing-slips
wp-mail-smtp
ti-woocommerce-wishlist
seo-by-rank-math
wordfence
updraftplus
litespeed-cache
"

for slug in $PLUGINS; do
  if wp plugin is-installed "$slug" 2>/dev/null; then
    wp plugin activate "$slug" 2>/dev/null || true
    echo "    $slug (already installed) activated."
  else
    wp plugin install "$slug" --activate
    echo "    $slug installed & activated."
  fi
done

# Activate the bundled custom plugin (IMEI tracking + POS/channel glue).
if wp plugin is-installed cellcraze-core 2>/dev/null; then
  wp plugin activate cellcraze-core 2>/dev/null || true
  echo "    cellcraze-core activated."
else
  echo "    cellcraze-core not found in wp-content/plugins — skipping (copy the folder there)."
fi

# NOTE: Sri Lankan card gateways (PayHere / WebXPay) and Stripe are configured
# on the LIVE host with real API keys — not in local dev. See README.
echo "    Done. Card gateway plugins are added live (see README)."
