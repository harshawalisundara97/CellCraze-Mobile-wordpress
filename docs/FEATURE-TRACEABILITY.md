# CellCraze — Feature Traceability Matrix

**Updated:** 2026-10-16 · Phase 0 verification. Supersedes the status columns of
[MODULE-REVIEW.md](MODULE-REVIEW.md) (which was unreliable — it listed
`cellcraze-core` as built before it existed).

Status legend: **VERIFIED** (implemented + tested first-hand this session) ·
**PARTIAL** · **MISSING** · **BLOCKED** (needs a business decision/external
access) · **NOT-VERIFIED** (present but not exercised) · **CONFIG** (native
Woo/plugin config, not code).

## Verified this session (with test evidence)

| ID | Feature | Status | Code | Evidence |
|---|---|---|---|---|
| CC-03 | Atomic IMEI claim (no double-allocation) | **VERIFIED** | `class-cellcraze-imei-db.php::mark_sold` (compare-and-set), `class-cellcraze-rest.php` (409 on race) | Test T13: 2nd concurrent claim = false; 8/8 Phase-1 checks |
| CC-04 | IMEI lifecycle: receive→order→processing→assign→cancel→restore | **VERIFIED** | `class-cellcraze-imei-orders.php` | 8/8 Phase-1 checks (assign 2/2, stock −2, restore) |
| CC-06 | Receiving ↔ Woo stock sync | **VERIFIED** | `class-cellcraze-imei-admin.php::receive/adjust_stock` | 6/6 Phase-2 checks (receive 3 → stock +3; dup no inflate) |
| CC-09 | Bank-slip upload & verification | **VERIFIED** | `class-cellcraze-bankslip.php` *(untracked local)* | 10/10 browser/HTTP scenarios (403 gates, verify fires IMEI once, idempotent) |
| CC-13 | Custom staff roles + server-side caps | **VERIFIED** | `class-cellcraze-roles.php` | 5/5 checks (caps present, cashier least-priv) |
| — | Channel tagging (online/pos) | NOT-VERIFIED | `class-cellcraze-channel.php` | Code present; no orders exercised the column |

## Customer storefront

| ID | Feature | Status | Notes |
|---|---|---|---|
| CUS-01 | Homepage (Modernist) | VERIFIED | `front-page.php`, HTTP 200 |
| CUS-02 | Catalogue / categories (9) | VERIFIED | shop + category pages 200; "Coming soon" mode disabled |
| CUS-03 | Product detail (specs/stock/brand) | VERIFIED | PDP hooks render |
| CUS-04 | Search | CONFIG | Woo core search |
| CUS-05 | Filters / sorting | PARTIAL | Woo sort only; no attribute/price filters wired |
| CUS-06 | Variants (storage/colour) | MISSING | all sample products `simple`; attributes exist |
| CUS-07 | Cart / checkout / guest | VERIFIED | pages 200; guest checkout on |
| CUS-08 | Accounts / orders / addresses | VERIFIED | Woo My Account |
| CUS-09 | Stock reflects available units | PARTIAL | Woo stock = source of truth; CC-06 keeps it synced for serialized |
| CUS-10 | Wishlist | CONFIG | TI Wishlist active |
| CUS-11 | Coupons / sale price | CONFIG | Woo core (sale prices set on imports) |
| CUS-12 | Order tracking timeline | NOT-VERIFIED | `cellcraze_order_timeline()` |
| CUS-13 | Bank-slip upload (customer) | VERIFIED | CC-09 |
| CUS-14 | Contact / FAQ / About pages | MISSING | only default Privacy + Returns pages |
| CUS-15 | Policy pages (returns/warranty/terms) | PARTIAL | default pages exist, unbranded/unlinked |
| CUS-16 | Returns/refund requests (customer) | MISSING | no RMA |
| CUS-17 | Related / recently-viewed | PARTIAL | related = Woo core; recently-viewed missing |

## Admin / operations

