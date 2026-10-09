# CellCraze — local setup with XAMPP (Windows)

Run the store locally on XAMPP to browse and test it. XAMPP lives on `C:\xampp`;
the project code stays in this repo on `D:\Harsha\CellCraze-Mobile-wordpress`
and is symlinked into WordPress so git edits apply live.

> This repo tracks only the `cellcraze` theme and `cellcraze-core` plugin.
> WordPress core, WooCommerce, and the other plugins are installed separately
> (below).

---

## 1. Start XAMPP

- Open **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
- Start **Apache** and **MySQL**.

## 2. Create the database

- Visit http://localhost/phpmyadmin
- **Databases** → create a database named **`cellcraze`** (collation
  `utf8mb4_general_ci`).

## 3. Install WordPress

- Download WordPress (https://wordpress.org/latest.zip), extract into
  `C:\xampp\htdocs\cellcraze\`.
- Visit http://localhost/cellcraze and run the installer:
  - Database name: `cellcraze`
  - Username: `root`
  - Password: *(blank — XAMPP default)*
  - Host: `localhost`
- Set the site title + an admin account you'll remember.

## 4. Link the theme + plugin (run cmd.exe **as Administrator**)

```bat
mklink /D "C:\xampp\htdocs\cellcraze\wp-content\themes\cellcraze" "D:\Harsha\CellCraze-Mobile-wordpress\wp-content\themes\cellcraze"
mklink /D "C:\xampp\htdocs\cellcraze\wp-content\plugins\cellcraze-core" "D:\Harsha\CellCraze-Mobile-wordpress\wp-content\plugins\cellcraze-core"
```

(If symlinks are a hassle, just copy those two folders into the same
destinations instead.)

## 5. Install required plugins + parent theme

In **wp-admin** (http://localhost/cellcraze/wp-admin):

- **Themes → Add New:** install **Storefront** (the `cellcraze` theme is its
  child — required).
- **Plugins → Add New:** install & activate at least **WooCommerce** and
  **ATUM Inventory Management**. The full list is in
  [`scripts/02-install-plugins.sh`](../scripts/02-install-plugins.sh):
  WooCommerce, ATUM, PDF Invoices & Packing Slips, WP Mail SMTP, TI Wishlist,
  Rank Math, Wordfence, UpdraftPlus, LiteSpeed Cache.

## 6. Activate CellCraze

- **Appearance → Themes:** activate **CellCraze**.
- **Plugins:** activate **CellCraze Core** (creates the `wp_cellcraze_imei`
  table on activation).

## 7. Configure WooCommerce (Sri Lanka / LKR)

Either run WooCommerce's setup wizard, or set these manually under
**WooCommerce → Settings** (mirrors
[`scripts/03-configure-woo.sh`](../scripts/03-configure-woo.sh)):

- **General:** Selling/Shipping location = **Sri Lanka**; Currency = **LKR
  (Rs)**, position *left with space*, thousand sep `,`, decimal `.`, 2 decimals.
- **Payments:** enable **Cash on Delivery** and **Direct bank transfer**.
- **Shipping → add zone "Sri Lanka"** (region = Sri Lanka) with:
  - Flat rate **Rs 350** ("Standard Delivery")
  - Free shipping, requires min order **Rs 10,000** ("Free Delivery")
  - Local pickup ("Store Pickup", cost 0)
- **Tax:** leave disabled unless VAT-registered.

## 8. Test checklist

1. **Catalog:** add a product (or a few categories/products). On a product, open
   the **IMEI / Serial** tab and paste 2–3 IMEIs.
2. **Storefront:** browse the homepage, a category, a product page, add to cart.
3. **Checkout:** place an order with **Cash on Delivery**.
4. **IMEI assignment:** in wp-admin move the order to **Processing** → confirm
   the IMEI is auto-assigned and shows on the order, the customer order view,
   and the order email. Stock should decrement.
5. **Restore:** **Cancel** the order → confirm the IMEI returns to available.
6. **Registry:** **WooCommerce → IMEI Registry** lists units; the **Channel**
   column appears on the orders list (orders tagged "Online").

## PHP lint (project convention — run before committing PHP changes)

XAMPP includes PHP, so you can finally run the lint:

```powershell
Get-ChildItem -Recurse 'D:\Harsha\CellCraze-Mobile-wordpress\wp-content\plugins\cellcraze-core' -Filter *.php |
  ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }
```

All files should report **No syntax errors detected**.

## Notes

- `scripts/*.sh` are Linux/Docker wp-cli scripts; on XAMPP configure via the
  admin UI (section 7) or install WP-CLI for Windows to run the `wp` commands.
- This local site is independent of the live `phoneshop.nuvirahub.com` deploy.
