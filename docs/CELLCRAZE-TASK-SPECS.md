# CellCraze — Task Specifications

Companion to `CELLCRAZE-MASTER-IMPLEMENTATION-ROADMAP.md`. One task per section. Each is sized to
implement, test, review and document in a **single Claude Code session**. Execute **one at a
time**; do not start the next until the current is reviewed and accepted (see
`CELLCRAZE-IMPLEMENTATION-PROTOCOL.md`).

> Same env caveat as the roadmap: re-verify against the laptop working tree and preserve
> uncommitted work before editing. Evidence paths below are **repository-relative**.

Spec fields: Priority · Purpose · Current-state evidence · Scope · Out-of-scope · Dependencies ·
Files/tables/hooks · Approach · Security · Integrity/concurrency · Failure/retry/rollback · Tests ·
Acceptance (checkable) · Regression · Effort · Completion evidence.

---

## PLAN-01 — Baseline verify + regression harness + DB/env diagnosis
- **Priority:** P0. **Purpose:** establish a trustworthy baseline before any change.
- **Current-state evidence:** `docs/FEATURE-TRACEABILITY.md` lists prior VERIFIED items; no
  committed automated test runner in repo root.
- **Scope:** inventory the running env (WP/Woo/PHP/HPOS versions, active plugins); read-only DB
  reachability check; record how to re-run the existing manual/CLI verifications for CC-03/04/06/
  09/13; capture a baseline "known-green" list.
- **Out-of-scope:** any code change; any DB mutation; forced MySQL recovery.
- **Dependencies:** none. **Files/tables/hooks:** read-only across plugin + `docs/`.
- **Approach:** `wp core version`, `wp plugin list`, `wp cli info`; `wp db check` (read-only);
  document commands in a `docs/` note.
- **Security/Integrity:** read-only. **Failure:** if DB down, record as blocked; do not recover.
- **Tests:** n/a (diagnostic). **Acceptance:** [ ] env recorded; [ ] DB reachability stated;
  [ ] regression re-run steps documented; [ ] baseline green-list captured.
- **Regression:** none affected. **Effort:** M.
- **Completion evidence:** the baseline note committed; blockers listed.

## INV-01 — Document + assert shared inventory invariants (central guard)
- **Priority:** P0. **Purpose:** one authoritative place for the stock/IMEI invariants so modules
  don't reimplement unsafe logic.
- **Current-state evidence:** invariants live implicitly in `class-cellcraze-imei-db.php`
  (`mark_sold` compare-and-set) and `class-cellcraze-imei-orders.php`; no single guard/service.
- **Scope:** a small service (e.g. `Cellcraze_Stock_Guard`) exposing the sanctioned operations
  (claim/restore IMEI, apply qty delta via Woo, never force `_stock`=IMEI count); document the
  single-writer rule; route existing callers to it where low-risk.
- **Out-of-scope:** rewriting working IMEI/GRN logic; POS/RMA logic.
- **Dependencies:** PLAN-01. **Files:** new `includes/class-cellcraze-stock-guard.php`; callers in
  `imei-admin`, `purchasing`. **Hooks:** none new.
- **Approach:** thin wrappers over `IMEI_DB::mark_sold/restore` + `wc_update_product_stock`; reject
  operations that would force negative qty.
- **Security:** cap-gated callers unchanged. **Integrity/concurrency:** preserve compare-and-set;
  no new write path to `_stock`.
- **Failure/rollback:** operations return bool/WP_Error; callers handle.
- **Tests:** unit — negative-delta rejected; claim idempotent; restore safe.
- **Acceptance:** [ ] single documented writer per stock type; [ ] no path forces `_stock` to IMEI
  count; [ ] negative qty impossible via guard.
- **Regression:** CC-03/04/06 stay green. **Effort:** M.
- **Completion evidence:** guard + tests + traceability update.

## INV-02 — Stock-movement viewer (read-only) over `_audit`
- **Priority:** P1. **Purpose:** make every movement visible/auditable in admin.
- **Current-state evidence:** `class-cellcraze-audit.php` + `wp_cellcraze_audit` exist (T-101);
  viewer referenced but confirm coverage.
