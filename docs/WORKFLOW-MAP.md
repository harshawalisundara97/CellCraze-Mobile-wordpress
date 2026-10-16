# CellCraze — Workflow Map

**Updated:** 2026-10-16 (Phase 0). Each workflow lists steps, the system of
record, and the **authorization gate** on sensitive transitions. ✅ = built &
verified this session · ⬜ = to build · 🔒 = blocked on a decision.

## 1. Customer purchase (online) ✅ core / ⬜ parts
`browse → add to cart → checkout (guest/login) → place order → confirmation`
- System of record: WooCommerce order (`wp_wc_orders`), stock reserved via
  `wc_reserved_stock`.
- Server MUST revalidate price/stock/coupon on place (Woo does; **audit in Phase 3**).
- Auth: customer owns their order; guest via order key.

## 2. Bank-transfer verification ✅ (CC-09)
`BACS order → on-hold → customer uploads slip (private) → staff reviews → verify → processing`
- Record: order meta (`_cellcraze_bank_slip`, `_slip_verified_by/_at`); file in
  `uploads/cellcraze-private/` (gated endpoint).
- 🔒 gate: upload = order owner (user id or order key) + nonce; **verify =
  `edit_shop_orders`** + nonce; idempotent (guarded by `verified_by`).
- Payment completion ONLY via this verify (never auto on status change).
- Fires IMEI assignment on `processing` (shared with workflow 4).

## 3. Inventory receiving ✅ (CC-06)
`receive IMEIs (or qty) → create unit rows → Woo stock += N (once) → cost/supplier recorded`
- Record: `wp_cellcraze_imei` rows + Woo stock; duplicate IMEI rejected (no
  stock inflation).
- Gate: `cellcraze_receive_stock` / `manage_woocommerce`.
- ⬜ to add: stock-movement ledger row per receive (T-101/102), partial receiving
  against a PO (Phase 5).

## 4. IMEI reservation / assignment ✅ (CC-03/04)
`order → processing/completed → FIFO claim available units (compare-and-set) → mark sold + link order → show on order/email/invoice`
`cancel/refund → restore units to available + clear line meta`
- Record: `wp_cellcraze_imei.status/order_id`.
- Integrity: atomic claim — a unit can transition `available→sold` once only;
  lost race advances to next unit (no double-allocation; verified T13).
- Gate: internal (order status hooks) or POS/API with `cellcraze_manage_imei`.

## 5. Physical POS sale 🔒 (API ✅, register blocked)
`scan/search → add items → pick IMEI (API) → tender (cash/card-record/bank) → complete → Woo order (channel=pos) → stock decremented once → receipt`
- Record: native Woo order tagged `_cellcraze_channel=pos`; IMEI via REST
  `imei/assign` (compare-and-set, 409 on race).
- Gate: `cellcraze_use_pos` (Cashier role) for the API.
- 🔒 register UI = POS plugin (decision D2). Card = *recorded* payment, not a
  gateway charge.

## 6. Return / refund / warranty ⬜ (Phase 7)
`request (customer/staff) → validate order/item/qty/policy → staff approve/reject → inspect → disposition (restock | damaged | quarantine | supplier-return) → refund via original method → records → inventory updated per disposition → notify`
- Integrity: status change ≠ refund; never restock twice (guard like CC-04);
  serialized unit identity/history preserved.
- Gate: refund approval = manager/owner cap; refund links to original order/payment.

## 7. Expense & profit reporting ⬜ (Phase 8)
`record expense (category/amount/payee/method/receipt) → approve → report`
`profit = gross sales − discounts − refunds − COGS − operating expenses`
- Record: `cc_expense` CPT; COGS from IMEI.cost (serialized) or `_cost` (qty).
- Integrity: don't double-count purchase cost vs COGS vs expense; flag
  missing-cost items rather than guess (decision D6).
- Gate: expense entry vs financial reports = separate caps.

## Authorization matrix (sensitive transitions)
| Action | Required capability | Built |
|---|---|---|
| Upload own bank slip | order owner + nonce | ✅ |
| Verify bank transfer | `edit_shop_orders` + nonce | ✅ |
| Receive stock / register IMEI | `cellcraze_receive_stock` / `cellcraze_manage_imei` | ✅ |
| POS sale + IMEI assign (API) | `cellcraze_use_pos` / `cellcraze_manage_imei` | ✅ |
| Approve refund | manager/owner (Phase 7) | ⬜ |
| Enter expense | manager/owner (Phase 8) | ⬜ |
| View financial reports | owner (Phase 8) | ⬜ |
| Change staff roles | `manage_options` | core |

Every sensitive server action checks capability + nonce; UI-hiding is never the
only control.
