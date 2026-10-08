# CellCraze — implementing the mockups in WordPress

Plan to recreate the CellCraze "Modernist" design handoff (9 screens) and its full
shopping + inventory flow in **WordPress + WooCommerce**.

> The handoff was authored for a Next.js/Tailwind build. We recreate the **look and flow**
> in WordPress — we do not port the React code. Source of truth for styling:
> `design-tokens.css` and the handoff README (Archivo font, radius 0, 2px rules,
> grayscale imagery, single red accent `#ec3013`, flush-left everything).

## Strategy: two layers

| Layer | Screens | How in WordPress | Effort |
|---|---|---|---|
| **Storefront** (pixel-match the design) | 1a–1g | Custom theme (extend the `cellcraze` child theme) + WooCommerce template overrides + CSS from the design tokens | High, standard work |
| **Back office** (match the *function*, use plugin UI) | 1h, 1i | WooCommerce admin + **ATUM** (inventory, suppliers, PO, GRN) + WooCommerce **Analytics** | Low — install & configure |

Pixel-matching the WordPress **admin** to 1h/1i is a large custom build for screens only staff
see; not recommended initially. We revisit a custom admin dashboard only if you want it later.

---

## Data model mapping (design → WooCommerce)

| Design concept | WooCommerce / ATUM |
|---|---|
| Product, SKU, price, compare-at | WooCommerce product; Regular + Sale price |
| Brand (Samsung, Sony…) | Product attribute `Brand` (filterable) |
| Storage / Colour variants | **Variable product** with `Storage` & `Colour` attributes → variations, each its own SKU/price/stock |
| Category tree (Phones → Android/iPhone) | Hierarchical product categories (parent/child) |
| `stockQuantity`, reorder point, low-stock | WooCommerce stock + ATUM low-stock threshold / reorder point |
| Specifications (key/value) | Product attributes, or ACF fields rendered in a spec table |
| Order, status PENDING…DELIVERED | WooCommerce order statuses (custom-labelled to match) |
| Address, payment method STRIPE/COD/BANK | WooCommerce addresses + gateways (card / COD / BACS) |
| Supplier, Purchase Order, GRN | **ATUM** Suppliers, Purchase Orders, Inbound Stock / goods receipt |
| Dashboard KPIs, sales chart, category split | WooCommerce **Analytics** + ATUM dashboard |

---

## Screen-by-screen build (storefront)

**1a Homepage** → custom `front-page.php` in the theme:
hero (two-column / red-poster variant), "Shop by category" ruled grid, Featured products grid,
New arrivals list, service band, Modernist footer. Product data via WooCommerce queries
(`wc_get_products`), featured flag, newest by date.

**1b Product listing + filters** → override `archive-product.php` + `content-product.php`:
title row with result count and sort (segmented control), left sidebar with category tree,
**Brand** checkboxes, price min/max, **Storage** chips, "in stock only". Filtering via a
filter plugin (e.g. a WooCommerce product-filter plugin) or custom `pre_get_posts` + attribute
tax queries, state kept in URL params. 3-column ruled product grid + square pagination.

**1c Product detail** → override `single-product.php` and its parts:
gallery + thumbs, brand kicker, H1, SKU line, price + compare-at + "Save Rs X" tag, Colour +
Storage segmented controls (WooCommerce variations drive price/stock/line-total), quantity
stepper, Add-to-cart with live line total, wishlist (TI Wishlist), Delivery/Payment/Warranty
rows, Specifications table, "Goes well with" (related/up-sells).

**1d Checkout** → override `form-checkout.php` / `checkout/*`:
3-step bar, address cards (saved addresses, single-select), payment rows (Card / COD / Bank),
order notes, right-hand order-summary panel; submit-button label changes with method. COD +
BACS are core; card = a gateway (PayHere/WebXPay live, or Stripe).

**1e/1f Mobile** → responsive CSS on the same templates: grids collapse (6→3, 4/3→2), filters
to a drawer, PDP single-column with a **sticky bottom add-to-cart bar**.

**1g Order tracking** → customise My Account "view order": status headline, vertical timeline
built from order status history, item rows, "Download invoice" (PDF Invoices plugin).

## Back office (function via plugins)

**1h Dashboard** → WooCommerce **Analytics** (revenue, orders, top products, category split,
date ranges 7/30/90) + **ATUM** dashboard (stock alerts). Delivers every KPI/chart in the mock,
in the plugins' own UI.

**1i GRN / Purchase Orders** → **ATUM Suppliers + Purchase Orders + Inbound Stock**:
create a PO → send → receive goods (GRN) → stock increments and the PO moves to
partially/fully received. (Full line-level GRN with rejected-qty may need ATUM's PO Pro add-on.)

---

## Theme approach

Extend the existing **`cellcraze`** child theme (of Storefront):
1. Load **Archivo** (Google Fonts) and port `design-tokens.css` variables into the theme.
2. Global Modernist CSS: radius 0, 2px section rules, `display:grid;gap:2px` grid lines,
   flush-left, grayscale image wrapper, red accent, status markers, buttons/inputs/tags/tables
   per the component specs.
3. WooCommerce template overrides under `cellcraze/woocommerce/` for the screens above.
4. Register the `Brand`, `Storage`, `Colour` attributes; set products as variable where needed.

Classic child theme + template overrides is the pragmatic path for this bespoke look and for
deep WooCommerce control (a block theme would fight the fixed 2px-grid layout).

---

## Prerequisite (the blocker we keep hitting)
A custom theme must run on an actual WordPress instance to be seen. The **theme code can be
written and committed now** regardless, but to view it you need WordPress running somewhere —
a free online sandbox (InstaWP/TasteWP), or real hosting. Choose this once; it doesn't change
the plan.

## Build order
1. Get a running WordPress (sandbox or host) + WooCommerce (Sri Lanka/LKR).
2. Install plugins (ATUM, PDF Invoices, wishlist, filters, SEO, security, backups).
3. Attributes + categories + sample variable products (from `data/`).
4. Theme: tokens/fonts/global Modernist CSS.
5. Templates: 1a → 1c → 1b → 1d → 1g, then mobile polish (1e/1f).
6. Back office: configure ATUM (suppliers, PO, GRN) + Analytics; map order-status labels.
7. Payments, shipping zone, invoices, test order end-to-end.
