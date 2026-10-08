# CellCraze — WordPress Implementation Guide

**Project:** Online store + inventory management for a mobile accessories shop (Sri Lanka)
**Platform:** WordPress + WooCommerce
**Sells:** phones, headphones, earphones, chargers, smartwatches, power banks, cases, cables, accessories
**Currency:** Sri Lankan Rupee (LKR)

This document is the build plan. It covers the two workflows (customer and admin), the exact WordPress plugins to use for each requirement, and a step-by-step setup and launch checklist.

---

## 1. Why WordPress + WooCommerce

WooCommerce is a free WordPress plugin that turns a WordPress site into a full online store. It handles products, cart, checkout, orders, payments, stock, taxes, shipping, coupons, reviews and reports out of the box. Add-on plugins cover purchase orders, goods receipt, suppliers, PDF invoices and local Sri Lankan payment gateways. This gives the shop everything in the requirements without custom coding, and the owner can manage it from one dashboard.

**Core pieces**
- **WordPress** — the site engine (content, users, admin dashboard).
- **WooCommerce** — the store (products, cart, checkout, orders, stock, reports).
- **A WooCommerce-ready theme** — the look of the shop (e.g. Storefront [free], Astra, Botiga, Blocksy).
- **Plugins** — one each for the extra features (inventory/PO/GRN, invoices, payments, wishlist, security, SEO, backups).

---

## 2. Customer Workflow

How a shopper uses the site, start to finish.

```
Browse / Search
      ↓
Product page  (images, specs, price, stock, reviews)
      ↓
Add to Cart   (stock checked automatically)
      ↓
View Cart     (update quantities, apply coupon)
      ↓
Checkout      (delivery address + shipping + payment method)
      ↓
Pay           → Card (gateway) / Cash on Delivery / Bank transfer
      ↓
Order Confirmed  → confirmation email + invoice
      ↓
Track order   (My Account → Orders → status)
      ↓
After delivery → leave a review
```

**Step by step**
1. **Browse or search** — shopper lands on the home page (featured products, new arrivals, categories) and browses by category or uses the search bar and filters (price, brand, category).
2. **Product page** — image gallery, description, specifications, price (and discount price), live stock status, and customer reviews. Buttons: *Add to cart* and *Add to wishlist*.
3. **Cart** — shopper reviews items, changes quantities, applies a coupon code, sees subtotal + shipping + total.
4. **Checkout** — enters delivery address, picks a delivery method (home delivery or store pickup), and a payment method. Can check out as a guest or sign in.
5. **Payment** — Card (via gateway), Cash on Delivery, or bank transfer. Card payments are handled securely by the gateway; the shop never stores card numbers.
6. **Confirmation** — order is created, cart clears, shopper sees a thank-you page and gets a confirmation email with an invoice (PDF).
7. **Track** — in *My Account → Orders*, the shopper sees each order's status (Processing → Shipped → Delivered) and can download invoices.
8. **Review** — after delivery, the shopper can rate and review the product.

**WooCommerce covers steps 1–8 natively.** Wishlist and advanced filters need one extra plugin each (listed below).

---

## 3. Admin Workflow (Shop Owner / Staff)

How the owner runs the shop from the WordPress dashboard.

```
New order arrives  → notification + email
      ↓
Check order        → stock auto-deducted on sale
      ↓
Prepare items      → pack
      ↓
Update status      → "Processing" → "Completed/Shipped"
      ↓
Print invoice / packing slip
      ↓
Hand to courier    → customer gets shipping update
      ↓
Stock runs low     → low-stock alert
      ↓
Create Purchase Order → send to supplier
      ↓
Goods arrive       → Goods Receipt (GRN) → stock increased
      ↓
Review reports     → sales, profit, top products, stock value
```

**Day-to-day tasks and where they live**
- **Products** — `Products → Add New`: name, description, images (min 3), price, cost price, SKU, brand, category, stock quantity, low-stock threshold. Bulk add via CSV import.
- **Orders** — `WooCommerce → Orders`: view, open an order, see customer + items + address, update status, print invoice/packing slip, issue refunds, add internal notes.
- **Inventory** — stock auto-deducts when an order is placed. Low-stock and out-of-stock items are flagged. Manual adjustments and stock history via the inventory plugin (ATUM).
- **Suppliers & procurement** — add suppliers, create Purchase Orders (PO), and record Goods Receipt (GRN) when stock arrives (ATUM, below). Receiving a PO increases stock automatically.
- **Reports** — `WooCommerce → Analytics`: revenue, orders, top products, categories, and (with cost price set) gross profit. Export to CSV.
- **Staff** — add users with the right role (Shop Manager, or custom roles for sales vs. warehouse).