| ID | Feature | Status | Notes |
|---|---|---|---|
| ADM-01 | Order management | CONFIG | Woo admin (HPOS) |
| ADM-02 | Product management | CONFIG | Woo products |
| ADM-03 | IMEI registry + product panel | NOT-VERIFIED | admin UI present (CC-03 stack); not click-tested |
| ADM-04 | Receiving (IMEI + stock) | VERIFIED | CC-06 |
| ADM-05 | Channel column (online/POS) | **VERIFIED** | `class-cellcraze-channel.php`: `set()` now re-fetches a fresh order instance so an explicit channel wins over the `woocommerce_new_order` default (stale-instance clobber fixed). Verified: pos persists, default online, override both ways, and T-104 report channel filter returns 1 POS / 2 all |
| ADM-06 | Bank-transfer verify UI | VERIFIED | CC-09 metabox |
| ADM-07 | Staff roles/permissions | VERIFIED | CC-13 |
| ADM-08 | Dashboard (real KPIs) | **VERIFIED** | `class-cellcraze-dashboard.php`: read-only, real data only — today/month net, gross+operating profit (via `Reports::compute`), online vs POS, orders processing, awaiting bank verification, low-stock, available IMEI, **purchasing (draft/ordered/partial POs, active suppliers, GRN-7d via cheap grouped aggregates)**, needs-attention tables + quick links. Gated `cellcraze_view_reports`. HTTP-verified figures reflect real PO/supplier/stock records |
| ADM-09 | Suppliers | **VERIFIED** | `class-cellcraze-suppliers.php`: `wp_cellcraze_supplier` table; CRUD + soft deactivate (never hard-deleted, preserves history); validation + cap `cellcraze_receive_stock` |
| ADM-10 | Purchase orders / GRN | **VERIFIED** | `class-cellcraze-purchasing.php`: `wp_cellcraze_po`/`_po_item`/`_grn`; draft→ordered→partial→received→cancelled; GRN partial receiving reuses `IMEI_Admin::receive()` (IMEI) + `wc_update_product_stock` (qty) — no competing count; UNIQUE grn_token idempotency; over-receive guard; IMEI tagged `po_id`. E2E verified: stock 20→22/48→51, idempotent re-submit no-op, over-receive blocked |
| ADM-11 | Inventory valuation / movement log | PARTIAL | `_cost`/unit cost captured; **stock-movement ledger built (T-101)**; valuation report still to build |
| ADM-16 | Stock-movement + audit ledger (`wp_cellcraze_audit`) | **VERIFIED** | `class-cellcraze-audit.php` — T-101: receive(+1/unit), imei_assign/restore (status), order_stock_reduce(-1)/restore(+1); idempotent (UNIQUE event_key + INSERT IGNORE); assign/restore keys include the assignment epoch (sold_at) so retries dedupe but distinct lifecycles log separately. Read-only viewer gated `manage_woocommerce` (guest→302). DB-verified schema; T-101 8/8 + review 8/8 (incl. retry-no-dup & event-identity regressions) |
| ADM-12 | Expenses | **VERIFIED** | `class-cellcraze-expense.php` (T-103): `wp_cellcraze_expense` table; CRUD + date/category filters; server validation (date/category/method/amount/lengths); receipts in private dir (public 403, gated admin 200, guest blocked); soft-delete (void, row retained); cap `cellcraze_manage_expenses` (admin+manager only, cashier excluded); create/update/void logged to T-101 ledger. Part A 9/9 + Part B HTTP CRUD/receipt all green |
| ADM-13 | Profit/COGS reports | **VERIFIED** | `class-cellcraze-cogs.php` + `class-cellcraze-reports.php` (T-104): order-item COGS snapshot (serialized live from IMEI table w/ auto-reversal on restock; accessory qty×`_cost` frozen); Profit Report page + CSV, cap `cellcraze_view_reports` (admin+manager); ex-tax; recognized sales = processing/completed. Math verified: net/COGS/gross-profit/opex/operating-profit, status exclusions, date boundaries, snapshot-frozen, idempotent. Channel filter verified working (ADM-05 tagging fixed) |
| ADM-14 | Audit log | PARTIAL | **Stock/IMEI (T-101) + bank-slip verification + staff role-change (T-102) logged**; price-change/expense events pending later tasks |
| ADM-15 | Analytics (sales) | CONFIG | Woo Analytics |

## POS / payments / delivery / after-sales

| ID | Feature | Status | Notes |
|---|---|---|---|
| POS-01 | Register UI | BLOCKED | plugin choice needed; integration API (IMEI/channel) built |
| POS-02 | IMEI at counter (API) | VERIFIED | REST `imei/available`+`assign` (401/409 gated) |
| PAY-01 | COD | CONFIG | enabled |
| PAY-02 | Bank transfer (BACS) | CONFIG+CC-09 | enabled; slip verify built |
| PAY-03 | Online gateway | BLOCKED | PayHere/WebXPay keys + live host |
| PAY-04 | POS card recording | BLOCKED | depends POS-01 |
| DEL-01 | Shipping zone (LK) | CONFIG | Rs350 / free>10k / pickup |
| DEL-02 | Courier tracking / COD settlement | MISSING | model needed |
| RET-01 | Returns/RMA | MISSING | plugin vs custom decision |
| RET-02 | Refund→payment linkage | NOT-VERIFIED | Woo refunds; IMEI restore wired |
| WAR-01 | Warranty period per product + lookup | PARTIAL | months per unit; no expiry/lookup UI |

## Cross-cutting

| ID | Feature | Status | Notes |
|---|---|---|---|
| SEC-01 | Nonces/caps/sanitize/escape on custom code | VERIFIED | CC-09/CC-13 reviewed |
| SEC-02 | Wordfence / SSL | BLOCKED | live host |
| OPS-01 | SMTP email delivery | BLOCKED | WP Mail SMTP + sender |
| OPS-02 | Backups (UpdraftPlus) | BLOCKED | live host |
| OPS-03 | SEO (Rank Math) | CONFIG | not installed locally |
| OPS-04 | Performance cache (LiteSpeed) | CONFIG | not installed locally |

> "Next action" for each MISSING/PARTIAL item is tracked in
> [MASTER-IMPLEMENTATION-PLAN.md](MASTER-IMPLEMENTATION-PLAN.md).
