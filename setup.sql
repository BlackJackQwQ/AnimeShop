USE anime_shop;
UPDATE wp_options SET option_value = 'a:1:{i:0;s:25:"anime-shop/anime-shop.php";}' WHERE option_name = 'active_plugins';
UPDATE wp_options SET option_value = 'http://localhost:8080' WHERE option_name = 'siteurl';
UPDATE wp_options SET option_value = 'http://localhost:8080' WHERE option_name = 'home';
