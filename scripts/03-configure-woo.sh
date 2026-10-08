#!/bin/sh
# Configure WooCommerce for CellCraze: Sri Lanka / LKR, store pages,
# payment methods, and a Sri Lanka shipping zone. Idempotent.
set -eu

# --- Locale & currency ---
wp option update woocommerce_default_country "LK:*"
wp option update woocommerce_currency "LKR"
wp option update woocommerce_currency_pos "left_space"      # Rs 1,000
wp option update woocommerce_price_thousand_sep ","
wp option update woocommerce_price_decimal_sep "."
wp option update woocommerce_price_num_decimals "2"
wp option update woocommerce_weight_unit "kg"
wp option update woocommerce_dimension_unit "cm"
echo "    Locale set: Sri Lanka / LKR."

# --- Core store pages (shop, cart, checkout, my account) ---
wp wc --user="$WP_ADMIN_USER" tool run install_pages >/dev/null 2>&1 || \
  echo "    (store pages step skipped or already done)"

# --- Payments: Cash on Delivery (default for SL) + Direct bank transfer ---
wp option update woocommerce_cod_settings --format=json '{
  "enabled":"yes",
  "title":"Cash on Delivery",
  "description":"Pay with cash when your order is delivered.",
  "instructions":"Pay with cash upon delivery."
}'

wp option update woocommerce_bacs_settings --format=json '{
  "enabled":"yes",
  "title":"Direct Bank Transfer",
  "description":"Make your payment directly into our bank account. Use your Order ID as the payment reference.",
  "instructions":"Bank details are shown on the order confirmation and emailed to you."
}'
echo "    Payments enabled: Cash on Delivery + Direct bank transfer."

# --- Shipping: one "Sri Lanka" zone with flat rate + free-over-threshold ---
# Guard against duplicate zones on re-run.
ZONE_EXISTS="$(wp wc shipping_zone list --user="$WP_ADMIN_USER" --field=name 2>/dev/null | grep -c '^Sri Lanka$' || true)"
if [ "$ZONE_EXISTS" = "0" ]; then
  ZONE_ID="$(wp wc shipping_zone create --user="$WP_ADMIN_USER" --name="Sri Lanka" --porcelain)"
  wp wc shipping_zone_location update "$ZONE_ID" --user="$WP_ADMIN_USER" \
    --data='[{"code":"LK","type":"country"}]' >/dev/null

  # Flat rate (cost from env, default Rs 350).
  wp wc shipping_zone_method create "$ZONE_ID" --user="$WP_ADMIN_USER" \
    --method_id=flat_rate \
    --settings="{\"title\":\"Standard Delivery\",\"cost\":\"${SHOP_FLAT_RATE:-350}\"}" >/dev/null

  # Free shipping above the threshold (default Rs 10,000).
  wp wc shipping_zone_method create "$ZONE_ID" --user="$WP_ADMIN_USER" \
    --method_id=free_shipping \
    --settings="{\"title\":\"Free Delivery\",\"requires\":\"min_amount\",\"min_amount\":\"${FREE_SHIPPING_MIN:-10000}\"}" >/dev/null

  # Local pickup for in-store collection.
  wp wc shipping_zone_method create "$ZONE_ID" --user="$WP_ADMIN_USER" \
    --method_id=local_pickup \
    --settings='{"title":"Store Pickup","cost":"0"}' >/dev/null

  echo "    Shipping zone 'Sri Lanka' created (flat Rs ${SHOP_FLAT_RATE:-350}, free over Rs ${FREE_SHIPPING_MIN:-10000}, store pickup)."
else
  echo "    Shipping zone 'Sri Lanka' already exists — skipping."
fi

# Tax left off by default (enable in WooCommerce → Settings → Tax if registered).
wp option update woocommerce_calc_taxes "no"
