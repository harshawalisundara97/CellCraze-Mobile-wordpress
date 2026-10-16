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
