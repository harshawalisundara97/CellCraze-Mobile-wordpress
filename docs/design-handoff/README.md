# Handoff: CellCraze — Storefront, Mobile & Back Office UI

## Overview
UI design for **CellCraze**, a mobile phone & accessories shop (phones, headphones, earphones, chargers, smartwatches, accessories). Covers the customer storefront (desktop + mobile) and the admin back office. It pairs with the engineering plan (Next.js 14 App Router, TypeScript, Prisma/PostgreSQL, NextAuth, Tailwind + shadcn/ui, Zustand, Stripe, Recharts).

9 screens:
| ID | Screen | Route (per plan) |
|---|---|---|
| 1a | Homepage (desktop) | `(storefront)/page.tsx` |
| 1b | Product listing — Phones, with filters | `(storefront)/products/page.tsx`, `categories/[slug]` |
| 1c | Product detail | `(storefront)/products/[slug]/page.tsx` |
| 1d | Checkout (delivery + payment) | `(storefront)/checkout/page.tsx` |
| 1e | Mobile homepage (390px) | same as 1a, responsive |
| 1f | Mobile product detail, sticky add-to-cart | same as 1c, responsive |
| 1g | Mobile order tracking | `(storefront)/orders/[id]/page.tsx` |
| 1h | Admin dashboard | `admin/page.tsx` |
| 1i | Admin — create GRN against a PO | `admin/grn/new` |

## About the Design Files
The files in this bundle are **design references created in HTML** — prototypes showing intended look and behavior, **not production code to copy**. Recreate them in the target codebase (Next.js + Tailwind + shadcn/ui per the plan) using its patterns: shadcn primitives restyled to these tokens, Tailwind theme extended with the values below, Lucide icons (`lucide-react`).

To view: open `CellCraze Mockups.dc.html` in a browser (keep the folder structure — it loads `support.js`, `image-slot.js` and `_ds/.../styles.css` relatively). All screens sit on one pan/zoom canvas, labelled 1a–1i. All sample data (product names, prices, people, order/PO numbers) lives in the `class Component` script at the bottom of the file and should be replaced by real API data.

## Fidelity
**High-fidelity.** Final colors, type, spacing, layout and core interactions. Recreate pixel-accurately. Product imagery is placeholder boxes (real photos to be supplied via Cloudinary).

---

## Visual Language (the "Modernist" system) — rules to follow everywhere
- **Zero border radius.** Everywhere. Set shadcn `--radius: 0`.
- **Strong 2px rules** (`--color-divider`) separate all major sections; 1px of the same color separates rows inside a section.
- **Visible grid:** product/category/KPI grids are built as `display:grid; gap:2px; background:var(--color-divider)` with each cell `background:var(--color-bg)` — the 2px gap *is* the grid line. Grids run full-bleed with a 2px top+bottom border.
- **Flush-left everything**, including button labels. Wide buttons use `justify-content:space-between` → label left, icon right. Never centre labels or hero copy.
- **One accent (red), used sparingly:** primary actions, discount badges, low-stock/alert signals, active nav marker, current step. Everything else is ink on paper.
- **Photography is grayscale:** wrap every product/hero image in a container with `filter: grayscale(1) contrast(1.08)`.
- **Status is mono, not traffic-light:** square markers in ink / red / grey (see Status markers).
- **Small square marks** (8–14px solid squares) are the brand's bullet / logo mark / stock dot — no circles except radio dots.
- Single typeface: **Archivo** (400, 600, 800).

## Design Tokens

