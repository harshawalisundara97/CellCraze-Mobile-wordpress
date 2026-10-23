# CellCraze — Security notes (PHP web-shell / backdoor protection)

A "PHP web-shell" (a.k.a. PHP2Shell, c99/r57) is a malicious `.php` file an attacker
uploads to gain remote control of the site. This is the most common way WordPress sites
are fully compromised. Below is what the project does in code, and what the owner must do
on the live host.

## Built into the code (travels with every deploy)

- **Uploads folder is non-executable.** On `cellcraze-core` activation,
  `cellcraze_core_harden_uploads()` writes `wp-content/uploads/.htaccess` denying execution
  of `.php/.phtml/.phar/.cgi/.pl/.py/.asp/.sh` (Apache). An existing `.htaccess` is never
  overwritten. *(Nginx hosts must add the equivalent `location` rule — see below.)*
- **Private slip/receipt store.** Bank slips and expense receipts go to
  `uploads/cellcraze-private/` (its own `Require all denied` + randomized filenames), and
  are served only through a capability/ownership + nonce-gated endpoint (works on Nginx too).
- **Strict upload validation** (`class-cellcraze-bankslip.php`, `class-cellcraze-expense.php`):
  nonce, order ownership, size cap (5 MB), extension via `wp_check_filetype_and_ext()`, and
  **real MIME via `finfo`** (not the browser-sent type). Only JPG/PNG/PDF accepted.
- **File editor disabled.** `scripts/01-install-wp.sh` sets `DISALLOW_FILE_EDIT`, so a stolen
  admin session can't paste a shell via Appearance/Plugins → Editor.

## Owner must do on the live host (not in git)

- **Keep updated:** WordPress core, WooCommerce, every plugin/theme (enable auto-updates).
  Remove unused plugins/themes. Never use nulled/pirated plugins.
- **Strong auth:** unique admin password + **2FA**; limit login attempts; use HTTPS (already on).
- **Security plugin:** Wordfence (firewall + malware scan) — run a scan and enable the WAF.
- **Backups:** UpdraftPlus daily, stored off-site.
- **File permissions:** dirs `755`, files `644`, `wp-config.php` `640`.
- **Nginx hosts:** PHP in uploads is blocked by config, e.g.
  ```nginx
  location ~* /wp-content/uploads/.*\.(php|phtml|phar)$ { deny all; }
  ```
- Confirm the host runs its own malware scanner / WAF.
