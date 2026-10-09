# CellCraze — Module Review (all 235 modules)

**Reviewed:** 2026-10-09 · **Branch:** claude/sleepy-bohr-xefjt0
**Stack:** WordPress + WooCommerce (Storefront child "Modernist") · **Currency:** LKR / Sri Lanka

All 235 modules from the implementation brief, classified against the current scaffold.
The stack has **not been provisioned yet**, so **"Done" means scaffolded by the setup
scripts or theme** — not runtime-verified. Approach is **plugin-first**; custom code only
where no plugin fits cleanly.

## Status legend

| Status | Meaning |
|---|---|
| ✅ **Done** | Scaffolded by the setup scripts, theme, or `cellcraze-core` plugin |
| 🟡 **Partial** | Base exists; needs config, a PRO add-on, or custom glue |
| 🔴 **Missing** | Not scaffolded — the real build work |
| ⚪ **Not required** | Defer unless the business grows into it |

## Summary

| Category | ✅ | 🟡 | 🔴 | ⚪ |
|---|--:|--:|--:|--:|
| 1. Customer Website | 17 | 5 | 2 | 0 |
| 2. Product & Inventory | 11 | 7 | 3 | 1 |
| 3. Physical Shop POS | 1 | 1 | 15 | 0 |
| 4. Order Management | 6 | 3 | 3 | 1 |
| 5. Payments | 6 | 4 | 3 | 0 |
| 6. Suppliers & Purchasing | 3 | 5 | 4 | 0 |
| 7. Courier & Delivery | 2 | 1 | 11 | 0 |
| 8. Finance, Expenses & Profit | 4 | 7 | 9 | 1 |
| 9. Users & Access | 4 | 4 | 3 | 0 |
| 10. Notifications | 5 | 1 | 3 | 0 |
| 11. Reports & Dashboard | 7 | 5 | 5 | 0 |
| 12. WP/Woo Technical | 9 | 9 | 3 | 0 |
| 13. Testing & QA | 0 | 1 | 19 | 0 |

> Note: items marked ✅ in the POS/IMEI rows below reflect the `cellcraze-core`
> plugin now built (Phases 1–2). Everything else is the scaffold as committed.

---

## 1. Customer Website
*Storefront + WooCommerce templates deliver most of this; the Modernist child theme styles it.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 1 | Home Page | ✅ | `front-page.php` renders the Modernist homepage. |
| 2 | Product Categories | ✅ | 9 categories created by `04-create-categories.sh`; category bar menu registered. |
| 3 | Product Listing | ✅ | WooCommerce shop loop, 3-col grid via `loop_shop_columns`. |
| 4 | Product Search | ✅ | WooCommerce core search; add live-search plugin later. |
| 5 | Filters & Sorting | 🟡 | Default Woo sorting only. Add Woo block filters or FacetWP for attribute filtering. |
| 6 | Product Details | ✅ | Woo single-product template, theme-styled. |
| 7 | Images & Gallery | ✅ | Gallery zoom/lightbox/slider enabled in `functions.php`. |
| 8 | Variations (storage/colour/model) | ✅ | Brand/Storage/Colour attributes auto-registered; use variable products. |
| 9 | Availability & Stock Status | ✅ | Custom `cellcraze_stock_indicator()` (out / only-N-left / in stock). |
| 10 | Shopping Cart | ✅ | Woo cart page installed via install_pages. |
| 11 | Checkout | ✅ | Woo checkout page; COD + bank transfer live. |
| 12 | Registration & Login | ✅ | Woo My Account. |
| 13 | Guest Checkout | ✅ | Woo default; verify setting is on after setup. |
| 14 | Profile Management | ✅ | Woo My Account → account details. |
| 15 | Address Management | ✅ | Woo billing/shipping address book. |
| 16 | Order History | ✅ | Woo My Account → orders. |
| 17 | Order Tracking | 🟡 | Status visible in account. For parcel tracking add a courier/tracking plugin. |
| 18 | Order Cancellation | 🟡 | No customer-facing cancel. Add a cancel-order plugin or custom endpoint. |
| 19 | Return & Refund Requests | 🔴 | Add an RMA plugin (WooCommerce Returns/RMA) — no scaffold yet. |
| 20 | Reviews & Ratings | ✅ | Woo reviews; confirm enabled in settings. |
| 21 | Wishlist | ✅ | TI WooCommerce Wishlist installed. |
| 22 | Offers / Coupons | ✅ | Woo coupons core; create in dashboard. |
| 23 | Related Products | ✅ | Woo related products on single product. |
| 24 | Contact & Support | 🟡 | No contact page/form scaffolded. Add a form plugin + page. |
| 25 | Terms / Privacy / Return Policy | 🔴 | Create policy pages; link in footer menus (menus already registered). |