### Colors
| Token | Hex | Use |
|---|---|---|
| `--color-bg` | `#f3f2f2` | Page ground, card cells |
| `--color-surface` | `#eae9e9` | Image wells, inputs, selected rows, order summary panel |
| `--color-text` | `#201e1d` | Ink: text, dark bars, filled tags, top utility bar bg |
| `--color-accent` | `#ec3013` | Primary buttons, badges, alerts, marks |
| `--color-accent-600` | `#dd2b0f` | Primary hover |
| `--color-accent-700` | `#ae1800` | Primary pressed; **any small text in red** (kickers, "Deals", counts) — base accent fails 4.5:1 for small text |
| `--color-accent-100` | `#fff2ef` | `.tag-accent` background |
| `--color-accent-800` | `#7c1405` | `.tag-accent` text |
| `--color-divider` | `#201e1d` @ 40% (`color-mix(in srgb,#201e1d 40%,transparent)`) | All rules, input borders, secondary-button borders |
| `--color-neutral-200` | `#eae7e7` | Progress-bar tracks |
| `--color-neutral-300` | `#d7d3d3` | Inactive carousel dashes, pending timeline line |
| `--color-neutral-400` | `#bab6b6` | Out-of-stock dot, pending markers |
| `--color-neutral-500` | `#9b9797` | Pending status outline |
| `--color-neutral-800` | `#444141` | Chart bars |
| `--color-neutral-100` / `-800` | `#f8f4f4` / `#444141` | `.tag-neutral` bg / text |
| Muted text | ink @ 70% (`color-mix(in srgb,var(--color-text) 70%,transparent)`) | Meta, labels, counts (≈5.8:1). 75–78% for paragraph copy, 60% for strikethrough prices |

Full 100–900 ramps for neutral and accent are in `design-tokens.css`.

### Typography (Archivo, Google Fonts, weights 400/600/800)
| Role | Size / line-height | Weight | Letter-spacing |
|---|---|---|---|
| Hero display (desktop) | 76px / 1.02 | 800 | -0.03em, margin-left -0.05em (optical) |
| Hero display (red poster variant) | 104px / 0.98 | 800 | -0.035em |
| Hero display (mobile) | 42px / 1.02 | 800 | -0.03em |
| Page title (listing H1) | 56px | 800 | -0.03em |
| PDP H1 | 44px (mobile 30px) | 800 | -0.025em |
| Admin page H1 | 30px | 800 | -0.02em |
| Section H2 | 32px (mobile 22px) | 800 | -0.015em |
| H3 | 25px; H4 20px | 800 | -0.015em |
| KPI value | 34px | 800 | -0.02em |
| Price — PDP | 36px (mobile 26px) | 800 | -0.02em |
| Price — card | 19px (mobile 16px) | 800 | — |
| Product name — card | 16px / 1.25 (mobile 14px) | 800 | — |
| Body | 15px / 1.55 | 400 | — |
| Lead paragraph | 17px / 1.6, max 44ch | 400 | — |
| UI text / nav | 14px | 600 (nav), 400 | — |
| Kicker / eyebrow / table header | 11–13px, UPPERCASE | 400–600 | 0.08em |
| Meta | 12px | 400 | — |
| Numbers in tables/IDs | `font-feature-settings: "tnum" 1` | | |
Headings: line-height 1.12, margin 0 0 8px by default.

### Spacing
Scale: 4, 8, 12, 16, 24, 32px (`--space-1..8`). Layout values used: desktop page gutter **40px**; admin content gutter **32px**; mobile gutter **16px**; grid cell padding **20px** (desktop), **12px** (mobile); section top spacing **48–56px**; header row padding **18px 40px**.

### Radius & shadows
Radius: **0** everywhere. Shadows (rarely used; only dialogs): `--shadow-sm: 0 1px 2px #2d2b2b24`, `--shadow-md: 0 3px 10px #2d2b2b29`, `--shadow-lg: 0 12px 32px #2d2b2b38`.

### Tailwind mapping (suggested)
```ts
// tailwind.config.ts → theme.extend
colors: {
  bg: '#f3f2f2', surface: '#eae9e9', ink: '#201e1d',
  accent: { DEFAULT:'#ec3013', 100:'#fff2ef', 600:'#dd2b0f', 700:'#ae1800', 800:'#7c1405' },
  neutral: { 100:'#f8f4f4',200:'#eae7e7',300:'#d7d3d3',400:'#bab6b6',500:'#9b9797',800:'#444141' },
  divider: 'rgb(32 30 29 / 0.4)',
},
fontFamily: { sans: ['Archivo','system-ui','sans-serif'] },
borderRadius: { none:'0', DEFAULT:'0', sm:'0', md:'0', lg:'0' },
borderWidth: { rule: '2px' },
```

## Core Components

