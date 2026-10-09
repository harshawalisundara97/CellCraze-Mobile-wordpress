# CellCraze — Integration & Data-Model Audit

**Date:** 2026-10-09 · Read-only. No DB changes or migrations run. Verified on
the local runtime (WP 7.1.3, WooCommerce 11.2.0, **HPOS enabled**) + repo code.
Companion to [AUDIT-REPORT.md](AUDIT-REPORT.md) and
[WORKFLOW-AUDIT.md](WORKFLOW-AUDIT.md).

---

## Part 1 — End-to-end integration traces

Status: `WORKS` (code path complete, native or verified) · `PARTIAL` ·
`BROKEN` · `MISSING`.

| # | Workflow | Status | Notes / evidence |
|--:|---|---|---|
| 1 | Purchase → receive → IMEI register → availability | **MISSING link** | No PO→IMEI path. IMEI rows only via manual product tab. Woo stock and `wp_cellcraze_imei` can drift because nothing writes units at receiving. |
| 2 | Checkout → order → reservation → payment → IMEI alloc → dispatch | PARTIAL | Order+reservation native (`wc_reserved_stock`). IMEI alloc hooked to `processing`/`completed` (unproven). Dispatch manual. |
| 3 | POS sale → payment → IMEI assign → stock update → receipt | PARTIAL (no UI) | API exists (`/imei/assign`, `/channel`); stock decremented by Woo order. No register/receipt. |
| 4 | Online + POS share one inventory | WORKS (by design) | Single Woo stock pool; POS orders are native Woo orders. No second stock store. ✅ Correct source of truth. |
| 5 | Bank transfer → verify → process | PARTIAL | BACS → on-hold; admin marks paid manually. No slip upload; manual verification only. |
| 6 | Gateway payment → server-side verify → status | MISSING | No gateway installed. **Must** verify server-side (IPN/webhook), never trust client redirect. |
| 7 | COD → ship → delivery → collection → settlement | MISSING | No courier/COD settlement model. |
| 8 | Return → IMEI verify → inspect → restock/damaged → refund | PARTIAL | IMEI restore on refund wired; no RMA UI, no inspect/damaged workflow entry. |
| 9 | Supplier return → stock adjust → supplier balance | MISSING | No supplier ledger. |
| 10 | Sales/POS → COGS → expenses → profit | MISSING | `_cost` captured; no COGS/expense/profit computation. |

## Part 2 — Consistency & concurrency risk register