---

## 4. Plugin Map — Requirement → WordPress Plugin

| Requirement | Plugin | Cost |
|---|---|---|
| **Store, cart, checkout, orders, stock, coupons, reviews, reports** | **WooCommerce** (core) | Free |
| **Advanced inventory, low-stock alerts, stock history, SKU/barcode, multi-location** | **ATUM Inventory Management** | Free (core) |
| **Purchase Orders (PO) + Goods Receipt (GRN) + suppliers** | **ATUM** — suppliers & PO are built in; *Inbound Stock / PO Pro* add-on for full GRN | Free + paid add-on |
| **PDF invoices, receipts, packing slips** | **WooCommerce PDF Invoices & Packing Slips** | Free |
| **Card payments (Sri Lanka)** | **PayHere**, **WebXPay**, or **DPO/Onepay** gateway plugin | Free plugin (gateway fees apply) |
| **Card payments (international)** | **Stripe for WooCommerce** | Free plugin (Stripe fees apply) |
| **Cash on Delivery / Bank transfer** | WooCommerce core (built-in) | Free |
| **Wishlist / save for later** | **TI WooCommerce Wishlist** or **YITH Wishlist** | Free / Freemium |
| **Advanced product filters** | **WooCommerce Product Filter** (or theme-built-in) | Free / Freemium |
| **Email delivery (reliable)** | **WP Mail SMTP** (send via Gmail/SendGrid/Brevo) | Free |
| **SMS / WhatsApp order updates** | **Twilio SMS** / **WhatsApp order notifications** plugin | Freemium |
| **Staff roles & permissions** | WordPress roles + **Members** or **User Role Editor** | Free |
| **Coupons / promo codes** | WooCommerce core | Free |
| **SEO (Google ranking)** | **Rank Math** or **Yoast SEO** | Free |
| **Security (firewall, login protection)** | **Wordfence Security** | Free |
| **Backups** | **UpdraftPlus** | Free |
| **Performance / caching** | **LiteSpeed Cache** or **WP Super Cache** | Free |
| **Live chat** | **Tidio** or **WhatsApp chat button** | Freemium |

> Most of the system is free. The likely paid items are: a premium theme (optional), the ATUM PO/GRN add-on if full goods-receipt tracking is needed, and the per-transaction fees charged by the card gateway.

---

## 5. Step-by-Step Setup

