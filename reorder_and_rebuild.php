<?php
// reorder_and_rebuild.php
// Reorders the CSV IDs starting from 1, permanently deletes existing anime_product posts, and reimports using the plugin import function.

// Load WordPress
// Run from webroot: C:\xampp\htdocs\AnimeShop\reorder_and_rebuild.php
define('WP_USE_THEMES', false);
require __DIR__ . '/wp-load.php';

$orig = __DIR__ . '/wc-product-export-27-4-2026-1777228433640.csv';
$tmp = __DIR__ . '/wc-product-export-reordered.csv';

if ( ! file_exists( $orig ) ) {
    echo "Original CSV not found: $orig\n";
    exit(1);
}

$lines = file( $orig );
if ( ! $lines || count( $lines ) < 2 ) {
    echo "CSV appears empty or unreadable.\n";
    exit(1);
}

// Parse header
$header_line = array_shift( $lines );
$header = str_getcsv( trim( $header_line ) );

$fp = fopen( $tmp, 'w' );
if ( ! $fp ) {
    echo "Unable to open tmp file for writing: $tmp\n";
    exit(1);
}
// write header
fputcsv( $fp, $header );

$index = 1;
foreach ( $lines as $line ) {
    $line = trim( $line );
    if ( $line === '' ) continue;
    $row = str_getcsv( $line );
    if ( ! $row ) continue;
    // Replace ID (first column) with sequential index
    $row[0] = (string) $index;
    fputcsv( $fp, $row );
    $index++;
}
fclose( $fp );

// Permanently delete existing anime_product posts
$posts = get_posts( array( 'post_type' => 'anime_product', 'numberposts' => -1, 'post_status' => 'any' ) );
if ( $posts ) {
    foreach ( $posts as $p ) {
        wp_delete_post( $p->ID, true );
        echo "Deleted post ID: " . $p->ID . "\n";
    }
} else {
    echo "No existing anime_product posts found.\n";
}

// Replace original CSV with reordered one
if ( ! rename( $tmp, $orig ) ) {
    echo "Failed to replace original CSV with reordered CSV.\n";
    exit(1);
}

// Call plugin import function
if ( function_exists( 'anime_shop_import_from_csv' ) ) {
    $count = anime_shop_import_from_csv();
    echo "Imported products: $count\n";
    exit(0);
} else {
    echo "Import function anime_shop_import_from_csv() not found. Make sure the plugin is active.\n";
    exit(1);
}
