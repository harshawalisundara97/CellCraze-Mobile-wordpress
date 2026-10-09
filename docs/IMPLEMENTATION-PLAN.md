# CellCraze — Implementation Plan (awaiting approval)

**Date:** 2026-10-09 · Derived from [AUDIT-REPORT.md](AUDIT-REPORT.md),
[WORKFLOW-AUDIT.md](WORKFLOW-AUDIT.md), [INTEGRATION-DATA-MODEL.md](INTEGRATION-DATA-MODEL.md).
**No code or configuration has been changed.** Nothing below is implemented
until you approve specific phases.

Guiding rules: prefer native WooCommerce + the existing `cellcraze-core` plugin;
never edit third-party plugin files; additive DB changes only; back up before any
`ALTER`; keep order/payment/delivery/refund statuses separate; verify payments
server-side.

## Issue register

| ID | Module | Status | Priority | Business impact | Root cause (if confirmed) | Proposed solution | Depends on | Affected | Tests | Risk / rollback |
|---|---|---|---|---|---|---|---|---|---|---|
| CC-01 | Data/backup | MISSING (here) | Critical | No recovery if a change corrupts data | UpdraftPlus not installed | Install UpdraftPlus on live + first backup before any change | — | live wp-admin | Confirm restore works | Low; config only |
| CC-02 | Security | MISSING (here) | Critical | Store exposed at launch | Wordfence not installed | Install Wordfence + SSL on live | — | live | Login/scan | Low |
| CC-03 | IMEI integrity | **BROKEN (race)** | Critical | Same IMEI could be sold twice (web+POS concurrent) | `assign_order()`/REST read `status=available` then update without lock | Compare-and-set: `UPDATE wp_cellcraze_imei SET status='sold',order_id=? WHERE id=? AND status='available'`; check affected rows; wrap loop in txn | — | `cellcraze-core` orders + REST classes | Concurrency test #13 | Revert plugin version |
| CC-04 | IMEI flow | UNVERIFIED | Critical | Core feature may not work | Never exercised | Run end-to-end: add units→order→processing→assign→cancel→restore | CC-03 | — | Tests 8,10,14,15 | Test only, no change |
| CC-05 | Overselling | VERIFIED-config | High | Oversell if reservation off | — | Confirm `wc_reserved_stock` + hold minutes persist on live | — | Woo settings | Test #13 | Config |
| CC-06 | Purchasing→IMEI | MISSING | Critical | IMEI table has no real data source | No PO→unit path | Add a "Receive stock" screen in `cellcraze-core` (or ATUM hook) that creates IMEI rows + increments Woo stock + records unit cost/supplier/`purchase_id` | CC-03 | plugin, DB (additive col) | Test #8,9 | Additive; drop feature flag |
| CC-07 | Email | MISSING (here) | High | Order emails undelivered | WP Mail SMTP not installed | Install WP Mail SMTP + real sender on live | CC-01 | live | Order email delivers | Config |
| CC-08 | Payments | MISSING | High | No card payments | No gateway | Install PayHere/WebXPay; **server-side verification**; idempotent callbacks | CC-02 | live, gateway plugin | Tests 16,17 | Disable gateway |
| CC-09 | BACS proof | MISSING | High | Manual, error-prone bank verify | No slip upload | Add slip-upload field (My Account/checkout) in `cellcraze-core`; admin verify action | — | plugin | Test #16 | Remove field |
| CC-10 | Variants | MISSING | High | Phones can't offer storage/colour | All simple products | Model phones as variable products (attributes + variations) | brand taxonomy | data/import | Test #2 | Data only |
| CC-11 | Browse/filter | PARTIAL | Medium | Weak discovery | Simple attr brand; no filters | Register `pa_brand` taxonomy; add Woo filter blocks to `cc_shop_filters` | — | config/theme | Tests #2,3 | Config |
| CC-12 | POS | MISSING | High | No counter sales | No register | Select a WooCommerce POS plugin; wire to `cellcraze-core` channel+IMEI API | CC-03,CC-04 | new plugin | Tests 11,12 | Deactivate plugin |
| CC-13 | Roles | MISSING | Medium | No least-privilege for staff | Only admin/shop_manager | Add Cashier/Warehouse/Order-processing roles+caps in `cellcraze-core` | — | plugin | Test #25 | Remove roles on deactivate |
| CC-14 | Audit log | MISSING | Medium | No traceability | None | WP Activity Log (or custom log table) for stock/IMEI/role/payment | — | plugin/config | Test #26 | Disable |
| CC-15 | Returns/RMA + warranty | MISSING | Medium | No return path; warranty unscoped | None | RMA plugin or custom endpoint; per-order warranty view scoped to buyer (never expose others' IMEIs) | CC-04 | plugin | Test #15,7 | Disable |
| CC-16 | Courier/COD settlement | MISSING | Medium | No delivery/COD reconciliation | None | `cc_shipment` CPT (tracking, status, cod_amount, settled_at) | — | plugin, DB (CPT) | Tests 18,20 | Remove CPT |
| CC-17 | Finance/COGS/profit | MISSING | Medium | No real profit | No expense/COGS module | `cc_expense` CPT + COGS from `_cost`/unit cost on native orders; profit report; avoid double-count refunds/expenses | CC-06 | plugin | Tests 21-24 | Additive |
| CC-18 | Quick wins | MISSING | Low | Polish/compliance | — | Contact form + page; branded policy pages; LiteSpeed cache; Rank Math | — | config | Smoke | Config |

## Phased plan with acceptance criteria

**Essential for launch = Phases 0–4. Phases 5–7 = post-launch improvements.**

### Phase 0 — Safety net (CC-01, CC-02, CC-07)
*Live-host config you perform; I provide steps.*
- **Accept:** a restore-tested backup exists; Wordfence active + SSL; a test
  order email is received.

### Phase 1 — IMEI correctness (CC-03, CC-04, CC-05)
- Fix double-allocation (compare-and-set); prove allocation/restore end-to-end.
- **Accept:** concurrency test #13 never sells a unit twice; cancel restores the
  unit; `php -l` clean; no duplicate `order_id` per unit.

### Phase 2 — Stock data source (CC-06)
- Receiving screen creates IMEI units + increments Woo stock + records cost.
- **Accept:** receiving 3 IMEIs raises Woo stock by 3 and adds 3 `available`
  rows; duplicate IMEI rejected (test #9).

### Phase 3 — Checkout & payment correctness (CC-09, CC-08 live, CC-10, CC-11)
- Bank-slip upload + manual verify; variants; brand taxonomy + filters; (gateway
  on live with server-side verify).
- **Accept:** BACS order carries a slip admin can verify; variable phone
  selectable; brand/price filter narrows results; gateway (if live) only marks
  paid after server-side confirmation.

### Phase 4 — POS + sales integration (CC-12, CC-13)
- Integrate chosen POS plugin; staff roles.
- **Accept:** a POS sale decrements the same Woo stock, tags channel `pos`,
  assigns an IMEI (tests 11,12); cashier role limited to POS (test #25).

### Phase 5 — Delivery & COD (CC-16)
- **Accept:** shipment tracking + COD settlement reconciles collected vs orders
  (tests 18,20).

### Phase 6 — Returns, refunds, warranty (CC-15)
- **Accept:** return restocks or marks damaged; refund links to original order;
  customer sees only their own warranty/IMEI (test #15, privacy).

### Phase 7 — Finance, audit, polish (CC-17, CC-14, CC-18)
- **Accept:** profit = revenue − COGS − expenses with no double counting
  (tests 23,24); audit log captures stock/IMEI/role changes (test #26).

## Costs / conflicts to decide
- **ATUM PRO** (purchasing/PO/log) vs custom in `cellcraze-core` — recurring
  license vs build effort. Recommendation: custom receiving (CC-06) avoids the
  license for the core IMEI intake; keep ATUM free for supplier list + stock view.
- **POS plugin** (CC-12): wePOS (freemium) / Oliver (freemium) / FooSales —
  verify split-payment, barcode, offline, and recurring cost before choosing.
- **FacetWP** (filters) — optional; Woo filter blocks are free and likely enough.
- **RMA plugin** — evaluate free vs paid at Phase 6.

## Approval request
Please approve **which phases** to implement (recommend starting with Phases 1–2,
since Phase 0 is live-host config you do). I will implement **only** the approved
phase, follow the implementation rules, test with evidence, and report before
moving on. I will not expand scope or touch production without your go-ahead.
