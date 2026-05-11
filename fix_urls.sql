-- Run this in your InfinityFree phpMyAdmin AFTER importing your database.
-- Replace 'http://animeshop.rf.gd' with your ACTUAL InfinityFree domain.

UPDATE wp_options SET option_value = REPLACE(option_value, 'http://localhost/AnimeShop', 'http://animeshop.rf.gd') WHERE option_name = 'home' OR option_name = 'siteurl';

UPDATE wp_posts SET post_content = REPLACE(post_content, 'http://localhost/AnimeShop', 'http://animeshop.rf.gd');

UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, 'http://localhost/AnimeShop', 'http://animeshop.rf.gd');

UPDATE wp_options SET option_value = REPLACE(option_value, 'http://localhost/AnimeShop', 'http://animeshop.rf.gd') WHERE option_name = 'active_plugins' OR option_name = 'template' OR option_name = 'stylesheet';
