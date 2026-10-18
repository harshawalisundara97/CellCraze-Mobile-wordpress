# CellCraze — Master Implementation Plan

**Updated:** 2026-10-16 (Phase 0). Consolidates and supersedes the planning in
[IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md); reuses the audits
([AUDIT-REPORT.md](AUDIT-REPORT.md), [WORKFLOW-AUDIT.md](WORKFLOW-AUDIT.md),
[INTEGRATION-DATA-MODEL.md](INTEGRATION-DATA-MODEL.md)). Companion docs:
[FEATURE-TRACEABILITY.md](FEATURE-TRACEABILITY.md),
[WORKFLOW-MAP.md](WORKFLOW-MAP.md), [TEST-STRATEGY.md](TEST-STRATEGY.md).

## 1. Vision & scope
One WordPress + WooCommerce platform serving **online store + admin/ops + in-shop
POS** on a **single shared source of truth**: WooCommerce for stock, orders and
money; the custom `cellcraze-core` plugin only for what Woo can't model
(individual IMEI units, channel tagging, bank-slip verification, staff roles,
and — to be built — expenses/audit/courier). **No parallel inventory or sales
stores.** In scope: parts 3–7 of the brief. Out of scope unless approved: full
accounting/statutory tax, multi-branch, loyalty, SMS/WhatsApp.

## 2. Architecture guardrails
- Source of truth: `wp_wc_orders` (HPOS) + product stock (postmeta +
  `wc_product_meta_lookup`) + `wc_reserved_stock`. Custom table: `wp_cellcraze_imei`.
- Custom logic lives in `cellcraze-core`; **never edit Woo/WP core or 3rd-party
  plugin files**; use hooks/REST/extension points.
- Every stock movement must carry a reason/reference; critical IMEI changes use
  compare-and-set/transactions (pattern established in CC-03).
- Additive, reversible DB migrations only; back up before any `ALTER`.

## 3. Phase roadmap (adjusted from the brief by verified dependencies)
Phases 1 foundations are **largely done** (IMEI integrity, receiving, roles,
bank-slip) — do **not** rebuild. Reordered so the *stock/finance ledger* lands
before POS and reports, because POS and profit both depend on it.

| Phase | Theme | State |
|---|---|---|
| 0 | Audit + master plan | **This document** |
| 1 | Data integrity, IMEI, roles, security foundation | **Mostly VERIFIED** (CC-03/04/06/09/13); remaining: audit log, stock-movement ledger |
| 2 | Storefront design system, catalogue, search, PDP | Storefront done; **variants + filters** remain |
| 3 | Cart/checkout/order lifecycle/payments/bank-transfer | COD/BACS+slip done; **gateway BLOCKED**, server-side revalidation audit |
| 4 | Inventory ledger, IMEI lifecycle, reconciliation | CC-04/06 done; **movement ledger + reconciliation** remain |
| 5 | Suppliers, PO, GRN, partial receiving, valuation | **decision: ATUM PRO vs custom**; CC-06 is the intake base |
| 6 | POS, payment recording, invoices, receipts | **BLOCKED on POS plugin choice**; API ready |
| 7 | Returns, refunds, warranty | **MISSING** (RMA approach decision) |
| 8 | Expenses, COGS, profit reporting | **MISSING** (accounting defs first) |
| 9 | Promotions/coupons/retention | Woo coupons; extras optional |
| 10 | Permissions (done), audit logs, notifications | roles done; **audit log next**, SMTP BLOCKED |
| 11 | A11y, performance, security review, regression, deploy | ongoing |

## 4. Acceptance criteria (per phase) — see TEST-STRATEGY.md
Each phase closes only when: business logic + authorization + data integrity +
tests verified (not "code written"). Phase 1/CC items already meet this with
this session's evidence.

## 5. Risks & unresolved decisions
See §"Open business decisions" below; plus: nonce/session caveats for CLI tests,
Nginx `.htaccess` limitation (gated endpoints are the real control), HPOS
compatibility (declared), and missing historical cost → profit must flag gaps.

