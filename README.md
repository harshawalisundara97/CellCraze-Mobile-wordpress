# CellCraze — WordPress / WooCommerce Store

Online store + inventory for a mobile-accessories shop in Sri Lanka (currency: **LKR**).
Built on **WordPress + WooCommerce** and scaffolded so the whole store stands up with a
single command — no manual dashboard clicking for the initial setup.

Sells: phones, headphones, earphones, chargers, smartwatches, power banks, cases, cables, accessories.

> Full conceptual blueprint: [`docs/IMPLEMENTATION-GUIDE.md`](docs/IMPLEMENTATION-GUIDE.md)

---

## What this repo gives you

| Piece | Purpose |
|---|---|
| `docker-compose.yml` | Local WordPress + MariaDB + a `wp-cli` runner |
| `scripts/` | Idempotent setup: install WP, plugins, configure WooCommerce (SL/LKR), categories, products |
| `wp-content/themes/cellcraze/` | CellCraze child theme of Storefront (brand colours, Woo tweaks) |
| `data/products-sample.csv` | Sample catalogue **and** the bulk-import template for your real products |
| `docs/IMPLEMENTATION-GUIDE.md` | The full build + launch guide |

WordPress core and plugins are downloaded at setup time (not committed) — only our own
theme, scripts, and data are version-controlled.

---

## Quick start (local)

Requires Docker + Docker Compose.

```bash
# 1. Configure environment
cp .env.example .env        # then edit passwords in .env

# 2. Start the containers
docker compose up -d

# 3. Build the store (installs WP, plugins, configures WooCommerce, imports products)
docker compose run --rm wpcli /scripts/setup.sh
```

Then open:

- **Storefront:** http://localhost:8080
- **Admin:** http://localhost:8080/wp-admin (user/password from your `.env`)

`setup.sh` is **idempotent** — re-running it won't duplicate categories, shipping zones, or re-install WordPress.

---

## What setup.sh configures

1. **WordPress core** + activates the **CellCraze** child theme (installs Storefront parent).
2. **Plugins:** WooCommerce, ATUM Inventory, PDF Invoices & Packing Slips, WP Mail SMTP,
   TI Wishlist, Rank Math SEO, Wordfence, UpdraftPlus, LiteSpeed Cache.
3. **WooCommerce:** country = Sri Lanka, currency = **LKR (Rs)**, store pages,
   **Cash on Delivery** + **Direct bank transfer** enabled, and a **"Sri Lanka" shipping zone**
   (flat Rs 350, free over Rs 10,000, plus store pickup).
4. **Categories:** Phones, Headphones, Earphones, Chargers, Smartwatches, Power Banks, Cases, Cables, Accessories.
5. **Products:** imports `data/products-sample.csv`.

---

## Adding your real products

Edit `data/products-sample.csv` (keep the header row) or duplicate it, then either:

- re-run `docker compose run --rm wpcli /scripts/05-import-products.sh`, or
- in the dashboard: **Products → Import → Upload CSV**.

Key columns: `SKU`, `Name`, `Regular price`, `Sale price`, `Stock`, `Low stock amount`,
`Categories`, `Attribute 1 value(s)` (brand), `Images` (URL), `Meta: _cost` (cost price for profit reports).

---

## Going live

This scaffold is host-agnostic. Recommended path:

1. **Develop & test here** (free).
2. **Launch on Hostinger** (best price-to-performance for a small store) — or a local
   **LKR host like LankaHost ECOM** if you prefer rupee billing + local support.
3. **Scale** to Cloudways / xCloud (Vultr High-Frequency) once you have steady daily orders.

On the live host:

- [ ] Enable **SSL (https)** — site must run on https.
- [ ] Install & configure a **card gateway** (PayHere / WebXPay / Stripe) with real API keys
      — these are intentionally **not** set up in local dev.
- [ ] Connect **WP Mail SMTP** to a real sending service (Brevo/SendGrid/Gmail).
- [ ] Add suppliers + a test Purchase Order → Goods Receipt in **ATUM**.
- [ ] Schedule **UpdraftPlus** daily backups; activate **Wordfence**.
- [ ] Submit the **Rank Math** sitemap to Google.
- [ ] **Remove all test/sample products and orders** before go-live.

Full checklist: [`docs/IMPLEMENTATION-GUIDE.md`](docs/IMPLEMENTATION-GUIDE.md) §7.

---

## Useful commands

```bash
# Run wp-cli directly
docker compose run --rm wpcli wp plugin list
docker compose run --rm wpcli wp option get woocommerce_currency

# Stop / reset
docker compose down            # stop (keeps data)
docker compose down -v         # stop and WIPE the database + WP files
```
