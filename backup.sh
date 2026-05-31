#!/bin/bash
# ══════════════════════════════════════════════════════
# GASF WordPress Backup Script
# Exports: snippets, CSS, theme files, pages
# ══════════════════════════════════════════════════════

set -e
REPO="/home4/germanta/gasf-repo"
WP="/home4/germanta/public_html"
PHP="/usr/local/bin/php"
DATE=$(date +"%Y-%m-%d %H:%M:%S")

echo "=== GASF WordPress Backup — $DATE ==="

# ── 1. Code Snippets ──────────────────────────────────
echo "Exporting snippets..."
$PHP $REPO/export_snippets.php

# ── 2. SiteOrigin CSS ────────────────────────────────
echo "Exporting CSS..."
cp $WP/wp-content/uploads/so-css/so-css-hoot-du-premium.css $REPO/css/so-css-hoot-du-premium.css
echo "  so-css-hoot-du-premium.css"

# ── 3. Theme custom files ────────────────────────────
echo "Exporting theme files..."
cp $WP/wp-content/themes/hoot-du-premium/single-mec-events.php $REPO/theme/single-mec-events.php
echo "  single-mec-events.php"

# ── 4. Published pages ───────────────────────────────
echo "Exporting pages..."
$PHP $REPO/export_pages.php

# ── 5. Crontab ───────────────────────────────────────
echo "Exporting crontab..."
crontab -l > $REPO/docs/crontab.txt 2>/dev/null || echo "(empty)" > $REPO/docs/crontab.txt
echo "  crontab.txt"

# ── 6. Git commit and push ───────────────────────────
echo "Committing to GitHub..."
cd $REPO
git add -A
git diff --cached --quiet && echo "Nothing changed — no commit needed." || {
    git commit -m "Backup: $DATE"
    git push origin main
    echo "Pushed to GitHub successfully."
}

echo "=== Done ==="
