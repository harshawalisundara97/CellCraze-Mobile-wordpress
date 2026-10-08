# Managing multiple WordPress sites on one Mac

A convention for working across several Local sites (nuvirahub, nuvirashop, cellcraze, …),
each with its own GitHub repo. One site = one repo = one Local site.

## Folder layout (parallel, matching names — never nested)

```
~/Projects/                    ← your code repos (edit here in VS Code)
   nuvirahub/
   nuvirashop/
   cellcraze/

~/Local Sites/                 ← Local creates these (running sites + databases)
   nuvirahub/app/public/...
   nuvirashop/app/public/...
   CellCraze/app/public/...
```

Each repo's custom theme is **symlinked** into its matching Local site, so there is a single
source of truth and pulled changes show up live.

## Golden rule: git = code, Local database = content

| In git (shared via GitHub) | Only in each Local site's database |
|---|---|
| Custom theme(s), custom plugins you write | Installed plugins |
| Scripts, docs, config | Products, pages, menus |
| | Settings (payments, shipping, SEO) |
| | Orders, customers, media uploads |

Code flows repo → GitHub → other machines. Content/settings do **not**; back those up with
**Local → right-click site → Export**, or a migration plugin.

## One-time setup per site

1. Site exists in **Local**, repo exists on **GitHub**.
2. Clone the repo into `~/Projects/<site>/` with VS Code (Source Control → Clone).
3. Link its theme into Local (reusable helper in this repo):
   ```bash
   # from inside the cellcraze repo, or copy the script anywhere
   scripts/link-theme-to-local.sh ~/Projects/<site>/wp-content/themes/<theme> <LocalSiteFolderName>
   ```
   Examples:
   ```bash
   scripts/link-theme-to-local.sh ~/Projects/cellcraze/wp-content/themes/cellcraze CellCraze
   scripts/link-theme-to-local.sh ~/Projects/nuvirahub/wp-content/themes/nuvirahub nuvirahub
   scripts/link-theme-to-local.sh ~/Projects/nuvirashop/wp-content/themes/nuvirashop nuvirashop
   ```
4. Each repo uses a `.gitignore` that excludes WP core, plugins, uploads, and the DB
   (see this repo's `.gitignore` for the pattern).

## Switch projects fast — one VS Code workspace

Create `~/Projects/all-sites.code-workspace`:

```json
{
  "folders": [
    { "path": "nuvirahub" },
    { "path": "nuvirashop" },
    { "path": "cellcraze" }
  ],
  "settings": {}
}
```

Double-click it → all three open in one window. Each keeps its own git in the Source Control
panel, so you commit/push each independently. (Prefer focus? Open one folder per window.)

## Daily loop (any site)

- **Get pushed changes:** VS Code → Source Control → **Pull** on that project → refresh the
  site (Cmd+Shift+R for CSS). Symlinked themes update with no copying.
- **Push your own:** edit → Commit → **Sync/Push** on that project.
- Keep each project's commits to its own repo — the multi-root window does this automatically
  as long as you use each folder's own Source Control section.