| Risk | Present? | Cause / evidence | Impact | Proposed fix |
|---|---|---|---|---|
| Duplicate/parallel stock sources | **Low** | One Woo stock pool; IMEI table tracks *units*, not a second count. | — | Keep Woo stock as source of truth; IMEI table is a *ledger of units*, not a stock counter. |
| Overselling on concurrent buys | **Mitigated (Woo)** | `wc_reserved_stock` + `hold_stock_minutes=60`. | — | Verify setting persists live; keep `manage_stock=yes`. |
| **IMEI assigned to >1 active sale** | **Possible** | `assign_order()` and REST `assign` both `mark_sold`; FIFO reads `status=available`. No DB transaction/row lock around read-then-update. Two simultaneous orders could grab the same unit between SELECT and UPDATE. | Wrong unit shipped / audit breaks. | Wrap selection+update in a transaction or `UPDATE ... WHERE status='available' LIMIT 1` with affected-rows check (compare-and-set). **Fix before POS + web run together.** |
| Order marked paid without verified payment | **Future risk** | No gateway yet. | Revenue leak. | Server-side verification only (trace #6). |
| Duplicate webhook/callback | **Future risk** | No gateway yet. | Double payment/stock. | Idempotency key on gateway txn id. |
| Incorrect stock restore on cancel/return | **Unverified** | `restore_order()` restores IMEI + clears item meta; Woo restores stock. Not exercised. | Phantom stock. | Test cancel/refund end-to-end. |
| Duplicate sales/refunds in reports | **N/A yet** | No finance module. | — | Build COGS/expense on native orders/refunds only. |
| Missing audit logs | **Yes** | No activity log plugin. | No traceability. | Add WP Activity Log (or custom) for stock/IMEI/role changes. |
| Broken links purchase↔unit↔order↔payment↔refund | **Partial** | `wp_cellcraze_imei.order_id` links unit→order; no purchase_id or payment/refund link. | Incomplete traceability. | Add `purchase_id`/receiving ref to IMEI; rely on native order refunds for payment linkage. |

**Recommended source of truth:** native WooCommerce **orders** (`wp_wc_orders`)
and **products/stock** (postmeta + `wc_product_meta_lookup`) for all money and
stock. The custom `wp_cellcraze_imei` table is the **only** justified custom
store — it tracks *individual physical units*, which WooCommerce cannot model.
Do **not** introduce a parallel stock count.

## Part 3 — Database / data model

### Confirmed existing structures (evidence from live DB)

| Entity | Storage | Key | Relationships / status fields |
|---|---|---|---|
| Products | `wp_posts` (`product`) + `wp_postmeta` | post ID | `_sku`,`_stock`,`_price`,`_cost`,`_manage_stock`,`_low_stock_amount` (10 each, verified) |
| Product lookup (fast) | `wp_wc_product_meta_lookup` | product_id (PK) | sku, stock_quantity, stock_status, min/max_price |
| Variations | `wp_posts` (`product_variation`) | post ID | **none yet** (no variable products) |
| Categories/attributes | `wp_terms`/`term_taxonomy` (`product_cat`, `pa_*`) | term_id | Brand currently simple attr, not taxonomy |
| Orders | **`wp_wc_orders`** (HPOS) + `wp_wc_orders_meta` | order ID | status, total; channel in `_cellcraze_channel` meta |
| Order items | `wp_woocommerce_order_items`/`itemmeta` | item ID | `_cellcraze_imeis` per item (code) |
| Stock reservation | `wp_wc_reserved_stock` | (order_id, product_id) PK | stock_quantity, expires |
| **IMEI units** | **`wp_cellcraze_imei`** | id (PK), **UNIQUE imei** | product_id, variation_id, status(available/reserved/sold/damaged/returned), order_id, cost, warranty_months, supplier, received_at, sold_at. Indexes on imei(UNIQUE), product_id, status, order_id — **verified**. |
| Payments | native order payment meta | order ID | no separate txn table yet |
| Suppliers | ATUM | ATUM CPT | free tier |
| Shipments/courier/settlement | — | — | **MISSING** |
| Returns/refunds | native Woo refunds | refund ID | RMA MISSING |
| Expenses | — | — | **MISSING** |
| COGS | `_cost` meta + IMEI.cost | — | not computed |
| Roles | `wp_options` (`wp_user_roles`) | role slug | admin/shop_manager/customer |
| Audit log | — | — | **MISSING** |
| Warranty | IMEI.warranty_months | IMEI id | no expiry calc |

### Recommended additions (NOT yet created — pending approval)

Fit the existing model; add only what WooCommerce can't represent:

1. **IMEI table — extend** (not replace): add `purchase_id` (BIGINT, link to
   receiving) and a `warranty_expires` DATETIME (computed at sale). Add a
   partial compare-and-set on assignment to prevent double-allocation.
2. **Expenses** — a CPT `cc_expense` or table `wp_cellcraze_expense`
   (id, date, category, amount, payee, order/supplier ref, note). CPT preferred
   (native admin, revisions, caps).
3. **Supplier payments / balance** — table `wp_cellcraze_supplier_ledger`
   (id, supplier_id, type debit/credit, amount, ref, date). Only if ATUM PRO
   not adopted.
4. **Courier shipments / COD settlement** — CPT `cc_shipment`
   (order_id, courier, tracking_no, status, cod_amount, settled_at) + settlement
   grouping. Only when courier phase starts.

**Constraints/indexes to enforce:** keep `imei` UNIQUE; index foreign keys;
use `dbDelta` for creation (already done for IMEI); wrap unit allocation in a
transaction.

### Migration plan (when approved)
- All additions are **additive** — no destructive migration. No existing
  customer/order data touched.
- IMEI table already created by plugin activation (`dbDelta`), idempotent.
- New columns via guarded `ALTER TABLE` inside the plugin's versioned installer
  (bump `cellcraze_core_version`), reversible by ignoring the columns.
- CPTs need no migration (WordPress-native).
- **Backup required before any `ALTER`** (UpdraftPlus or host snapshot).

### Integration map (source of truth)
```
                 ┌───────────────────────── WooCommerce (SOURCE OF TRUTH) ─────────────────────────┐
                 │  Products/stock (postmeta + meta_lookup)   Orders (wp_wc_orders, HPOS)  Refunds  │
                 └───────▲───────────────▲──────────────────────────▲───────────────▲───────────────┘
   Supplier/PO           │ stock +/-     │ reserve (wc_reserved_stock)│ payment meta  │ refund link
   (ATUM) ──receive──────┘               │                            │               │
        │ (MISSING link)                 │                            │               │
        ▼                                │                            │               │
   wp_cellcraze_imei  ◄── assign on processing/POS ──┘  channel meta _cellcraze_channel (online|pos)
   (units: UNIQUE imei, status, order_id, cost, warranty)
        │
        └── reports: COGS from _cost/unit cost  +  (MISSING) expenses → profit
```

## Prioritized technical remediation
1. **IMEI double-allocation guard** (compare-and-set) — correctness, do before
   POS+web concurrent use.
2. **Prove IMEI allocation/restore** end-to-end (test, not code).
3. **PO → IMEI intake** link (the missing data source).
4. **Audit log** for stock/IMEI/role/payment changes.
5. **Finance model** (expenses CPT + COGS on native orders) — additive.
6. Courier/COD settlement model — later phase.

> No migrations or code changes will run until you approve
> [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md).