## 2. Product & Inventory
*WooCommerce + ATUM (free) cover the basics; serial/IMEI and purchasing need ATUM PRO or custom.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 26 | Product Management | ✅ | Woo products. |
| 27 | Category & Brand Management | ✅ | Categories scripted; Brand as global attribute. |
| 28 | Attributes & Variations | ✅ | Brand/Storage/Colour registered. |
| 29 | SKU Management | ✅ | Woo SKU field; sample CSV uses it. |
| 30 | IMEI / Serial Numbers | ✅ | Built: `cellcraze-core` plugin, `wp_cellcraze_imei` table (UNIQUE imei). |
| 31 | Phone Warranty Management | 🟡 | Warranty months captured per unit; expiry/alerts still to build. |
| 32 | Cost & Selling Price | ✅ | Sample CSV has `Meta: _cost`; ATUM tracks purchase price. |
| 33 | Bulk Import / Export | ✅ | Woo CSV import; `05-import-products.sh` + template. |
| 34 | Manual Initial Entry | ✅ | Dashboard product entry. |
| 35 | Stock Quantity | ✅ | Woo stock mgmt; ATUM central view. |
| 36 | Physical Shop Stock | 🟡 | Shared Woo stock pool; POS decrements it (see POS phase). |
| 37 | Online Store Stock | ✅ | Woo stock is the online source of truth. |
| 38 | Shared Inventory Sync | ✅ | POS + web share one Woo stock pool; IMEI table tracks which unit sold. |
| 39 | Stock Reservation for Orders | 🟡 | Woo holds stock for pending orders. Confirm hold-stock minutes configured. |
| 40 | Low-Stock Alerts | ✅ | Woo low-stock threshold (CSV has it) + ATUM alerts. |
| 41 | Out-of-Stock Mgmt | ✅ | Woo out-of-stock handling + indicator. |
| 42 | Stock Adjustment | ✅ | ATUM stock-control / manual edits. |
| 43 | Stock Movement History | 🟡 | ATUM logs some movement; full audit needs ATUM PRO Log add-on. |
| 44 | Damaged / Lost Stock | 🟡 | IMEI `damaged` status exists; ATUM logs (PRO) for aggregate stock. |
| 45 | Stock Return Mgmt | 🔴 | Tie to RMA + supplier returns; IMEI restore built, RMA UI not. |
| 46 | Inventory Valuation | 🟡 | ATUM gives stock value; full COGS valuation in finance phase. |
| 47 | Multiple Locations | ⚪ | Single shop + online. Only if multi-branch — defer. |

## 3. Physical Shop POS
*Register UI = a WooCommerce POS plugin (pick from wp.org). `cellcraze-core` adds IMEI-at-counter + channel glue.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 48 | Point of Sale | 🔴 | Install a WooCommerce POS plugin (wePOS / Oliver / WooCommerce POS). |
| 49 | Cashier Login & Permissions | 🔴 | POS plugin roles + WP roles; see User Management. |
| 50 | Search & Barcode Scan | 🔴 | POS plugin barcode support; USB scanner acts as keyboard. |
| 51 | IMEI Selection at Sale | ✅ | Built: REST `GET /cellcraze/v1/imei/available` + `POST /imei/assign`. |
| 52 | Cart & Quantity at POS | 🔴 | Provided by POS plugin. |
| 53 | Discounts at POS | 🔴 | POS plugin line/cart discounts. |
| 54 | Cash Payments | 🔴 | POS plugin cash tender. |
| 55 | Card Terminal (physical) | 🔴 | Record as POS payment type; terminal is offline/manual. |
| 56 | Bank Transfer at POS | 🔴 | POS payment type. |
| 57 | Split Payments | 🟡 | Some POS plugins support split tender; verify before choosing. |
| 58 | Receipt / Invoice Print | 🔴 | POS plugin receipt; or PDF Invoices plugin (installed). |
| 59 | POS Returns / Refunds | 🔴 | POS plugin refund flow (IMEI restore already wired on refund). |
| 60 | POS Order History | 🔴 | POS orders flow into Woo orders (channel-tagged `pos`). |
| 61 | Cash Drawer / Reconciliation | 🔴 | POS plugin feature or custom; rarely in free tiers. |
| 62 | Shift Open / Close | 🔴 | POS plugin register/shift feature — a selection criterion. |
| 63 | POS Sales Reports | 🔴 | POS plugin + Woo reports (channel column added). |
| 64 | Auto Stock Update after POS | ✅ | POS sale decrements the shared Woo stock pool automatically. |