### Phase 1 — Hosting & WordPress (Day 1)
1. Buy a **.lk domain** (e.g. from a local registrar) and **hosting** (local SL host, or DigitalOcean/Hostinger/SiteGround — pick one with 1-click WordPress and good support).
2. Install **WordPress** (most hosts have a 1-click installer).
3. Install an **SSL certificate** (free Let's Encrypt — most hosts enable it in one click). The site must run on **https://**.

### Phase 2 — Store foundation (Day 1–2)
4. Install **WooCommerce** → run its setup wizard:
   - Store address: your shop address, **Country/Region: Sri Lanka**.
   - Currency: **LKR (Rs)**.
   - Product type: physical products.
5. Install a **WooCommerce-ready theme** (Storefront is free and made for WooCommerce) and set the logo + brand colours.
6. Create **product categories**: Phones, Headphones, Earphones, Chargers, Smartwatches, Power Banks, Cases, Cables, Accessories.

### Phase 3 — Products & inventory (Day 2–4)
7. Add products (`Products → Add New`). For each: title, description, **images (min 3)**, **Regular price** and **Sale price**, **SKU**, brand (as an attribute or tag), **category**, and under *Inventory*: **Manage stock = yes**, stock quantity, **low-stock threshold**. Set **Cost of goods** (needed for profit reports — ATUM adds this field).
   - To load many at once: `Products → Import` with a **CSV** file (columns: name, SKU, price, stock, category, etc.).
8. Install **ATUM Inventory Management** for the stock dashboard, low-stock alerts, stock history, suppliers, and purchase orders.

### Phase 4 — Payments, shipping, tax (Day 4–5)
9. **Payments** (`WooCommerce → Settings → Payments`):
   - Enable **Cash on Delivery** (recommended default for Sri Lanka).
   - Enable **Direct bank transfer** (show your bank details).
   - Install and configure a **card gateway** (PayHere / WebXPay / Stripe) — create a merchant account, paste the API keys, test in sandbox first.
10. **Shipping** (`WooCommerce → Settings → Shipping`): create a shipping zone **"Sri Lanka"** with either a **flat rate** (e.g. Rs 350) and a **free shipping** rule above a threshold (e.g. free over Rs 10,000), or rates per city/zone. Add a **Local pickup** option if the shop offers it.
11. **Tax** (if registered): `WooCommerce → Settings → Tax` — add the applicable rate; otherwise leave tax off.

### Phase 5 — Invoices, emails, extras (Day 5–6)
12. Install **WooCommerce PDF Invoices & Packing Slips** → attach the PDF invoice to the order-confirmation email and enable packing slips for the warehouse.
13. Install **WP Mail SMTP** and connect a sending service (Brevo/SendGrid free tier, or Gmail) so order emails reliably reach customers. Customize the WooCommerce email templates with the logo.
14. Install the extras you want: **wishlist**, **product filters**, **SEO (Rank Math)**, **security (Wordfence)**, **backups (UpdraftPlus)**, **caching**.

### Phase 6 — Suppliers & procurement (Day 6–7)
15. In **ATUM → Suppliers**, add each supplier (name, contact, email, phone, terms).
16. When stock is low (ATUM flags it), create a **Purchase Order** in `ATUM → Purchase Orders`: pick the supplier, add products and quantities, send it. When the goods arrive, mark the PO **received** (GRN) — ATUM increases the stock automatically and records the movement.

### Phase 7 — Roles & staff (Day 7)
17. Add staff under `Users → Add New`:
   - **Shop Manager** (WooCommerce's built-in role) — manage products, orders, stock, reports (no site settings).
   - For finer control (e.g. sales staff who only handle orders, or warehouse staff who only handle stock), use **Members** or **User Role Editor** to make custom roles.
   - The owner is the **Administrator**.

---

## 6. Roles & Permissions (recommended)

| Role | Can do | Cannot do |
|---|---|---|
| **Administrator** (owner) | Everything: settings, users, products, orders, stock, reports, plugins | — |
| **Shop Manager** | Products, orders, stock, suppliers, reports | Site settings, plugins, users |
| **Sales Staff** (custom) | View & process orders, print invoices | Edit products, change stock, view cost/profit |
| **Warehouse** (custom) | Stock levels, PO/GRN, stock adjustments | Orders, pricing, reports |
| **Customer** | Shop, order, review, manage own account | Any admin area |

---

## 7. Launch Checklist

- [ ] Domain + hosting live, **SSL (https) active**
- [ ] WooCommerce set to **Sri Lanka / LKR**
- [ ] All product categories created
- [ ] Products added with images, price, cost price, SKU, stock, low-stock threshold
- [ ] Payment methods working (test a real COD order + a sandbox card payment)
- [ ] Shipping zone + rates (and free-shipping threshold) set
- [ ] Order confirmation email + PDF invoice tested (received in inbox, not spam)
- [ ] Stock deducts correctly after a test order
- [ ] Supplier added + one test Purchase Order → GRN → stock increased
- [ ] Reports showing real numbers (place a few test orders)
- [ ] Mobile layout checked on a phone
- [ ] Staff accounts created with correct roles
- [ ] **Security** (Wordfence) + **Backups** (UpdraftPlus, scheduled daily) active
- [ ] SEO basics (Rank Math): site title, meta descriptions, sitemap submitted to Google
- [ ] Remove all test orders/products before go-live

---

## 8. Rough Budget (recurring)

| Item | Typical cost |
|---|---|
| Domain (.lk) | ~Rs 3,500 / year |
| Hosting (shared/managed WordPress) | ~Rs 1,500–6,000 / month |
| Theme (optional premium) | Rs 0 (free) or ~$50 one-time |
| ATUM PO/GRN add-on (if needed) | ~$100–150 / year |
| Card gateway | Free plugin + ~2.5–3.5% per transaction |
| SMS/WhatsApp (optional) | pay-per-message |
| Everything else (WooCommerce, invoices, SEO, security, backups) | Free |

A lean launch (COD + bank transfer, free plugins) can go live for little more than domain + hosting. Card payments and the PO/GRN add-on are the main paid extras.

---

## 9. Build Order (summary)

1. Hosting + WordPress + SSL
2. WooCommerce + theme + categories
3. Products + ATUM inventory
4. Payments + shipping + tax
5. Invoices + emails + extras (wishlist, SEO, security, backups)
6. Suppliers + PO/GRN
7. Staff roles
8. Test everything → launch

---

*Prepared as a WordPress build plan for the CellCraze mobile accessories shop. Hand this to whoever sets up the site; each section maps a shop requirement to the exact plugin and the steps to configure it.*