- **Scope:** read-only admin table (filter by type/date/object/actor), newest-first, paginated,
  cap-gated. **Out-of-scope:** editing/deleting audit rows (append-only).
- **Dependencies:** PLAN-01. **Files:** `class-cellcraze-audit.php` (viewer) or new admin page.
  **Cap:** `manage_woocommerce`/`cellcraze_view_reports`.
- **Approach:** prepared `$wpdb` queries; escape output; no PII/secrets.
- **Integrity:** append-only; no mutation. **Failure:** empty-state rendered.
- **Tests:** rows render; filters narrow; non-cap user 403.
- **Acceptance:** [ ] movements listed w/ actor+reason+ref; [ ] filters work; [ ] gated.
- **Regression:** audit writes unaffected. **Effort:** S–M.

## INV-03 — Low-stock / reorder alerts
- **Priority:** P1. **Purpose:** warn before stockouts. **Evidence:** dashboard shows low-stock
  count (ADM-08); no alerting.
- **Scope:** reorder threshold per product (reuse Woo low-stock amount), admin surfacing + optional
  email digest (WP mail only). **Out-of-scope:** SMS/WhatsApp (D10).
- **Dependencies:** INV-02. **Files:** dashboard/reports; a cron for the digest.
- **Security:** cap-gated. **Integrity:** read-only aggregates.
- **Tests:** threshold crossing flagged; digest lists correct items.
- **Acceptance:** [ ] low-stock list accurate; [ ] optional email sends to managers only.
- **Effort:** M.

## GRN-01 — Validate purchasing/GRN reliability; reconcile laptop WIP
- **Priority:** P0. **Purpose:** confirm GRN is safe; preserve the laptop's in-progress GRN work.
- **Current-state evidence:** `class-cellcraze-purchasing.php` — PO/GRN tables, partial receiving
  reuses `IMEI_Admin::receive()` + `wc_update_product_stock`, `grn_token` idempotency, over-receive
  guard (ADM-10 VERIFIED). **Laptop has uncommitted GRN reliability changes here — reconcile first.**
- **Scope:** diff laptop WIP vs committed; verify partial-receive, retry/duplicate, over-receive,
  IMEI+qty both update without double count; add missing tests only.
- **Out-of-scope:** refactor/replace working GRN; ATUM-Pro decision (D3).
- **Dependencies:** PLAN-01. **Files:** `class-cellcraze-purchasing.php`, `class-cellcraze-imei-admin.php`.
- **Integrity/concurrency:** `grn_token` UNIQUE must stay; receive is single stock writer.
- **Failure/retry:** duplicate submit = no-op; partial failure leaves consistent PO/GRN state.
- **Tests:** receive 3 → stock+3 & 3 IMEI rows; re-submit same token = no-op; over-receive blocked;
  partial across multiple GRNs sums correctly.
- **Acceptance:** [ ] laptop WIP preserved+understood; [ ] all receive tests green; [ ] no double
  decrement. **Regression:** ADM-10 green. **Effort:** S–M.

## F16-01 — `cellcraze_adjust_stock` capability + reasons + validation
- **Priority:** P0. **Purpose:** gate and constrain manual adjustments.
- **Current-state evidence:** no `cellcraze_adjust_stock` cap (grep: only `adjust_stock()` private
  method in `imei-admin`); deletion gated by `manage_woocommerce` only.
- **Scope:** register cap (admin + shop_manager + warehouse as decided); define reasons
  `miscount|damaged|lost|display_unit|write_off|re_grade|found`; validation: integer, non-zero,
  |delta| ≤ 100, reason required; loss/`write_off` restricted to managers.
- **Out-of-scope:** the apply logic (F16-02); returns (RMA).
- **Dependencies:** INV-01. **Files:** `class-cellcraze-roles.php` (cap), new adjustment form/handler.
- **Security:** cap + nonce on submit; manager-only reasons double-checked server-side.
- **Tests:** zero/non-int/>100/invalid-reason rejected; non-cap 403; manager-only reason blocked for
  cashier. **Acceptance:** [ ] cap installed on activation; [ ] all invalid inputs rejected with
  message; [ ] reason allow-list enforced. **Effort:** M.

