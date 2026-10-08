#!/bin/sh
# Import sample products from data/products-sample.csv.
# Requires the wp-cli "product CSV import" command shipped with WooCommerce.
set -eu

CSV="/data/products-sample.csv"

if [ ! -f "$CSV" ]; then
  echo "    No CSV at $CSV — skipping product import."
  exit 0
fi

# wc_product_csv is provided by WooCommerce's CLI. Fall back gracefully.
if wp help wc_product_csv >/dev/null 2>&1; then
  wp wc_product_csv import "$CSV" --user="$WP_ADMIN_USER"
  echo "    Sample products imported."
else
  echo "    'wp wc_product_csv' not available in this image."
  echo "    Import manually: WP Admin -> Products -> Import -> upload data/products-sample.csv"
fi
