# CellCraze — Decisions & Open Questions

Records confirmed requirements, architectural constraints, approved decisions, and the open
decisions that **block** specific tasks. Do not re-ask approved items.

## Confirmed requirements
- Single shop (not multi-branch) — see D9.
- Shared inventory across online (Woo) + in-store (POS).
- Every phone is serialized (IMEI); accessories are quantity-based unless serial is explicitly
  required.
- Sri Lanka / LKR; HPOS enabled.

## Architectural constraints (verified, must hold)
- `imei` is DB-UNIQUE; IMEI claim is atomic compare-and-set (`IMEI_DB::mark_sold`).
- WooCommerce is the single writer of quantity `_stock`; GRN/POS reuse it (no competing count).
- Never force `_stock` = IMEI row count.
- All inventory/money/IMEI movements audited via `Cellcraze_Audit::log()` with idempotent keys.
- Returns never auto-sellable; regrade is explicit + audited.

## Approved decisions (do not reconfirm)
- A1: Back-office inventory/purchasing built in `cellcraze-core` (custom), not reskinning ATUM —
  *subject to D3 for whether to add ATUM-Pro for advanced purchasing.*
- A2: Payments at launch: Cash (POS), COD, Direct bank transfer + **bank-slip verification**
  (built, CC-09). Online card gateway is a later, provider-gated task.
- A3: Storefront is largely built; do not redesign unnecessarily (design system is "White/Premium"
  per `CLAUDE.md`).
- A4: Custom roles/caps enforced server-side (CC-13), not by hiding menus.

## Open decisions (BLOCKERS) — each names the blocked task(s) and consequences

| # | Decision | Blocks | Consequence if unresolved | Safe provisional |
|---|---|---|---|---|
| D1 | Online card gateway: PayHere / WebXPay / Stripe (+ live keys, live host) | PAY-GW-01 | No online card payments | Ship COD+BACS+slip; gateway later |
| D2 | POS approach: custom in `cellcraze-core` vs plugin (wePOS/Oliver/FooSales) | POS-01..04 | POS core can't start cleanly | Custom minimal POS that creates Woo orders (reuses existing stack) |
| D3 | Purchasing: ATUM-Pro license vs keep custom PO/GRN | GRN advanced features | Advanced receiving/valuation limited | Keep custom (ADM-10 works); revisit |
| D4 | Courier: Pronto/Koombiyo/API vs manual | SHIP-01/02 | No automated tracking | Manual courier + tracking field |
| D5 | Returns/RMA: plugin vs custom endpoint; policy content | RMA-01..03 | No returns workflow | Custom RMA tied to orders (coordinated, not auto-restock) |
| D6 | COGS method: per-unit IMEI cost vs average `_cost`; what counts as expense | RMA-03, REPORT-01 | Profit numbers ambiguous | Use IMEI cost where present, else `_cost`; flag gaps |
| D7 | Tax: VAT-registered? | storefront/checkout tax | Currently off | Keep tax off until confirmed |
| D8 | Warranty: period per category + claim workflow scope | warranty UX | Only months stored | Store months; no claim workflow yet |
| D9 | Multi-branch? | inventory model | — | **Single shop assumed/confirmed** |
| D10 | SMS/WhatsApp notifications: Notify.lk/Twilio + consent | comms (optional) | Email only | Email notifications only |

## Testing/environment limitations
- MySQL/MariaDB on the laptop had **Aria recovery / `mysql.plugin`** startup issues. DB-dependent
  verification is **blocked** until the DB is healthy. **Do not** force-recover, delete/move DB
  files, or repeatedly restart; read-only diagnosis only.
- No committed automated test framework; testing strategy is adapted to the repo (protocol doc).
