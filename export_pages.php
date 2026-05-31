<?php
require '/home4/germanta/public_html/wp-load.php';

$pages_dir = dirname(__FILE__) . '/pages';

$pages = get_posts([
    'post_type'      => 'page',
    'post_status'    => 'publish',
    'numberposts'    => -1,
    'orderby'        => 'ID',
    'order'          => 'ASC',
]);

foreach($pages as $p) {
    $slug     = $p->post_name ?: 'page-' . $p->ID;
    $safe     = preg_replace('/[^a-z0-9-]+/', '_', $slug);
    $filename = $pages_dir . '/' . $p->ID . '_' . $safe . '.html';
    $content  = "<!-- Page: {$p->post_title} | ID: {$p->ID} | Slug: {$p->post_name} -->\n"
              . $p->post_content;
    file_put_contents($filename, $content);
}

echo "Total: " . count($pages) . " pages\n";
