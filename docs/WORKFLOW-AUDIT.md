# CellCraze — Workflow Audit (Customer + Admin/Staff)

**Date:** 2026-10-09 · Read-only. No changes made. Verified on the local
runtime (WP 7.1.3, WooCommerce 11.2.0, HPOS enabled) and the repo code.
Companion to [AUDIT-REPORT.md](AUDIT-REPORT.md).

Status keys: `VERIFIED` runtime-checked · `UNVERIFIED` code present, not
exercised · `PARTIAL` · `MISSING` · `BROKEN`.

---

## Part 1 — Customer journey (17 steps)

### 1. Open the website — VERIFIED
- **Exists:** `front-page.php`; `/cellcraze/` HTTP 200.
- **Backend:** theme `cellcraze` (Storefront child).
- **Concern:** no HTTPS locally (live-host only); emoji/speculationrules fine.
- **Tests:** load home → 200, header/footer render, no console fatals.

### 2. Browse categories & brands — PARTIAL
- **Exists:** 9 categories, category bar nav; `/product-category/phones/` 200.
- **Missing:** **Brand** is a per-product simple attribute, not a global
  taxonomy with archives → no `/brand/samsung/` browsing. Brand filter absent.
- **Fix:** register `pa_brand` global attribute + assign terms; add brand to
  filters.
- **Tests:** category page lists only its products; brand archive resolves.

### 3. Search & filter — PARTIAL
- **Exists:** Woo product search (header form `post_type=product`).
- **Missing:** attribute/price/stock **filters** (sidebar fallback only lists
  categories). No live search.
- **Fix:** add Woo "Filter by" blocks to the `cc_shop_filters` widget area, or
  FacetWP (paid). Decide before launch.
- **Tests:** filter by price/brand narrows results; empty state renders.

### 4. Open product details — VERIFIED
- **Exists:** `/product/<slug>/` 200; PDP hooks (brand kicker, SKU line, stock
  line, info rows, specs) in `functions.php`.
- **Tests:** PDP shows title, price, stock indicator, specs table.

### 5. Select variants (colour/storage) — MISSING
- **Exists:** Storage/Colour attributes auto-registered in theme.
- **Missing:** **No variable products** — all 10 samples are `simple`. No
  variation selector appears.
- **Fix:** convert phones to variable products with Storage+Colour variations
  (or import variable rows). Depends on step 2 attributes.
- **Tests:** PDP shows dropdowns; price/stock update per variation; add-to-cart
  disabled until selection.

### 6. Price, availability, warranty — PARTIAL
- **Exists:** price + `cellcraze_stock_indicator()` (out / only-N / in stock),
  driven by real `_stock`; "Official warranty" static line.
- **Concern:** stock accuracy depends on IMEI vs Woo stock staying in sync
  (see Integration doc). Warranty is static text, not per-product months.
- **Privacy:** ✅ **IMEI numbers must never be shown on the PDP** — currently
  they are not. Keep it that way; availability must be a *count*, never a list
  of serials.
- **Fix:** surface per-product warranty months; keep stock as count only.
- **Tests:** out-of-stock product shows "Out of stock" + disabled cart; no IMEI
  strings anywhere in PDP HTML.

### 7. Add to cart — VERIFIED
- **Exists:** Woo cart; `/cart/` 200; sticky mobile add-to-cart bar (1e/1f).
- **Tests:** add simple product → cart count increments.

### 8. Update qty / remove — UNVERIFIED
- **Exists:** Woo cart core.
- **Tests:** change qty beyond stock → blocked; remove → total updates.

### 9. Checkout (guest/login) — VERIFIED (config)
- **Exists:** `/checkout/` 200; `woocommerce_enable_guest_checkout = yes`.
- **Tests:** complete as guest and as logged-in user.

### 10. Contact & delivery address — UNVERIFIED
- **Exists:** Woo checkout fields.
- **Concern:** no Sri-Lanka-specific validation (phone format, district).
- **Security/privacy:** ensure no PII in URLs; validate/sanitize server-side.
- **Tests:** invalid email/phone rejected; required fields enforced.

### 11. Delivery method — VERIFIED
- **Exists:** "Sri Lanka" zone — flat Rs 350, free over Rs 10,000, local pickup
  (`wp_woocommerce_shipping_zones`).
- **Tests:** cart < 10k shows Rs 350; ≥ 10k shows free; pickup selectable.

### 12. Payment: COD / bank / online — PARTIAL
- **Exists:** COD + BACS enabled (verified options).
- **Missing:** online **gateway** (PayHere/WebXPay/Stripe) not installed;
  **bank-slip upload** for BACS proof absent.
