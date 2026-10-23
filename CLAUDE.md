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
- [x] 3.4 Product detail (1c).
- [x] 3.5 Product listing + filters (1b).
- [x] 3.6 Cart + Checkout (1d).
- [x] 3.7 Order tracking / My Account (1g).
- [x] 3.8 Mobile polish (1e/1f) incl. PDP sticky add-to-cart bar.

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

**Design system (White / Premium) — follow everywhere on the storefront:**
_(This replaced the earlier "Modernist" direction on 2026-10-18 by owner decision —
a full pivot to a white, premium, Apple-store-calm + modern-SaaS look.)_
- White (`#ffffff`) and soft off-white (`#f6f7f9`) surfaces; charcoal ink (`#1d1d1f`),
  muted grey secondary (`#6e6e73`); hairline grey borders (`#e6e8eb`).
- Soft radii (`--radius-sm 8 / --radius-md 12 / --radius-lg 18`, pill for chips);
  subtle, cool, low-contrast shadows; real colour product photography (no grayscale).
- One restrained accent — **CellCraze brand RED `#e3301a`** (hover `#d12a14`, pressed / small
  red text `#b01f08`), with **brand ORANGE `#f26a21`** as the secondary accent — for primary
  actions, badges & key status, plus semantic success/warning/danger. Generous whitespace, clear
  hierarchy. _(Accent switched from blue to the logo's red+orange on 2026-10-23 by owner decision;
  surfaces stay White/Premium.)_

**Brand palette (from the CellCraze logo — `assets/img/cellcraze-logo.jpg`):**
| Role | Token | Hex |
|---|---|---|
| Primary accent (red / "CRAZE") | `--color-accent` | `#e3301a` |
| Accent hover / pressed / small-text | `--color-accent-600/700` | `#d12a14` / `#b01f08` |
| Secondary accent (orange / phone screen) | `--color-accent-2` | `#f26a21` |
| Brand dark (logo bg, dark sections, footer lockup) | `--color-brand-dark` | `#1b1917` |
| Brand cream (logo "CELL") | `--color-brand-cream` | `#e9e2d4` |
| Surfaces (unchanged) | `--color-bg` / `--color-surface` | `#ffffff` / `#f6f7f9` |
| Ink / muted | `--color-text` / `--color-text-muted` | `#1d1d1f` / `#6e6e73` |
Full 100–900 red + orange ramps live in `assets/css/tokens.css` (source of truth). The logo is
wired into the theme header/footer (`.cc-logo-img`); also set it in Appearance → Customize on the
live site.
- Selective glassmorphism (translucent white + blur + fine border) is allowed on the
  storefront too now — sticky header, overlays — but never at the cost of readability,
  and never on every card. Provide an opaque `@supports` fallback.
- Single typeface **Archivo** (400/500/600/700/800). Tokens: `assets/css/tokens.css`
  (source of truth) → `assets/css/cellcraze.css` (screens). Keep class names stable so
  WooCommerce hooks/templates keep working; change the visual language, not the markup.

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

---

## UI/UX Design Standards (permanent)

Apply these to **every** task that builds, modifies, refactors or improves any interface —
customer storefront, account, and the admin / POS / inventory / IMEI / purchasing / orders /
delivery / reports screens **we control**. Backend-only tasks must leave the UI untouched.

**Precedence (read this first):**
1. The **"White / Premium" design system** (`assets/css/tokens.css` = source of truth,
   `assets/css/cellcraze.css` = storefront screens, `cellcraze-core/assets/css/cc-admin.css`
   = admin) governs **all** surfaces — customer storefront *and* admin/POS/dashboard. Its rules:
   **white/off-white surfaces, charcoal ink, brand RED accent `#e3301a` + ORANGE secondary
   `#f26a21` + semantic statuses, soft radii, hairline borders, subtle shadows, real colour
   photography, Archivo.** (Brand palette table above is the authority.) Rounded
   corners and *selective* glassmorphism are now allowed on the storefront too (header/overlays),
   with opaque fallbacks and never at the cost of readability.
2. The standards below set the **universal quality bar** (hierarchy, spacing, accessibility,
   responsive, states) across every surface. Keep storefront **class names** stable so
   WooCommerce hooks/templates keep working — restyle, don't restructure markup.
3. Functional correctness, accessibility, readability, security and business usability **always**
   outrank visual decoration.