**Button** (14px, weight 800, line-height 1.2, padding 8px 14.4px, radius 0, gap 6px, 1px transparent border)
- Primary: bg accent, text `--color-bg`; hover accent-600; active accent-700.
- Secondary: transparent, 1px divider border; hover ink 7% tint; active 14%.
- Ghost: text accent, padding-x 4px; hover accent 10% tint.
- Icon: 36×36 (44×44 on mobile).
- Large CTA: min-height 48–52px, padding 0 20px, 15px text, `justify-content:space-between` with trailing Lucide icon 18px.
- Disabled: opacity .45. Focus: `outline:2px solid accent; outline-offset:2px` (no browser blue ring).

**Input**: min-height 36px (42–44px in headers/mobile), padding 6px 10px, 14px, bg surface, 1px divider border, radius 0; hover border ink 45%; focus border accent. Field label 12px, ink 70%, 5px below-gap. Search inputs have a Lucide `search` icon absolutely placed at left 13px, input padding-left 40px.

**Segmented control** (`.seg`): inline-flex, 1px divider border; options 7px 12px, 13px text, 1px divider separators; selected = accent fill + bg-colored text; hover ink 7% tint. Built on hidden native radios.

**Radio**: 16px circle, 1.5px divider border; checked = accent fill with 4px inset ring of bg.

**Checkbox** (custom): 18px square, 1.5px divider border; checked = accent fill + white Lucide `check` 12px (stroke 3.5).

**Tags**: 11px, padding 3px 10px, radius 0. `tag-accent` (accent-100 / accent-800), `tag-neutral` (neutral-100 / neutral-800), `tag-outline` (1px accent border, accent text). Discount badge on images: solid accent bg, bg-colored text, weight 600, pinned top-left at 0,0.

**Table**: 14px, collapsed; th 11px uppercase 0.08em ink 60%, padding 8px, 2px divider bottom; td padding 8px, 1px divider bottom; row hover ink 4%.

**Product card** (grid cell, padding 20px, column, gap 14px): 1:1 grayscale image well (surface bg, `object-fit:contain`) with optional discount badge → brand (11px uppercase, ink 70%) + name (16px/800) → price (19px/800) + compare-at price (13px, line-through, ink 60%) → stock line (8px square + 12px label) → full-width secondary button "Add to cart" with trailing `plus` icon, label left. Out-of-stock: label "Out of stock", grey square, button "Notify me".

**Stock indicator** (from `stockQuantity` vs threshold): `0` → "Out of stock", neutral-400 square; `1–5` → "Only N left", accent square; `>5` → "In stock", ink square. (Threshold 5 is a design assumption; can use `reorderPoint`.)

**Status markers** (orders, 10px square + label, 2px border):
| Status | Fill | Border |
|---|---|---|
| PENDING | transparent | neutral-500 |
| CONFIRMED | neutral-400 | neutral-400 |
| PROCESSING | transparent | accent |
| SHIPPED | accent | accent |
| DELIVERED | ink | ink |
| CANCELLED (not shown) | suggest transparent + neutral-400 + line-through label |

**Logo**: 14×14 accent square + "CellCraze" Archivo 800 22px, -0.02em (19–20px in admin/mobile/footer).

**Icons**: Lucide, 16–24px, `stroke-width:2`, `stroke-linecap:square`. Used: search, user, heart, shopping-bag, arrow-right, plus, minus, check, chevron-down, chevron-left, truck, credit-card, shield-check, lock, triangle-alert, download, menu.

---

## Screens

### Shared desktop storefront header (1a, 1b, 1c) — width 1280 design, fluid
1. **Utility bar**: bg ink, text bg color, 12px, padding 8px 40px, flex gap 32px: "Free delivery over Rs 10,000" · "Cash on delivery available" · "Official warranty on every device" · right-aligned "Track your order". (Homepage only in mock; fine sitewide.)
2. **Main bar**: grid `220px | 1fr | auto`, gap 32px, padding 18px 40px, 2px rule below. Logo · search input (42px tall, placeholder "Search phones, earbuds, chargers…") · actions: ghost "Sign in" (user icon; shows first name when logged in), heart icon button, primary "Cart" button with bag icon and a count chip (bg-colored chip, accent-700 text, 12px, min-width 20px).
3. **Category bar**: flex gap 28px, padding 12px 40px, 14px/600, 2px rule below: Phones, Headphones, Earphones, Chargers, Smartwatches, Accessories, right-aligned "Deals" in accent-700. Active category text accent-700.