- **Security:** online payment must be **verified server-side** (see Integration
  doc #6). Never mark paid on client redirect alone.
- **Tests:** place COD order; place BACS order → on-hold with bank details.

### 13. Place order — UNVERIFIED
- **Exists:** Woo order creation (HPOS `wp_wc_orders`).
- **Tests:** order persists; stock decrements; reserved_stock cleared.

### 14. Confirmation & updates — PARTIAL
- **Exists:** Woo order-received page + status emails; IMEI shown on emails
  (code).
- **Missing/concern:** **no SMTP** installed → emails via PHP mail, unreliable;
  likely undelivered on live host. No SMS/WhatsApp.
- **Fix:** install WP Mail SMTP + real sender before launch.
- **Tests:** order emails actually deliver (after SMTP).

### 15. Track delivery — PARTIAL
- **Exists:** status timeline on order view/thankyou (`cellcraze_order_timeline`).
- **Missing:** no courier tracking number / parcel status.
- **Tests:** timeline reflects status transitions.

### 16. Order history & invoices — PARTIAL
- **Exists:** My Account orders; PDF Invoices plugin active.
- **UNVERIFIED:** invoice PDF generation/attachment not tested.
- **Tests:** customer sees past orders; invoice PDF downloads.

### 17. Returns / refunds / warranty — MISSING
- **Missing:** no customer cancel, no RMA/return request, no warranty claim.
  IMEI-restore-on-refund exists server-side but no customer entry point.
- **Privacy:** a warranty/claim view must expose **only that customer's own
  purchased product's** warranty — never other units or IMEIs.
- **Fix:** RMA plugin or custom endpoint; per-order warranty display scoped to
  the buyer.
- **Tests:** customer A cannot see customer B's order/warranty/IMEI.

### Cross-cutting
- **Mobile responsiveness:** theme built mobile-first (1e/1f); UNVERIFIED on a
  real device — needs manual check.
- **Stock accuracy / checkout validation / payment status / confirmation /
  privacy:** see each step above and the Integration doc.

---

## Part 2 — Admin / staff workflows

### A. Product management — PARTIAL
- VERIFIED: create/edit products, SKU, price, images, categories (Woo core; 10
  imported with `_cost`).
- PARTIAL: brands (simple attr, not taxonomy); warranty period (stored per IMEI
  unit, not per product); activate/deactivate = Woo publish/draft (works).
- MISSING: variations not used yet.

### B. Supplier & purchasing — MISSING/PARTIAL
- UNVERIFIED: supplier records (ATUM free).
- PARTIAL: purchase orders / receiving (ATUM **PRO**, likely paid).
- **MISSING (critical): IMEI registration at receiving** — no code path feeds
  `wp_cellcraze_imei` from a PO. Today IMEIs can only be hand-entered on the
  product IMEI tab.
- MISSING: supplier payments, balances, returns.
- **Dependency:** receiving → stock → IMEI → purchase cost → inventory reports.

### C. Inventory — PARTIAL
- VERIFIED: stock managed (`manage_stock=yes`), reservation (`wc_reserved_stock`,
  hold 60 min), low-stock flag.
- PARTIAL: available/sold/damaged/returned visible for IMEI units via the
  registry (code), aggregate via ATUM; stock-movement history needs ATUM PRO.
- MISSING: stock adjustment with reason (aggregate), full movement audit.

### D. Online orders — PARTIAL
- VERIFIED: order admin (HPOS); COD/BACS.
- UNVERIFIED: IMEI auto-allocation on processing (hooked, never run); invoices;
  pack/dispatch (PDF packing slips).
- MISSING: bank-transfer slip verification UI; online-payment confirmation
  (no gateway); customer returns/refunds UI.

### E. Physical POS — MISSING
- VERIFIED (API only): IMEI select (`/imei/available`), assign, channel tag.
- MISSING: register UI, scan, cash/card/bank tender, receipts, POS returns,
  cash reconciliation, shifts. **No POS plugin installed.**

### F. Financial operations — MISSING
- PARTIAL: revenue via Woo Analytics; `_cost` captured.
- MISSING: expense ledger, supplier payments, courier/COD settlement, COGS &
  profit computation, dashboards.

### G. Administration — PARTIAL
- VERIFIED: roles administrator/shop_manager/customer; shop_manager has
  `manage_woocommerce`, `edit_shop_orders`, `view_woocommerce_reports`.
- MISSING: Cashier / Warehouse / Order-processing roles; activity/audit log;
  backups (UpdraftPlus not installed here); SMTP/security/cache plugins.

### Module dependency map (admin)
```
Supplier → Purchase Order → Receiving ─┬─> Woo stock (+/-)
                                       ├─> wp_cellcraze_imei (unit rows)
                                       └─> purchase cost (_cost / per-unit)
Woo stock ──> Online order & POS sale (shared pool)
Order (processing) ──> IMEI allocation ──> invoice/receipt ──> dispatch
Cancel/refund ──> stock restore + IMEI restore
Sales + cost + expenses ──> profit reports
```

---

## Prioritized workflow gaps (customer + admin)

**Critical:** IMEI intake at receiving (B) — the system's data source;
IMEI allocation unproven at runtime (D/§7 integration); POS register (E).
**High:** SMTP/email delivery (14); online gateway + server-side verification
(12); variable products (5); bank-slip upload + verification (12/D).
**Medium:** filters/brand browsing (2/3); returns/RMA + warranty scoping (17);
custom roles (G); courier tracking + COD settlement (15/F).
**Low:** contact form, branded policy pages, activity log, SMS.

> **Approval gate:** no implementation will start until you approve the plan in
> [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md).
