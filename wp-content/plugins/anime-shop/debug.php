<?php
require '/var/www/html/wp-load.php';
echo function_exists('anime_shop_header_shortcode') ? 'PLUGIN LOADED OK' : 'PLUGIN NOT LOADED';
echo PHP_EOL;
echo 'Active plugins: ' . get_option('active_plugins');
echo PHP_EOL;
