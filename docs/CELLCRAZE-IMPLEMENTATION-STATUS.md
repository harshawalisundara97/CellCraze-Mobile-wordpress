# CellCraze — Implementation Status Tracker

Statuses: **Not Started · Ready · In Progress · Blocked · Implemented · Tested · Accepted**.
Marked from *evidence available in the committed `main` checkout* + `docs/FEATURE-TRACEABILITY.md`.
Nothing is marked Implemented/Tested based on plans alone. **Re-verify against the laptop working
tree** (uncommitted GRN WIP) before acting.

| Task | Title | Status | Depends | Latest test result | Blockers |
|---|---|---|---|---|---|
| PLAN-01 | Baseline verify + regression harness + DB diag | Ready | — | NOT-RUN here (needs live env) | DB health on laptop |
| INV-01 | Shared inventory invariants (central guard) | Ready | PLAN-01 | NOT-RUN | — |
| INV-02 | Stock-movement viewer over `_audit` | Ready | PLAN-01 | T-101 ledger built; viewer NOT-VERIFIED | — |
| INV-03 | Low-stock / reorder alerts | Not Started | INV-02 | — | — |
| GRN-01 | Validate GRN reliability; reconcile laptop WIP | Ready | PLAN-01 | ADM-10 VERIFIED (committed) | Laptop uncommitted WIP to reconcile |
| F16-01 | `cellcraze_adjust_stock` cap + reasons + validation | Ready | INV-01 | NOT-RUN | — |
| F16-02 | Audited adjustment service + IMEI guards | Not Started | F16-01, INV-01 | — | — |
| F16-03 | Fix unaudited delete-decrement | Ready | F16-02 | Gap CONFIRMED in code | needs F16-02 |
| PAY-01 | Payment/order-state reliability + slip≠paid | Ready | PLAN-01 | CC-09 VERIFIED | — |
| POS-01 | POS sale lifecycle | Blocked | F16-02, PAY-01 | — | D2 (POS approach) |
| POS-02 | POS receipt/invoice + print | Blocked | POS-01 | — | D2 |
| POS-03 | POS register + reconciliation + cashier perms | Blocked | POS-01 | — | D2 |
| POS-04 | POS vs online reporting | Not Started | POS-03, REPORT-01 | — | D2 |
| RMA-01 | Returns intake | Blocked | PAY-01 | — | D5 |
| RMA-02 | Inspection → disposition (regrade), audited | Blocked | RMA-01, F16-02 | — | D5 |
| RMA-03 | Refund + COGS/profit reversal | Blocked | RMA-02 | — | D5, D6 |
| SHIP-01 | Manual courier dispatch + tracking | Blocked | PAY-01 | — | D4 |
| SHIP-02 | COD collection + remittance reconciliation | Blocked | SHIP-01 | — | D4 |
| REPORT-01 | Reconciliation/valuation reports | Ready | INV-02 | ADM-08 dashboard VERIFIED | D6 (COGS method) |
| PAY-GW-01 | Online card gateway | Blocked | — | — | D1 |
| QA-01 | Regression/security/HPOS/recovery/release | Not Started | all | — | — |

## Verified (carried from `docs/FEATURE-TRACEABILITY.md`, committed main)
CC-03 atomic IMEI claim · CC-04 IMEI lifecycle · CC-06 receiving↔stock · CC-09 bank-slip ·
CC-13 roles/caps · ADM-05 channel · ADM-08 dashboard · ADM-09 suppliers · ADM-10 PO/GRN ·
T-101 stock-movement/audit ledger. Storefront CUS-01..13 largely verified.

## Recommended first task
**F16-03** (or the F16-01→02→03 sequence): closes a **confirmed, business-critical** gap — the
unaudited stock decrement when deleting an available IMEI — and establishes the audited-adjustment
foundation that POS and RMA both depend on. It is small, unblocked (once F16-01/02 land), and
independently testable. If a single quickest win is wanted, **PLAN-01** first to lock the baseline.