**Overall direction:** modern, premium, professional, trustworthy — a real commercial product, not
a default WordPress/admin look. Inspect the existing design system before adding any new style.

**Visual quality (all surfaces):** deliberate layout & spacing; clear visual hierarchy; consistent
typography and sizing; cohesive palette; accessible contrast; clean alignment and consistent
component sizing; refined borders and subtle shadows (radii per precedence above); consistent,
professional icons; consistent product imagery; designed loading / empty / error / success states.

**Glassmorphism:** admin/POS/dashboard only, and only where it helps (floating nav, modal overlays,
contextual panels, hero/dashboard sections). Subtle translucency + blur + fine borders; never on
every component; never at the cost of readability, contrast or performance. Never on the storefront.

**Reusable components:** prefer existing theme components and helpers
(`cellcraze_stock_indicator()`, `cellcraze_brand()`, `Cellcraze_Flat_Walker`, cards, chips, buttons)
and improve them rather than duplicating. Keep consistent padding, spacing, borders, radii,
typography and interaction states across product/category/stat/dashboard cards, status & filter
chips, tabs/segmented controls, search bars, dropdowns, buttons (clear hierarchy), labelled+validated
inputs, modal/confirmation dialogs, readable tables, toasts/inline feedback, breadcrumbs, pagination,
skeleton loaders and empty states. No decorative card/chip/badge without a functional reason.

**Motion:** subtle, fast, purposeful transitions (hover/focus, button feedback, card hover,
dropdown/modal, tabs/nav, loading/progress). No decorative or distracting movement, no parallax, no
animation that delays an action. Honor `prefers-reduced-motion`.

**Responsive:** must work on phone, tablet, laptop, desktop — responsive layouts, sensible
breakpoints, touch-friendly targets, readable type. Grids, nav, tables, forms, dashboards and POS
screens adapt to their intended devices. Admin/cashier UIs prioritise speed, readability and
efficient daily operation. Never assume desktop-only.

**E-commerce UX:** make it easy to discover, compare, understand availability, pick variants, see
warranty, add to cart, check out with minimal friction, choose delivery/payment, understand
order/payment status, track delivery, and request support/returns/warranty. Clear CTAs, helpful
microcopy, descriptive labels, understandable errors.

**Admin / inventory / POS UX:** accuracy, efficiency, clarity. Clear tables with search/sort/filter,
status chips, quick actions, useful dashboard summaries, clear stock/order indicators, good forms,
**confirmation dialogs for destructive or financial actions**, visible validation + success/error
feedback, loading/empty states. **SKU, IMEI, order number and transaction reference must be easy to
find and read.** Never use colour as the *only* status signal. Never hide business-critical info
behind decoration. (Reminder: **never expose available-stock IMEI serials to customers**; a
customer sees only their own purchased unit's warranty/IMEI.)

**Accessibility:** sufficient contrast, keyboard operability, visible focus, correct form labels,
clear validation, accessible button names, adequate touch targets, clear hierarchy, reduced-motion
support.

**Technical consistency:** before UI changes, inspect current theme/components/conventions; reuse
and improve existing components; avoid duplicate/conflicting CSS; follow WordPress/WooCommerce
conventions and the repo architecture; never edit third-party plugin files (use the theme or
`cellcraze-core` extension points); keep it maintainable, performant, secure; test responsive
behaviour. **Do not add a new frontend/CSS/animation/UI library without checking compatibility and
explaining why.** If a third-party theme/plugin UI can't be safely customised, state the limitation
and propose a compatible solution.

**Mandatory pre-completion UI/UX review** — a feature is not "done" until, for any UI it touches:
reuse relevant components; apply these standards; ensure visual consistency; verify
mobile/tablet/desktop; verify hover/focus/active/disabled/loading/success/error states; verify
accessibility; remove inconsistent/duplicated styling the change introduced; run available tests;
and report any unresolved UI/UX issues. Do not ship a working feature with default/unstyled/
inconsistent UI when styling is in scope.

**Quality gate (ask before declaring a UI task done):** does it look like a modern professional
commercial site? Is hierarchy clear? Are spacing/type/colour/components consistent? Are glass &
motion purposeful not excessive? Is it responsive? Is the workflow easy for customer/owner/cashier?
Are loading/empty/error/success states handled? Is existing functionality preserved? Are the
relevant interactions tested? If any important answer is "no", improve before completing.

These are permanent project standards — apply them to every relevant task without being re-asked.
