# CellCraze — manually supplied product images

Drop **licensed** product image files here, named by **exact product SKU**, then run the
importer (see below). The importer matches files to products by SKU and sets them as the
WooCommerce **featured image** — it never changes prices, stock, descriptions, orders, IMEI,
suppliers, or any other business data, and never deletes media.

## Filename format

`<SKU>.<ext>` — extension is one of: `jpg`, `jpeg`, `png`, `webp` (case-insensitive).

Example: `CC-EAR-001.jpg` → *Apple AirPods (3rd gen)*.

One image per SKU (this sets the **featured** image). Gallery images are out of scope for this tool.

## Current product → SKU mapping

| SKU | Product | Category | Expected file |
|-----|---------|----------|---------------|
| `CC-PHN-001` | Redmi Note 13 (8GB/256GB) | Phones | `CC-PHN-001.jpg` |
| `CC-HDP-001` | Sony WH-CH520 Wireless Headphones | Headphones | `CC-HDP-001.jpg` |
| `CC-EAR-001` | Apple AirPods (3rd gen) | Earphones | `CC-EAR-001.jpg` |
| `CC-CHG-001` | Anker 20W USB-C Fast Charger | Chargers | `CC-CHG-001.jpg` |
| `CC-WCH-001` | Samsung Galaxy Watch6 (44mm) | Smartwatches | `CC-WCH-001.jpg` |
| `CC-PWB-001` | Anker PowerCore 10000mAh Power Bank | Power Banks | `CC-PWB-001.jpg` |
| `CC-CSE-001` | Spigen Rugged Armor Case (iPhone 15) | Cases | `CC-CSE-001.jpg` |
| `CC-CBL-001` | UGREEN USB-C to USB-C Cable 2m | Cables | `CC-CBL-001.jpg` |
| `CC-ACC-001` | Baseus Phone Holder for Car | Accessories | `CC-ACC-001.jpg` |
| `CC-ACC-002` | Tempered Glass Screen Protector (Universal 6.5in) | Accessories | `CC-ACC-002.jpg` |

> Regenerate this mapping any time with:
> `C:/xampp/php/php.exe C:/xampp/wp-cli.phar eval-file tools/print-sku-map.php --path=C:/xampp/htdocs/cellcraze`

## Image guidance

- Use only images you are **licensed** to use (your own photos, distributor-supplied assets, or
  stock you've licensed). Do **not** drop in a manufacturer's copyrighted press photo for a
  commercial storefront unless you have permission.
- Each file must be the **correct model** for that SKU.
- Square-ish images (e.g. 1000×1000) on a white/neutral background look best — the storefront uses
  `object-fit: contain`, so any aspect ratio renders without stretching or cropping.
- Products without a supplied file keep the branded CellCraze placeholder automatically.

## Run the importer

**Dry-run first (changes nothing):**

```bash
C:/xampp/php/php.exe tools/import-product-images.php --dry-run
```

**Real import (only products missing a featured image):**

```bash
C:/xampp/php/php.exe tools/import-product-images.php
```

**Replace images even where one is already set:**

```bash
C:/xampp/php/php.exe tools/import-product-images.php --replace
```

Optional flags: `--dir="C:/path/to/images"` (default: this folder), `--path="C:/xampp/htdocs/cellcraze"`
(WordPress root). The importer is **idempotent** — re-running it will not create duplicate attachments.

> Image binaries in this folder are intentionally **git-ignored** (see `.gitignore`); only this
> README is tracked. Keep licensed originals backed up outside the repo.