### 1a Homepage
1. **Hero (default "Photo split")**: 2 equal columns, 2px rule between and below. Left padding 80/40/64: kicker "New this week — Galaxy S25 series" (13px uppercase accent-700) → H1 76px "Phones, sound and power. **In stock today.**" (second sentence in accent) → lead 17px "Genuine flagships and the accessories that go with them — warranty-backed, delivered nationwide, paid the way you like." → primary "Shop phones →" (48px) + secondary "Browse accessories". Gap 28px. Right: grayscale hero image, min-height 540px, full-bleed to the cell edges.
   - **Variant "Red poster"**: full accent field, columns 7fr/5fr, all text bg-colored; H1 104px "In stock today."; 24px/600 subline; primary button inverted (bg fill, ink text); secondary = 1px bg-colored outline; image inset 40px.
2. **Shop by category**: header row (H2 32px left, "All categories" 14px/600 right; padding 56/40/20). 6-column ruled grid; each cell: index "01"… (12px tnum, ink 70%), 1:1 image well, name 18px/800 + "N products" 12px, arrow-right icon bottom-right.
3. **Featured**: header "Featured" / "View all"; 4-column ruled grid of product cards.
4. **New arrivals**: grid 4fr/8fr, padding 56/40. Left: H2 + "Landed in store this week. Limited first stock." Right: ruled list (2px top), rows grid `40px | 72px | 1fr | 140px | 140px | 24px`, gap 20px, padding 14px 0, 1px row rules: index · 72px thumb · brand+name · category · price · arrow.
5. **Service band**: 3-column ruled grid, cell padding 28px 40px, 24px accent Lucide icon + title 17px/800 + 14px copy: "Delivered in 2–4 days" / "Card, cash or transfer" (Stripe, COD, bank) / "Genuine, with warranty".
6. **Footer**: grid `4fr 2fr 2fr 2fr`, gap 40px, padding 48/40/32, 14px. Logo + blurb; columns Shop / Help / Account with 11px uppercase headings. Bottom bar 2px rule, 12px ink 70%: "© 2026 CellCraze" · payment methods list.

### 1b Product listing (Phones)
- Breadcrumb 13px ink 70% "Home / Phones" (padding 20px 40px 0).
- Title row (2px rule below): H1 56px "Phones" + "48 products" 15px muted; right: "Sort" + segmented control [Featured | Price, low–high | Price, high–low | Newest].
- Body grid `280px | 1fr`; sidebar has 2px right rule. Sidebar groups (padding 24px 28px 24px 40px, 2px rules between, heading 11px uppercase 600):
  - Category tree: "Phones 48" (800) → indented 14px children Android 31, iPhone 14, Feature phones 3 (counts muted). Maps to hierarchical `Category.parentId`.
  - Brand: checkbox list with counts (Samsung checked).
  - Price: two inputs Min / Max side by side.
  - Storage: toggle chips 128GB / 256GB (selected = ink fill, bg text) / 512GB / 1TB (`tag-outline`, padding 6px 12px, 13px).
  - "In stock only" checkbox.
- Main: active filter chips row (padding 16px 24px): "Filtered by" + `tag-neutral` chips "Samsung ✕", "256GB ✕", "In stock ✕" + "Clear all" (accent-700, 600). Then 3-column ruled grid of product cards. Pagination row (padding 24px): 40×40 squares, current page ink fill; "Next page →" secondary button right.

### 1c Product detail
- Header (no utility bar / category bar in mock), breadcrumb row with 2px rule: "Home / Phones / Samsung / Galaxy S25 Ultra".
- Main grid `7fr | 5fr`, 2px rule between and below.
  - Gallery (padding 32px 40px): 1:1 main image; 4-column thumb row, 2px gaps; active thumb has 2px inset ink ring.
  - Buy box (padding 40px, column gap 24px): kicker "Samsung" (accent-700) → H1 "Galaxy S25 Ultra" → "SKU SM-S938B · Official Samsung warranty" (12px tnum muted) → price 36px + compare-at strike + `tag-accent` "Save Rs 30,000" → 2px rule → "Colour — **Titanium Black**" + segmented [Titanium Black | Titanium Gray | Titanium Silverblue] → "Storage" + segmented [256GB | 512GB | 1TB] (padding 9px 18px) → stock line "In stock — 12 units, ships today" → action row: quantity stepper (1px divider box, −/+ icon buttons 44×48, value 800 tnum) + primary Add to cart (flex 1, 50px, label left "Add to cart", live line total right) + 50×50 secondary heart button → info rows (2px top rule, 1px row rules, 12px 0, label 120px bold): Delivery / Payment / Warranty.
