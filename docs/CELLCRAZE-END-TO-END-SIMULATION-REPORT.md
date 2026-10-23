# CellCraze — End-to-End Business Workflow Simulation & Feature-Gap Audit

**Date:** 2026-10-23 · **Auditor:** Claude Code (QA/BA/WooCommerce/POS/inventory)
**Repo:** `harshawalisundara97/CellCraze-Mobile-wordpress` · **Branch:** `main` · **HEAD:** `d58cea5`
**This report is the main source of truth for the audit.** No application code, DB, config, or Git
history was changed in producing it (confirmed in §13).

---

## 1. Environment & test limitations (read first)

| Item | Finding |
|---|---|
| Session | **Cloud clone** (Linux container), not the Windows/XAMPP laptop. |
| Working tree | `main` @ `d58cea5`, **clean**. |
| Running website | **None reachable.** No web server in this env; the live site `phoneshop.nuvirahub.com` cannot be reached from here. |
| Browser automation | **Unavailable** — no Chromium/Chrome installed, and no site to drive. **All UI simulations are NOT RUN.** |
| Database | **Unavailable** — no MySQL, port 3306 not listening, no DB container. **All DB-state checks are BLOCKED.** (The laptop's MariaDB Aria/`mysql.plugin` issue can only be diagnosed on the laptop.) |
| Laptop-only inputs | The laptop's **uncommitted GRN diff** in `class-cellcraze-purchasing.php` and docs `CELlCRAZE-EXISTING-FEATURES-AUDIT.md`, `CELLCRAZE-FEATURE-GAP-IMPLEMENTATION-PLAN.md`, `F16-IMPLEMENTATION-KICKOFF.md` are **not present in this clone** and were not inspected. |

**Method actually used:** rigorous **source-code audit** of the committed `cellcraze-core` plugin
and `cellcraze` theme, cross-referenced with `docs/FEATURE-TRACEABILITY.md`,
`docs/CELLCRAZE-MASTER-IMPLEMENTATION-ROADMAP.md`, and the task specs. Findings are labelled
**CONFIRMED** (proven in source), **SUSPECTED** (needs runtime), or **BLOCKED** (needs DB/UI).
No workflow is marked "passed" — nothing was executed at runtime.

No credentials, secrets, or customer data were accessed or exposed.

## 2. Workflow-by-workflow results (source-based)

- **Catalogue/PDP/cart/checkout/accounts** — present in theme + Woo core (CUS-01..13). Variants
  not built (products `simple`); storefront brand/price/storage **filters not wired** (sort only).
- **Payments / bank-slip** — COD + BACS (Woo) + custom **bank-slip upload/verify**
  (`class-cellcraze-bankslip.php`): nonce, ownership, size, extension + real-MIME, private gated
  store, **slip ≠ paid**, idempotent verify. **Strong.** No online card gateway.
- **Serialized IMEI** — `wp_cellcraze_imei` with states available/reserved/sold/damaged/returned;
  **atomic compare-and-set claim**; idempotent, concurrency-safe FIFO allocation; audit-logged.
  **Strong.**
- **Accessory qty** — WooCommerce stock (single writer). OK.
- **Stock adjustments** — **no reasoned/audited manual-adjustment workflow**; IMEI delete path
  has an **unaudited** decrement (see SIM-02).
- **Suppliers / PO / GRN** — `_supplier`, `_po/_po_item/_grn/_grn_item`; partial receiving reuses
  `IMEI_Admin::receive()` + `wc_update_product_stock`; `grn_token` idempotency; over-receive guard.
  **Strong** (⚠ reconcile laptop WIP).
- **Expenses / COGS / dashboard** — expense CPT-like table + audit; COGS captured on processing/
  completed (serialized read live from IMEI cost, accessory snapshot + missing-cost flag);
  dashboard shows real KPIs. **COGS not reversed on refund** (SIM-06).
- **POS** — **not implemented** (only REST `/imei/available`, `/imei/assign`, `/channel` + channel
  tagging). SIM-04.
- **Roles/caps** — server-side caps (`cellcraze_use_pos/receive_stock/manage_imei/manage_expenses/
  view_reports`), roles cashier/warehouse/order-processing. **Strong.** Missing
  `cellcraze_adjust_stock` (F16).
- **REST / tables** — 3 routes, permission-gated; 8 custom tables (dbDelta additive).
- **Delivery/courier/COD** — **not implemented.** SIM-07.
- **Returns/refunds/RMA** — **not implemented**; worse, **refund auto-restores IMEI to available**
  (SIM-01).
- **Notifications/cron** — Woo transactional email only; no SMS/WhatsApp; no low-stock alerting.
- **Responsiveness** — theme has responsive CSS (not runtime-verified).

## 3. Test matrix (Scenarios A–J)

Legend: **NOT RUN** (needs browser), **BLOCKED** (needs DB), **SRC-VERIFIED** (behavior proven in
source), **SRC-DEFECT** (defect proven in source).

| # | Scenario | Status | Note |
|---|---|---|---|
| A | Customer buys accessory online | NOT RUN | Flow exists in theme+Woo; qty validation = Woo core. |
| B | Customer buys phone online (IMEI alloc) | SRC-VERIFIED (no-double-sell) / NOT RUN (UI) | Atomic claim + idempotent allocation proven (CC-03/04). |
| C | Bank transfer + slip verification | SRC-VERIFIED / NOT RUN (UI) | Slip≠paid, idempotent verify, gated (CC-09). |
| D | Owner manages products/inventory | NOT RUN / SRC-DEFECT | IMEI delete unaudited decrement (SIM-02); no reasoned adjust (SIM-03). |
| E | Purchasing → PO → GRN | SRC-VERIFIED / NOT RUN (UI) | Partial/idempotent/over-receive guard (ADM-10); reconcile laptop WIP. |
| F | Physical POS sale | NOT RUN — **MISSING** | No POS register/sale/receipt/reconciliation (SIM-04). |
| G | Returns / refunds / RMA | NOT RUN — **MISSING + SRC-DEFECT** | No RMA; refund auto-restocks IMEI (SIM-01); COGS not reversed (SIM-06). |
| H | Delivery / COD | NOT RUN — **MISSING** | No courier/tracking/COD remittance (SIM-07). |
| I | Reporting / reconciliation | BLOCKED / NOT RUN | Dashboard real KPIs; valuation/reconciliation report missing (SIM-12). |
| J | Security & usability | SRC-VERIFIED (partial) | Caps/nonces/REST gating/escaping/prepared SQL present; UI a11y NOT RUN. |

## 4–8. Findings (full detail)

Each finding also appears in the consolidated register (§ Issue Register). Categories: BUG /
MISSING / INCOMPLETE / ENHANCEMENT / SECURITY / DATA INTEGRITY / UX / DEPENDENCY.

### SIM-01 — Refund automatically returns an IMEI to sellable stock
- **Category/Severity:** DATA INTEGRITY · **P1** (P0 if refunds are used before RMA exists). **Confirmed.**
- **Repro (source):** `class-cellcraze-imei-orders.php` hooks `woocommerce_order_status_refunded`
  and `woocommerce_order_fully_refunded` → `restore_order()` → `IMEI_DB::restore()` sets status =
  `available`.
- **Expected:** a refunded phone must **not** become sellable automatically; it should be
  quarantined (`returned`) pending an explicit, audited inspection/regrade.
- **Actual:** refund silently makes the unit `available` again (audited, but wrong disposition).
- **Impact:** a device that may be damaged/not-returned can be resold; stock integrity + warranty
  risk.
- **Files:** `class-cellcraze-imei-orders.php` (`restore_order`, hook registration lines ~26–28).
- **Fix:** on refund, transition to `returned` (not `available`); only RMA inspection
  (`re_grade`, manager) returns it to `available`. Keep cancel→restore (unsold) as-is or review.
- **Dependencies:** RMA-02 (regrade) · decision D5. **Risk:** changing refund behavior affects
  existing flows — needs tests.
- **Acceptance:** [ ] refund sets IMEI `returned`, not `available`; [ ] only audited regrade makes
  it sellable; [ ] cancel-before-ship still restores. **Regression:** CC-04 lifecycle stays green.
- **Task:** feeds **RMA-02** (and interim guard could land with **F16-02**).

### SIM-02 — Unaudited stock decrement when deleting an available IMEI
- **Category/Severity:** DATA INTEGRITY · **P0**. **Confirmed.**
- **Repro (source):** `class-cellcraze-imei-admin.php::handle_delete_imei()` → for an `available`
  unit calls `self::adjust_stock($product_id, -1)` with **no `Cellcraze_Audit::log()`** entry.
- **Expected:** every stock movement is audited (actor, reason, signed delta, reference).
- **Actual:** sellable stock drops by one with no audit trail; gated only by `manage_woocommerce`.
- **Impact:** silent stock loss; breaks reconciliation; no accountability.
- **Files:** `class-cellcraze-imei-admin.php` (`handle_delete_imei`, `adjust_stock`).
- **Fix:** route through the F16 audited-adjustment service with a reason + `cellcraze_adjust_stock`
  cap. **Acceptance:** [ ] delete writes exactly one audit row + one decrement; [ ] cap enforced;
  [ ] sold units still undeletable. **Task:** **F16-03** (needs F16-01/02).

### SIM-03 — No capability-gated, reasoned, bounded manual stock adjustment
- **Category/Severity:** MISSING (DATA INTEGRITY) · **P1**. **Confirmed.**
- **Evidence:** grep shows only a private `adjust_stock()`; no `cellcraze_adjust_stock` cap; no
  reasons (`miscount/damaged/lost/display_unit/write_off/re_grade/found`), no magnitude cap, no
  negative-stock guard, no manager-only restriction for loss/write-off.
- **Impact:** inventory corrections are either impossible or unsafe/unaudited.
- **Fix:** F16-01 (cap+reasons+validation) + F16-02 (audited service + IMEI-state guards).
- **Acceptance:** see F16 specs. **Tasks:** **F16-01, F16-02**.

### SIM-04 — POS (physical shop) not implemented
- **Category/Severity:** MISSING · **P1**. **Confirmed.**
- **Evidence:** only REST `/imei/available`,`/imei/assign`,`/channel` + `cellcraze_use_pos` cap +
  channel tagging exist; no register UI, sale flow, receipt, or cash reconciliation.
- **Impact:** no in-store selling through the shared inventory.
- **Fix:** POS-01 (sale lifecycle = Woo order, channel=pos, IMEI alloc, audit) → POS-02 (receipt) →
  POS-03 (register/reconciliation/cashier). **Dependency:** D2 (custom vs plugin), F16-02, PAY-01.
- **Tasks:** **POS-01..04**.

### SIM-05 — Returns / refunds / RMA workflow missing
- **Category/Severity:** MISSING · **P1**. **Confirmed.**
- **Evidence:** `returned` status exists but no intake/inspection/refund workflow; refund path
  mishandles disposition (SIM-01).
- **Fix:** RMA-01 (intake + limits + IMEI validation) → RMA-02 (inspection/regrade, audited) →
  RMA-03 (refund + COGS/profit reversal, idempotent). **Dependency:** D5, D6.
- **Tasks:** **RMA-01..03**.

### SIM-06 — COGS/profit not reversed on refund
- **Category/Severity:** DATA INTEGRITY · **P1**. **Confirmed.**
- **Evidence:** `class-cellcraze-cogs.php` captures on processing/completed only; no reversal hook
  on refund. Profit after refunds will be overstated.
- **Fix:** reversal coordinated in RMA-03 + reconciliation in REPORT-01; define method (D6).
- **Tasks:** **RMA-03, REPORT-01**.

### SIM-07 — Courier / delivery / COD remittance missing
- **Category/Severity:** MISSING · **P2** (P1 for a delivery business). **Confirmed.**
- **Evidence:** no shipment model, tracking, or COD collection/remittance.
- **Fix:** SHIP-01 (manual courier + tracking + statuses) → SHIP-02 (COD reconciliation).
  **Dependency:** D4. **Tasks:** **SHIP-01, SHIP-02**.

### SIM-08 — Online card gateway not available
- **Category/Severity:** DEPENDENCY · **P1**. **Confirmed.**
- **Evidence:** no gateway plugin; by design, card is live-only. Blocked on **D1**.
- **Fix:** PAY-GW-01 after provider pick; sandbox then live; dup-webhook guard. **Task:** **PAY-GW-01**.

### SIM-09 — Storefront variants + filters incomplete
- **Category/Severity:** INCOMPLETE · **P2**. **Confirmed.**
- **Evidence:** attributes Brand/Storage/Colour exist; products are `simple` (no variations);
  shop filters not wired (sort only) — `docs/FEATURE-TRACEABILITY.md` CUS-05/06.
- **Fix:** variable phones + brand taxonomy + filter blocks. **Task:** **T-105** (existing ID).

### SIM-10 — Low-stock / reorder alerts missing
- **Category/Severity:** MISSING · **P2**. **Confirmed.**
- **Evidence:** dashboard shows a low-stock count; no alerting/digest. **Task:** **INV-03**.

### SIM-11 — Content pages (contact/FAQ) missing; policy pages unbranded
- **Category/Severity:** UX/INCOMPLETE · **P2/P3**. **Confirmed.** (CUS-14/15.)
- **Fix:** add Contact/FAQ/About; brand+link policy pages. **Task:** new content task (P3) or fold
  into storefront polish.

### SIM-12 — Inventory valuation / reconciliation report missing
- **Category/Severity:** ENHANCEMENT (DATA INTEGRITY support) · **P1**. **Confirmed.**
- **Evidence:** cost captured; movement ledger built (T-101); **valuation + reconciliation report
  not built** (ADM-11 partial). **Task:** **REPORT-01** (D6 for method).

### Preserve (implemented correctly — do NOT rebuild)
CC-03 atomic IMEI claim · CC-04 IMEI lifecycle · CC-06 receiving↔stock sync · CC-09 bank-slip
(slip≠paid, idempotent, gated) · CC-13 roles/caps · ADM-05 channel tagging · ADM-08 dashboard ·
ADM-09 suppliers · ADM-10 PO/GRN (idempotent, over-receive guard) · T-101 stock-movement/audit
ledger · storefront CUS-01..13.

### Security notes (Scenario J, source)
Bank-slip + expense uploads: nonce, ownership, size, extension + real-MIME, private gated store —
**good**. REST routes have real permission callbacks. Admin actions use `check_admin_referer` and
caps. SQL uses `$wpdb->prepare`. Output escaped. **Residual:** IMEI delete cap is coarse
(`manage_woocommerce`) and unaudited (SIM-02); harden via F16. No destructive security tests were
run.

## Issue Register (consolidated)

| ID | Cat | Title | Sev | Module | State | Expected | Actual | Impact | Files/Fn | Fix | Deps | Acceptance | Task |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| SIM-01 | DATA INTEGRITY | Refund auto-restocks IMEI as available | P1 | Returns/IMEI | Confirmed | Quarantine as `returned`, regrade to sell | Set `available` on refund | Resell bad unit | imei-orders `restore_order` | Set `returned`; regrade via RMA | RMA-02, D5 | refund→returned; regrade-only sellable | RMA-02 |
| SIM-02 | DATA INTEGRITY | Unaudited decrement on available-IMEI delete | P0 | Inventory/IMEI | Confirmed | Audited, cap-gated decrement | Unaudited −1 | Silent stock loss | imei-admin `handle_delete_imei` | Route via F16 audited service | F16-01/02 | 1 audit row + 1 decrement; cap | F16-03 |
| SIM-03 | MISSING | No reasoned/bounded audited stock adjustment | P1 | Inventory | Confirmed | Cap+reasons+limits+audit | None | Unsafe/absent corrections | roles, new service | Build F16 adjust | INV-01 | per F16 specs | F16-01/02 |
| SIM-04 | MISSING | POS not implemented | P1 | POS | Confirmed | Full POS on shared inventory | REST+tag only | No in-store sales | new POS controller | Build POS | D2,F16-02,PAY-01 | POS==Woo order+IMEI+audit | POS-01..04 |
| SIM-05 | MISSING | Returns/RMA workflow absent | P1 | Returns | Confirmed | Intake/inspect/refund | None | No returns handling | new RMA | Build RMA | D5,D6 | per RMA specs | RMA-01..03 |
| SIM-06 | DATA INTEGRITY | COGS/profit not reversed on refund | P1 | Accounting | Confirmed | Refund reverses COGS | No reversal | Profit overstated | cogs capture | Reverse in RMA-03 | D6 | profit reconciles post-refund | RMA-03/REPORT-01 |
| SIM-07 | MISSING | Courier/COD remittance absent | P2 | Delivery | Confirmed | Dispatch+track+COD recon | None | No delivery ops | new SHIP | Build SHIP | D4 | per SHIP specs | SHIP-01/02 |
| SIM-08 | DEPENDENCY | Online card gateway absent | P1 | Payments | Confirmed | Gateway sandbox+live | None | No online card | Woo gateway | After provider | D1 | sandbox paths pass | PAY-GW-01 |
| SIM-09 | INCOMPLETE | Variants + shop filters | P2 | Storefront | Confirmed | Variable phones+filters | simple+sort only | Weaker UX | theme/attrs | Build T-105 | — | variant price/stock; filters narrow | T-105 |
| SIM-10 | MISSING | Low-stock/reorder alerts | P2 | Inventory | Confirmed | Alerts/digest | count only | Stockouts | dashboard/cron | Build INV-03 | INV-02 | low-stock flagged; email | INV-03 |
| SIM-11 | UX | Contact/FAQ missing; policy unbranded | P3 | Storefront | Confirmed | Pages present/linked | missing | Trust/UX | pages | Add pages | — | pages exist+linked | (new P3) |
| SIM-12 | ENHANCEMENT | Valuation/reconciliation report | P1 | Reporting | Confirmed | Reconcile+value | missing | Blind spots | reports | Build REPORT-01 | D6 | reconciles to fixture | REPORT-01 |

## Recommended implementation order (reuses existing task IDs — no competing roadmap)

**1. Critical data-integrity first:** PLAN-01 (finish on laptop) → INV-01 → **F16-01 → F16-02 →
F16-03** (fixes SIM-02/03; interim guard for SIM-01) → GRN-01 (preserve laptop WIP).
**2. Required missing features:** PAY-01 → **RMA-01 → RMA-02 → RMA-03** (fixes SIM-01/05/06) →
**POS-01 → POS-02 → POS-03** (SIM-04).
**3. Finish incomplete:** T-105 (SIM-09) → INV-02 → REPORT-01 (SIM-06/12) → INV-03 (SIM-10).
**4. Enhancements:** SHIP-01 → SHIP-02 (SIM-07); PAY-GW-01 after D1 (SIM-08).
**5. Optional:** Contact/FAQ + policy branding (SIM-11); POS-04.
**Gate QA-01 (regression/security/HPOS/recovery) before release.**

## Regression test plan
Keep green: CC-03/04/06/09/13, ADM-05/08/09/10, T-101. For every inventory/payment/IMEI/refund
change add: duplicate-submission, partial-failure, concurrency, and reconciliation tests. Testing
adapted to repo (no PHPUnit assumed): `php -l`, WP-CLI `eval-file`, documented HTTP/browser scripts;
**UI tests remain NOT RUN until a browser+running site is available; DB tests BLOCKED until MySQL is
healthy.**

## Unresolved decisions (block specific tasks)
D1 gateway→PAY-GW-01 · D2 POS approach→POS-01..04 · D4 courier→SHIP · D5 RMA approach→RMA +
SIM-01 fix · D6 COGS method→RMA-03/REPORT-01 · D7 tax · D8 warranty · D9 single-shop (confirmed) ·
D10 SMS/WhatsApp. (See `docs/CELLCRAZE-DECISIONS-AND-OPEN-QUESTIONS.md`.)

## Proposed documentation changes (no code)
- Add **SIM-01** (refund→auto-sellable) and **SIM-06** (COGS not reversed on refund) to the risk
  register in the master roadmap and tie the fixes into **RMA-02/RMA-03** acceptance.
- In `CELLCRAZE-IMPLEMENTATION-STATUS.md`, note SIM-01/SIM-02 as **confirmed defects** feeding
  F16-03 / RMA-02 (evidence-based), without changing task statuses to Implemented/Tested.
