# CellCraze Core

Custom WooCommerce back-office glue for the CellCraze phone shop. Delivers the
parts the module brief has no clean plugin for:

1. **IMEI / serial tracking** — every physical unit is one row in
   `wp_cellcraze_imei` (UNIQUE `imei`), with status, cost, warranty, supplier.
2. **Order IMEI assignment** — FIFO auto-assign when an order hits
   *processing* / *completed*; restore to stock on *cancelled* / *refunded*.
   IMEIs show on the admin order, the customer order view, and order emails.
3. **Sales-channel tagging** — every order is tagged `online` or `pos`
   (`_cellcraze_channel`) with a column in the orders list, so reports can
   split web sales from counter sales.
4. **REST API (`cellcraze/v1`)** for a POS plugin / integrations.

## Requirements

- WordPress 6.0+, PHP 7.4+, WooCommerce 8.0+ (HPOS-compatible).

## Install

Copy this folder to `wp-content/plugins/cellcraze-core/` and activate it, or:

```bash
wp plugin activate cellcraze-core
```

Activation creates/updates the `wp_cellcraze_imei` table via `dbDelta`.

## Using it

- **Add units:** edit a product → **IMEI / Serial** tab → paste one or many
  IMEIs (newline/comma separated), set cost + warranty, **Add unit**.
- **Registry:** WooCommerce → **IMEI Registry** (search all units).
- **Orders:** move a test order to *Processing* and the oldest available units
  are assigned automatically; cancel/refund to restore them.

## REST API

Authenticate as a `manage_woocommerce` user (WooCommerce REST keys or an
application password).

| Method | Route | Body / Query | Purpose |
|---|---|---|---|
| GET  | `/wp-json/cellcraze/v1/imei/available` | `product_id`, `variation_id?` | List available units. |
| POST | `/wp-json/cellcraze/v1/imei/assign` | `imei`, `order_id` | Hand-pick a unit at the counter. |
| POST | `/wp-json/cellcraze/v1/channel` | `order_id`, `channel` (`online`\|`pos`) | Tag an order's channel. |

## Data safety

Uninstalling does **not** drop the IMEI table unless you define
`CELLCRAZE_CORE_DROP_DATA` as `true` in `wp-config.php` first.

## Linting

Run `php -l` on each PHP file on a machine with PHP before committing changes
(the project convention). No PHP runtime was available where these files were
authored.
