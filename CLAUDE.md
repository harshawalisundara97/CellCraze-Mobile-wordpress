# CellCraze — Project Guide & Task Tracker

CellCraze is an online store **and** inventory-management system for a mobile phone & accessories
shop in **Sri Lanka (LKR)**, built on **WordPress + WooCommerce**. The storefront recreates a
high-fidelity "Modernist" design handoff (9 screens, `docs/design-handoff/`) as a custom theme;
the back-office inventory/procurement flow is delivered by the **ATUM** plugin (its own UI).

Full roadmap context: `docs/DESIGN-IMPLEMENTATION-PLAN.md`, `docs/IMPLEMENTATION-GUIDE.md`.
Work branch: `claude/sleepy-bohr-xefjt0`.

---

## Task tracker

Keys: `[x]` done · `[~]` in progress · `[ ]` todo. Keep this updated as work proceeds.

### Task 1 — Running WordPress + store foundation
- [~] 1.1 Stand up a WordPress sandbox (InstaWP) to view the theme.
- [ ] 1.2 Install WooCommerce; wizard = Sri Lanka / LKR, physical products.
- [ ] 1.3 Install parent theme Storefront; upload & activate the `cellcraze` theme.
- [ ] 1.4 Install plugins: ATUM, PDF Invoices & Packing Slips, WP Mail SMTP, TI Wishlist,
  product-filter plugin, Rank Math, Wordfence, UpdraftPlus, LiteSpeed Cache.

### Task 2 — Catalog structure & data
- [ ] 2.1 Create 9 categories; add Phones children (Android / iPhone / Feature).
- [ ] 2.2 Confirm Brand / Storage / Colour attributes (auto-registered in `functions.php`); add terms.
- [ ] 2.3 Import `data/products-sample.csv`; set cost price, low-stock/reorder, brand, images.
- [ ] 2.4 Make phones variable products (Storage + Colour variations).

### Task 3 — Storefront theme (match the design)
- [x] 3.1 Foundation: tokens, fonts, global CSS, header, footer.
- [x] 3.2 Homepage (1a).
- [x] 3.3 Product card.
- [ ] 3.4 Product detail (1c).
- [ ] 3.5 Product listing + filters (1b).
- [ ] 3.6 Cart + Checkout (1d).
- [ ] 3.7 Order tracking / My Account (1g).
- [ ] 3.8 Mobile polish (1e/1f).

### Task 4 — Payments, shipping, tax
- [ ] 4.1 Enable Cash on Delivery + Direct bank transfer.
- [ ] 4.2 Sri Lanka shipping zone: flat Rs 350, free over Rs 10,000, local pickup.
- [ ] 4.3 Card gateway (PayHere/WebXPay) live keys; tax off unless registered.

### Task 5 — Invoices & transactional email
- [ ] 5.1 PDF Invoices attached to order emails; packing slips.
- [ ] 5.2 WP Mail SMTP via real sender; brand WooCommerce emails.

### Task 6 — Inventory & procurement (ATUM UI)
- [ ] 6.1 Stock dashboard, low-stock/reorder flags, stock history.
- [ ] 6.2 Suppliers.
- [ ] 6.3 Purchase Order flow (create → send).
- [ ] 6.4 Goods Receipt (GRN) → stock auto-increment; PO partially/fully received.

### Task 7 — Reports, roles, hardening
- [ ] 7.1 WooCommerce Analytics (revenue, orders, top products, category split, profit via cost).
- [ ] 7.2 Roles: Administrator, Shop Manager, custom Sales & Warehouse.
- [ ] 7.3 Rank Math SEO basics; Wordfence; UpdraftPlus daily backups; LiteSpeed caching.

### Task 8 — Test & go live
- [ ] 8.1 End-to-end test (browse → cart → COD checkout → stock deduct → email + PDF → tracking;
  PO → GRN → stock up; reports show numbers).
- [ ] 8.2 Mobile check on a real phone.
- [ ] 8.3 Migrate sandbox → real hosting (Hostinger/LankaHost) + domain + SSL + live card gateway.
- [ ] 8.4 Remove test/sample data; final launch checklist (`docs/IMPLEMENTATION-GUIDE.md` §7).

---

## Conventions

**Design system (Modernist) — follow everywhere on the storefront:**
- Zero border radius. Strong **2px** section rules; 1px row rules.
- Visible grids via `display:grid; gap:2px; background:var(--color-divider)` (the gap is the line).
- Flush-left everything (incl. button labels; wide buttons = label left / icon right).
- One accent (red `#ec3013`), used sparingly. Photography is grayscale.
- Single typeface **Archivo** (400/600/800). Tokens: `assets/css/tokens.css`.

**Code vs. data:**
- Git tracks **code only** — the `cellcraze` theme, scripts, docs. It does **not** track the
  WordPress **database** (plugins, products, categories, settings, orders). Those live on the
  running site; back them up via host/export.

**Where things go:**
- Theme: `wp-content/themes/cellcraze/`. WooCommerce template overrides: `.../cellcraze/woocommerce/`.
- Reuse helpers in `functions.php`: `cellcraze_stock_indicator()`, `cellcraze_brand()`,
  `Cellcraze_Flat_Walker`.
- Back office (dashboard, PO, GRN, reports) = ATUM + WooCommerce Analytics UI, not custom-reskinned.

**Workflow:** `docs/DEV-WORKFLOW-MAC.md` (GitHub ↔ VS Code ↔ Local) and
`docs/MULTI-SITE-WORKFLOW.md` (managing nuvirahub / nuvirashop / cellcraze together).

**Before each commit:** `php -l` every changed PHP file. One task ≈ one commit on the work branch.
