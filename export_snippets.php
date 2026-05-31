<?php
require '/home4/germanta/public_html/wp-load.php';
global $wpdb;

$repo = dirname(__FILE__);
$snippets_dir = $repo . '/snippets';

$snippets = $wpdb->get_results("SELECT id, name, description, code, scope, active, priority FROM {$wpdb->prefix}snippets ORDER BY id");

foreach($snippets as $s) {
    $safe_name = preg_replace('/[^a-z0-9]+/', '_', strtolower($s->name));
    $filename  = $snippets_dir . '/' . $s->id . '_' . $safe_name . '.php';
    $active    = $s->active ? 'Yes' : 'No';
    $header    = "<?php\n/**\n * Snippet #{$s->id}: {$s->name}\n * Scope: {$s->scope} | Active: {$active} | Priority: {$s->priority}\n * {$s->description}\n */\n\n";
    file_put_contents($filename, $header . $s->code);
    echo "  Snippet #{$s->id}: {$s->name}\n";
}

echo "Total: " . count($snippets) . " snippets\n";