## 4. Order Management
*Woo covers online orders; fulfilment extras and IMEI assignment handled by `cellcraze-core`.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 65 | Online Order Management | ✅ | Woo orders admin. |
| 66 | Order Status Management | ✅ | Woo statuses; custom statuses via plugin if needed. |
| 67 | Order Confirmation | ✅ | Woo order-received + email. |
| 68 | Payment Verification | 🟡 | COD/bacs are manual-verify; bank-slip upload (84) needed to complete. |
| 69 | Processing Workflow | 🟡 | Woo status transitions; formal pick/pack workflow needs a plugin. |
| 70 | Picking & Packing | 🔴 | Add a picking-list plugin, or use PDF packing slips (installed). |
| 71 | IMEI Assignment to Orders | ✅ | Built: FIFO auto-assign on processing/completed; shown on order + receipt. |
| 72 | Invoice Generation | ✅ | PDF Invoices & Packing Slips installed. |
| 73 | Order Notes & History | ✅ | Woo order notes core. |
| 74 | Partial Fulfilment | ⚪ | Defer unless multi-shipment needed. |
| 75 | Cancellation + Stock Restore | 🟡 | Woo restores stock; IMEI units restored too. Customer-facing cancel still missing (18). |
| 76 | Return / Refund Processing | 🔴 | Tie to RMA plugin (19); IMEI restore on refund already wired. |
| 77 | Failed / Unpaid Orders | ✅ | Woo failed/pending statuses + hold-stock cleanup. |

## 5. Payments
*COD + bank transfer scaffolded. Card gateways are deliberately live-host only.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 78 | Cash on Delivery | ✅ | Enabled in `03-configure-woo.sh`. |
| 79 | Bank Transfer | ✅ | BACS enabled with instructions. |
| 80 | Online Payment Gateway | 🟡 | Deferred to live host by design — PayHere/WebXPay/Stripe with real keys. |
| 81 | Card Payments (website) | 🟡 | Via the gateway above; not in local dev. |
| 82 | Card Terminal (physical) | 🔴 | POS payment type (55). |
| 83 | Payment Status Tracking | ✅ | Woo order payment status. |
| 84 | Bank Slip Upload | 🔴 | Custom upload field on checkout or My Account for BACS proof. |
| 85 | Manual Bank Verification | 🟡 | Admin marks order paid; slip upload (84) makes it real. |
| 86 | Transaction Records | ✅ | Woo order + gateway logs once live. |
| 87 | Failed Payment Handling | ✅ | Woo failed-payment flow. |
| 88 | Refund Tracking | 🟡 | Woo refunds core; reconcile with COD/courier in finance phase. |
| 89 | Payment Reconciliation | 🔴 | Finance phase — match gateway/COD/bank to orders. |
| 90 | COD Collection & Settlement | 🔴 | Custom/courier phase — track cash collected by courier. |

