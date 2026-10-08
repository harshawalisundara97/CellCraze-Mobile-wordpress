#!/bin/sh
# Install WordPress core (idempotent) and activate the CellCraze child theme.
set -eu

if wp core is-installed 2>/dev/null; then
  echo "    WordPress already installed — skipping core install."
else
  wp core install \
    --url="$SITE_URL" \
    --title="$SITE_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
  echo "    WordPress installed."
fi

# Keep the public URL consistent with the compose port mapping.
wp option update siteurl "$SITE_URL"
wp option update home "$SITE_URL"

# Storefront is the free, WooCommerce-native parent theme for our child theme.
if ! wp theme is-installed storefront 2>/dev/null; then
  wp theme install storefront
fi

# The cellcraze child theme is mounted from the repo; just activate it.
if wp theme is-installed cellcraze 2>/dev/null; then
  wp theme activate cellcraze
  echo "    CellCraze child theme active."
else
  echo "    WARNING: cellcraze theme not found (is the volume mounted?). Activating Storefront."
  wp theme activate storefront
fi
