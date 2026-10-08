#!/usr/bin/env bash
# Link a repo's theme folder into a "Local by Flywheel" site (macOS).
#
# Reusable across all your sites — run it once per site. It deletes any existing
# theme folder/symlink at the target and replaces it with a symlink to your repo,
# so the Local site always runs the theme you edit in VS Code / pull from GitHub.
#
# Usage:
#   scripts/link-theme-to-local.sh <path-to-repo-theme-dir> <local-site-folder-name>
#
# Examples:
#   scripts/link-theme-to-local.sh ~/Projects/cellcraze/wp-content/themes/cellcraze CellCraze
#   scripts/link-theme-to-local.sh ~/Projects/nuvirashop/wp-content/themes/nuvirashop nuvirashop
#
# Find <local-site-folder-name> in the Local app: right-click the site ->
# "Open site folder"; it is the folder name under "~/Local Sites/".

set -euo pipefail

if [ "$#" -ne 2 ]; then
  echo "Usage: $0 <path-to-repo-theme-dir> <local-site-folder-name>" >&2
  exit 1
fi

THEME_SRC="${1%/}"                       # repo theme dir (strip trailing slash)
SITE="$2"                                 # Local site folder name
LOCAL_THEMES="$HOME/Local Sites/$SITE/app/public/wp-content/themes"
THEME_NAME="$(basename "$THEME_SRC")"
TARGET="$LOCAL_THEMES/$THEME_NAME"

# --- Safety checks ---
if [ ! -d "$THEME_SRC" ]; then
  echo "ERROR: repo theme dir not found: $THEME_SRC" >&2
  exit 1
fi
if [ ! -f "$THEME_SRC/style.css" ]; then
  echo "ERROR: $THEME_SRC has no style.css — is this really a theme folder?" >&2
  exit 1
fi
if [ ! -d "$LOCAL_THEMES" ]; then
  echo "ERROR: Local themes folder not found: $LOCAL_THEMES" >&2
  echo "       Check the site name. In Local: right-click site -> Open site folder." >&2
  exit 1
fi

# --- Replace target with a symlink ---
if [ -L "$TARGET" ]; then
  echo "Removing old symlink: $TARGET"
  rm "$TARGET"
elif [ -d "$TARGET" ]; then
  echo "Removing duplicate theme copy: $TARGET"
  rm -rf "$TARGET"
fi

ln -s "$THEME_SRC" "$TARGET"
echo "Linked:"
echo "  $TARGET"
echo "   -> $THEME_SRC"
echo "Done. Activate the theme in the dashboard if it isn't already."
