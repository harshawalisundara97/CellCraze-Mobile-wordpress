# Production deployment — phoneshop.nuvirahub.com (Namecheap)

Automatic deploy after a PR is **merged into `main`**. Your Claude Code → GitHub
PR workflow is unchanged; this only adds what happens *after* a merge.

Workflow: [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml)

## How it behaves

- **Trigger:** `push` to `main` (a merge is a push). Opening or approving a PR
  does **not** deploy — PR events run the checks job only (`deploy` is gated to
  non-PR events on `refs/heads/main`).
- **Deploys only code:** the folders in `DEPLOY_PATHS` —
  `wp-content/themes/cellcraze` and `wp-content/plugins/cellcraze-core` — rsynced
  to the same paths under `REMOTE_WP_ROOT`. `--delete` is **scoped to each of
  those folders only**.
- **Never touches** WordPress core, `wp-config.php`, `wp-content/uploads`, other
  plugins, customer orders/accounts/products, or the database. Excludes
  `.git*`, `node_modules/`, `.github/`, `tests/`, `.env*`, `*.log`.
- **Checks:** validates deploy paths + `php -l` lint before deploying.
- **Concurrency:** group `deploy-phoneshop` — no overlapping deploys.
- **Verification:** after rsync, curls `https://phoneshop.nuvirahub.com/` and
  fails the run if it is not reachable (HTTP 200/301/302).
- **Failure reporting:** a failed deploy is marked failed in the Actions tab and
  written to the job summary.

## Required GitHub secrets

Repo → **Settings → Secrets and variables → Actions** (and the workflow uses a
`production` **environment**, so add them there if you want environment
protection rules):

| Secret | Value |
|---|---|
| `SSH_HOST` | your Namecheap server host (e.g. `server302.web-hosting.com` or the server IP) |
| `SSH_PORT` | Namecheap shared SSH port — usually **`21098`** (not 22) |
| `SSH_USER` | cPanel username (the `nuviwviu` in `REMOTE_WP_ROOT`) |
| `SSH_PRIVATE_KEY` | the **private** key whose public key is added in cPanel → SSH Access |
| `SSH_KNOWN_HOSTS` | the server's **pre-verified** host key line (see "Verify the host key" below). The deploy **fails closed** if this is empty — no trust-on-first-use. |

Credentials are never committed — they live only in GitHub Secrets.

## Verify the host key independently (before adding `SSH_KNOWN_HOSTS`)

The workflow no longer trusts whatever `ssh-keyscan` returns at deploy time; it
pins the key you store in `SSH_KNOWN_HOSTS`. You must obtain that key through a
channel you trust and confirm its fingerprint, so a man-in-the-middle key is
never trusted.

1. From a trusted machine, fetch the candidate key line and its fingerprint:
   ```bash
   ssh-keyscan -p 21098 198.54.120.224 > candidate_known_hosts
   ssh-keyscan -p 21098 198.54.120.224 | ssh-keygen -lf -
   ```
2. **Independently confirm** that fingerprint through a second channel — e.g.
   cPanel → **SSH Access** (which shows the server's key fingerprint), or
   Namecheap support — and check it matches what step 1 printed. Do **not**
   accept a fingerprint just because `ssh-keyscan` returned it.
3. Once it matches, paste the **contents of `candidate_known_hosts`** (one or
   more `198.54.120.224 ssh-ed25519 AAAA…` lines) into the `SSH_KNOWN_HOSTS`
   secret. `StrictHostKeyChecking=yes` then verifies every deploy against it.

> I did not invent or assume this key — you must verify and supply it.

## Verify the remote `rsync` dependency safely

rsync-over-SSH needs `rsync` on the server too (Namecheap cPanel usually has
it). Confirm once, read-only, from a trusted machine:
```bash
ssh -p 21098 nuviwviu@198.54.120.224 'which rsync && rsync --version | head -1'
```
If it prints a path/version, you're set. If missing, ask Namecheap to enable it
(or switch to the FTPS fallback). A missing remote rsync makes the deploy
**fail safely** — it never risks data.

## Namecheap / cPanel setup (you do this — needs your login)

1. **Enable SSH access.** On Namecheap shared hosting SSH is often off by
   default — enable it in cPanel → **SSH Access**, or request it from Namecheap
   support. Confirm the **port** (typically `21098`).
2. **Create a deploy SSH key:**
   - cPanel → **SSH Access → Manage SSH Keys → Generate a New Key** (no
     passphrase, or store it as a secret too), then **Authorize** the public key.
   - Download the **private** key → paste it into the `SSH_PRIVATE_KEY` secret.
     (Or generate locally with `ssh-keygen`, upload the `.pub` to cPanel,
     authorize it, and use the private half as the secret.)
3. **Confirm the document root path.** The workflow uses
   `REMOTE_WP_ROOT=/home/nuviwviu/phoneshop.nuvirahub.com`. Verify in cPanel →
   **File Manager** that WordPress for the subdomain lives there (it may instead
   be `/home/<user>/public_html/phoneshop` or similar). If different, update
   `REMOTE_WP_ROOT` in the workflow.
4. **First-time prerequisites on the live site** (installed via wp-admin, not by
   this pipeline): Storefront parent theme, WooCommerce, ATUM, and the other
   wordpress.org plugins; then activate the **CellCraze** theme and **CellCraze
   Core** plugin once.

## What is still MISSING / to confirm (per your "don't assume" rule)

- Whether **SSH is enabled** on your Namecheap plan and the **exact port**.
- The **real document-root path** for the subdomain (update `REMOTE_WP_ROOT` if
  it differs from the assumed `/home/nuviwviu/phoneshop.nuvirahub.com`).
- The four **secrets** above are set in GitHub.

If SSH cannot be enabled, the fallback is **FTPS** (a different workflow using
`SamKirkland/FTP-Deploy-Action`); tell me and I'll provide it — but SSH/rsync is
safer (incremental, scoped delete) and is what this workflow uses.

## Verify it works

1. Set the four secrets; confirm SSH + path.
2. Merge a small PR into `main` (e.g. a theme CSS tweak).
3. **Actions tab** → the *CI & Deploy (phoneshop)* run: `check` → `deploy` →
   **Verify deployment** should be green, and the run summary shows
   "✅ Deployed and reachable".
4. Load `https://phoneshop.nuvirahub.com/` and confirm your change is live.
5. If `deploy` fails at **Configure SSH**/**Rsync**, the secrets or port/path are
   wrong; if it fails at **Verify deployment**, files deployed but the site
   didn't respond 200 — check the live site/plugins.

## Rollback

Revert the merge commit on `main` and merge the revert — the previous code
redeploys automatically. Data is never touched by deploys.