## 6. Open business decisions (BLOCKERS — need your input)
| # | Decision | Why it blocks | Options |
|---|---|---|---|
| D1 | **Online payment gateway** | PAY-03, Phase 3/6 | PayHere / WebXPay / Stripe (+ live keys, live host) |
| D2 | **POS plugin** | POS-01, Phase 6 | wePOS / Oliver / FooSales (freemium vs paid) vs custom |
| D3 | **Purchasing: ATUM PRO vs custom** | Phase 5 | PRO license vs build PO/GRN in cellcraze-core |
| D4 | **Courier integration** | DEL-02, Phase 7 | Pronto/Koombiyo/none; manual tracking field |
| D5 | **Return/refund (RMA) approach** | Phase 7 | plugin vs custom endpoint; policy content |
| D6 | **Profit/COGS methodology** | Phase 8 | per-unit actual cost (IMEI.cost) vs `_cost` average; what counts as expense |
| D7 | **Tax** | Phase 3 | VAT-registered? currently off |
| D8 | **Warranty policy** | WAR-01 | period per category; claim workflow scope |
| D9 | **Multi-branch** | inventory | single shop assumed; confirm |
| D10 | **SMS/WhatsApp notifications** | Phase 10 | Notify.lk/Twilio cost + consent |

## 7. First 5 recommended tasks (detail in §Task breakdown)
Chosen to be code-only, unblocked, independently testable, and dependency-ordered.

| Task | Title | Phase | Effort | Depends |
|---|---|---|---|---|
| **T-101** | Stock-movement ledger + audit-log foundation (`wp_cellcraze_audit`) | 1/10 | M | — |
| **T-102** | Wire IMEI/receiving/bank-slip/roles events into the audit log | 1/10 | S | T-101 |
| **T-103** | Expense module (`cc_expense` CPT) + capability + receipt upload | 8 | M | CC-13 |
| **T-104** | Accounting definitions doc + COGS/profit report (read-only) | 8 | L | T-103, D6 |
| **T-105** | Variable-product template for phones (storage/colour) + brand taxonomy + filters | 2 | M | D-none (sample data) |

### Task detail (per PART 12)
**T-101 — Stock-movement + audit ledger.** *Objective:* every stock/IMEI/money
event has an immutable record (reason, reference, actor, timestamp). *Scope:* new
`wp_cellcraze_audit` table + `Cellcraze_Audit::log()` API; admin read-only
viewer (WooCommerce submenu, `manage_woocommerce`). *Inspect:* CC-03/04/06 write
points. *DB:* additive table via dbDelta. *Security:* no PII/secrets; append-only;
cap-gated viewer. *Acceptance:* a receive/sale/cancel writes exactly one row each
with actor+ref; viewer lists newest-first; no duplicate rows on hook re-fire.
*Tests:* unit (log + dedupe), integration (receive/assign/restore produce rows).
*Regression:* IMEI flow unchanged. *DoD:* tested green, traceability updated.

**T-102 — Emit events.** Hook `Cellcraze_Audit::log()` into existing CC-03/04/06/09/13
transitions. S. Depends T-101. *Acceptance:* IMEI assign, restore, receive,
slip-verify, role change each log once.

**T-103 — Expenses.** `cc_expense` CPT (date, category, amount, payee, method,
reference, receipt upload — reuse CC-09 private-upload pattern), `edit` gated to
manager/owner. M. *Acceptance:* create/list/filter by date; receipt stored
privately; audit-logged; CSV export.

**T-104 — Profit report.** First write accounting definitions (gross/net sales,
COGS from IMEI.cost or `_cost`, expenses, refunds — no double count), then a
read-only report. L. Depends D6. *Acceptance:* numbers reconcile to a hand-checked
fixture; missing-cost items flagged, not guessed.

**T-105 — Variants + filters.** Register `pa_brand` taxonomy, convert 1–2 phones
to variable (storage/colour), add Woo filter blocks to `cc_shop_filters`. M.
*Acceptance:* PDP variant selector drives price/stock; brand/price filter narrows
results; customer stock = available units.

## 8. Dependency graph (first tasks)
```
T-101 ──▶ T-102
CC-13 ──▶ T-103 ──▶ T-104 ◀── D6
(independent) T-105
```
Parallelizable: T-105 is independent of T-101/102/103. Sequential: T-102 after
T-101; T-104 after T-103 + D6.

## 9. What is explicitly NOT being rebuilt
CC-03, CC-04, CC-06, CC-09, CC-13 are verified; storefront theme (Task 3) is done.
Native Woo capabilities (orders, products, coupons, analytics, refunds) are used
as-is, not re-implemented.
