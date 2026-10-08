#!/bin/sh
# Create the CellCraze product categories (idempotent).
set -eu

CATEGORIES="Phones Headphones Earphones Chargers Smartwatches Power-Banks Cases Cables Accessories"

for raw in $CATEGORIES; do
  # Display name: turn "Power-Banks" into "Power Banks".
  name="$(echo "$raw" | tr '-' ' ')"
  existing="$(wp wc product_cat list --user="$WP_ADMIN_USER" --field=name 2>/dev/null | grep -Fxc "$name" || true)"
  if [ "$existing" = "0" ]; then
    wp wc product_cat create --user="$WP_ADMIN_USER" --name="$name" >/dev/null
    echo "    + $name"
  else
    echo "    = $name (exists)"
  fi
done
echo "    Categories ready."
