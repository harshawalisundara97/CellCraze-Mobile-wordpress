# Dev workflow: GitHub ↔ VS Code ↔ Local (Mac)

How to edit CellCraze in VS Code (or have Claude edit it), push to GitHub, pull on your
Mac, and see the change on your Local site — without keeping messy duplicate copies.

## What git syncs (and what it doesn't)

| Lives in git (synced) | Lives only in your Local database (NOT synced) |
|---|---|
| Theme files (`wp-content/themes/cellcraze/`) | Installed plugins |
| Setup scripts, CSV, docs | Products, categories, pages |
| | Payment / shipping settings |
| | Orders, customers |

So a **theme CSS/PHP change** flows through git to your site. A **setting or product**
does not — you redo those in the dashboard, or we script them.

---

## One-time setup

### 1. Install the tools
- **VS Code:** https://code.visualstudio.com → download → drag to Applications.
- **Git:** open **Terminal** (Cmd+Space → "Terminal"), type `git --version`, press Enter.
  If git isn't installed, macOS offers to install it — click **Install**.

### 2. Clone the repo with VS Code
1. Open **VS Code** → click the **Source Control** icon on the left (branches) → **Clone Repository**.
2. Paste: `https://github.com/harshawalisundara97/CellCraze-Mobile-wordpress.git`
3. Choose a folder to keep projects, e.g. make a **Projects** folder in your home folder.
4. When prompted, **Sign in to GitHub** (a browser window opens — approve it). This lets you push/pull.
5. VS Code opens the project. Bottom-left, click the branch name and switch to
   **`claude/sleepy-bohr-xefjt0`**.

### 3. Link the theme into Local (single source of truth)

You earlier dragged a copy of `cellcraze` into Local's themes folder. We'll delete that copy
and replace it with a **symlink** (a shortcut) that points at the repo copy — so Local always
uses the latest files you pull.

**Find your two real paths first:**
- **Repo path:** in VS Code, the folder you cloned into. (Right-click the top folder → *Copy Path*.)
- **Local site path:** in the **Local** app, right-click the **CellCraze** site → **Open site folder**,
  then go into `app/public/wp-content/themes`. (In Finder, right-click that `themes` folder →
  hold **Option** → *Copy "themes" as Pathname*.)

**Then, in Terminal**, run these two lines — replace the paths with YOUR real ones
(tip: type `rm -rf ` then drag the folder from Finder into Terminal to paste its path):

```bash
# 1. Remove the duplicate copy you dragged in earlier
rm -rf "/Users/YOU/Local Sites/CellCraze/app/public/wp-content/themes/cellcraze"

# 2. Link Local's theme slot to the repo's theme folder
ln -s "/Users/YOU/Projects/CellCraze-Mobile-wordpress/wp-content/themes/cellcraze" \
      "/Users/YOU/Local Sites/CellCraze/app/public/wp-content/themes/cellcraze"
```

Check it worked: in the dashboard **Appearance → Themes**, CellCraze still shows and stays active.

---

## Daily loop

### To get changes Claude (or you) pushed to GitHub → onto your site
1. In VS Code → **Source Control** → click the **⋯** menu → **Pull** (or the sync arrows at the bottom).
2. Refresh your site in the browser. For CSS changes, do a hard refresh: **Cmd+Shift+R**.
   (Because the theme is symlinked, pulled files are already in place — no copying needed.)

### To make your own change and push it
1. Edit files in VS Code (e.g. `wp-content/themes/cellcraze/style.css`).
2. **Source Control** → type a short message → **Commit** → **Sync / Push**.
3. It's now on GitHub for Claude and you to build on.

> Only `wp-content/themes/cellcraze/` reflects live on the site. If you add a new plugin or
> change a setting, that's database state — set it in the dashboard; it won't come from git.

---

## Common issues
- **"Theme is missing" after pull:** the symlink was broken/removed. Re-run step 3's `ln -s` line.
- **CSS change not showing:** hard refresh (Cmd+Shift+R); also check LiteSpeed Cache isn't caching (Dashboard → LiteSpeed → Purge All).
- **Push asks for a password:** finish the VS Code "Sign in to GitHub" step (2.4).
- **You see `cellcraze/cellcraze/`:** the symlink points one level too deep; it must point at the
  folder that directly contains `style.css`.