## 6. Suppliers & Purchasing
*ATUM free gives suppliers; purchase orders & receiving are ATUM PRO or custom.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 91 | Supplier Management | ✅ | ATUM Suppliers (free). |
| 92 | Supplier Contacts | ✅ | ATUM supplier fields. |
| 93 | Purchase Order Management | 🟡 | ATUM PRO Purchase Orders — free tier limited. Likely paid add-on. |
| 94 | Stock Receiving | 🟡 | ATUM PRO goods-receipt on PO. |
| 95 | IMEI at Receiving | 🔴 | Custom: capture IMEIs when a PO is received (Phase 3; depends on 30). |
| 96 | Supplier Invoice Recording | 🟡 | ATUM PRO / custom meta. |
| 97 | Purchase Cost Recording | ✅ | ATUM purchase price per product (+ per-unit cost in IMEI table). |
| 98 | Supplier Payments | 🔴 | ATUM PRO or custom ledger. |
| 99 | Supplier Returns | 🔴 | Custom / ATUM PRO. |
| 100 | Purchase History | 🟡 | ATUM inventory logs. |
| 101 | Supplier Balance | 🔴 | Custom ledger / accounting integration. |
| 102 | Purchase Cost & Valuation | 🟡 | ATUM stock valuation. |

## 7. Courier & Delivery
*Minimal scaffold (shipping zone + pickup). Courier integration is custom or plugin.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 103 | Courier Service Mgmt | 🔴 | Add a courier plugin for local couriers (Pronto, Koombiyo, etc.). |
| 104 | Delivery Method Selection | ✅ | Shipping zone with flat/free/pickup in `03-configure-woo.sh`. |
| 105 | Delivery Fee Mgmt | ✅ | Flat Rs 350 / free over Rs 10,000 configured. |
| 106 | Address Validation | 🟡 | Woo required fields; no SL-specific validation. |
| 107 | Shipment Creation | 🔴 | Courier plugin or custom shipment CPT. |
| 108 | Tracking Number Recording | 🔴 | Field on order + courier plugin. |
| 109 | Courier Assignment | 🔴 | Custom / plugin. |
| 110 | Shipment Status Tracking | 🔴 | Custom / plugin. |
| 111 | Dispatch / Delivery Confirmation | 🔴 | Order status + courier webhook. |
| 112 | Failed Delivery Handling | 🔴 | Custom status flow. |
| 113 | Returned-to-Sender | 🔴 | Custom status + stock restore. |
| 114 | COD Amount Tracking | 🔴 | Links to 90 — courier COD settlement. |
| 115 | Courier Fee / Settlement | 🔴 | Finance phase. |
| 116 | Delivery Reports | 🔴 | Reports phase. |

## 8. Finance, Expenses & Profit
*Woo has basic sales analytics; true P&L, expenses and COGS need a plugin or custom.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 117 | Sales Revenue | ✅ | Woo Analytics → Revenue. |
| 118 | COGS | 🟡 | `_cost` + per-unit cost captured; needs a COGS/profit plugin to compute. |
| 119 | Gross Profit | 🟡 | Via COGS plugin (Cost of Goods for Woo). |
| 120 | Net Profit | 🔴 | Needs expenses module (121) + reporting. |
| 121 | Expense Management | 🔴 | No expense ledger in Woo. Add accounting plugin or custom CPT. |
| 122 | Expense Categories | 🔴 | Part of expense module. |
| 123 | Rent / Utilities | 🔴 | Expense entries. |
| 124 | Salaries / Operating | 🔴 | Expense entries. |
| 125 | Courier / Delivery Expense | 🔴 | Expense + courier settlement. |
| 126 | Gateway Fees | 🔴 | Expense tracking per gateway. |
| 127 | Supplier Payment Tracking | 🔴 | Links to 98/101. |
| 128 | Refund / Return Records | 🟡 | Woo refunds; financial rollup in reports. |
| 129 | Cash Flow Reports | 🔴 | Reporting / accounting plugin. |
| 130 | Daily Sales Reports | ✅ | Woo Analytics date range. |
| 131 | Weekly / Monthly Reports | ✅ | Woo Analytics. |
| 132 | Profit by Product | 🟡 | COGS plugin. |
| 133 | Profit by Category | 🟡 | COGS plugin. |
| 134 | Profit by Channel (online vs POS) | 🟡 | Channel tagging built (`_cellcraze_channel`); profit rollup needs COGS. |
| 135 | Payment Method Reports | 🟡 | Woo Analytics by payment method. |
| 136 | Tax / Accounting Reports | ⚪ | Tax off by default; enable if VAT-registered. |
| 137 | Expense & Profit Dashboard | 🔴 | Reports phase — combines 117-135. |

