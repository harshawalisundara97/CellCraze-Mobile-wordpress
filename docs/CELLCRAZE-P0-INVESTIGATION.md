# CellCraze — P0 Investigation (code-evidence, read-only)

**Date:** 2026-10-24 · **Env:** cloud clone, `main` @ `d18e066`, tree clean.
**Scope:** investigate 3 P0 findings from `CELLCRAZE-WORDPRESS-ADMIN-AUDIT.md` **against source**.
No application code, config, DB, or Git state was changed. The referenced audit MD is **not
present in this clone** (laptop-only); findings below are derived from the actual plugin code.

> **Verification limits:** this session has **no database and no running site**, so exact runtime
> values (how many rows, which supplier strings, which role hit the error) are **UNVERIFIED** —
> each issue lists a read-only procedure to confirm on the laptop. Nothing here is a confirmed
> data defect until those DB/role checks run.

---

## P0-1 — IMEI Registry vs product-level IMEI data mismatch

**Files/classes/functions:** `wp-content/plugins/cellcraze-core/includes/class-cellcraze-imei-admin.php`
- Registry list: `registry_page()` — `SELECT * FROM {imei} ORDER BY id DESC LIMIT 200`; Product
  column rendered via `get_the_title( $row->product_id )` (blank `—` when `product_id` is 0 or has
  no title).
- Product tab: `product_panel()` → `units_for_product($product_id)` =
  `SELECT * FROM {imei} WHERE product_id = %d` (no limit). `$product_id = $post->ID` (the edit-screen
  post = the **parent** product).
- Write path: `handle_add_imei()` → `receive($product_id, …)` → `IMEI_DB::add_unit([... 'product_id' => $product_id])`.

**Verified current behavior (from code):** registry shows *all* units (capped at **200**) with the
product title looked up per row; the product tab shows *all* units whose `product_id` equals that
product. The two views use **different queries** and the registry is **truncated at 200**.

**Most likely root cause(s) — code-supported, pick confirmed by DB check:**
1. **LIMIT 200 truncation** in `registry_page()` — with >200 units the registry undercounts vs a
   product tab (which has no limit), producing an apparent mismatch. *(code: line ~354.)*
2. **`product_id` stored as 0 or a variation ID** — any unit with `product_id = 0` (or a *variation*
   id rather than the parent) shows in the registry with a blank Product column but does **not**
   appear under the parent product's tab, and vice-versa. `add_unit` stores whatever `product_id`
   the form posts; `receive()` stores the value passed in. *(code: `units_for_product` filters on the
   parent id only.)*
3. **Deleted/!published product** → `get_the_title()` returns '' so the registry Product column is
   `—` even though the unit exists.

**DB inspection needed:** YES.
**Read-only verification (phpMyAdmin / `wp db query`, SELECT only):**
```sql
SELECT COUNT(*) FROM wp_cellcraze_imei;                         -- total vs 200
SELECT product_id, COUNT(*) FROM wp_cellcraze_imei GROUP BY product_id;  -- any 0 / variation ids?
SELECT i.product_id, p.post_type, p.post_status
  FROM wp_cellcraze_imei i LEFT JOIN wp_posts p ON p.ID = i.product_id
  GROUP BY i.product_id, p.post_type, p.post_status;            -- variations? missing posts?
```

**Proposed fix (pending approval):**
- Registry: add pagination (or raise/announce the limit) so counts are not silently truncated.
- Normalise `product_id` to the **parent** product on write (map variation→parent) so both views
  agree; show variation detail separately if needed.
- Registry Product column: when `product_id` resolves to a variation/parent, display the parent
  title; label orphaned rows explicitly.
**Regression tests:** receive N>200 units → registry paginates and total matches
`COUNT(*)`; receive against a variation → appears under the parent tab AND registry with the parent
name; CC-04 IMEI lifecycle stays green.

**Risks:** LOW if read-paths only. Changing how `product_id` is normalised on **write** touches
allocation (`IMEI_Orders` matches on parent id) — must keep parent-id semantics so **CC-03 atomic
claim / CC-04 lifecycle** are unaffected. No stock/purchasing/HPOS impact for read-side fixes.

---

## P0-2 — Supplier referenced by audit records but missing from Suppliers page

**Files/classes/functions:**
- `class-cellcraze-suppliers.php`: table `wp_cellcraze_supplier` (schema has `name`, `status`
  active/inactive); list query `render()` line ~209 (`… WHERE {$where_sql} … LIMIT 500`), status
  filter dropdown (active/inactive); `active()` returns only `status='active'`.
- `class-cellcraze-purchasing.php`: PO stores a **structured FK** `supplier_id`; on receive,
  `handle_receive()` copies the supplier **name string** into each IMEI unit
  (`'supplier' => $sup_name`, line ~352).
- `class-cellcraze-imei-admin.php`: `handle_add_imei()` has a **free-text** Supplier field →
  stored in IMEI `supplier` (string) + receive **audit** `reference` = that string.

**Verified current behavior (from code):** suppliers are a first-class table referenced by POs via
`supplier_id` (FK). But **audit/IMEI records reference suppliers by a plain NAME string**, not by id
— two different mechanisms with **no referential integrity** between them.

**Most likely root cause(s):**
1. **Free-text supplier on manual IMEI add** — a unit added via the product panel with a typed
   supplier name creates audit/IMEI references to a name that may have **no row** in
   `wp_cellcraze_supplier` → "referenced but missing." *(code: `handle_add_imei` supplier is free text.)*
