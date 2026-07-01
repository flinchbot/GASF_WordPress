#!/bin/bash
# ══════════════════════════════════════════════════════
# GASF WordPress Backup Script
# Exports: snippets, CSS, theme files, pages, crontab → GitHub
# Mirrors the live site (source of truth): the snippet/page dirs are
# pruned and regenerated each run so removals are captured too.
# ══════════════════════════════════════════════════════
set -uo pipefail
REPO="/home4/germanta/gasf-repo"
WP="/home4/germanta/public_html"
PHP="/usr/local/bin/php"
DATE=$(date +"%Y-%m-%d %H:%M:%S")

echo "=== GASF WordPress Backup — $DATE ==="

# ── 1. Code Snippets (prune → export, mirrors the live DB) ──
echo "Exporting snippets..."
rm -f "$REPO"/snippets/*.php
"$PHP" "$REPO/export_snippets.php"

# ── 2. SiteOrigin CSS ──
echo "Exporting CSS..."
if cp "$WP/wp-content/uploads/so-css/so-css-hoot-du-premium.css" "$REPO/css/so-css-hoot-du-premium.css" 2>/dev/null; then
    echo "  so-css-hoot-du-premium.css"
else
    echo "  (so-css file not found — skipped)"
fi

# ── 3. Theme custom files (only those still present; retire the rest) ──
echo "Exporting theme files..."
for tf in single-mec-events.php; do
    src="$WP/wp-content/themes/hoot-du-premium/$tf"
    if [ -f "$src" ]; then
        cp "$src" "$REPO/theme/$tf"; echo "  $tf"
    else
        rm -f "$REPO/theme/$tf"; echo "  ($tf retired — removed from backup)"
    fi
done

# ── 4. Published pages (prune → export, mirrors the live set) ──
echo "Exporting pages..."
rm -f "$REPO"/pages/*.html
"$PHP" "$REPO/export_pages.php"

# ── 5. Crontab ──
echo "Exporting crontab..."
crontab -l > "$REPO/docs/crontab.txt" 2>/dev/null || echo "(empty)" > "$REPO/docs/crontab.txt"
echo "  crontab.txt"

# ── 6. Git commit and push (commit → rebase → push, self-heals a behind checkout) ──
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
