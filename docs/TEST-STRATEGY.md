# CellCraze — Test Strategy

**Updated:** 2026-10-16 (Phase 0). How each feature is verified. Reflects the
current reality: **no automated PHPUnit suite is scaffolded yet**; verification
to date uses WP-CLI `eval-file` harnesses + real HTTP (curl) + `php -l`, run on
the local XAMPP stack. This doc defines the target layers and the per-phase
acceptance tests.

## Test layers

| Layer | Tool | Status | Notes |
|---|---|---|---|
| Static | `php -l` on every changed file | **In use** | run before each commit (project convention) |
| Unit | PHPUnit + WP test scaffolding | **TODO** | pure logic (CAS claim, meta normalize, COGS math) |
| Integration | WP-CLI `eval-file` harness | **In use** | exercises real WC/order/stock/IMEI paths |
| Security / HTTP | curl (multipart, auth, nonce) | **In use** | 403/409 gates, ownership, idempotency |
| Browser / manual | real browser (built-in browser can't reach localhost) | partial | curl substitutes where possible |
| Regression | re-run prior phase harnesses | **In use** | each change re-runs IMEI/stock checks |

> Limitation: nonces for logged-in users are session-bound, so CLI-generated
> nonces don't validate against an HTTP session — authenticated HTTP tests must
> scrape the page nonce (done for CC-09 verify).

## Verified test runs this session (evidence)
- **CC-03/04** (Phase 1): 8/8 — receive, duplicate-reject, CAS (2nd claim false),
  assign 2/2, stock −2, cancel-restore.
- **CC-06** (Phase 2): 6/6 — receive→stock +3, dup no inflate, baseline restored.
- **CC-13** (roles): 5/5 — caps present, cashier least-privilege, admin retains caps.
- **CC-09** (bank-slip): 10/10 browser/HTTP — valid jpg/png/pdf upload, private
  403, owner view 200, wrong-owner 403, invalid/oversize rejected (no file left),
  re-upload replaces, admin verify → processing + IMEI once, idempotent repeat,
  COD unaffected.
- **T-101** (stock-movement + audit ledger): 8/8 — receive logs +1/unit,
  duplicate event_key ignored (idempotent), imei_assign/restore as qty-NULL
  status traces, order_stock_reduce (−1)/restore (+1), ledger never mutated
  stock, consistency (available=2 after sale+cancel), viewer gated (guest → 302
  login; `manage_woocommerce` + explicit cap check).
- **T-102** (integrate slip-verify + role-change into ledger): role_change —
  events logged on `set_user_role` (≥2 distinct changes), same-key retry
  suppressed, note holds role slugs only (no PII). slip_verify (real HTTP admin
  verify) — logged **exactly once after two POSTs** (idempotent), order →
  processing, actor_id recorded, no PII in ref/note, and a COD verify attempt
  logged **nothing** (failed/non-applicable). Log-only: no change to verify, IMEI,
  stock, or role behaviour. (One role-privacy assertion was a test-code bug —
  empty test-user email made `strpos('')===0`; note verified clean by inspection.)
- **T-103** (expense module): Part A 9/9 (CLI) — validation rejects
  negative/bad-date/bad-category/bad-method, amount rounding + length caps, caps
  (admin+manager yes, cashier no), table created. Part B (real HTTP) — create
  (+expense_create audit, receipt stored private), **receipt public URL → 403,
  gated admin → 200 image/png, guest blocked**, unauthorized create rejected,
  edit (+expense_update), void (soft-delete, row retained, +expense_void once).
  Accounting boundary respected (no profit/COGS, no supplier auto-import).
- **Suppliers + Purchasing (PO/GRN)**: E2E via real HTTP admin GRN submit — supplier+ordered PO with an IMEI line (qty 2) and a quantity line (qty 3); partial receive (1 phone+IMEI, 2 accessories) → IMEI unit created with `po_id`, phone stock 20→21, accessory 48→50, PO→partial, 1 GRN row; **idempotent** re-submit of the same `grn_token` = no-op (no dup IMEI, no stock change); **over-receive** (recv 5 vs remaining 1) blocked (stock unchanged, IMEIs not created); completion → phone 22/acc 51, PO→received, 2 IMEI units for the PO. Inventory only via existing authoritative paths. All PASS; `php -l` clean; data cleaned up.
- **T-104** (COGS + profit report): phone COGS 52000 (live from IMEI), accessory
  COGS 2400 (snapshot), snapshot stays 2400 after `_cost`→9999, duplicate capture
  idempotent, serialized auto-reverse after cancel (sold-cost→0); report math over
  a fixed range — net 5400, COGS 4800, gross profit 600, opex 5000, operating
  profit −4400, recognized orders=2 (cancelled/pending excluded), out-of-range → 0.
  All financial assertions PASS.
- **Channel fix** (ADM-05): `set()` re-fetches a fresh order so an explicit
  channel wins over the `woocommerce_new_order` default. Verified: pos persists,
  default online, override both ways, T-104 report channel=pos → 1 order (all → 2).
- **T-103 review**: 13/13 + id-tamper HTTP. Fresh activation grants the cap to
  admin+manager; a real `cc_cashier` **user** fails `user_can` (server-side);
  amount edge cases (zero accepted-by-design, huge/malformed rejected,
  over-precision rounded); void excluded from an active-only SUM while retained;
  two same-second updates both log (unique keys) but a true retry dedupes;
  fail-safe guard refuses to store a receipt when `.htaccess` is missing;
  **id-tamper**: receipt nonce for id 5 used with id 6 → 403 (correct → 200).
  Four minimal fixes applied (below).
- **T-101 review**: 8/8 — DB-verified schema (direct `SHOW TABLES`/`DESCRIBE`,
  not a cast); fresh-activation recreates the table; **retried
  `woocommerce_order_status_processing` adds no second assign row** (CAS);
  event-identity regression — same key retry suppressed, distinct `sold_at`
  epoch logs separately. Residual limit: `sold_at` is second-resolution, so a
  full assign→cancel→reassign→cancel of the *same unit+order within one second*
  could still collapse to one restore row (practically impossible in manual ops).

## Per-phase acceptance tests (map to brief PART 15)
1. Browse/search/filter → products list, filters narrow, empty state. (Phase 2)
2. Purchase available product → order created, stock reserved/decremented. (Phase 3)
3. Checkout rejects unavailable stock → server revalidation blocks. (Phase 3)
4. Online order updates inventory once. (Phase 4) — covered by CC-04 pattern.
5. POS sale updates same inventory once. (Phase 6)
6. Duplicate request can't deduct twice → idempotency/CAS. (Phase 4/6) — pattern proven.
7. IMEI uniqueness prevents double sale. (Phase 4) — ✅ CC-03 T13.
8. Authorized staff issue/print invoices. (Phase 6)
9. Coupons follow configured rules. (Phase 9)
10. Returns/partial refunds keep accurate records. (Phase 7)
11. Failed/cancelled orders don't corrupt stock. (Phase 4) — ✅ CC-04 restore.
12. Purchase receiving increases stock correctly. (Phase 5) — ✅ CC-06.
13. Unauthorized staff blocked server-side. (Phase 1/10) — ✅ CC-13 caps; extend per action.
14. Responsive mobile/tablet/desktop. (Phase 11)
15. Existing features keep working. (every phase) — regression harness.

## Test data & safety
- Local XAMPP only; test orders/units/files are created and **cleaned up** by the
  harness. A DB backup is taken before risky changes
  (`wp-content/_baseline-*.sql`).
- Never real charges, never real customer emails during tests.
- Sandbox/test gateway keys only when gateway work begins (Phase 3/6).

## Definition of done (every task)
Business logic + authorization (capability + nonce) + data integrity (no
double/concurrent corruption) + tests green + regression clean + traceability
updated. "Code written" or "screen exists" is **not** done.