2. **Soft-deactivated supplier** — suppliers are never hard-deleted (`handle_toggle` sets
   `status='inactive'`); the Suppliers list **defaults to a status filter** and the PO dropdown uses
   `active()` only, so an inactive supplier still referenced by old POs/audit may appear "missing"
   until you switch the filter to *Inactive*. *(code: `active()` WHERE status='active'; list `$where_sql`.)*
3. **Renamed supplier** — editing a supplier's name doesn't update the historic name string copied
   into IMEI/audit rows, so the old string no longer matches any current supplier name.

**DB inspection needed:** YES.
**Read-only verification (SELECT only):**
```sql
SELECT DISTINCT reference FROM wp_cellcraze_audit WHERE event_type IN ('stock_receive','supplier_save');
SELECT id,name,status FROM wp_cellcraze_supplier ORDER BY status,name;
SELECT DISTINCT supplier FROM wp_cellcraze_imei WHERE supplier <> '';
-- names in IMEI/audit that have no active supplier row:
SELECT DISTINCT i.supplier FROM wp_cellcraze_imei i
  LEFT JOIN wp_cellcraze_supplier s ON s.name = i.supplier AND s.status='active'
  WHERE i.supplier <> '' AND s.id IS NULL;
```

**Proposed fix (pending approval):**
- Prefer a **supplier_id FK** on IMEI rows (dropdown of suppliers) instead of (or alongside) the
  free-text name, so references resolve even after rename/deactivate. Keep the name snapshot for
  history.
- Show **inactive** suppliers in the list by default (or a clear "Inactive" tab + count) so
  referenced-but-deactivated suppliers aren't invisible.
- This is **additive** (new nullable column / UI), not a rewrite of purchasing.
**Regression tests:** receive a PO → IMEI rows carry supplier_id + name; deactivate a supplier →
still visible under Inactive and still resolvable from audit; ADM-09/ADM-10 stay green.

**Risks:** MEDIUM — touches the **supplier/purchasing** area, which includes the laptop's
**uncommitted GRN work**; any schema change must be additive `dbDelta` and must not alter
`receive()`/GRN stock/IMEI semantics. No HPOS impact. Must reconcile with laptop WIP before coding.

---

## P0-3 — IMEI Registry direct-URL permission / slug error

**Files/classes/functions:** `class-cellcraze-imei-admin.php::registry_menu()` —
`add_submenu_page( 'woocommerce', …, 'manage_woocommerce', 'cellcraze-imei', [registry_page] )`.
Custom roles (`class-cellcraze-roles.php`): `cc_cashier`, `cc_warehouse`, `cc_order_processing` hold
**`cellcraze_manage_imei`** (NOT `manage_woocommerce`); admin + shop_manager hold both.

**Verified current behavior (from code):** the registry page and its menu item require
**`manage_woocommerce`**. Direct URL is `admin.php?page=cellcraze-imei`.

**Most likely root cause:** **capability mismatch.** The staff roles designed to manage IMEI
(cashier/warehouse/order-processing) have `cellcraze_manage_imei` but **not** `manage_woocommerce`,
so visiting `admin.php?page=cellcraze-imei` returns WordPress's *"Sorry, you are not allowed to access
this page."* for them (WP gates the page by the submenu's capability). Only administrators/shop
managers can open the registry. The REST IMEI endpoints already use `cellcraze_manage_imei ||
manage_woocommerce` — the registry is **inconsistent** with that. *(A pure "slug" 404 is less likely
since the slug/parent are valid while WooCommerce is active; confirm the exact error text.)*

**DB inspection needed:** NO (capability/role logic). Role assignment on the site should be confirmed.
**Read-only verification:** as a user with the **Warehouse/Cashier** role (or via
`wp user list` + `wp cap list <user>`), open `…/wp-admin/admin.php?page=cellcraze-imei` and record the
exact message; confirm that role lacks `manage_woocommerce` but has `cellcraze_manage_imei`.

**Proposed fix (pending approval):** register the registry page (and align the add/delete handlers'
`current_user_can` checks) to **`cellcraze_manage_imei`** so IMEI-managing roles can reach it, matching
the REST layer. Keep nonces intact.
**Regression tests:** warehouse/cashier can open the registry and add/delete units; a user with
neither cap gets a clean 403; admin/shop_manager unaffected; CC-13 role checks stay green.

**Risks:** LOW–MEDIUM (authorization change). Widening who can view/edit IMEI must be deliberate —
ensure `cellcraze_manage_imei` is the intended least-privilege for the registry, and that
**delete** (which currently also decrements stock, see the separate SIM-02 finding) stays
appropriately gated. No stock/purchasing/HPOS data impact from the cap change itself.

---

## Summary

| ID | Area | Code-verified cause (likely) | DB check needed | Risk |
|---|---|---|---|---|
| P0-1 | IMEI registry vs product tab | registry `LIMIT 200` + `product_id` 0/variation/deleted | YES | LOW (read) |
| P0-2 | Supplier referenced but missing | audit/IMEI use free-text name, no FK; soft-inactive hidden | YES | MEDIUM (purchasing/GRN WIP) |
| P0-3 | Registry direct-URL permission | page cap `manage_woocommerce` ≠ staff `cellcraze_manage_imei` | NO (role) | LOW–MED (authz) |

**Not changed:** no application code, DB, config, or Git state. Laptop uncommitted
`class-cellcraze-purchasing.php` + planning docs are untouched (not present in this clone).
**Next:** run the read-only SQL/role checks above on the laptop to confirm which cause applies, then
approve per-issue fixes. **Stop — awaiting approval before any implementation.**