## 9. Users & Access
*WordPress roles cover most; POS/inventory roles and activity logs need plugins.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 138 | Administrator | ✅ | WP admin role. |
| 139 | Shop Owner | 🟡 | Map to admin or a custom role. |
| 140 | Manager | 🟡 | Woo shop_manager role. |
| 141 | Cashier | 🔴 | Custom role, created with POS phase. |
| 142 | Inventory Staff | 🔴 | Custom role (ATUM has capabilities to assign). |
| 143 | Order Processing Staff | 🔴 | Custom role. |
| 144 | Role-Based Access Control | 🟡 | WP roles/caps; refine with a role-editor plugin. |
| 145 | Module-Level Permissions | 🟡 | Via caps + role editor. |
| 146 | User Activity Logs | 🔴 | Add an activity-log plugin (WP Activity Log). |
| 147 | Login / Session Security | ✅ | Wordfence installed (hardens login). |
| 148 | Password Reset / Recovery | ✅ | WP core + email (needs SMTP live). |

## 10. Notifications
*Woo transactional emails exist; SMS/WhatsApp and some alerts are add-ons.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 149 | New Order Notifications | ✅ | Woo admin email. |
| 150 | Order Status Notifications | ✅ | Woo customer emails. |
| 151 | Payment Confirmation | ✅ | Woo emails. |
| 152 | Low-Stock Notifications | ✅ | Woo + ATUM alerts. |
| 153 | Warranty / Return Notifications | 🔴 | Depends on warranty (31) / RMA (19). |
| 154 | Delivery Notifications | 🔴 | Depends on courier phase. |
| 155 | Customer Email | ✅ | Woo emails via WP Mail SMTP (configure live). |
| 156 | SMS / WhatsApp | 🔴 | Add SMS/WhatsApp plugin + local gateway (Notify.lk, Twilio). |
| 157 | Admin / Staff Alerts | 🟡 | Woo admin emails; extend per module. |

## 11. Reports & Dashboard
*Woo Analytics gives the sales side; inventory/IMEI/courier/finance reports follow their modules.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 158 | Business Overview Dashboard | 🟡 | Woo Analytics home; full dashboard in reports phase. |
| 159 | Total Sales | ✅ | Woo Analytics. |
| 160 | Online vs Physical | 🟡 | Channel tag built; add a report view/filter on it. |
| 161 | Orders & Status Summary | ✅ | Woo Analytics / Orders. |
| 162 | Current Stock Report | ✅ | ATUM stock central. |
| 163 | Low-Stock Report | ✅ | ATUM / Woo. |
| 164 | IMEI / Serial Report | 🟡 | IMEI table exists + admin list; a dedicated report view to add. |
| 165 | Purchase / Supplier Reports | 🟡 | ATUM (PRO) reports. |
| 166 | Payment Reports | 🟡 | Woo Analytics. |
| 167 | COD Pending Collection | 🔴 | Depends on COD settlement (90). |
| 168 | Courier / Delivery Reports | 🔴 | Depends on courier phase. |
| 169 | Expense Reports | 🔴 | Depends on expense module (121). |
| 170 | Gross / Net Profit Reports | 🔴 | Depends on COGS + expenses. |
| 171 | Returns / Refund Reports | 🟡 | Woo refunds; RMA data (19). |
| 172 | Best-Selling Products | ✅ | Woo Analytics → Products. |
| 173 | Sales / Profit Trends | 🟡 | Woo trends; profit needs COGS. |
| 174 | Export to CSV / Excel | ✅ | Woo Analytics export. |

