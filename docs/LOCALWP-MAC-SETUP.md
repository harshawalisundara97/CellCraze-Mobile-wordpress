# CellCraze — Local WordPress setup on Mac (LocalWP)

A beginner-friendly, click-by-click guide to run the CellCraze store on your Mac using
**Local** (by Flywheel) — no commands needed. End result: a working store at a
`http://cellcraze.local` address you open in your browser.

> You'll reuse two things from this repo:
> - `data/products-sample.csv` — import your products
> - `wp-content/themes/cellcraze/` — the CellCraze theme (optional, step 9)

---

## 1. Install Local

1. Go to **https://localwp.com** → click **Download** → choose **Mac (Apple Silicon or Intel** — pick the one matching your Mac; if unsure, click the Apple logo → *About This Mac* to check the chip).
2. Open the downloaded `.dmg` and drag **Local** into **Applications**.
3. Open **Local** from Applications. (If macOS warns it's from the internet, click **Open**.)

## 2. Create the CellCraze site

1. In Local, click **+ Create a new site** → **Create a new site** → **Continue**.
2. **Site name:** `CellCraze` → **Continue**.
3. **Environment:** choose **Preferred** → **Continue**.
4. **WordPress admin:**
   - Username: `admin` (or your choice)
   - Password: pick one you'll remember — **write it down**
   - Email: your email
   → **Add Site**.
5. Local sets everything up (takes a minute). When done, click **Start site** if it isn't already green.

## 3. Open your store

- In Local, click **WP Admin** → the dashboard opens (that's your control panel).
- Click **Open site** (or **View Site**) → that's the shop your customers see.
- The web address will look like `http://cellcraze.local`. (This is the LocalWP equivalent of `localhost:8080`.)

## 4. Install WooCommerce (the store engine)

1. In the dashboard: **Plugins → Add New Plugin**.
2. Search **WooCommerce** → **Install Now** → **Activate**.
3. The WooCommerce setup wizard starts. Enter:
   - **Country/Region:** Sri Lanka
   - **Currency:** LKR (Rs) — it usually sets this from the country automatically
   - **Product type:** Physical products
   - Skip marketing/extensions offers you don't want.

## 5. Install the rest of the plugins

Repeat **Plugins → Add New Plugin → search → Install → Activate** for each:

| Search for | What it does |
|---|---|
| ATUM Inventory Management | Stock dashboard, low-stock alerts, suppliers, purchase orders |
| PDF Invoices & Packing Slips for WooCommerce | PDF invoices on orders |
| TI WooCommerce Wishlist | Wishlist / save for later |
| Rank Math SEO | Google ranking / sitemap |
| Wordfence Security | Firewall + login protection |
| UpdraftPlus | Backups |
| WP Mail SMTP | Reliable order emails (configure later with a real email service) |
| LiteSpeed Cache *(optional locally)* | Performance/caching |

## 6. Create product categories

**Products → Categories.** Add each of these (type the name → **Add new category**):

Phones · Headphones · Earphones · Chargers · Smartwatches · Power Banks · Cases · Cables · Accessories

## 7. Import the sample products

1. **Products → All Products → Import** (button at the top).
2. Click **Choose File** → select `data/products-sample.csv` from this project folder.
   (In LocalWP, the project folder is wherever you cloned this repo on your Mac.)
3. **Continue** → let it auto-map the columns → **Run the importer**.
4. You'll see 10 sample products appear under **Products**. Edit them or add your real ones later.

> Later, to add your real catalogue: open `data/products-sample.csv` in Numbers/Excel,
> replace the rows with your products (keep the header row), save as CSV, and import again.

## 8. Set up payments & shipping

**Payments** — go to **WooCommerce → Settings → Payments**:
- Turn **ON** *Cash on Delivery*.
- Turn **ON** *Direct bank transfer* → click **Manage** and enter your bank details.

**Shipping** — go to **WooCommerce → Settings → Shipping → Add shipping zone**:
- Zone name: `Sri Lanka`; Region: Sri Lanka.
- **Add shipping method → Flat rate** → set cost `350`.
- **Add shipping method → Free shipping** → requires a *minimum order amount* → `10000`.
- *(Optional)* **Add → Local pickup** → cost `0` for in-store collection.

## 9. (Optional) Use the CellCraze theme

1. First install the parent theme: **Appearance → Themes → Add New** → search **Storefront** → **Install** (don't activate yet).
2. Copy the folder `wp-content/themes/cellcraze/` from this repo into your LocalWP site's
   themes folder. To find that folder: in Local, right-click the site → **Open site folder**
   → go to `app/public/wp-content/themes/` → paste the `cellcraze` folder there.
3. Back in the dashboard: **Appearance → Themes** → activate **CellCraze**.

*(If this feels fiddly, just activate **Storefront** instead — the store works fine without the child theme.)*

## 10. Test it

- Open your site, add a product to the cart, go to checkout, place a **test order** (choose Cash on Delivery).
- In the dashboard: **WooCommerce → Orders** — your test order appears, and the product's stock drops by 1.
- Open the order → you can download the **PDF invoice**.

That's a fully working local store. 🎉

---

## When you're ready to go live
Everything here can be rebuilt on real hosting (Hostinger or LankaHost). See
[`README.md`](../README.md) → *Going live* and [`IMPLEMENTATION-GUIDE.md`](IMPLEMENTATION-GUIDE.md) §7
for the launch checklist (SSL, card gateway like PayHere, backups, remove test data).