## F16-02 — Audited adjustment service + IMEI-state guards
- **Priority:** P0. **Depends:** F16-01, INV-01. **Purpose:** apply adjustments safely + audited.
- **Scope:** service applies a **signed** qty delta via the stock guard (never negative result);
  for serialized products, block unsafe ops on `sold`/`reserved` IMEIs; allowed IMEI transitions
  only: `available→damaged` (write_off/damaged), `damaged→available` **only via re_grade by
  manager**, `found`→create/`available`. Writes `_audit` (actor, reason, signed delta, reference,
  idempotent `event_key`).
- **Out-of-scope:** returns path (RMA owns `returned`→sellable).
- **Files:** new `class-cellcraze-adjust.php`; `class-cellcraze-imei-db.php` (transition helpers);
  `class-cellcraze-audit.php`.
- **Integrity/concurrency:** compare-and-set on IMEI transitions; idempotent event key; no negative
  stock. **Failure/rollback:** on failure, no partial write; return WP_Error.
- **Tests:** adjust +5/−5 audited once; negative-result blocked; sold IMEI untouched; re_grade
  manager-only; duplicate submit = single audit row.
- **Acceptance:** [ ] every adjustment audited exactly once; [ ] illegal IMEI transitions blocked;
  [ ] no negative stock. **Regression:** CC-03/04 green. **Effort:** M.

## F16-03 — Fix unaudited stock decrement on available-IMEI delete
- **Priority:** P0. **Depends:** F16-02. **Purpose:** close the known gap.
- **Current-state evidence:** `class-cellcraze-imei-admin.php::handle_delete_imei()` calls
  `self::adjust_stock($product_id, -1)` for an available unit **with no audit entry**.
- **Scope:** route the delete-driven decrement through the F16-02 audited service with reason
  (e.g. `write_off`/`miscount`), cap `cellcraze_adjust_stock`; keep refusing to delete `sold`.
- **Out-of-scope:** broader delete UX.
- **Files:** `class-cellcraze-imei-admin.php`.
- **Tests:** deleting available unit writes one audit row + decrements once; deleting sold blocked;
  non-cap 403. **Acceptance:** [ ] no unaudited decrement remains; [ ] cap enforced. **Effort:** S.

## PAY-01 — Payment/order-state reliability (COD/BACS/slip) + dup-callback guard
- **Priority:** P0. **Purpose:** payment states are trustworthy; slip ≠ paid.
- **Current-state evidence:** `class-cellcraze-bankslip.php` upload/verify is VERIFIED (CC-09),
  idempotent verify. COD/BACS via Woo core.
- **Scope:** audit the full state map (pending/authorized/paid/failed/refunded) across COD/BACS;
  ensure uploading a slip does **not** mark paid; ensure stock reservation/release matches state;
  add duplicate-callback/idempotency guard scaffold for future gateway.
- **Out-of-scope:** online gateway (PAY-GW-01, D1).
- **Files:** `class-cellcraze-bankslip.php`, order-status hooks.
- **Integrity:** verify is idempotent; one IMEI assignment per order. **Tests:** slip upload keeps
  order unpaid; verify marks paid once; re-verify no-op. **Acceptance:** [ ] slip≠paid; [ ] states
  reconcile; [ ] no double IMEI assign. **Regression:** CC-09. **Effort:** M.

## POS-01 — POS sale lifecycle (shared inventory)
- **Priority:** P1. **Depends:** F16-02, PAY-01. **Purpose:** in-store sale via one inventory.
- **Current-state evidence:** REST `/imei/available`,`/imei/assign` + Channel tagging exist; no POS
  sale flow/UI.
- **Scope:** create a **WooCommerce order** (channel=pos) for a POS sale so it reuses Woo
  accounting + `IMEI_Orders` assignment + audit; accessory qty via Woo; cash/bank/card-terminal
  **recorded** (not a gateway). Authoritative sequence: build order → set paid/record payment →
  Woo reduces stock → IMEI assign hook fires → audit. **Out-of-scope:** register open/close
  (POS-03), receipt (POS-02), online gateway.