## 12. WordPress / WooCommerce Technical
*Most foundations are scaffolded; integrations and production hardening remain.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 175 | WordPress Config | ✅ | `01-install-wp.sh`. |
| 176 | WooCommerce Config | ✅ | `03-configure-woo.sh` (LK/LKR, pages, payments, shipping). |
| 177 | Theme & Responsive UI | ✅ | `cellcraze` child theme (Modernist) — verify responsiveness in browser. |
| 178 | Required Plugins | ✅ | `02-install-plugins.sh` installs 9 plugins + activates `cellcraze-core`. |
| 179 | Custom Plugin Dev | 🟡 | `cellcraze-core` built (IMEI, POS glue); more phases to come. |
| 180 | POS ↔ Woo Integration | ✅ | Channel tagging + IMEI REST; POS orders are native Woo orders. |
| 181 | Shared Stock Sync Logic | ✅ | One Woo stock pool; no extra sync needed for Woo-native POS. |
| 182 | IMEI Tracking Integration | ✅ | Built (Phase 1). |
| 183 | Payment Gateway Integration | 🟡 | Live-host only by design. |
| 184 | Courier Integration | 🔴 | Courier phase. |
| 185 | Email Config | 🟡 | WP Mail SMTP installed; connect real service live. |
| 186 | DB Structure & Relationships | 🟡 | Woo schema + `wp_cellcraze_imei`; courier tables to design. |
| 187 | API & Webhooks | 🟡 | Woo REST API + `cellcraze/v1`; wire more per integration. |
| 188 | Scheduled Tasks / Cron | 🟡 | WP-Cron; move to real cron on live host. |
| 189 | Error Logging / Monitoring | 🟡 | WP debug log; add monitoring live. |
| 190 | Performance Optimization | ✅ | LiteSpeed Cache installed. |
| 191 | SEO Config | ✅ | Rank Math installed. |
| 192 | Backup & Recovery | ✅ | UpdraftPlus installed (schedule live). |
| 193 | Security Hardening | ✅ | Wordfence installed (+ SSL/checklist live). |
| 194 | Staging / Production Deploy | 🟡 | README gives host path; no CI yet. |
| 195 | Update Management | 🟡 | Manual; consider managed updates live. |

## 13. Testing & QA
*No automated tests scaffolded. A manual QA checklist exists implicitly in the README go-live list.*

| # | Module | Status | Recommendation |
|--:|---|---|---|
| 196 | Registration / Login Test | 🔴 | Manual test plan to write. |
| 197 | Product & Search Test | 🔴 | Manual. |
| 198 | Cart & Checkout Test | 🔴 | Manual. |
| 199 | Payment Method Test | 🔴 | Manual (COD/BACS now; gateways live). |
| 200 | COD Order Test | 🔴 | Manual. |
| 201 | Bank Transfer Verify Test | 🔴 | Manual (after slip upload 84). |
| 202 | Online Payment Success/Fail Test | 🔴 | Manual on live gateway. |
| 203 | POS Sales Test | 🔴 | After POS plugin chosen. |
| 204 | Stock Sync Test | 🔴 | Verify POS sale decrements web stock + IMEI marked sold. |
| 205 | IMEI Uniqueness / Assignment Test | 🔴 | Verify UNIQUE constraint + FIFO/hand-pick assignment. |
| 206 | Purchase / Receiving Test | 🔴 | After purchasing phase. |
| 207 | Return / Refund Test | 🔴 | After RMA phase. |
| 208 | Courier / COD Settlement Test | 🔴 | After courier phase. |
| 209 | Expense / Profit Calc Test | 🔴 | After finance phase. |
| 210 | User Permission Test | 🔴 | After roles defined. |
| 211 | Security Test | 🟡 | Wordfence scan; add a go-live pen check. |
| 212 | Mobile Responsiveness Test | 🔴 | Manual — theme is responsive, verify. |
| 213 | Performance Test | 🔴 | Manual (LiteSpeed + a speed test). |
| 214 | Backup / Restore Test | 🔴 | Manual UpdraftPlus restore drill. |
| 215 | End-to-End Workflow Test | 🔴 | Full flow after all phases. |

---

## 14. Review tasks (216–235)

These are the review process itself, not buildable modules. Tasks 216–231
(inspect, classify, prioritize, plan) are satisfied by this document. Tasks
232–235 (implement in phases, test after each change, final report, document)
are in progress:

- **Phase 1 — IMEI / serial tracking:** built (`cellcraze-core`).
- **Phase 2 — POS + shared stock sync glue:** built (channel tagging + IMEI REST).
- **Phase 3 — Supplier purchasing → IMEI at receiving:** next.
- **Later:** courier/COD settlement, finance/profit, RMA, quick wins (bank-slip
  upload, policy pages, contact form), then QA.

> All built code is verified by PHP lint + review only. Nothing is
> runtime-tested yet because the stack has not been provisioned. First real test:
> run `setup.sh`, receive an IMEI unit, place a test order to *processing*,
> confirm the IMEI appears and stock decrements, then cancel and confirm restore.