- **Specifications**: grid 4fr/8fr, H2 left; right a key/value list (2px top rule, rows `200px | 1fr`, 1px rules, 15px, key muted). Rendered from `Product.specifications` JSON.
- **Goes well with**: 4-column ruled grid of product cards (related/accessories).

### 1d Checkout
- Minimal header: logo left; "Secure checkout" (lock icon 13px) and "Back to cart" (600) right.
- Step bar: 3 equal cells with 2px rules: "01 Cart" (done: 24px ink square with check), "02 Delivery & payment" (current: 800 weight, 24px accent square with "02", 4px accent bar on bottom edge via inset shadow), "03 Confirmation" (upcoming: outlined square, muted).
- Body grid `8fr | 4fr`.
  - Left (padding 40px, gap 40px):
    - **Delivery address**: H3 + ghost "+ Add new address". 2-column selectable address cards (padding 18px, radio + text 14px/1.5). Selected: 2px inset ink ring + surface bg; unselected: 1px inset divider ring. Default address shows `tag-neutral` "Default". Data from `Address` model.
    - **Payment method**: stacked selectable rows, same selected/unselected treatment: "Card or digital wallet" (Visa, Mastercard, Apple Pay, Google Pay — completes on Stripe Checkout), "Cash on delivery", "Bank transfer" (ships once transfer clears). Maps to `Payment.method` STRIPE | COD | BANK_TRANSFER.
    - Order notes textarea (optional) → `Order.notes`.
  - Right summary panel (surface bg, padding 40px, gap 20px): H3 "Order summary"; item rows (56px thumb, name 14px/800, "variant · Qty N" 12px, line total right); Subtotal / Delivery "Free"; 2px rule; Total 28px/800; full-width primary button (52px) whose label depends on method:
    - STRIPE → "Pay {total} with Stripe" → create Checkout Session & redirect
    - COD → "Place order — pay on delivery"
    - BANK_TRANSFER → "Place order — pay by transfer"
    - Fine print 12px: "Card details are handled by Stripe and never stored by CellCraze."

### 1e Mobile homepage (390px)
Header (padding 10px 12px, 2px rule): menu icon button 44×44 · logo 19px · cart icon button with accent count badge (16px square, 10px/800). Search row (padding 12px 16px, 44px input). Hero (padding 32/16/24, gap 16): kicker, H1 42px, 4:3 grayscale image, full-width primary "Shop phones →" 48px. "Shop by category" H2 22px → 3-column ruled grid (cell padding 12px, 1:1 image, name 13px/800). "Featured" → 2-column ruled grid of compact cards (name 14px, price 16px, stock 11px, button "Add" 44px). Trust list: three rows with 8px accent squares, 13px.

### 1f Mobile product detail (390×844)
Header: back chevron · breadcrumb "Phones / Samsung" · heart · cart with badge. 1:1 image; carousel indicator = 24×4px dashes (active ink, others neutral-300). Brand kicker, H1 30px, price 26px + strike, full-width segmented storage control (options flex:1, 44px tall), stock line, short spec list (rows `96px | 1fr`, 13px). **Sticky bottom bar** (absolute/fixed bottom, 2px top rule, bg, padding 12px 16px 20px): "Total" label + price 18px/800, primary "Add to cart +" flex 1, 50px.

