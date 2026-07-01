#!/bin/bash
# ══════════════════════════════════════════════════════
# GASF WordPress Backup Script
# Exports: CSS, theme files, pages, crontab → GitHub
# Mirrors the live site (source of truth): the pages dir is pruned and
# regenerated each run so removals are captured too.
# (Code Snippets was removed 2026-07 — its logic now lives in the
#  GASF-Utilities mu-plugin — so there is no snippet export anymore.)
# ══════════════════════════════════════════════════════
set -uo pipefail
REPO="/home4/germanta/gasf-repo"
WP="/home4/germanta/public_html"
PHP="/usr/local/bin/php"
DATE=$(date +"%Y-%m-%d %H:%M:%S")

echo "=== GASF WordPress Backup — $DATE ==="

# ── 1. SiteOrigin CSS ──
echo "Exporting CSS..."
if cp "$WP/wp-content/uploads/so-css/so-css-hoot-du-premium.css" "$REPO/css/so-css-hoot-du-premium.css" 2>/dev/null; then
    echo "  so-css-hoot-du-premium.css"
else
    echo "  (so-css file not found — skipped)"
fi

# ── 2. Theme custom files (only those still present; retire the rest) ──
echo "Exporting theme files..."
for tf in single-mec-events.php; do
    src="$WP/wp-content/themes/hoot-du-premium/$tf"
    if [ -f "$src" ]; then
        cp "$src" "$REPO/theme/$tf"; echo "  $tf"
    else
        rm -f "$REPO/theme/$tf"; echo "  ($tf retired — removed from backup)"
    fi
done

# ── 3. Published pages (prune → export, mirrors the live set) ──
echo "Exporting pages..."
rm -f "$REPO"/pages/*.html
"$PHP" "$REPO/export_pages.php"

# ── 4. Crontab ──
echo "Exporting crontab..."
crontab -l > "$REPO/docs/crontab.txt" 2>/dev/null || echo "(empty)" > "$REPO/docs/crontab.txt"
echo "  crontab.txt"

# ── 5. Git commit and push (commit → rebase → push, self-heals a behind checkout) ──
echo "Committing to GitHub..."
cd "$REPO" || exit 1
git add -A
if git diff --cached --quiet; then
    echo "Nothing changed — no commit needed."
else
    git commit -m "Backup: $DATE"
    git pull --rebase origin main 2>&1 | tail -3 || true
    if git push origin main; then
        echo "Pushed to GitHub successfully."
    else
        echo "PUSH FAILED — see output above."
    fi
fi

echo "=== Done ==="