- **Decision dependency:** D2 (POS plugin vs custom) — if custom, build minimal; if plugin, adapt.
- **Files:** new POS controller + REST; reuse `Cellcraze_IMEI_Orders`, `Cellcraze_Channel`.
- **Integrity/concurrency:** no direct `_stock` writes; IMEI claim atomic; duplicate-click guard
  (idempotency token per sale). **Failure:** interrupted sale leaves no half order/stock.
- **Tests:** POS sale of 1 phone assigns 1 IMEI, reduces stock once, tags pos, audits; double-click
  = one order. **Acceptance:** [ ] POS sale == Woo order accounting; [ ] IMEI rules honored;
  [ ] audited; [ ] dup-safe. **Effort:** L.

## POS-02 — Receipt/invoice + print  (P1, depends POS-01)
Receipt from the Woo order (SKU/IMEI/total/cashier/ref legible); printable. Tests: receipt shows
allocated IMEI + totals. Acceptance: [ ] printable, [ ] matches order. Effort: M.

## POS-03 — Register open/close + cash reconciliation + cashier perms  (P1, depends POS-01)
Open float, close count, variance; cap `cellcraze_use_pos`; audited. Acceptance: [ ] reconciliation
balances expected vs counted; [ ] perms enforced. Effort: L.

## POS-04 — POS vs online reporting  (P2, depends POS-03, REPORT-01)
Split by `channel`. Acceptance: [ ] totals reconcile to orders. Effort: S–M.

## RMA-01 — Returns intake  (P1, depends PAY-01)
Link return → original order + line item; quantity limits (original − prior returns); reasons; IMEI
validation for phones. Out-of-scope: refund/regrade. Integrity: idempotent intake. Acceptance:
[ ] cannot over-return; [ ] IMEI must belong to the order. Effort: L.

## RMA-02 — Inspection → disposition (regrade/quarantine/damage/write-off), audited  (P1, depends RMA-01, F16-02)
Returned unit is **not** sellable until explicit `re_grade` (manager). Uses F16-02 transitions +
audit. Acceptance: [ ] returned→available only via audited regrade; [ ] disposition recorded.
Effort: L.

## RMA-03 — Refund + COGS/profit reversal  (P1, depends RMA-02)
Refund to original method where supported; refund, inventory disposition, accounting are **separate
but coordinated**; idempotent (no double refund). Depends D6 (COGS method). Acceptance: [ ] single
refund; [ ] profit reversal matches definitions; [ ] no auto-restock. Effort: L–XL.

## SHIP-01 — Manual courier dispatch + tracking  (P1, depends PAY-01)
Courier name + tracking ref + statuses (packed/dispatched/in-transit/delivered/failed/RTS);
delivery charge. Distinct from a courier API (D4). Acceptance: [ ] status transitions audited;
[ ] customer sees tracking. Effort: M–L.

## SHIP-02 — COD collection + remittance reconciliation  (P1, depends SHIP-01)
Track COD amount expected vs collected vs remitted. Acceptance: [ ] COD reconciles per order +
batch. Effort: M.

## REPORT-01 — Reconciliation/valuation reports on verified data  (P1, depends INV-02)
Inventory valuation (unit cost/`_cost`), sales vs stock-movement vs payments reconciliation;
timezone-correct ranges; only real data. Acceptance: [ ] figures reconcile to a hand-checked
fixture; missing-cost flagged not guessed. Effort: M.

## PAY-GW-01 — Online card gateway (after D1)  (P1, BLOCKED on D1)
Install/configure chosen gateway (PayHere/WebXPay/Stripe); sandbox test; duplicate-webhook guard;
do not claim operational until live-tested. Acceptance: [ ] sandbox success+failure paths; [ ] no
double capture. Effort: M.

## QA-01 — Regression + security + HPOS + recovery + release readiness  (P0, depends all)
Run focused + regression suites; permission tests; duplicate/concurrency tests; HPOS checks;
responsive/a11y storefront pass; backup/restore verify. Never claim tests passed unless executed.
Acceptance: [ ] release checklist green on staging. Effort: M–L.