### 1g Mobile order tracking (390×844)
Header back + "My orders". Status block (2px rule): "Order CC-10482 · Placed Tue 6 Oct" (12px tnum muted), H1 34px "Shipped." (current status word), "Arriving **Friday 9 October**". Vertical timeline (grid `20px | 1fr`, gap 14px): 14px square markers (done = ink fill; current = accent fill; upcoming = transparent with neutral-400 border, text at 60% opacity) joined by a 2px vertical line (ink for completed segments, neutral-300 after current). Steps: Order placed → Payment confirmed → Processing → Shipped (courier ref) → Delivered. Item rows (48px thumb), "Paid by card {total}", full-width secondary "Download invoice" with download icon.

### Shared admin layout (1h, 1i) — 1280 design
Grid `232px | 1fr`. **Sidebar** (2px right rule, padding 20px 0, 14px): logo 19px + "BACK OFFICE" 11px uppercase muted. Groups with 11px uppercase headings: Overview (Dashboard) · Catalog (Products, Categories) · Sales (Orders, Invoices) · Supply (Inventory, Suppliers, Purchase orders, Goods received) · Insights (Reports) · Store (Settings). Items padding 7px 20px, an 8px square marker (accent when active, else transparent), active row surface bg + 800 weight; optional count right in accent-700 800 12px (Orders "12" pending, Inventory "9" alerts). Hide Suppliers/Settings/Users for MANAGER role per the access matrix.

### 1h Admin dashboard
- Top bar (padding 20px 32px, 2px rule): H1 "Dashboard" + date "Wednesday, 7 October 2026" 13px muted; right: segmented [7D | 30D | 90D], secondary "Export CSV" (download icon), 36px ink avatar square with initials.
- KPI row: 4-column ruled grid, cell padding 22px 24px: label 11px uppercase muted, value 34px/800, delta 12px. "Revenue today" / "Orders today" / "New customers" / "Stock alerts" (value in accent; "3 out of stock · 6 below reorder point"). → `/api/admin/dashboard/stats`.
- Row grid `8fr | 4fr` (2px rules):
  - **Sales chart** (padding 24px 32px): H4 "Sales — last N days", "N orders" muted; total 26px right. Bar chart 220px tall: one bar per day, flex:1, neutral-800 fill, **today's bar accent**; 3 horizontal 1px divider gridlines at 0/33/66%; 2px ink baseline; bar gap 14px (7D) / 5px (30D) / 2px (90D). X labels: start date, mid date, "Today" (12px muted). Implement with Recharts `BarChart` (radius 0, no tooltip chrome beyond ink/bg). → `/api/admin/dashboard/sales-chart?range=`.
  - **Sales by category**: rows of name + % (800), 6px bar on neutral-200 track, ink fill, width relative to largest.
- Row grid `7fr | 5fr`:
  - **Recent orders**: `.table` columns Order (800 tnum) · Customer · Payment · Status (marker) · Total (right, 800). "All orders" link. → `/recent-orders`.
  - **Low stock**: H4 with accent `triangle-alert` icon, primary "Create PO" (32px). Rows (2px top rule, 1px rules, padding 11px 0): name bold + "N left" / "Out of stock" (accent when 0, else ink, 800), 4px bar (stock ÷ reorderPoint, min 3%), "SKU · reorder point N" 11px; ghost "Reorder" right → prefilled PO. → `/inventory/low-stock`.

### 1i Admin — new GRN
- Top bar: breadcrumb "Goods received / New", H1 "GRN-2026-0091" (tnum) + `tag-neutral` "Draft"; right: secondary "Save draft", primary "Confirm & add to stock ✓".
- Header info: 4-column ruled grid (padding 18px 24px): Purchase order select (input "PO-2026-0148" + chevron-down; choose from SENT / PARTIALLY_RECEIVED POs) · Supplier name + "Sent 29 Sep · Contact: …" · Received date input · "PO status after confirming" `tag-accent` (live: "Partially received" / "Fully received").
- Lines `.table` (padding 8px 32px 0): Product (name 800 + SKU 12px tnum muted) · Ordered · Prev. received · **Received now** (number input 88px, 800) · **Rejected** (number input 88px) · Adds to stock (`+N`, 800) · Still due (accent-700 when >0; "Complete" when fully received earlier). Fully-received lines render at 45% opacity with inputs disabled.
- Totals: ruled grid `1fr 1fr 1fr 2fr` (margin-top 32px): Received (30px/800) · Rejected (accent) · Adds to stock (+N) · plain-language note "Confirming adds N units to stock and marks PO-2026-0148 as partially received."
- Notes textarea "Notes for this delivery".

