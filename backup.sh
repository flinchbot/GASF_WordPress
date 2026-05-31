#!/bin/bash
# ══════════════════════════════════════════════════════
# GASF WordPress Backup Script
# Exports: snippets, CSS, theme files, pages
# Run: bash /home4/germanta/gasf-repo/backup.sh
# ══════════════════════════════════════════════════════

set -e
REPO="/home4/germanta/gasf-repo"
WP="/home4/germanta/public_html"
DATE=$(date +"%Y-%m-%d %H:%M:%S")

echo "=== GASF WordPress Backup — $DATE ==="

# ── 1. Code Snippets ──────────────────────────────────
echo "Exporting snippets..."
cd $WP && php -r "
require 'wp-load.php';
global \$wpdb;
\$snippets = \$wpdb->get_results(\"SELECT id, name, description, code, scope, active, priority FROM {\$wpdb->prefix}snippets ORDER BY id\");
foreach(\$snippets as \$s) {
    \$filename = '{$REPO}/snippets/' . \$s->id . '_' . preg_replace('/[^a-z0-9]+/', '_', strtolower(\$s->name)) . '.php';
    \$header = \"<?php\\n/**\\n * Snippet #{$s->id}: {\$s->name}\\n * Scope: {\$s->scope} | Active: {\$s->active} | Priority: {\$s->priority}\\n * {\$s->description}\\n */\\n\";
    file_put_contents(\$filename, \$header . \$s->code);
    echo '  Snippet #' . \$s->id . ': ' . \$s->name . PHP_EOL;
}
"

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
cd $WP && php -r "
require 'wp-load.php';
\$pages = get_posts(['post_type'=>'page','post_status'=>'publish','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']);
foreach(\$pages as \$p) {
    \$slug = \$p->post_name ?: 'page-' . \$p->ID;
    \$filename = '{$REPO}/pages/' . \$p->ID . '_' . preg_replace('/[^a-z0-9-]+/', '_', \$slug) . '.html';
    \$content  = \"<!-- Page: {\$p->post_title} | ID: {\$p->ID} | Slug: {\$p->post_name} -->\\n\";
    \$content .= \$p->post_content;
    file_put_contents(\$filename, \$content);
}
echo '  ' . count(\$pages) . ' pages exported.' . PHP_EOL;
"

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
    echo "Pushed to GitHub."
}

echo "=== Done ==="