---

## Interactions & Behavior
- **Add to cart** (cards, PDP, mobile bar): increments header cart count (Zustand `cart.store`) — call `POST /api/cart/items` with stock check; out-of-stock cards show "Notify me" (no-op in mock).
- **PDP storage segment**: changes price, compare-at price, line total and the "Storage" spec row. (Mock prices 256GB 389,900 / 512GB 429,900 / 1TB 499,900; compare-at = price + 30,000.) In code: variants likely separate `Product` rows/SKUs or a variant model.
- **Quantity stepper**: min 1, max 12 in mock (use available stock). Add-to-cart button shows `price × qty`.
- **Checkout payment method**: selecting a row updates selected styling and the submit label (see 1d). Address cards are single-select.
- **Dashboard range (7D/30D/90D)**: swaps chart data, totals, order count, axis labels.
- **GRN inputs**: received clamped to `0 … ordered − prevReceived`; rejected clamped to `0 … received`. Adds-to-stock = received − rejected. Still-due = ordered − prev − received. PO status after confirm = all lines due ≤ 0 → FULLY_RECEIVED else PARTIALLY_RECEIVED. (Note: the plan increments stock by `receivedQty`; this design shows **received − rejected** as what enters stock — confirm with the business and align `grn.service.ts`.)
- **Hover/press/focus**: as per Button/Input specs; table rows hover ink 4%; grid cells may take an ink 4% hover tint. Focus ring always 2px accent, offset 2px.
- **Transitions**: none specified; if adding, keep to ≤150ms color/background fades. No movement/scale effects (system is flat).
- **Loading**: suggest surface-colored skeleton blocks in the same grid cells (no shimmer gradients).
- **Errors/validation**: input border accent + 12px accent-700 message below; Zod schemas per plan.
- **Responsive**: desktop designed at 1280; mobile at 390 (plan requires no horizontal scroll at 375). Grids collapse 6→3 (categories), 4/3→2 (products), filters move to a drawer/sheet on mobile, PDP becomes single column with sticky add-to-cart bar, checkout summary stacks below form. Admin is desktop-first; collapse sidebar under ~1024px.

## State Management (from the prototype)
- `cartCount` (global, Zustand; server cart source of truth)
- PDP: `selectedVariant/storage`, `selectedColour`, `qty`
- Checkout: `selectedAddressId`, `paymentMethod: 'STRIPE'|'COD'|'BANK_TRANSFER'`, `notes`
- Listing: filters `{ category, brands[], priceMin, priceMax, storage[], inStockOnly }`, `sort`, `page` — keep in URL search params
- Dashboard: `range: 7|30|90`
- GRN form: `purchaseOrderId`, `receivedDate`, `lines[{ poItemId, receivedQty, rejectedQty, notes }]`, `notes` (React Hook Form + Zod)

## Copy / localisation
Mock assumes **Sri Lanka / LKR** ("Rs 389,900", Colombo addresses). Currency format: `Rs ` + en-US grouping, no decimals. The prototype has a USD toggle (Tweaks) for comparison. Centralise in `formatCurrency()` in `lib/utils.ts`.

## Assets
- No final imagery: all product, category and hero images are placeholders — supply real product photos (Cloudinary, displayed grayscale per the brand rule; transparent/white-background packshots work best with `object-fit: contain`).
- Icons: Lucide (`lucide-react`).
- Font: Archivo from Google Fonts (`next/font/google`, weights 400/600/800).
- Logo: CSS-only (accent square + wordmark); replace if a real logo exists.

## Files in this bundle
- `CellCraze Mockups.dc.html` — all 9 screens (open in a browser). The `class Component` script at the end holds all mock data and interaction logic.
- `design-tokens.css` — the Modernist design-system stylesheet: CSS variables (full color ramps, type, spacing, radius, shadows) + component classes (`.btn`, `.input`, `.seg`, `.radio`, `.tag`, `.table`, `.card`, `.grayscale`). Port these into Tailwind/shadcn.
- `_ds/…/styles.css`, `_ds/…/_ds_bundle.js`, `support.js`, `image-slot.js` — runtime files needed only to open the HTML mock.
