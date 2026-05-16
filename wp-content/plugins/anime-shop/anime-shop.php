<?php
/*
Plugin Name: Anime Shop
Description: Simple custom plugin to manage anime products and cart.
Version: 0.1
Author: You
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Shop_Plugin {
    public function __construct() {
        add_action( 'init', array( $this, 'register_product_cpt' ) );
        add_action( 'init', array( $this, 'register_taxonomy' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_filter( 'body_class', array( $this, 'add_theme_body_class' ) );
    }

    public function add_theme_body_class( $classes ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $theme = get_user_meta( $user_id, 'theme_preference', true );
            if ( $theme === 'dark' ) {
                $classes[] = 'dark-theme';
            }
        }
        return $classes;
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style( 'anime-shop-frontend', plugins_url( 'anime-shop-frontend.css', __FILE__ ), array(), time() );
        wp_enqueue_script( 'anime-shop-js', plugins_url( 'anime-shop.js', __FILE__ ), array('jquery'), '1.0.1', true );
        wp_localize_script( 'anime-shop-js', 'AnimeShop', array(
            'apiUrl'  => esc_url_raw( rest_url( 'anime-shop/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'homeUrl' => home_url(),
        ) );
    }

    public function register_product_cpt() {
        $labels = array(
            'name' => 'Products',
            'singular_name' => 'Product',
            'menu_name' => 'Products',
            'name_admin_bar' => 'Product',
            'all_items' => 'All Products',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Product',
            'edit_item' => 'Edit Product',
            'new_item' => 'New Product',
            'view_item' => 'View Product',
            'search_items' => 'Search Products',
            'not_found' => 'No products found',
            'not_found_in_trash' => 'No products found in Trash',
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            // disable Gutenberg for this CPT so classic meta boxes show like WooCommerce
            'show_in_rest' => false,
            // avoid the block editor/content editor — use title + thumbnail + custom-fields
            'supports' => array( 'title', 'thumbnail' ),
            'menu_position' => 5,
            'menu_icon' => 'dashicons-cart',
        );
        register_post_type( 'anime_product', $args );
    }

    public function register_taxonomy() {
        // Use built-in 'category' taxonomy for product classification and ensure it's attached to the CPT
        register_taxonomy_for_object_type( 'category', 'anime_product' );
    }

    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'anime_shop_carts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            session_key VARCHAR(64) NULL,
            cart_data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // create a customer role if not present
        if ( ! get_role( 'shop_customer' ) ) {
            add_role( 'shop_customer', 'Shop Customer', array( 'read' => true ) );
        }
        // capability grants intentionally omitted; manage roles manually if needed

        // taxonomy registered on init (anime_shop_register_taxonomy)


        // create essential pages if missing
        $pages = array(
            array('slug' => 'home', 'title' => 'Home', 'content' => '[anime_homepage]'),
            array('slug' => 'anime-shop', 'title' => 'Shop', 'content' => '[anime_products]'),
            array('slug' => 'cart', 'title' => 'Cart', 'content' => '[anime_cart]'),
            array('slug' => 'checkout', 'title' => 'Checkout', 'content' => '[anime_checkout]'),
            array('slug' => 'about', 'title' => 'About', 'content' => '[anime_about]'),
            array('slug' => 'terms', 'title' => 'Terms & Conditions', 'content' => '[anime_terms]'),
            array('slug' => 'order-confirmed', 'title' => 'Order Confirmed', 'content' => '[anime_success]'),
            array('slug' => 'order-view', 'title' => 'Order Record', 'content' => '[anime_order_view]'),
            array('slug' => 'contact', 'title' => 'Contact', 'content' => '[anime_contact]'),
            array('slug' => 'search-results', 'title' => 'Discovery Results', 'content' => '[anime_search]'),
            array('slug' => 'register', 'title' => 'Register', 'content' => '[anime_register]'),
            array('slug' => 'login', 'title' => 'Login', 'content' => '[anime_login]'),
            array('slug' => 'account', 'title' => 'Account', 'content' => '[anime_account]'),
        );
        foreach ( $pages as $p ) {
            if ( ! get_page_by_path( $p['slug'] ) ) {
                wp_insert_post( array(
                    'post_title'   => $p['title'],
                    'post_name'    => $p['slug'],
                    'post_content' => $p['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ) );
            }
        }
    }
}

$anime_shop_plugin = new Anime_Shop_Plugin();

register_activation_hook( __FILE__, array( 'Anime_Shop_Plugin', 'activate' ) );

function anime_shop_get_or_create_term_hierarchy( $term_string ) {
    $parts = array_map( 'trim', explode( '>', $term_string ) );
    $parent = 0;
    foreach ( $parts as $part ) {
        $existing = term_exists( $part, 'category', $parent );
        if ( $existing !== 0 && $existing !== null ) {
            if ( is_array( $existing ) ) {
                $term_id = $existing['term_id'];
            } else {
                $term_id = $existing;
            }
        } else {
            $t = wp_insert_term( $part, 'category', array( 'parent' => $parent ) );
            $term_id = is_wp_error( $t ) ? 0 : $t['term_id'];
        }
        $parent = $term_id;
    }
    return $parent;
}

function anime_shop_import_from_csv( $file_path = null ) {
    $file = $file_path;
    if ( ! $file || ! file_exists( $file ) ) {
        return 0;
    }

    $handle = fopen( $file, 'r' );
    if ( ! $handle ) {
        return 0;
    }

    $headers = fgetcsv( $handle );
    if ( ! $headers ) {
        fclose( $handle );
        return 0;
    }

    $count = 0;
    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        if ( count( $row ) < 2 ) continue;
        $data = @array_combine( $headers, $row );
        if ( ! $data ) continue;
        
        $title = isset( $data['Name'] ) ? $data['Name'] : '';
        if ( ! $title ) continue;

        $q = new WP_Query( array( 'post_type' => 'anime_product', 'title' => $title, 'posts_per_page' => 1 ) );
        if ( $q->have_posts() ) {
            $post_id = $q->posts[0]->ID;
        } else {
            $post = array(
                'post_title' => $title,
                'post_content' => isset( $data['Description'] ) ? $data['Description'] : '',
                'post_excerpt' => isset( $data['Short description'] ) ? $data['Short description'] : '',
                'post_status' => ( isset( $data['Published'] ) && trim( $data['Published'] ) === '1' ) ? 'publish' : 'draft',
                'post_type' => 'anime_product',
            );
            $post_id = wp_insert_post( $post );
            if ( is_wp_error( $post_id ) || ! $post_id ) continue;
        }

        if ( ! empty( $data['Regular price'] ) ) update_post_meta( $post_id, '_price', trim( $data['Regular price'] ) );
        if ( ! empty( $data['Sale price'] ) ) update_post_meta( $post_id, '_sale_price', trim( $data['Sale price'] ) );
        if ( isset( $data['In stock?'] ) ) update_post_meta( $post_id, '_in_stock', trim( $data['In stock?'] ) );
        if ( ! empty( $data['SKU'] ) ) update_post_meta( $post_id, '_sku', trim( $data['SKU'] ) );
        if ( isset( $data['Stock'] ) ) update_post_meta( $post_id, 'stock', trim( $data['Stock'] ) );

        // New fields: Dimensions & Weight
        if ( isset( $data['Weight'] ) ) update_post_meta( $post_id, 'weight', trim( $data['Weight'] ) );
        if ( isset( $data['Length'] ) ) update_post_meta( $post_id, 'length', trim( $data['Length'] ) );
        if ( isset( $data['Width'] ) ) update_post_meta( $post_id, 'width', trim( $data['Width'] ) );
        if ( isset( $data['Height'] ) ) update_post_meta( $post_id, 'height', trim( $data['Height'] ) );

        // New fields: Attributes (JSON)
        if ( ! empty( $data['Attributes'] ) ) {
            $attrs = json_decode( $data['Attributes'], true );
            if ( is_array( $attrs ) ) {
                update_post_meta( $post_id, '_product_attributes', $attrs );
            }
        }

        if ( ! empty( $data['Images'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $image_urls = array_map( 'trim', explode( ',', $data['Images'] ) );
            $gallery_ids = array();
            foreach ( $image_urls as $img_url ) {
                if ( ! filter_var( $img_url, FILTER_VALIDATE_URL ) ) continue;
                // Changed 'src' to 'id' to get the attachment ID directly (more reliable)
                $attach_id = media_sideload_image( $img_url, $post_id, null, 'id' );
                if ( is_wp_error( $attach_id ) ) continue;
                
                if ( $attach_id ) {
                    $gallery_ids[] = intval( $attach_id );
                    if ( ! has_post_thumbnail( $post_id ) ) set_post_thumbnail( $post_id, $attach_id );
                }
            }
            if ( ! empty( $gallery_ids ) ) update_post_meta( $post_id, '_product_image_ids', $gallery_ids );
        }

        if ( ! empty( $data['Categories'] ) ) {
            $cats = array_map( 'trim', explode( ',', $data['Categories'] ) );
            $term_ids = array();
            foreach ( $cats as $c ) {
                if ( ! $c ) continue;
                $term_id = anime_shop_get_or_create_term_hierarchy( $c );
                if ( $term_id ) $term_ids[] = intval( $term_id );
            }
            if ( ! empty( $term_ids ) ) wp_set_post_terms( $post_id, $term_ids, 'category', false );
        }
        $count++;
    }

    fclose( $handle );
    return $count;
}


// --- Product data meta box (consolidated, WooCommerce-like) ---
add_action( 'add_meta_boxes', 'anime_shop_add_product_data_box' );
function anime_shop_add_product_data_box() {
    add_meta_box( 'anime_shop_product_data', 'Product Data & Categories', 'anime_shop_product_data_box', 'anime_product', 'normal', 'high' );
    
    // Remove standard boxes to move them "out of the right side"
    remove_meta_box( 'categorydiv', 'anime_product', 'side' );
    remove_meta_box( 'postimagediv', 'anime_product', 'side' );
}

function anime_shop_product_data_box( $post ) {
    wp_nonce_field( 'anime_shop_product_data', 'anime_shop_product_data_nonce' );

    $price = get_post_meta( $post->ID, '_price', true );
    $sale = get_post_meta( $post->ID, '_sale_price', true );
    $in_stock = get_post_meta( $post->ID, '_in_stock', true );
    $sku = get_post_meta( $post->ID, '_sku', true );
    $stock = get_post_meta( $post->ID, 'stock', true );
    $weight = get_post_meta( $post->ID, 'weight', true );
    $length = get_post_meta( $post->ID, 'length', true );
    $width = get_post_meta( $post->ID, 'width', true );
    $height = get_post_meta( $post->ID, 'height', true );
    $image_ids = get_post_meta( $post->ID, '_product_image_ids', true );
    if ( ! is_array( $image_ids ) ) {
        if ( empty( $image_ids ) ) $image_ids = array(); else $image_ids = array_filter( array_map( 'intval', (array) $image_ids ) );
    }

    // Redesign anime_shop_product_data_box (stacked/sectioned, premium)
    echo '<div id="anime-product-data" class="wrap" style="padding:15px; background:#f0f0f1; border-radius:8px;">';
    echo '<style>
        .anime-admin-section { margin-bottom: 25px; padding: 20px; border: 1px solid #e1e1e1; border-radius: 10px; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .anime-admin-section h3 { margin-top: 0; color: #1d2327; font-size: 1.1em; border-bottom: 3px solid #2271b1; display: inline-block; padding-bottom: 4px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .anime-admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .anime-admin-field { margin-bottom: 12px; }
        .anime-admin-field label { display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; font-size: 0.9em; }
        .anime-admin-field input[type="text"], .anime-admin-field input[type="number"], .anime-admin-field select { width: 100%; border: 1px solid #c3c4c7; border-radius: 6px; padding: 10px; font-size: 14px; background: #fff !important; transition: border-color 0.2s, box-shadow 0.2s; }
        .anime-admin-field input:focus, .anime-admin-field select:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
        .anime-attribute-row, .anime-variation-row { background: #f9f9f9; padding: 12px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 10px; }
    </style>';

    // General & Pricing
    echo '<div class="anime-admin-section"><h3>General & Pricing</h3><div class="anime-admin-grid">';
    echo '<div class="anime-admin-field"><label>Regular price</label><input type="text" name="_price" value="' . esc_attr( $price ) . '" /></div>';
    echo '<div class="anime-admin-field"><label>Sale price</label><input type="text" name="_sale_price" value="' . esc_attr( $sale ) . '" /></div>';
    echo '<div class="anime-admin-field"><label>SKU</label><input type="text" name="_sku" value="' . esc_attr( $sku ) . '" /></div>';
    echo '</div></div>';

    // Inventory & Shipping
    echo '<div class="anime-admin-section"><h3>Inventory & Shipping</h3><div class="anime-admin-grid">';
    echo '<div class="anime-admin-field"><label>Stock qty</label><input type="number" name="stock" value="' . esc_attr( $stock ) . '" min="0" /></div>';
    echo '<div class="anime-admin-field"><label>In stock</label><select name="_in_stock"><option value="1" ' . selected( $in_stock, '1', false ) . '>Yes</option><option value="0" ' . selected( $in_stock, '0', false ) . '>No</option></select></div>';
    echo '<div class="anime-admin-field"><label>Weight (kg)</label><input type="text" name="weight" value="' . esc_attr( $weight ) . '" /></div>';
    echo '<div class="anime-admin-field"><label>Length (cm)</label><input type="text" name="length" value="' . esc_attr( $length ) . '" /></div>';
    echo '<div class="anime-admin-field"><label>Width (cm)</label><input type="text" name="width" value="' . esc_attr( $width ) . '" /></div>';
    echo '<div class="anime-admin-field"><label>Height (cm)</label><input type="text" name="height" value="' . esc_attr( $height ) . '" /></div>';
    echo '</div></div>';

    // Categories (Moved from sidebar)
    echo '<div class="anime-admin-section"><h3>Product Categories</h3>';
    echo '<div class="anime-admin-field" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:10px; background:#fff; padding:10px; border-radius:6px; border:1px solid #ddd;">';
    $terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
    $current_cats = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        foreach ( $terms as $t ) {
            $checked = in_array( $t->term_id, $current_cats ) ? 'checked' : '';
            echo '<label style="font-weight:normal;"><input type="checkbox" name="post_category[]" value="' . intval( $t->term_id ) . '" ' . $checked . ' /> ' . esc_html( $t->name ) . '</label>';
        }
    }
    echo '</div></div>';

    // Attributes
    echo '<div class="anime-admin-section"><h3>Attributes</h3>';
    $attrs = get_post_meta( $post->ID, '_product_attributes', true );
    if ( ! is_array( $attrs ) ) $attrs = array();
    echo '<div id="anime-attributes">';
    if ( ! empty( $attrs ) ) {
        foreach ( $attrs as $key => $a ) {
            $aname = esc_attr( $a['name'] );
            $aval = esc_attr( is_array( $a['value'] ) ? implode( ',', $a['value'] ) : $a['value'] );
            $visible = ! empty( $a['visible'] ) ? 'checked' : '';
            $variation = ! empty( $a['variation'] ) ? 'checked' : '';
            echo '<div class="anime-attribute-row" style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">';
            echo '<input type="text" name="anime_attribute_name[]" value="' . $aname . '" placeholder="Name" style="flex:1;" />';
            echo '<input type="text" name="anime_attribute_value[]" value="' . $aval . '" placeholder="Values" style="flex:2;" />';
            echo '<label><input type="checkbox" name="anime_attribute_visible[]" value="1" ' . $visible . ' /> Visible</label>';
            echo '<label><input type="checkbox" name="anime_attribute_variation[]" value="1" ' . $variation . ' /> Variation</label>';
            echo ' <a href="#" class="anime-remove-attribute" style="color:red; text-decoration:none;">&times;</a>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<p><a href="#" class="button" id="anime-add-attribute">Add attribute</a></p>';
    echo '</div>';

    // Variations
    echo '<div class="anime-admin-section"><h3>Variations</h3>';
    $variations_meta = get_post_meta( $post->ID, '_product_variations', true );
    if ( ! is_array( $variations_meta ) ) $variations_meta = array();
    echo '<div id="anime-shop-data" style="display:none;" data-attrs="' . esc_attr( wp_json_encode( array_values( $attrs ) ) ) . '" data-variations="' . esc_attr( wp_json_encode( $variations_meta ) ) . '"></div>';
    echo '<div id="anime-variations-list"></div>';
    echo '<p><a href="#" class="button" id="anime-add-variation">Add variation</a></p>';
    echo '</div>';

    // ── Gallery – rebuilt clean ──────────────────────────────────────────
    echo '<div class="anime-admin-section" id="abp-gallery-wrap">';
    echo '<h3>Product Images</h3>';
    echo '<p class="description" style="margin:0 0 12px;color:#666;font-size:12px;">First image = primary featured image. Click &times; to remove.</p>';
    echo '<style>
        #abp-gallery-grid{display:flex;flex-wrap:wrap;gap:10px;min-height:40px;padding:4px 0 10px;}
        .abp-img-card{position:relative;width:100px;height:100px;border:2px solid #dcdcde;border-radius:8px;overflow:hidden;background:#f6f7f7;flex-shrink:0;}
        .abp-img-card img{width:100%;height:100%;object-fit:contain;display:block;}
        .abp-img-card:first-child{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;}
        .abp-img-card:first-child::after{content:\'PRIMARY\';position:absolute;bottom:0;left:0;right:0;background:rgba(34,113,177,.82);color:#fff;font-size:9px;text-align:center;padding:2px 0;letter-spacing:.5px;font-weight:700;}
        .abp-remove{position:absolute;top:3px;right:3px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.55);border:none;color:#fff;font-size:17px;line-height:1;cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;}
        .abp-remove:hover{background:rgba(200,30,30,.9);}
    </style>';
    echo '<div id="abp-gallery-grid">';
    if ( empty( $image_ids ) ) {
        echo '<span style="color:#aaa;font-size:13px;">No images yet.</span>';
    } else {
        foreach ( $image_ids as $img_id ) {
            $img_id = intval( $img_id );
            if ( ! $img_id ) continue;
            $src = wp_get_attachment_image_url( $img_id, 'medium' );
            if ( ! $src ) continue;
            echo '<div class="abp-img-card" data-id="' . $img_id . '">';
            echo '<img src="' . esc_url( $src ) . '" />';
            echo '<button type="button" class="abp-remove" data-id="' . $img_id . '" title="Remove">&times;</button>';
            echo '</div>';
        }
    }
    echo '</div>'; // #abp-gallery-grid
    echo '<input type="hidden" name="_product_image_ids" id="_product_image_ids" value="' . esc_attr( implode( ',', $image_ids ) ) . '">';
    echo '<button type="button" class="button button-primary" id="abp-add-images" style="margin-top:10px;">+ Add Images</button>';
    echo '</div>'; // #abp-gallery-wrap

    echo '<div class="anime-admin-section" style="border-bottom:none;"><h3>Tools</h3><p><button type="button" class="button" id="anime-copy-data">Copy product JSON</button> <button type="button" class="button" id="anime-paste-data">Paste product JSON</button></p></div>';

    echo '</div>';
}



// --- Simple cart storage ---
function anime_shop_get_session_key() {
    if ( is_user_logged_in() ) return 'user_' . get_current_user_id();
    if ( isset( $_COOKIE['anime_shop_session'] ) && $_COOKIE['anime_shop_session'] ) return sanitize_text_field( $_COOKIE['anime_shop_session'] );
    $k = wp_generate_password( 32, false );
    setcookie( 'anime_shop_session', $k, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
    return $k;
}

function anime_shop_get_cart() {
    $key = anime_shop_get_session_key();
    if ( strpos( $key, 'user_' ) === 0 ) {
        $user_id = intval( str_replace( 'user_', '', $key ) );
        $cart = get_user_meta( $user_id, 'anime_shop_cart', true );
        return is_array( $cart ) ? $cart : array();
    }
    $trans = get_transient( 'anime_shop_cart_' . $key );
    return is_array( $trans ) ? $trans : array();
}

function anime_shop_get_cart_count() {
    $cart = anime_shop_get_cart();
    $count = 0;
    if ( is_array( $cart ) ) {
        foreach ( $cart as $qty ) $count += intval( $qty );
    }
    return $count;
}

function anime_shop_set_cart( $cart ) {
    $key = anime_shop_get_session_key();
    if ( strpos( $key, 'user_' ) === 0 ) {
        $user_id = intval( str_replace( 'user_', '', $key ) );
        update_user_meta( $user_id, 'anime_shop_cart', $cart );
        return true;
    }
    return set_transient( 'anime_shop_cart_' . $key, $cart, 30 * DAY_IN_SECONDS );
}

// --- REST API for cart ---
add_action( 'rest_api_init', function() {
    register_rest_route( 'anime-shop/v1', '/cart/add', array(
        'methods' => 'POST',
        'callback' => 'anime_shop_rest_add_to_cart',
        'permission_callback' => '__return_true',
    ) );
    register_rest_route( 'anime-shop/v1', '/cart', array(
        array(
            'methods' => 'GET',
            'callback' => 'anime_shop_rest_get_cart',
            'permission_callback' => '__return_true',
        ),
        array(
            'methods' => 'POST',
            'callback' => 'anime_shop_rest_update_cart',
            'permission_callback' => '__return_true',
        ),
    ) );
} );

function anime_shop_rest_add_to_cart( $request ) {
    $params = $request->get_json_params();
    $product_id = isset( $params['product_id'] ) ? intval( $params['product_id'] ) : 0;
    $qty = isset( $params['quantity'] ) ? max(1, intval( $params['quantity'] ) ) : 1;
    if ( ! $product_id ) return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid product' ), 400 );

    // support variation attributes: attributes should be an associative array where keys are attribute slugs
    $attributes = isset( $params['attributes'] ) && is_array( $params['attributes'] ) ? $params['attributes'] : array();
    $variation_index = null;
    if ( ! empty( $attributes ) ) {
        $vars = get_post_meta( $product_id, '_product_variations', true );
        if ( is_array( $vars ) ) {
            foreach ( $vars as $i => $v ) {
                if ( empty( $v['attrs'] ) || ! is_array( $v['attrs'] ) ) continue;
                $match = true;
                foreach ( $v['attrs'] as $ak => $av ) {
                    if ( ! isset( $attributes[ $ak ] ) || (string) $attributes[ $ak ] !== (string) $av ) { $match = false; break; }
                }
                if ( $match ) { $variation_index = $i; break; }
            }
        }
    }

    $key = $product_id . ( $variation_index !== null ? (':v' . intval( $variation_index )) : '' );

    $cart = anime_shop_get_cart();
    if ( isset( $cart[ $key ] ) ) {
        $cart[ $key ] += $qty;
    } else {
        $cart[ $key ] = $qty;
    }
    anime_shop_set_cart( $cart );
    return new WP_REST_Response( array( 'success' => true, 'cart_count' => anime_shop_get_cart_count() ), 200 );
}

function anime_shop_rest_get_cart() {
    $cart = anime_shop_get_cart();
    $out = array();
    foreach ( $cart as $key => $qty ) {
        $pid = $key;
        $variation_index = null;
        if ( preg_match('/^(\d+):v(\d+)$/', $key, $m) ) {
            $pid = intval( $m[1] );
            $variation_index = intval( $m[2] );
        }
        $p = get_post( $pid );
        if ( ! $p ) continue;
        $price = floatval( get_post_meta( $pid, '_price', true ) );
        $sale = floatval( get_post_meta( $pid, '_sale_price', true ) );
        $final = $sale ? $sale : $price;
        $variation = null;
        if ( $variation_index !== null ) {
            $vars = get_post_meta( $pid, '_product_variations', true );
            if ( is_array( $vars ) && isset( $vars[ $variation_index ] ) ) {
                $v = $vars[ $variation_index ];
                $final = isset( $v['sale_price'] ) && $v['sale_price'] !== '' ? $v['sale_price'] : ( ( isset( $v['price'] ) && $v['price'] !== '' ) ? $v['price'] : $final );
                $variation = array( 'index' => $variation_index, 'attrs' => isset( $v['attrs'] ) ? $v['attrs'] : array(), 'sku' => isset( $v['sku'] ) ? $v['sku'] : '', 'image_id' => isset( $v['image_id'] ) ? intval( $v['image_id'] ) : 0 );
            }
        }
        $out[] = array( 'id' => $pid, 'title' => $p->post_title, 'quantity' => $qty, 'price' => $final, 'variation' => $variation );
    }
    return new WP_REST_Response( array( 'success' => true, 'items' => $out ), 200 );
}

function anime_shop_rest_update_cart( $request ) {
    $params = $request->get_json_params();
    $cart = anime_shop_get_cart();
    if ( isset( $params['updates'] ) && is_array( $params['updates'] ) ) {
        foreach ( $params['updates'] as $pid => $qty ) {
            $pid = intval( $pid );
            $qty = intval( $qty );
            if ( $qty <= 0 ) {
                unset( $cart[ $pid ] );
            } else {
                $cart[ $pid ] = $qty;
            }
        }
    }
    anime_shop_set_cart( $cart );
    return new WP_REST_Response( array( 'success' => true, 'cart' => $cart ), 200 );
}



add_shortcode( 'anime_cart', 'anime_shop_cart_shortcode' );
function anime_shop_cart_shortcode() {
    $cart = anime_shop_get_cart();
    
    if ( empty( $cart ) ) {
        return '<div class="anime-cart-empty" style="text-align:center; padding:100px 0;">
                    <div style="font-size:64px; margin-bottom:20px;">🛒</div>
                    <h2 style="font-weight:800; margin-bottom:10px;">Your cart is empty</h2>
                    <p style="color:#666; margin-bottom:30px;">Currently there are no artifacts in your collection.</p>
                    <a href="' . home_url('/anime-shop') . '" class="anime-btn anime-btn-primary">Back to Shop</a>
                </div>';
    }

    ob_start();
    ?>
    <div class="anime-container">
    <div class="cart-wrap">
        
        <!-- MAIN LIST -->
        <div class="cart-main">
            <h1 class="cart-page-title">Your Collection <span>(<?php echo count($cart); ?>)</span></h1>
            
            <div class="cart-items">
                <?php
                $total = 0;
                foreach ( $cart as $key => $qty ) :
                    $pid = $key;
                    $variation_index = null;
                    $var_label = '';
                    
                    if ( preg_match('/^(\d+):v(\d+)$/', $key, $m) ) {
                        $pid = intval( $m[1] );
                        $variation_index = intval( $m[2] );
                    }
                    $p = get_post( $pid );
                    if ( ! $p ) continue;
                    
                    $price = floatval( get_post_meta( $pid, '_price', true ) );
                    $sale = floatval( get_post_meta( $pid, '_sale_price', true ) );
                    $final = $sale ? $sale : $price;
                    
                    if ( $variation_index !== null ) {
                        $vars = get_post_meta( $pid, '_product_variations', true );
                        if ( is_array( $vars ) && isset( $vars[ $variation_index ] ) ) {
                            $v = $vars[ $variation_index ];
                            $final = isset( $v['sale_price'] ) && $v['sale_price'] !== '' ? $v['sale_price'] : ( ( isset( $v['price'] ) && $v['price'] !== '' ) ? $v['price'] : $final );
                            
                            // Build variation label
                            $v_labels = array();
                            if ( isset( $v['attrs'] ) && is_array( $v['attrs'] ) ) {
                                foreach ( $v['attrs'] as $aname => $aval ) {
                                    $v_labels[] = $aval;
                                }
                            }
                            $var_label = implode(' / ', $v_labels);
                        }
                    }
                    
                    $subtotal = $final * $qty;
                    $total += $subtotal;
                    $img_url = get_the_post_thumbnail_url( $pid, 'medium' );
                    if ( ! $img_url ) {
                        $ids = get_post_meta( $pid, '_product_image_ids', true );
                        if ( is_array( $ids ) && ! empty( $ids ) ) {
                            $img_url = wp_get_attachment_image_url( $ids[0], 'medium' );
                        }
                    }
                    $img_url = $img_url ?: '';
                    ?>
                    <div class="cart-row" data-key="<?php echo esc_attr($key); ?>">
                        <div class="cart-col-img">
                            <a href="<?php echo get_permalink($pid); ?>" style="display:block;width:80px;height:80px;background:#f6f7f7;border-radius:6px;overflow:hidden;">
                                <?php if ( $img_url ) : ?>
                                    <img src="<?php echo esc_url($img_url); ?>" alt="" style="width:100%;height:100%;object-fit:contain;display:block;">
                                <?php else : ?>
                                    <div class="cart-no-img">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5-9h10v2H7z"/></svg>
                                    </div>
                                <?php endif; ?>
                            </a>
                        </div>
                        
                        <div class="cart-col-info">
                            <h2 class="cart-item-title"><a href="<?php echo get_permalink($pid); ?>"><?php echo get_the_title($pid); ?></a></h2>
                            <?php if ( $var_label ) : ?>
                                <p class="cart-item-meta"><?php echo esc_html($var_label); ?></p>
                            <?php endif; ?>
                            <button class="cart-remove anime-remove-from-cart" data-key="<?php echo esc_attr($key); ?>">Remove</button>
                        </div>
                        
                        <div class="cart-col-qty">
                            <div class="cart-qty">
                                <button class="cart-qty-btn minus" data-key="<?php echo esc_attr($key); ?>">−</button>
                                <input type="number" class="cart-qty-val" value="<?php echo intval($qty); ?>" min="1" readonly>
                                <button class="cart-qty-btn plus" data-key="<?php echo esc_attr($key); ?>">+</button>
                            </div>
                        </div>
                        
                        <div class="cart-col-price">
                            <span class="cart-item-price"><?php echo number_format($subtotal, 0, ',', '.') . ' &#8363;'; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="cart-actions-bottom">
                <a href="<?php echo home_url('/anime-shop'); ?>" class="cart-continue">← Continue Discovery</a>
            </div>
        </div>

        <!-- SUMMARY PANEL -->
        <div class="cart-side">
            <div class="cart-summary-box">
                <h3 class="summary-title">Summary</h3>
                
                <div class="summary-details">
                    <div class="summary-line">
                        <span>Subtotal</span>
                        <span><?php echo number_format($total, 0, ',', '.') . ' &#8363;'; ?></span>
                    </div>
                    <div class="summary-line">
                        <span>Shipping</span>
                        <span class="summary-free">Complimentary</span>
                    </div>
                    <?php if ( $total > 5000000 ) : // Example discount ?>
                    <div class="summary-line discount">
                        <span>Boutique Tier Discount</span>
                        <span>- 0 &#8363;</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="summary-total">
                    <span>Total</span>
                    <span class="total-amount"><?php echo number_format($total, 0, ',', '.') . ' &#8363;'; ?></span>
                </div>
                
                <p class="summary-vat">Prices include VAT where applicable.</p>
                
                <button class="cart-checkout-btn checkout-btn btn-premium" onclick="window.location.href='<?php echo home_url('/checkout'); ?>'">Secure Checkout</button>
                
                <div class="cart-security-badges">
                    <span>🛡️ Encrypted SSL</span>
                    <span>💳 Global Payments</span>
                </div>
            </div>
        </div>
        
    </div>
    </div>
    <?php
    return ob_get_clean();
}

// Registration of JS handled by anime-shop.js file. Support for variations is now dynamic.

// --- Orders CPT and checkout ---
add_action( 'init', 'anime_shop_register_order_cpt' );
function anime_shop_register_order_cpt() {
    register_post_type( 'anime_order', array(
        'labels' => array('name' => 'Orders','singular_name' => 'Order'),
        'public' => false,
        'show_ui' => true,
        'capability_type' => 'post',
        'supports' => array( 'title' ),
        'menu_icon' => 'dashicons-clipboard',
    ) );
}

// REST endpoint for checkout



// --- Admin list columns for products (improved) ---
add_filter( 'manage_edit-anime_product_columns', 'anime_shop_product_columns' );
function anime_shop_product_columns( $columns ) {
    $cols = array();
    $cols['cb'] = isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />';
    $cols['thumbnail'] = 'Image';
    $cols['title'] = 'Product';
    $cols['sku'] = 'SKU';
    $cols['price'] = 'Price';
    $cols['stock'] = 'Stock';
    $cols['categories'] = 'Categories';
    $cols['date'] = isset( $columns['date'] ) ? $columns['date'] : 'Date';
    return $cols;
}
add_action( 'manage_anime_product_posts_custom_column', 'anime_shop_product_column', 10, 2 );
function anime_shop_product_column( $column, $post_id ) {
    // The 'title' core column — we output the title meta line for display here,
    // but the hidden quickdata is output in the 'price' column below since
    // WordPress core renders the title column itself and never fires this action for it.
    if ( $column === 'title' ) {
        $price = get_post_meta( $post_id, '_price', true );
        $sale = get_post_meta( $post_id, '_sale_price', true );
        $stock = get_post_meta( $post_id, 'stock', true );
        $in_stock = get_post_meta( $post_id, '_in_stock', true );
        $sku = get_post_meta( $post_id, '_sku', true );
        $terms = get_the_terms( $post_id, 'category' );
        $term_names = array();
        if ( $terms && ! is_wp_error( $terms ) ) {
            $term_names = wp_list_pluck( $terms, 'name' );
        }

        $price_display = $sale ? '<del>' . esc_html( $price ) . '</del> <strong>' . esc_html( $sale ) . '</strong>' : esc_html( $price );
        $stock_display = $in_stock === '1' ? intval( $stock ) : 'Out of stock';
        echo '<div style="color:#666;margin-top:4px;font-size:12px;">';
        if ( $sku ) echo 'SKU: ' . esc_html( $sku ) . ' &nbsp;|&nbsp; ';
        echo 'Price: ' . $price_display . ' &nbsp;|&nbsp; Stock: ' . $stock_display;
        if ( ! empty( $term_names ) ) {
            echo ' &nbsp;|&nbsp; Categories: ' . esc_html( implode( ', ', $term_names ) );
        }
        echo '</div>';

        return;
    }

    switch ( $column ) {
        case 'thumbnail':
            // Prefer WP featured image, fall back to first gallery image
            $thumb_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
            if ( ! $thumb_url ) {
                $ids = get_post_meta( $post_id, '_product_image_ids', true );
                if ( is_array( $ids ) && ! empty( $ids ) ) {
                    $thumb_url = wp_get_attachment_image_url( $ids[0], 'thumbnail' );
                }
            }
            if ( $thumb_url ) {
                echo '<div style="width:60px;height:60px;flex-shrink:0;background:#f6f7f7;border-radius:6px;border:1px solid #dcdcde;overflow:hidden;">';
                echo '<img src="' . esc_url( $thumb_url ) . '" style="width:100%;height:100%;object-fit:contain;display:block;" />';
                echo '</div>';
            } else {
                echo '<div style="width:60px;height:60px;background:#f0f0f1;border-radius:6px;border:1px solid #dcdcde;display:flex;align-items:center;justify-content:center;">';
                echo '<span class="dashicons dashicons-format-image" style="color:#c3c4c7;font-size:24px;width:24px;height:24px;"></span>';
                echo '</div>';
            }
            break;
        case 'sku':
            echo esc_html( get_post_meta( $post_id, '_sku', true ) );
            break;
        case 'price':
            $price = get_post_meta( $post_id, '_price', true );
            $sale = get_post_meta( $post_id, '_sale_price', true );
            $stock = get_post_meta( $post_id, 'stock', true );
            $in_stock = get_post_meta( $post_id, '_in_stock', true );
            $sku_val = get_post_meta( $post_id, '_sku', true );
            $terms = get_the_terms( $post_id, 'category' );
            $term_ids = array();
            if ( $terms && ! is_wp_error( $terms ) ) {
                $term_ids = wp_list_pluck( $terms, 'term_id' );
            }
            // Output hidden quickdata here — this column IS a custom column so the action fires
            echo '<div class="anime-quickdata" style="display:none"'
                . ' data-price="' . esc_attr( $price ) . '"'
                . ' data-sale_price="' . esc_attr( $sale ) . '"'
                . ' data-sku="' . esc_attr( $sku_val ) . '"'
                . ' data-stock="' . esc_attr( $stock ) . '"'
                . ' data-in_stock="' . esc_attr( $in_stock ) . '"'
                . ' data-cats="' . esc_attr( implode( ',', $term_ids ) ) . '"></div>';
            if ( $sale !== '' && $sale !== null ) {
                echo '<del>' . esc_html( $price ) . '</del> <strong>' . esc_html( $sale ) . '</strong>';
            } else {
                echo esc_html( $price );
            }
            break;
        case 'stock':
            $stock = get_post_meta( $post_id, 'stock', true );
            $in_stock = get_post_meta( $post_id, '_in_stock', true );
            echo $in_stock === '1' ? intval( $stock ) : 'Out';
            break;
        case 'categories':
            $terms = get_the_terms( $post_id, 'category' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $names = wp_list_pluck( $terms, 'name' );
                echo esc_html( implode( ', ', $names ) );
            }
            break;
    }
}

add_filter( 'manage_edit-anime_product_sortable_columns', 'anime_shop_product_sortable_columns' );
function anime_shop_product_sortable_columns( $cols ) {
    $cols['price'] = 'price';
    $cols['stock'] = 'stock';
    return $cols;
}

// Allow sorting by price/stock (meta fields)
add_action( 'pre_get_posts', 'anime_shop_products_orderby' );
function anime_shop_products_orderby( $query ) {
    if ( ! is_admin() ) return;
    // Only alter the main query for the anime_product list table
    $post_type = $query->get( 'post_type' );
    if ( $post_type !== 'anime_product' ) return;
    $orderby = $query->get( 'orderby' );
    if ( $orderby === 'price' ) {
        $query->set( 'meta_key', '_price' );
        $query->set( 'orderby', 'meta_value_num' );
    } elseif ( $orderby === 'stock' ) {
        $query->set( 'meta_key', 'stock' );
        $query->set( 'orderby', 'meta_value_num' );
    }
}

// Small admin CSS to make list table look closer to WooCommerce
add_action( 'admin_head', 'anime_shop_admin_head' );
function anime_shop_admin_head() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'anime_product' ) return;
    echo '<style>';
    echo '.wp-list-table .column-thumbnail{width:74px;padding-left:8px;padding-right:8px;}' . chr(10);
    echo '.wp-list-table .column-price{text-align:right;width:120px;}' . chr(10);
    echo '.wp-list-table .column-stock{text-align:center;width:80px;}' . chr(10);
    echo '.wp-list-table .column-categories{width:200px;}' . chr(10);
    echo '.wp-list-table .column-title strong a{font-weight:600;}' . chr(10);
    echo '</style>';

}
// Enqueue admin scripts for product screens (media uploader and quick-edit)
add_action( 'admin_enqueue_scripts', 'anime_shop_admin_enqueue' );
function anime_shop_admin_enqueue( $hook ) {
    $screen = get_current_screen();
    // Only load on the anime_product post edit/new screens and the product list
    if ( ! $screen ) return;
    if ( $screen->post_type !== 'anime_product' && $hook !== 'edit.php' ) return;
    wp_enqueue_media();
    wp_enqueue_script( 'anime-shop-admin-js', plugins_url( 'anime-shop-admin.js', __FILE__ ), array( 'jquery', 'wp-util' ), null, true );
    wp_localize_script( 'anime-shop-admin-js', 'AnimeShopAdmin', array(
        'nonce'    => wp_create_nonce( 'anime_shop_admin' ),
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ) );
}

// Quick edit fields
add_action( 'quick_edit_custom_box', 'anime_shop_quick_edit_custom_box', 10, 2 );
function anime_shop_quick_edit_custom_box( $column_name, $post_type ) {
    if ( $post_type !== 'anime_product' ) return;
    // Output the quick-edit fields once (attached to the Price column)
    if ( $column_name === 'price' ) {
        echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col">';
        echo '<h4 style="margin:10px 0 5px 0; border-bottom:1px solid #ddd; padding-bottom:3px; color:#1d2327;">Product Stats</h4>';
        echo '<label><span class="title">Price ($)</span><span class="input-text-wrap"><input type="text" name="_price" value="" /></span></label>';
        echo '<label><span class="title">Sale ($)</span><span class="input-text-wrap"><input type="text" name="_sale_price" value="" /></span></label>';
        echo '<label><span class="title">SKU</span><span class="input-text-wrap"><input type="text" name="_sku" value="" /></span></label>';
        echo '<label><span class="title">Stock</span><span class="input-text-wrap"><input type="number" name="stock" value="" min="0" /></span></label>';
        echo '<label><span class="title">Available</span><span class="input-text-wrap"><select name="_in_stock"><option value="1">Yes</option><option value="0">No</option></select></span></label>';
        echo '</div></fieldset>';
    }
}

// Allow quick-edit (AJAX) inline saves by accepting AJAX without nonce
add_action( 'save_post', 'anime_shop_save_product_data', 10, 2 );
function anime_shop_save_product_data( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( $post->post_type !== 'anime_product' ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Nonce check (skipped for AJAX quick-edit)
    if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        if ( ! isset( $_POST['anime_shop_product_data_nonce'] ) ||
             ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['anime_shop_product_data_nonce'] ) ), 'anime_shop_product_data' ) ) {
            return;
        }
    }

    if ( isset( $_POST['_price'] ) ) update_post_meta( $post_id, '_price', sanitize_text_field( wp_unslash( $_POST['_price'] ) ) );
    if ( isset( $_POST['_sale_price'] ) ) update_post_meta( $post_id, '_sale_price', sanitize_text_field( wp_unslash( $_POST['_sale_price'] ) ) );
    if ( isset( $_POST['_in_stock'] ) ) update_post_meta( $post_id, '_in_stock', sanitize_text_field( wp_unslash( $_POST['_in_stock'] ) ) );
    if ( isset( $_POST['_sku'] ) ) update_post_meta( $post_id, '_sku', sanitize_text_field( wp_unslash( $_POST['_sku'] ) ) );
    if ( isset( $_POST['stock'] ) ) update_post_meta( $post_id, 'stock', intval( $_POST['stock'] ) );
    if ( isset( $_POST['weight'] ) ) update_post_meta( $post_id, 'weight', sanitize_text_field( wp_unslash( $_POST['weight'] ) ) );
    if ( isset( $_POST['length'] ) ) update_post_meta( $post_id, 'length', sanitize_text_field( wp_unslash( $_POST['length'] ) ) );
    if ( isset( $_POST['width'] ) ) update_post_meta( $post_id, 'width', sanitize_text_field( wp_unslash( $_POST['width'] ) ) );
    if ( isset( $_POST['height'] ) ) update_post_meta( $post_id, 'height', sanitize_text_field( wp_unslash( $_POST['height'] ) ) );

    // Image persistence – always authoritative
    if ( array_key_exists( '_product_image_ids', $_POST ) ) {
        $raw     = sanitize_text_field( wp_unslash( $_POST['_product_image_ids'] ) );
        $ids_arr = array_values( array_filter( array_map( 'intval', explode( ',', $raw ) ) ) );
        update_post_meta( $post_id, '_product_image_ids', $ids_arr );
        if ( ! empty( $ids_arr ) ) {
            set_post_thumbnail( $post_id, $ids_arr[0] );
        } else {
            delete_post_thumbnail( $post_id );
        }
    }

    // handle categories from our custom meta box
    if ( isset( $_POST['post_category'] ) && is_array( $_POST['post_category'] ) ) {
        $term_ids = array_map( 'intval', $_POST['post_category'] );
        wp_set_post_terms( $post_id, $term_ids, 'category', false );
    }

    // handle quick-edit categories (array of term ids)
    if ( isset( $_POST['anime_quick_categories'] ) && is_array( $_POST['anime_quick_categories'] ) ) {
        $term_ids = array_map( 'intval', $_POST['anime_quick_categories'] );
        $term_ids = array_filter( $term_ids );
        if ( ! empty( $term_ids ) ) {
            wp_set_post_terms( $post_id, $term_ids, 'category', false );
        } else {
            // clear categories
            wp_set_post_terms( $post_id, array(), 'category', false );
        }
    }

    // Attributes
    if ( isset( $_POST['anime_attribute_name'] ) && is_array( $_POST['anime_attribute_name'] ) ) {
        $names = $_POST['anime_attribute_name'];
        $values = isset( $_POST['anime_attribute_value'] ) ? $_POST['anime_attribute_value'] : array();
        $visible = isset( $_POST['anime_attribute_visible'] ) ? $_POST['anime_attribute_visible'] : array();
        $variation = isset( $_POST['anime_attribute_variation'] ) ? $_POST['anime_attribute_variation'] : array();
        $attrs = array();
        foreach ( $names as $i => $n ) {
            $n = sanitize_text_field( wp_unslash( $n ) );
            if ( $n === '' ) continue;
            $v = isset( $values[ $i ] ) ? sanitize_text_field( wp_unslash( $values[ $i ] ) ) : '';
            // split by comma and trim
            $vals = array_map( 'trim', explode( ',', $v ) );
            $vals = array_filter( $vals, function( $x ){ return $x !== ''; } );
            $vis = in_array( '1', array( isset( $visible[ $i ] ) ? $visible[ $i ] : '' ) ) || ( isset( $visible[ $i ] ) && $visible[ $i ] );
            $var = in_array( '1', array( isset( $variation[ $i ] ) ? $variation[ $i ] : '' ) ) || ( isset( $variation[ $i ] ) && $variation[ $i ] );
            $attrs[ sanitize_title( $n ) ] = array(
                'name' => $n,
                'value' => $vals,
                'visible' => $vis ? 1 : 0,
                'variation' => $var ? 1 : 0,
            );
        }
        update_post_meta( $post_id, '_product_attributes', $attrs );
    }

    // Variations (JSON per variation submitted in anime_variations[])
    if ( isset( $_POST['anime_variations'] ) && is_array( $_POST['anime_variations'] ) ) {
        $vars = array();
        foreach ( $_POST['anime_variations'] as $vraw ) {
            $vraw = wp_unslash( $vraw );
            $v = json_decode( $vraw, true );
            if ( ! $v || ! is_array( $v ) ) continue;
            $san_attrs = array();
            if ( isset( $v['attrs'] ) && is_array( $v['attrs'] ) ) {
                foreach ( $v['attrs'] as $k => $val ) {
                    $san_attrs[ sanitize_title( $k ) ] = sanitize_text_field( $val );
                }
            }
            $vars[] = array(
                'attrs' => $san_attrs,
                'price' => isset( $v['price'] ) ? sanitize_text_field( $v['price'] ) : '',
                'sale_price' => isset( $v['sale_price'] ) ? sanitize_text_field( $v['sale_price'] ) : '',
                'sku' => isset( $v['sku'] ) ? sanitize_text_field( $v['sku'] ) : '',
                'stock' => isset( $v['stock'] ) ? intval( $v['stock'] ) : 0,
                'image_id' => isset( $v['image_id'] ) ? intval( $v['image_id'] ) : 0,
            );
        }
        update_post_meta( $post_id, '_product_variations', $vars );
    }

}

// --- Admin list columns for orders ---
add_filter( 'manage_edit-anime_order_columns', 'anime_shop_order_columns' );
function anime_shop_order_columns( $columns ) {
    $cols = array(
        'cb' => $columns['cb'],
        'title' => 'Order',
        'customer' => 'Customer',
        'total' => 'Total',
        'date' => $columns['date'],
    );
    return $cols;
}
add_action( 'manage_anime_order_posts_custom_column', 'anime_shop_order_column', 10, 2 );
function anime_shop_order_column( $column, $post_id ) {
    if ( $column === 'customer' ) {
        $name = get_post_meta( $post_id, 'customer_name', true );
        $email = get_post_meta( $post_id, 'customer_email', true );
        echo esc_html( $name ? $name . ' (' . $email . ')' : $email );
    }
    if ( $column === 'total' ) {
        $total = get_post_meta( $post_id, 'order_total', true );
        echo esc_html( number_format( floatval($total), 0, ',', '.' ) . ' ₫' );
    }
}

// --- Order meta box to view/edit status and items ---
add_action( 'add_meta_boxes', 'anime_shop_order_meta_box' );
function anime_shop_order_meta_box() {
    add_meta_box( 'anime_shop_order_details', 'Order Details', 'anime_shop_order_details_box', 'anime_order', 'normal', 'high' );
}
function anime_shop_order_details_box( $post ) {
    wp_nonce_field( 'anime_shop_save_order_meta', 'anime_shop_order_meta_nonce' );
    $items = get_post_meta( $post->ID, 'order_items', true );
    $total = get_post_meta( $post->ID, 'order_total', true );
    $name = get_post_meta( $post->ID, 'customer_name', true );
    $email = get_post_meta( $post->ID, 'customer_email', true );
    $address = get_post_meta( $post->ID, 'customer_address', true );
    $status = get_post_meta( $post->ID, 'order_status', true );
    if ( ! $status ) $status = 'pending';
    echo '<p><strong>Customer:</strong> ' . esc_html( $name ) . ' &lt;' . esc_html( $email ) . '&gt;</p>';
    echo '<p><strong>Address:</strong><br/>' . nl2br( esc_html( $address ) ) . '</p>';
    echo '<p><strong>Items:</strong></p><ul>';
    if ( is_array( $items ) ) {
        foreach ( $items as $it ) {
            $it_price = number_format($it['subtotal'], 0, ',', '.') . ' ₫';
            echo '<li>' . esc_html( $it['title'] ) . ' x' . intval( $it['quantity'] ) . ' — ' . esc_html( $it_price ) . '</li>';
        }
    }
    echo '</ul>';
    echo '<p><strong>Total:</strong> ' . esc_html( number_format( $total, 0, ',', '.' ) . ' ₫' ) . '</p>';

    echo '<p><label>Order status: <select name="order_status">';
    $options = array( 'pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'cancelled' => 'Cancelled' );
    foreach ( $options as $k => $label ) {
        echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, $k, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></label></p>';
}

add_action( 'save_post', 'anime_shop_save_order_meta', 20, 2 );
function anime_shop_save_order_meta( $post_id, $post ) {
    if ( $post->post_type !== 'anime_order' ) return;
    if ( ! isset( $_POST['anime_shop_order_meta_nonce'] ) || ! wp_verify_nonce( $_POST['anime_shop_order_meta_nonce'], 'anime_shop_save_order_meta' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( isset( $_POST['order_status'] ) ) update_post_meta( $post_id, 'order_status', sanitize_text_field( $_POST['order_status'] ) );
}

add_shortcode( 'anime_account', 'anime_shop_account_shortcode' );
function anime_shop_account_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="anime-auth-page"><div class="anime-auth-card"><h2>Private Area</h2><p>Please <a href="' . esc_url( home_url('/login') ) . '">log in</a> to view your account.</p></div></div>';
    }
    
    $user = wp_get_current_user();
    $phone = get_user_meta($user->ID, 'shipping_phone', true);
    $address = get_user_meta($user->ID, 'shipping_address', true);
    $card = get_user_meta($user->ID, 'billing_card', true);
    $theme = get_user_meta($user->ID, 'theme_preference', true) ?: 'light';
    
    ob_start();
    ?>
    <div class="anime-clean-dashboard">
        <aside class="dashboard-nav-sidebar">
            <div class="dashboard-user">
                <h2><?php echo esc_html( $user->display_name ); ?></h2>
                <span>Collector</span>
            </div>
            <nav class="dashboard-tabs">
                <a href="#profile" class="active">Account Info</a>
                <a href="#address">Address</a>
                <a href="#payment">Payment Settings</a>
                <a href="#theme">Theme</a>
                <a href="#history">Order History</a>
            </nav>
            <div class="dashboard-logout">
                <a href="<?php echo wp_logout_url( home_url() ); ?>">Sign Out</a>
            </div>
        </aside>

        <main class="dashboard-content-area">
            <form id="anime-settings-form" class="dashboard-form">
                
                <section id="profile-tab" class="dashboard-tab-content active-tab">
                    <h3>Account Information</h3>
                    <div class="input-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Email Address</label>
                        <input type="email" name="user_email" value="<?php echo esc_attr($user->user_email); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" value="<?php echo esc_attr($phone); ?>">
                    </div>
                    <div class="input-group">
                        <label>Change Password (Leave blank to keep current)</label>
                        <input type="password" name="new_password">
                    </div>
                    <button type="submit" class="dashboard-save-btn">Save Account Info</button>
                    <div class="dashboard-msg"></div>
                </section>

                <section id="address-tab" class="dashboard-tab-content">
                    <h3>Shipping Address</h3>
                    <div class="input-group">
                        <label>Full Address Details</label>
                        <textarea name="shipping_address" rows="5"><?php echo esc_textarea($address); ?></textarea>
                    </div>
                    <button type="submit" class="dashboard-save-btn">Save Address</button>
                    <div class="dashboard-msg"></div>
                </section>

                <section id="payment-tab" class="dashboard-tab-content">
                    <h3>Payment Settings</h3>
                    <div class="input-group">
                        <label>Linked Credit Card (Masked)</label>
                        <input type="text" name="billing_card" value="<?php echo esc_attr($card); ?>" placeholder="e.g. 4242">
                    </div>
                    <button type="submit" class="dashboard-save-btn">Save Payment Settings</button>
                    <div class="dashboard-msg"></div>
                </section>

                <section id="theme-tab" class="dashboard-tab-content">
                    <h3>Optics / Theme</h3>
                    <label class="dashboard-toggle-label">
                        <input type="checkbox" name="theme_dark" value="1" <?php checked($theme, 'dark'); ?>>
                        <span class="dashboard-toggle-text">Enable Dark Mode</span>
                    </label>
                    <button type="submit" class="dashboard-save-btn">Save Theme</button>
                    <div class="dashboard-msg"></div>
                </section>

                <input type="hidden" name="action" value="update_settings">
                <?php wp_nonce_field('wp_rest', '_wpnonce'); ?>
            </form>

            <section id="history-tab" class="dashboard-tab-content">
                <h3>Order History</h3>
                <?php
                $orders = get_posts(array(
                    'post_type' => 'anime_order',
                    'meta_query' => array(
                        'relation' => 'OR',
                        array('key' => 'customer_user_id', 'value' => $user->ID),
                        array('key' => 'customer_email', 'value' => $user->user_email)
                    ),
                    'posts_per_page' => 20
                ));
                if ( $orders ) : ?>
                    <div class="clean-order-list">
                        <?php foreach ( $orders as $order ) : 
                            $total = get_post_meta( $order->ID, 'order_total', true );
                            $status = get_post_meta( $order->ID, 'order_status', true );
                        ?>
                            <a href="<?php echo home_url('/order-view?order_id=' . $order->ID); ?>" class="clean-order-item">
                                <span class="co-id">#<?php echo $order->ID; ?></span>
                                <span class="co-date"><?php echo get_the_date('', $order); ?></span>
                                <span class="co-total"><?php echo number_format($total, 0, ',', '.') . ' ₫'; ?></span>
                                <span class="co-status status-<?php echo esc_attr($status); ?>"><?php echo ucfirst($status); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="clean-order-empty">No order history available.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <?php
    return ob_get_clean();
}




// --- Enhanced products shortcode: search, category filter, price range, pagination ---


// --- Checkout: include payment method and handle mock payments ---
add_shortcode( 'anime_checkout', 'anime_shop_checkout_shortcode' );
function anime_shop_checkout_shortcode() {
    $cart = anime_shop_get_cart();
    if ( empty($cart) ) {
        return '<div class="checkout-empty"><p>Your collection is currently empty.</p><a href="'.home_url('/anime-shop').'" class="anime-btn anime-btn-outline">Browse Artifacts</a></div>';
    }

    $total = 0;
    foreach ($cart as $key => $qty) {
        $pid = $key;
        if (preg_match('/^(\d+):v(\d+)$/', $key, $m)) $pid = intval($m[1]);
        $price = floatval(get_post_meta($pid, '_price', true));
        $sale = floatval(get_post_meta($pid, '_sale_price', true));
        $final = $sale ?: $price;
        $total += ($final * $qty);
    }

    ob_start();
    ?>
    <div class="checkout-visual-wrapper">
        <form id="anime-checkout-form" class="checkout-container">
            <div class="checkout-main">
                <section class="checkout-section">
                    <h2 class="section-title">Shipping & Logistics</h2>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Full Name</label>
                            <input type="text" name="name" required placeholder="Authenticated Name" />
                        </div>
                        <div class="form-group full">
                            <label>Email Address</label>
                            <input type="email" name="email" required placeholder="collector@nexus.com" />
                        </div>
                        <div class="form-group full">
                            <label>Vault Destination (Shipping Address)</label>
                            <textarea name="address" required placeholder="Full address for secure transit"></textarea>
                        </div>
                    </div>
                </section>

                <section class="checkout-section">
                    <h2 class="section-title">Acquisition Protocol</h2>
                    <div class="payment-methods">
                        <label class="payment-method-card active">
                            <input type="radio" name="payment_method" value="cod" checked>
                            <span class="method-meta">
                                <strong>Cash on Collection</strong>
                                <span>Verify artifact upon arrival</span>
                            </span>
                        </label>
                        <label class="payment-method-card">
                            <input type="radio" name="payment_method" value="card">
                            <span class="method-meta">
                                <strong>Secure Card</strong>
                                <span>Instant confirmation</span>
                            </span>
                        </label>
                        <label class="payment-method-card">
                            <input type="radio" name="payment_method" value="bank_transfer">
                            <span class="method-meta">
                                <strong>Bank Transfer</strong>
                                <span>VietQR Speed Transfer</span>
                            </span>
                        </label>
                    </div>

                    <div id="anime-card-fields" style="display:none; margin-top:20px;">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label>Card Details</label>
                                <input type="text" name="card_number" placeholder="4111 1111 1111 1111" />
                            </div>
                            <div class="form-group half">
                                <label>Expiry</label>
                                <input type="text" name="card_expiry" placeholder="MM/YY" />
                            </div>
                            <div class="form-group half">
                                <label>CVC</label>
                                <input type="text" name="card_cvc" placeholder="123" />
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="checkout-side">
                <div class="order-summary-box sticky-box">
                    <h3 class="summary-title">Acquisition Summary</h3>
                    <div class="summary-items">
                        <?php foreach($cart as $key => $qty): 
                            $pid = $key; 
                            $variation_index = null;
                            if (preg_match('/^(\d+):v(\d+)$/', $key, $m)) {
                                $pid = intval($m[1]);
                                $variation_index = intval($m[2]);
                            }
                            
                            $price = floatval(get_post_meta($pid, '_price', true));
                            $sale = floatval(get_post_meta($pid, '_sale_price', true));
                            $final = $sale ?: $price;
                            $var_label = '';
                            
                            if ($variation_index !== null) {
                                $vars = get_post_meta($pid, '_product_variations', true);
                                if (is_array($vars) && isset($vars[$variation_index])) {
                                    $v = $vars[$variation_index];
                                    $final = isset($v['sale_price']) && $v['sale_price'] !== '' ? $v['sale_price'] : ((isset($v['price']) && $v['price'] !== '') ? $v['price'] : $final);
                                    if (isset($v['attrs']) && is_array($v['attrs'])) {
                                        $var_label = implode(' / ', $v['attrs']);
                                    }
                                }
                            }
                            $subtotal = $final * $qty;
                            
                            $img_url = get_the_post_thumbnail_url($pid, 'thumbnail');
                            if ( ! $img_url ) {
                                $ids = get_post_meta( $pid, '_product_image_ids', true );
                                if ( is_array( $ids ) && ! empty( $ids ) ) {
                                    $img_url = wp_get_attachment_image_url( $ids[0], 'thumbnail' );
                                }
                            }
                            $img_url = $img_url ?: '';
                        ?>
                            <div class="summary-item-line" style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                <div style="width:55px; height:55px; background:#f6f7f7; border-radius:6px; overflow:hidden; flex-shrink:0; border:1px solid #eee;">
                                    <?php if ($img_url) : ?>
                                        <img src="<?php echo esc_url($img_url); ?>" style="width:100%; height:100%; object-fit:contain; display:block;" alt="">
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1; line-height:1.3;">
                                    <h4 style="margin:0; font-size:14px; font-weight:600; color:#000;"><?php echo get_the_title($pid); ?></h4>
                                    <?php if ($var_label): ?>
                                        <div style="font-size:12px; color:#666; margin-top:2px;"><?php echo esc_html($var_label); ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:12px; color:#888; margin-top:2px;">Qty: <?php echo $qty; ?></div>
                                </div>
                                <div style="font-weight:600; font-size:14px;">
                                    <?php echo number_format($subtotal, 0, ',', '.') . ' &#8363;'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="summary-details">
                        <div class="summary-line">
                            <span>Subtotal</span>
                            <span><?php echo number_format($total, 0, ',', '.') . ' &#8363;'; ?></span>
                        </div>
                        <div class="summary-line total-line">
                            <span>Total</span>
                            <span class="total-val"><?php echo number_format($total, 0, ',', '.') . ' &#8363;'; ?></span>
                        </div>
                    </div>
                    <button type="submit" class="anime-btn anime-btn-primary checkout-submit-btn">Finalize Acquisition</button>
                    <p class="summary-disclaimer">By clicking, you agree to our curation protocols.</p>
                </div>
            </aside>
        </form>
    </div>
    <?php
    return ob_get_clean();
}



// modify checkout REST handler to process payment method and set order status
add_action( 'rest_api_init', function() {
    register_rest_route( 'anime-shop/v1', '/checkout', array(
        'methods' => 'POST',
        'callback' => 'anime_shop_rest_checkout',
        'permission_callback' => '__return_true',
    ) );
    
    register_rest_route( 'anime-shop/v1', '/update-settings', array(
        'methods' => 'POST',
        'callback' => 'anime_shop_rest_update_settings',
        'permission_callback' => function() { return is_user_logged_in(); }
    ) );
} );

function anime_shop_rest_update_settings( $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid security token.' ), 403 );
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) return new WP_REST_Response( array( 'success' => false, 'message' => 'Not authenticated.' ), 401 );

    $params = $request->get_json_params();

    $user_data = array( 'ID' => $user_id );
    if ( isset( $params['display_name'] ) && ! empty( $params['display_name'] ) ) {
        $user_data['display_name'] = sanitize_text_field( $params['display_name'] );
    }
    if ( isset( $params['user_email'] ) && is_email( $params['user_email'] ) ) {
        $user_data['user_email'] = sanitize_email( $params['user_email'] );
    }
    if ( isset( $params['new_password'] ) && ! empty( $params['new_password'] ) ) {
        $user_data['user_pass'] = $params['new_password'];
    }

    $result = wp_update_user( $user_data );
    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
    }

    if ( isset( $params['phone_number'] ) ) {
        update_user_meta( $user_id, 'shipping_phone', sanitize_text_field( $params['phone_number'] ) );
    }
    if ( isset( $params['shipping_address'] ) ) {
        update_user_meta( $user_id, 'shipping_address', sanitize_textarea_field( $params['shipping_address'] ) );
    }
    if ( isset( $params['billing_card'] ) ) {
        update_user_meta( $user_id, 'billing_card', sanitize_text_field( $params['billing_card'] ) );
    }
    
    $theme = ! empty( $params['theme_dark'] ) ? 'dark' : 'light';
    update_user_meta( $user_id, 'theme_preference', $theme );

    return new WP_REST_Response( array( 'success' => true, 'message' => 'Settings securely synchronized.' ), 200 );
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'anime-shop/v1', '/confirm-payment', array(
        'methods' => 'POST',
        'callback' => 'anime_shop_rest_confirm_payment',
        'permission_callback' => '__return_true',
    ) );
} );

function anime_shop_rest_confirm_payment( $request ) {
    $params = $request->get_json_params();
    $order_id = isset($params['order_id']) ? intval($params['order_id']) : 0;
    if (!$order_id) return new WP_REST_Response(array('success'=>false), 400);
    
    update_post_meta($order_id, 'order_status', 'processing');
    update_post_meta($order_id, 'payment_confirmed_by_user', '1');
    
    return new WP_REST_Response(array('success'=>true), 200);
}

function anime_shop_rest_checkout( $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid nonce' ), 403 );
    }
    $params = $request->get_json_params();
    $user_id = get_current_user_id();
    $name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
    $email = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
    $address = isset( $params['address'] ) ? sanitize_textarea_field( $params['address'] ) : '';
    $payment_method = isset( $params['payment_method'] ) ? sanitize_text_field( $params['payment_method'] ) : 'cod';
    if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid customer info' ), 400 );
    }
    $cart = anime_shop_get_cart();
    if ( empty( $cart ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Cart is empty' ), 400 );
    }

    $total = 0;
    $items = array();
    foreach ( $cart as $pid => $qty ) {
        $pid = intval( $pid );
        $p = get_post( $pid );
        if ( ! $p ) continue;
        $price = floatval( get_post_meta( $pid, '_price', true ) );
        $sale = floatval( get_post_meta( $pid, '_sale_price', true ) );
        $final = $sale ? $sale : $price;
        $subtotal = $final * $qty;
        $total += $subtotal;
        $items[] = array( 'id' => $pid, 'title' => $p->post_title, 'quantity' => $qty, 'price' => $final, 'subtotal' => $subtotal );
    }

    // create order post
    $order_post = array(
        'post_title' => 'Order - ' . date( 'Y-m-d H:i:s' ),
        'post_type' => 'anime_order',
        'post_status' => 'publish',
    );
    $order_id = wp_insert_post( $order_post );
    if ( is_wp_error( $order_id ) || ! $order_id ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Unable to create order' ), 500 );
    }
    update_post_meta( $order_id, 'customer_name', $name );
    update_post_meta( $order_id, 'customer_email', $email );
    update_post_meta( $order_id, 'customer_user_id', $user_id );
    update_post_meta( $order_id, 'customer_address', $address );
    update_post_meta( $order_id, 'order_items', $items );
    update_post_meta( $order_id, 'order_total', $total );
    update_post_meta( $order_id, 'payment_method', $payment_method );

    // process payment (mock for card)
    $payment_status = 'unpaid';
    if ( $payment_method === 'card' ) {
        // simulate processing: accept any card for demo
        $payment_status = 'paid';
        update_post_meta( $order_id, 'order_status', 'processing' );
    } else {
        update_post_meta( $order_id, 'order_status', 'pending' );
    }
    update_post_meta( $order_id, 'payment_status', $payment_status );

    // email admin
    $to = get_option( 'admin_email' );
    $subject = 'New Anime Shop Order #' . $order_id;
    $message = "New order placed:\n\nOrder ID: $order_id\nName: $name\nEmail: $email\nAddress: $address\n\nItems:\n";
    foreach ( $items as $it ) {
        $message .= sprintf( "%s x%d - %s\n", $it['title'], $it['quantity'], number_format( $it['subtotal'], 0, ',', '.' ) . ' VND' );
    }
    $message .= "\nTotal: " . number_format( $total, 0, ',', '.' ) . " VND\nPayment: " . strtoupper( $payment_status ) . "\n\nView order: " . admin_url( 'post.php?post=' . $order_id . '&action=edit' );
    wp_mail( $to, $subject, $message );

    // clear cart
    anime_shop_set_cart( array() );

    // Push to Firebase for Global Realtime History
    if ( ! is_wp_error( $order_id ) ) {
        anime_shop_sync_order_to_firebase( $order_id );
    }

    return new WP_REST_Response( array( 
        'success' => true, 
        'order_id' => $order_id, 
        'redirect' => home_url('/order-confirmed?order_id=' . $order_id) 
    ), 200 );
}

function anime_shop_sync_order_to_firebase($order_id) {
    $firebase_url = 'https://animeshop-d06d1-default-rtdb.asia-southeast1.firebasedatabase.app/orders.json';
    $order_data = array(
        'id'        => $order_id,
        'customer'  => get_post_meta($order_id, 'customer_name', true),
        'total'     => floatval(get_post_meta($order_id, 'order_total', true)),
        'time'      => current_time('H:i'),
        'date'      => current_time('d/m'),
    );
    wp_remote_post($firebase_url, array(
        'method'    => 'POST',
        'body'      => json_encode($order_data),
        'headers'   => array('Content-Type' => 'application/json'),
    ));
}

// --- Admin: Add Product (custom page like WooCommerce) ---
add_action( 'admin_menu', function() {

} );

function anime_shop_add_product_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Handle form submission
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['anime_shop_add_product_nonce'] ) ) {
        check_admin_referer( 'anime_shop_add_product', 'anime_shop_add_product_nonce' );
        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $content = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $excerpt = isset( $_POST['short_description'] ) ? sanitize_text_field( wp_unslash( $_POST['short_description'] ) ) : '';
        $price = isset( $_POST['_price'] ) ? sanitize_text_field( wp_unslash( $_POST['_price'] ) ) : '';
        $sale = isset( $_POST['_sale_price'] ) ? sanitize_text_field( wp_unslash( $_POST['_sale_price'] ) ) : '';
        $sku = isset( $_POST['_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['_sku'] ) ) : '';
        $stock = isset( $_POST['stock'] ) ? intval( $_POST['stock'] ) : 0;
        $in_stock = isset( $_POST['_in_stock'] ) ? sanitize_text_field( wp_unslash( $_POST['_in_stock'] ) ) : '1';
        // accept categories as either comma-separated string (legacy) or as array of term IDs
        $cats_input = null;
        if ( isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ) {
            $cats_input = array_map( 'intval', $_POST['categories'] );
        } elseif ( isset( $_POST['categories'] ) ) {
            $cats_input = sanitize_text_field( wp_unslash( $_POST['categories'] ) );
        }
        $image_ids = isset( $_POST['_product_image_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['_product_image_ids'] ) ) : '';

        // attributes (optional)
        $attr_names = isset( $_POST['anime_attribute_name'] ) && is_array( $_POST['anime_attribute_name'] ) ? $_POST['anime_attribute_name'] : array();
        $attr_values = isset( $_POST['anime_attribute_value'] ) && is_array( $_POST['anime_attribute_value'] ) ? $_POST['anime_attribute_value'] : array();
        $attr_vis = isset( $_POST['anime_attribute_visible'] ) && is_array( $_POST['anime_attribute_visible'] ) ? $_POST['anime_attribute_visible'] : array();
        $attr_var = isset( $_POST['anime_attribute_variation'] ) && is_array( $_POST['anime_attribute_variation'] ) ? $_POST['anime_attribute_variation'] : array();

        if ( empty( $title ) ) {
            echo '<div class="notice notice-error"><p>Title is required.</p></div>';
        } else {
            $post_arr = array(
                'post_title' => $title,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_type' => 'anime_product',
                'post_status' => 'publish',
            );
            $post_id = wp_insert_post( $post_arr );
            if ( is_wp_error( $post_id ) ) {
                echo '<div class="notice notice-error"><p>Failed to create product.</p></div>';
            } else {
                update_post_meta( $post_id, '_price', $price );
                update_post_meta( $post_id, '_sale_price', $sale );
                update_post_meta( $post_id, '_sku', $sku );
                update_post_meta( $post_id, 'stock', $stock );
                update_post_meta( $post_id, '_in_stock', $in_stock );

                // categories: accept array of term IDs (multi-select) or legacy comma-separated 'Name > Sub' strings
                if ( is_array( $cats_input ) ) {
                    $term_ids = array_filter( array_map( 'intval', $cats_input ) );
                    if ( ! empty( $term_ids ) ) wp_set_post_terms( $post_id, $term_ids, 'category', false );
                } elseif ( $cats_input ) {
                    $cats = array_map( 'trim', explode( ',', $cats_input ) );
                    $term_ids = array();
                    foreach ( $cats as $c ) {
                        if ( ! $c ) continue;
                        $term_id = anime_shop_get_or_create_term_hierarchy( $c );
                        if ( $term_id ) $term_ids[] = intval( $term_id );
                    }
                    if ( ! empty( $term_ids ) ) wp_set_post_terms( $post_id, $term_ids, 'category', false );
                }

                // attributes: save to _product_attributes similar to edit screen
                $attrs = array();
                foreach ( $attr_names as $i => $n ) {
                    $n = sanitize_text_field( wp_unslash( $n ) );
                    if ( $n === '' ) continue;
                    $v = isset( $attr_values[ $i ] ) ? sanitize_text_field( wp_unslash( $attr_values[ $i ] ) ) : '';
                    $vals = array_map( 'trim', explode( ',', $v ) );
                    $vals = array_filter( $vals, function( $x ){ return $x !== ''; } );
                    $vis = in_array( '1', array( isset( $attr_vis[ $i ] ) ? $attr_vis[ $i ] : '' ) ) || ( isset( $attr_vis[ $i ] ) && $attr_vis[ $i ] );
                    $var = in_array( '1', array( isset( $attr_var[ $i ] ) ? $attr_var[ $i ] : '' ) ) || ( isset( $attr_var[ $i ] ) && $attr_var[ $i ] );
                    $attrs[ sanitize_title( $n ) ] = array(
                        'name' => $n,
                        'value' => $vals,
                        'visible' => $vis ? 1 : 0,
                        'variation' => $var ? 1 : 0,
                    );
                }
                if ( ! empty( $attrs ) ) update_post_meta( $post_id, '_product_attributes', $attrs );
                
                // Variations (JSON per variation submitted in anime_variations[])
                if ( isset( $_POST['anime_variations'] ) && is_array( $_POST['anime_variations'] ) ) {
                    $vars = array();
                    foreach ( $_POST['anime_variations'] as $vraw ) {
                        $vraw = wp_unslash( $vraw );
                        $v = json_decode( $vraw, true );
                        if ( ! $v || ! is_array( $v ) ) continue;
                        $san_attrs = array();
                        if ( isset( $v['attrs'] ) && is_array( $v['attrs'] ) ) {
                            foreach ( $v['attrs'] as $k => $val ) {
                                $san_attrs[ sanitize_title( $k ) ] = sanitize_text_field( $val );
                            }
                        }
                        $vars[] = array(
                            'attrs' => $san_attrs,
                            'price' => isset( $v['price'] ) ? sanitize_text_field( $v['price'] ) : '',
                            'sale_price' => isset( $v['sale_price'] ) ? sanitize_text_field( $v['sale_price'] ) : '',
                            'sku' => isset( $v['sku'] ) ? sanitize_text_field( $v['sku'] ) : '',
                            'stock' => isset( $v['stock'] ) ? intval( $v['stock'] ) : 0,
                            'image_id' => isset( $v['image_id'] ) ? intval( $v['image_id'] ) : 0,
                        );
                    }
                    update_post_meta( $post_id, '_product_variations', $vars );
                }

                // images (comma-separated IDs)
                $img_ids = array_filter( array_map( 'intval', explode( ',', $image_ids ) ) );
                if ( ! empty( $img_ids ) ) {
                    update_post_meta( $post_id, '_product_image_ids', $img_ids );
                    // set featured image
                    set_post_thumbnail( $post_id, $img_ids[0] );
                }

                // Redirect to edit screen for further edits
                wp_safe_redirect( admin_url( 'post.php?post=' . intval( $post_id ) . '&action=edit' ) );
                exit;
            }
        }
    }

    echo '<div class="wrap" style="max-width:900px; margin:0 auto; padding:20px;">';
    echo '<h1 style="margin-bottom:30px; font-weight:700; color:#1d2327; text-align:center; font-size:2em;">Add New Product</h1>';
    echo '<style>
        .anime-box { background: #fff; padding: 30px; border-radius: 15px; border: 1px solid #e1e1e1; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .anime-box h2 { margin: 0 0 25px 0; font-size: 1.3em; border-bottom: 4px solid #2271b1; padding-bottom: 10px; color: #1d2327; text-transform: uppercase; letter-spacing: 1px; display: inline-block; }
        .anime-field { margin-bottom: 25px; }
        .anime-field label { display: block; font-weight: 600; margin-bottom: 10px; color: #1d2327; font-size: 1em; }
        .anime-field input[type="text"], .anime-field input[type="number"], .anime-field select, .anime-field textarea { width: 100%; border: 1px solid #c3c4c7; padding: 14px; border-radius: 10px; background: #fff; transition: border-color 0.2s, box-shadow 0.2s; font-size: 15px; }
        .anime-field input:focus, .anime-field select:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
        .anime-cat-checklist { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; max-height: 250px; overflow-y: auto; border: 1px solid #c3c4c7; padding: 20px; border-radius: 10px; background: #fdfdfd; }
        .anime-cat-checklist label { font-weight: 500; margin-bottom: 0; display: flex; align-items: center; gap: 8px; color: #50575e; }
        #anime-shop-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .anime-shop-gallery-item { border: 1px solid #eee; padding: 5px; border-radius: 8px; position: relative; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .anime-shop-gallery-item .remove-image { position: absolute; top: -8px; right: -8px; background: #d63638; color: white; width: 22px; height: 22px; border-radius: 50%; text-align: center; line-height: 20px; font-size: 16px; text-decoration: none; font-weight: bold; border: 2px solid #fff; }
    </style>';
    echo '<form method="post">';
    wp_nonce_field( 'anime_shop_add_product', 'anime_shop_add_product_nonce' );
    
    // Main Content
    echo '<div class="anime-box">';
        echo '<div class="anime-field"><label for="title">Product Title</label><input type="text" name="title" id="title" required placeholder="e.g. Naruto Figure" /></div>';
        echo '<div class="anime-field"><label for="short_description">Short Description</label><input type="text" name="short_description" id="short_description" placeholder="Brief summary..." /></div>';
        echo '<div class="anime-field"><label for="description">Full Description</label>'; wp_editor( '', 'description', array( 'textarea_name' => 'description' ) ); echo '</div>';
    echo '</div>';

    // Categories (Moved from sidebar)
    echo '<div class="anime-box"><h2>Product Categories</h2>';
    echo '<div class="anime-cat-checklist">';
    $terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        foreach ( $terms as $t ) {
            echo '<label><input type="checkbox" name="categories[]" value="' . intval( $t->term_id ) . '" /> ' . esc_html( $t->name ) . '</label>';
        }
    }
    echo '</div></div>';

    // Images (Moved from sidebar)
    echo '<div class="anime-box"><h2>Product Images & Artwork</h2>';
    echo '<p class="description" style="margin-bottom:15px;">The first image will be used as the main feature image.</p>';
    echo '<div id="anime-shop-gallery"></div><p style="text-align:center;"><a href="#" class="button button-primary button-large" id="anime-shop-add-images">Manage Images</a></p><input type="hidden" name="_product_image_ids" id="_product_image_ids" value="" />';
    echo '</div>';
    
    // Stats
    echo '<div class="anime-box"><h2>Product Statistics</h2>';
    echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">';
    echo '<div class="anime-field"><label>Regular Price ($)</label><input type="text" name="_price" id="_price" placeholder="0.00" /></div>';
    echo '<div class="anime-field"><label>Sale Price ($)</label><input type="text" name="_sale_price" id="_sale_price" placeholder="0.00" /></div>';
    echo '<div class="anime-field"><label>SKU (Stock Keeping Unit)</label><input type="text" name="_sku" id="_sku" placeholder="ANIME-XXX" /></div>';
    echo '<div class="anime-field"><label>Available Stock</label><input type="number" name="stock" id="stock" value="0" min="0" /></div>';
    echo '<div class="anime-field"><label>In Stock Status</label><select name="_in_stock" id="_in_stock"><option value="1">Yes, Available</option><option value="0">Currently Out of Stock</option></select></div>';
    echo '</div></div>';
    
    // Attributes
    echo '<div class="anime-box"><h2>Attributes & Specifications</h2>';
    echo '<div id="anime-attributes"></div><p><a href="#" class="button" id="anime-add-attribute">Add New Attribute</a></p>';
    echo '</div>';
    
    // Variations
    echo '<div class="anime-box"><h2>Product Variations</h2>';
    echo '<div id="anime-shop-data" data-attrs="[]" data-variations="[]"></div><div id="anime-variations-list"></div><p><a href="#" class="button" id="anime-add-variation">Create Variation</a></p>';
    echo '</div>';

    echo '<div style="margin: 40px 0; text-align:center;">' . get_submit_button( 'Add Product to Shop', 'primary large', 'submit', false, array('style'=>'width:100%; max-width:400px; padding:15px; font-size:1.2em;') ) . '</div>';
    
    echo '</form></div>';
}



// AJAX: check duplicate by SKU or title
add_action( 'wp_ajax_anime_shop_check_duplicate', 'anime_shop_check_duplicate' );
function anime_shop_check_duplicate() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'no_permission' );
    }
    check_ajax_referer( 'anime_shop_admin', 'nonce' );
    $sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    if ( $sku ) {
        $q = new WP_Query( array( 'post_type' => 'anime_product', 'meta_query' => array( array( 'key' => '_sku', 'value' => $sku ) ), 'posts_per_page' => 1 ) );
        if ( $q->have_posts() ) {
            $p = $q->posts[0];
            wp_send_json_success( array( 'exists' => true, 'post_id' => $p->ID, 'title' => $p->post_title ) );
        }
    }
    if ( $title ) {
        $q = new WP_Query( array( 'post_type' => 'anime_product', 'title' => $title, 'posts_per_page' => 1 ) );
        if ( $q->have_posts() ) {
            $p = $q->posts[0];
            wp_send_json_success( array( 'exists' => true, 'post_id' => $p->ID, 'title' => $p->post_title ) );
        }
    }
    wp_send_json_success( array( 'exists' => false ) );
}


// Export handler: downloads CSV of products
add_action( 'admin_post_anime_shop_export', 'anime_shop_handle_export' );
function anime_shop_handle_export() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Forbidden' );
    }
    if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'anime_shop_export' ) ) {
        wp_die( 'Invalid nonce' );
    }

    $posts = get_posts( array( 'post_type' => 'anime_product', 'posts_per_page' => -1, 'post_status' => array( 'publish', 'draft' ) ) );
    $filename = 'anime-products-export-' . date( 'Y-m-d' ) . '.csv';

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );
    $out = fopen( 'php://output', 'w' );
    // Added new columns: Weight, Length, Width, Height, Attributes
    fputcsv( $out, array( 'Name', 'Description', 'Short description', 'Regular price', 'Sale price', 'SKU', 'Stock', 'In stock?', 'Published', 'Categories', 'Images', 'Weight', 'Length', 'Width', 'Height', 'Attributes' ) );

    foreach ( $posts as $p ) {
        $id = $p->ID;
        $name = $p->post_title;
        $desc = wp_strip_all_tags( $p->post_content );
        $short = get_post_field( 'post_excerpt', $id );
        $price = get_post_meta( $id, '_price', true );
        $sale = get_post_meta( $id, '_sale_price', true );
        $sku = get_post_meta( $id, '_sku', true );
        $stock = get_post_meta( $id, 'stock', true );
        $in_stock = get_post_meta( $id, '_in_stock', true );
        $pub = $p->post_status === 'publish' ? '1' : '0';
        
        // Dimensions & Weight
        $weight = get_post_meta( $id, 'weight', true );
        $length = get_post_meta( $id, 'length', true );
        $width = get_post_meta( $id, 'width', true );
        $height = get_post_meta( $id, 'height', true );
        
        // Attributes (JSON)
        $attrs = get_post_meta( $id, '_product_attributes', true );
        $attrs_json = ! empty( $attrs ) ? json_encode( $attrs, JSON_UNESCAPED_UNICODE ) : '';

        $terms = get_the_terms( $id, 'category' );
        $cats_formatted = array();
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                // Get the full hierarchy path for the term
                $ancestors = get_ancestors( $term->term_id, 'category' );
                $ancestors = array_reverse( $ancestors );
                $path = array();
                foreach ( $ancestors as $anc_id ) {
                    $anc = get_term( $anc_id, 'category' );
                    if ( $anc && ! is_wp_error( $anc ) ) {
                        $path[] = $anc->name;
                    }
                }
                $path[] = $term->name;
                $cats_formatted[] = implode( ' > ', $path );
            }
            $cats = implode( ', ', $cats_formatted );
        }

        // Image Logic: Include featured image + gallery images
        $img_ids_raw = get_post_meta( $id, '_product_image_ids', true );
        $img_ids = array();
        
        // Handle Featured Image
        $thumb_id = get_post_thumbnail_id( $id );
        if ( $thumb_id ) {
            $img_ids[] = intval( $thumb_id );
        }
        
        // Handle Gallery IDs
        if ( is_array( $img_ids_raw ) ) {
            $img_ids = array_merge( $img_ids, array_map( 'intval', $img_ids_raw ) );
        } elseif ( ! empty( $img_ids_raw ) ) {
            $gallery_array = array_filter( array_map( 'intval', explode( ',', $img_ids_raw ) ) );
            $img_ids = array_merge( $img_ids, $gallery_array );
        }
        
        // Final unique IDs
        $img_ids = array_unique( $img_ids );
        
        $imgs = array();
        foreach ( $img_ids as $aid ) {
            if ( ! $aid ) continue;
            $url = wp_get_attachment_url( $aid );
            if ( $url ) $imgs[] = $url;
        }
        
        fputcsv( $out, array( 
            $name, $desc, $short, $price, $sale, $sku, $stock, $in_stock, $pub, $cats, 
            implode( ',', $imgs ),
            $weight, $length, $width, $height, $attrs_json
        ) );
    }

    fclose( $out );
    exit;
}


// Helper: Generate Sample Boutique Artifacts
function anime_shop_generate_dummy_data() {
    if ( get_option( 'anime_shop_dummy_created' ) ) {
        return;
    }

    $samples = array(
        array( 'title' => 'Naruto Uzumaki: Sennin Mode', 'price' => '250', 'cat' => 'figures' ),
        array( 'title' => 'Zoro: Roronoa Masterpiece', 'price' => '550', 'cat' => 'statues' ),
        array( 'title' => 'Gundam: RX-78-2 Ver.Ka', 'price' => '85', 'cat' => 'model-kits' ),
        array( 'title' => 'Dragon Ball: Goku GT Spec', 'price' => '120', 'cat' => 'figures' ),
    );

    foreach ( $samples as $s ) {
        $id = wp_insert_post( array( 'post_type' => 'anime_product', 'post_title' => $s['title'], 'post_status' => 'publish' ) );
        update_post_meta( $id, '_price', $s['price'] );
        update_post_meta( $id, '_in_stock', '1' );
        wp_set_object_terms( $id, $s['cat'], 'category' );
    }
    
    update_option( 'anime_shop_dummy_created', true );
}
// Trigger dummy data creation once
add_action('init', 'anime_shop_generate_dummy_data');

// --- Dynamic Premium Product Card Helper ---
function anime_shop_render_product_card( $id ) {
    $price = get_post_meta( $id, '_price', true );
    $title = get_the_title( $id );
    $permalink = get_permalink( $id );
    
    ob_start(); ?>
    <a href="<?php echo esc_url( $permalink ); ?>" class="dynamic-artifact-card">
        <div class="dyn-card-img">
            <?php
            $thumb_url = get_the_post_thumbnail_url( $id, 'medium_large' );
            if ( ! $thumb_url ) {
                $ids = get_post_meta( $id, '_product_image_ids', true );
                if ( is_array( $ids ) && ! empty( $ids ) ) {
                    $thumb_url = wp_get_attachment_image_url( $ids[0], 'medium_large' );
                }
            }
            if ( $thumb_url ) : ?>
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="width:100%;height:100%;object-fit:contain;background:var(--bg-secondary);" />
            <?php else : ?>
                <div class="dyn-placeholder">No Image</div>
            <?php endif; ?>
            <div class="dyn-hover-action">
                <span>View Artifact</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </div>
        </div>
        <div class="dyn-card-info">
            <h3 class="dyn-card-title"><?php echo esc_html( $title ); ?></h3>
            <?php 
            $formatted_card_price = number_format(floatval($price), 0, ',', '.') . ' &#8363;'; 
            ?>
            <p class="dyn-card-price"><?php echo $formatted_card_price; ?></p>
        </div>
    </a>
    <?php
    return ob_get_clean();
}

// --- AJAX Discovery: Helper for rendering the products grid ---
function anime_shop_render_product_grid_inner( $query ) {
    ob_start();
    if ( $query->have_posts() ) : ?>
        <?php while ( $query->have_posts() ) : $query->the_post(); 
            echo anime_shop_render_product_card( get_the_ID() );
        endwhile; wp_reset_postdata(); ?>
    <?php else : ?>
        <div class="no-results-boutique">
            <h3 style="font-weight:900; letter-spacing:1px; text-transform:uppercase; font-size:14px;">No Artifacts Found</h3>
            <p style="font-size:12px;">Refine your search or clear filters to resume discovery.</p>
        </div>
    <?php endif;
    return ob_get_clean();
}

// REST API for real-time discovery
add_action( 'rest_api_init', function() {
    register_rest_route( 'anime-shop/v1', '/discovery', array(
        'methods' => 'GET',
        'callback' => 'anime_shop_rest_discovery',
        'permission_callback' => '__return_true',
    ) );
} );

function anime_shop_rest_discovery( $request ) {
    $cats = $request->get_param('cats'); 
    $min = floatval( $request->get_param('min') );
    $max = floatval( $request->get_param('max') );
    $sort = sanitize_text_field( $request->get_param('sort') );
    $q = sanitize_text_field( $request->get_param('q') );
    $attrs_filter = $request->get_param('attrs'); 
    $paged = max( 1, intval( $request->get_param('paged') ) );

    $args = array( 'post_type' => 'anime_product', 'post_status' => 'publish', 'posts_per_page' => 24, 'paged' => $paged );

    if ( ! empty( $q ) ) {
        $args['s'] = $q;
    }

    if ( ! empty( $cats ) && is_array( $cats ) ) {
        $args['tax_query'][] = array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $cats, 'operator' => 'IN' );
    }

    $meta_query = array();
    if ( $min > 0 || $max > 0 ) {
        $meta_query[] = array( 'key' => '_price', 'type' => 'NUMERIC', 'compare' => 'BETWEEN', 'value' => array( $min ?: 0, $max ?: 9999999 ) );
    }

    if ( ! empty( $attrs_filter ) && is_array( $attrs_filter ) ) {
        $args['meta_query']['relation'] = 'AND';
        foreach ( $attrs_filter as $slug => $vals ) {
            if ( empty( $vals ) ) continue;
            foreach ( $vals as $v ) {
                $args['meta_query'][] = array( 'key' => '_product_attributes', 'value' => '"' . $v . '"', 'compare' => 'LIKE' );
            }
        }
    }
    if ( ! empty( $meta_query ) ) {
        $args['meta_query'] = array_merge( isset($args['meta_query']) ? $args['meta_query'] : array(), $meta_query );
    }

    switch ( $sort ) {
        case 'price_asc': $args['meta_key'] = '_price'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'ASC'; break;
        case 'price_desc': $args['meta_key'] = '_price'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
        default: $args['orderby'] = 'date'; $args['order'] = 'DESC';
    }

    $query = new WP_Query( $args );
    $html = anime_shop_render_product_grid_inner( $query );
    $pagination = paginate_links( array( 'total' => $query->max_num_pages, 'current' => $paged, 'format' => '?paged=%#%', 'type' => 'plain' ) );

    return new WP_REST_Response( array( 'success' => true, 'html' => $html, 'pagination' => $pagination, 'count' => $query->found_posts ), 200 );
}

add_shortcode( 'anime_products', 'anime_shop_products_shortcode' );
function anime_shop_products_shortcode() {
    $cat_param = isset( $_GET['cat'] ) ? (is_array($_GET['cat']) ? $_GET['cat'] : array($_GET['cat'])) : array();
    $query = new WP_Query( array( 'post_type' => 'anime_product', 'post_status' => 'publish', 'posts_per_page' => 24 ) );

    global $wpdb;
    $all_attrs_meta = $wpdb->get_col( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_product_attributes'" );
    $unique_filters = array();
    foreach ( $all_attrs_meta as $meta ) {
        $data = maybe_unserialize( $meta );
        if ( is_array( $data ) ) {
            foreach ( $data as $slug => $attr ) {
                if ( ! isset( $unique_filters[ $slug ] ) ) $unique_filters[ $slug ] = array( 'name' => $attr['name'], 'values' => array() );
                foreach ( $attr['value'] as $v ) {
                    if ( ! in_array( $v, $unique_filters[ $slug ]['values'] ) ) $unique_filters[ $slug ]['values'][] = $v;
                }
            }
        }
    }

    ob_start();
    ?>
    <div class="anime-shop-page-wrapper" id="anime-shop-discovery">
        <aside class="boutique-sidebar">
            <div class="sidebar-group">
                <h3 class="group-title">Collections</h3>
                <div class="boutique-category-tree">
                    <?php
                    $parent_cats = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'parent' => 0 ) );
                    foreach ( $parent_cats as $parent ) :
                        $children = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'parent' => $parent->term_id ) );
                        if ( empty($children) ) continue; // Only show parents with content for now
                        ?>
                        <div class="category-parent-block">
                            <h4 class="category-parent-title"><?php echo esc_html( $parent->name ); ?></h4>
                            <div class="boutique-filter-list sub-list">
                                <?php foreach ( $children as $child ) : ?>
                                    <label class="boutique-check-label">
                                        <input type="checkbox" class="cat-filter" value="<?php echo esc_attr( $child->slug ); ?>" />
                                        <span class="check-box"></span>
                                        <span class="check-text"><?php echo esc_html( $child->name ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sidebar-group">
                <h3 class="group-title">Price Range</h3>
                <div class="boutique-price-inputs">
                    <input type="number" class="min-price boutique-field" placeholder="From" />
                    <input type="number" class="max-price boutique-field" placeholder="To" />
                </div>
            </div>
            
            <button id="reset-filters" class="boutique-reset">Reset Discovery</button>
        </aside>

        <div class="shop-main-content">
            <div class="boutique-toolbar">
                <span id="discovery-count" class="boutique-count">Found <?php echo $query->found_posts; ?> artifacts</span>
                
                <div class="boutique-search-wrap">
                    <input type="text" id="discovery-search" class="boutique-field" placeholder="Search collection..." autocomplete="off" />
                </div>

                <div class="boutique-sort">
                    <select class="boutique-sort-select" id="discovery-sort">
                        <option value="latest">Sort: Newest</option>
                        <option value="price_asc">Sort: Price Low</option>
                        <option value="price_desc">Sort: Price High</option>
                    </select>
                </div>
            </div>

            <div id="active-pills" class="boutique-pills"></div>

            <div class="boutique-grid" id="discovery-grid">
                <?php echo anime_shop_render_product_grid_inner( $query ); ?>
            </div>

            <div class="boutique-pagination" id="discovery-pagination">
                <?php echo paginate_links( array( 'total' => $query->max_num_pages, 'current' => 1, 'format' => '?paged=%#%', 'type' => 'plain' ) ); ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}




// Register manual Import page and Add Export button
add_action( 'admin_menu', function() {
    add_submenu_page( null, 'Import Products', 'Import Products', 'manage_options', 'anime-shop-import', 'anime_shop_import_page' );
} );

add_action( 'manage_posts_extra_tablenav', 'anime_shop_products_top_actions', 10, 1 );
function anime_shop_products_top_actions( $which ) {
    if ( $which !== 'top' ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'anime_product' ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    $export_url = admin_url( 'admin-post.php?action=anime_shop_export&nonce=' . wp_create_nonce( 'anime_shop_export' ) );
    $import_url = admin_url( 'edit.php?post_type=anime_product&page=anime-shop-import' );
    echo '<div class="alignleft actions">';
    echo '<a href="' . esc_url( $import_url ) . '" class="page-title-action">Import</a>';
    echo '<a href="' . esc_url( $export_url ) . '" class="page-title-action">Export</a>';
    echo '</div>';
}

function anime_shop_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    echo '<div class="wrap"><h1>Anime Shop - Import Products</h1>';

    if ( isset( $_POST['anime_shop_import_action'] ) && check_admin_referer( 'anime_shop_import_action', 'anime_shop_import_nonce' ) ) {
        if ( ! empty( $_FILES['anime_csv_file']['tmp_name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $overrides = array( 'test_form' => false );
            $uploaded = wp_handle_upload( $_FILES['anime_csv_file'], $overrides );
            if ( isset( $uploaded['file'] ) && file_exists( $uploaded['file'] ) ) {
                $count = anime_shop_import_from_csv( $uploaded['file'] );
                echo '<div class="updated"><p>Imported ' . intval( $count ) . ' products from uploaded file.</p></div>';
            } else {
                echo '<div class="error"><p>Upload error: ' . esc_html( $uploaded['error'] ) . '</p></div>';
            }
        } else {
            echo '<div class="error"><p>No file uploaded.</p></div>';
        }
    }

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field( 'anime_shop_import_action', 'anime_shop_import_nonce' );
    echo '<p><label for="anime_csv_file">Select CSV file to import:</label> <input type="file" name="anime_csv_file" accept="text/csv,application/csv" required /></p>';
    echo '<p><input type="submit" name="anime_shop_import_action" class="button button-primary" value="Upload & Import" /></p>';
    echo '</form></div>';
}


// --- Single Product Layout Helper ---
function anime_shop_render_single_product_page() {
    global $post;
    $id         = $post->ID;
    $price      = get_post_meta( $id, '_price', true );
    $sale_price = get_post_meta( $id, '_sale_price', true );
    $title      = get_the_title( $id );
    $desc       = get_the_excerpt( $id ) ?: wp_trim_words( strip_tags( $post->post_content ), 40, '...' );
    $full_desc  = apply_filters( 'the_content', $post->post_content );
    $stock      = intval( get_post_meta( $id, 'stock', true ) );
    $in_stock   = get_post_meta( $id, '_in_stock', true );
    $sku        = get_post_meta( $id, '_sku', true );
    $weight     = get_post_meta( $id, 'weight', true );
    $length     = get_post_meta( $id, 'length', true );
    $width      = get_post_meta( $id, 'width', true );
    $height     = get_post_meta( $id, 'height', true );

    $attrs      = get_post_meta( $id, '_product_attributes', true );
    if ( ! is_array( $attrs ) ) $attrs = array();

    $variations = get_post_meta( $id, '_product_variations', true );
    if ( ! is_array( $variations ) ) $variations = array();

    // Build image list
    $img_ids = get_post_meta( $id, '_product_image_ids', true );
    if ( ! is_array( $img_ids ) ) {
        $img_ids = $img_ids ? array_filter( array_map( 'intval', explode( ',', $img_ids ) ) ) : array();
    }
    $all_imgs = array();
    if ( has_post_thumbnail( $id ) ) $all_imgs[] = get_post_thumbnail_id( $id );
    foreach ( $img_ids as $aid ) {
        if ( ! in_array( $aid, $all_imgs ) ) $all_imgs[] = $aid;
    }

    // Price formatting
    $price_display = number_format( floatval( $sale_price ?: $price ), 0, ',', '.' ) . ' &#8363;';

    // Stock label
    if ( $in_stock !== '1' ) {
        $stock_label = '<span class="sp-out-of-stock">Out of stock</span>';
    } elseif ( $stock > 0 && $stock <= 5 ) {
        $stock_label = '<span class="sp-low-stock">Only ' . $stock . ' left</span>';
    } else {
        $stock_label = '<span class="sp-in-stock">In stock</span>';
    }
    ?>
    <div class="sp-wrap anime-product" data-id="<?php echo esc_attr( $id ); ?>" data-variations="<?php echo esc_attr( wp_json_encode( $variations ) ); ?>">

        <!-- LEFT: Image column (Slideshow) -->
        <div class="sp-images sp-gallery-slider">
            <?php if ( ! empty( $all_imgs ) ) : ?>
                <div class="sp-main-view">
                    <?php echo wp_get_attachment_image( $all_imgs[0], 'large', false, array( 'id' => 'sp-main-img' ) ); ?>
                </div>
                <?php if ( count( $all_imgs ) > 1 ) : ?>
                    <div class="sp-thumb-track">
                        <?php foreach ( $all_imgs as $index => $aid ) : ?>
                            <div class="sp-thumb-item <?php echo $index === 0 ? 'active' : ''; ?>" data-full="<?php echo esc_url( wp_get_attachment_image_url( $aid, 'large' ) ); ?>">
                                <?php echo wp_get_attachment_image( $aid, 'thumbnail', false ); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="sp-img-placeholder">No Image</div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Info panel -->
        <div class="sp-info">
            <div class="sp-info-sticky">

                <!-- Breadcrumb -->
                <nav class="sp-breadcrumb">
                    <a href="<?php echo esc_url( home_url( '/anime-shop' ) ); ?>">Shop</a>
                    <span> / </span>
                    <span><?php echo esc_html( $title ); ?></span>
                </nav>

                <!-- Title -->
                <h1 class="sp-title"><?php echo esc_html( $title ); ?></h1>

                <!-- Price -->
                <p class="sp-price"><span class="current-price"><?php echo $price_display; ?></span>
                <?php if ( $sale_price ) : ?>
                    <span class="sp-original-price"><?php echo number_format( floatval( $price ), 0, ',', '.' ) . ' &#8363;'; ?></span>
                <?php endif; ?>
                </p>

                <!-- Stock status -->
                <p class="sp-stock"><?php echo $stock_label; ?></p>

                <?php if ( $sku ) : ?>
                    <p class="sp-sku">SKU: <?php echo esc_html( $sku ); ?></p>
                <?php endif; ?>

                <!-- Short description -->
                <?php if ( $desc ) : ?>
                    <p class="sp-desc"><?php echo esc_html( $desc ); ?></p>
                <?php endif; ?>

                <!-- Variation selectors -->
                <?php foreach ( $attrs as $slug => $a ) : ?>
                    <?php if ( empty( $a['variation'] ) ) continue; ?>
                    <div class="sp-option-row">
                        <label class="sp-option-label"><?php echo esc_html( $a['name'] ); ?></label>
                        <select class="sp-option-select anime-attr-select" data-attr="<?php echo esc_attr( $slug ); ?>">
                            <option value="">Choose <?php echo esc_html( $a['name'] ); ?></option>
                            <?php foreach ( (array) $a['value'] as $v ) : ?>
                                <option value="<?php echo esc_attr( trim( $v ) ); ?>"><?php echo esc_html( trim( $v ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>

                <!-- Quantity + Add to cart -->
                <div class="sp-cart-row">
                    <div class="sp-qty">
                        <button class="qty-control qty-minus" type="button">−</button>
                        <input class="qty-val" type="number" value="1" min="1" max="99" />
                        <button class="qty-control qty-plus" type="button">+</button>
                    </div>
                    <button class="sp-add-btn anime-add-to-cart btn-premium" data-id="<?php echo esc_attr( $id ); ?>">
                        Add to Cart
                    </button>
                </div>

                <!-- Service Info -->
                <div class="sp-service-info">
                    <div class="sp-service-item">
                        <span class="sp-service-icon">✦</span>
                        <div class="sp-service-content">
                            <strong>Authenticated Artifact</strong>
                            <p>Verified for quality and provenance by our curation team.</p>
                        </div>
                    </div>
                    <div class="sp-service-item">
                        <span class="sp-service-icon">✦</span>
                        <div class="sp-service-content">
                            <strong>Secure Transit</strong>
                            <p>Insured priority shipping in custom protective packaging.</p>
                        </div>
                    </div>
                </div>

                <!-- Specifications table (visible, no accordion) -->
                <?php
                $spec_rows = '';
                foreach ( $attrs as $slug => $a ) {
                    if ( ! empty( $a['variation'] ) ) continue;
                    if ( empty( $a['visible'] ) ) continue;
                    $spec_rows .= '<tr><th>' . esc_html( $a['name'] ) . '</th><td>' . esc_html( implode( ', ', (array) $a['value'] ) ) . '</td></tr>';
                }
                
                if ( $weight ) $spec_rows .= '<tr><th>Weight</th><td>' . esc_html( $weight ) . '</td></tr>';
                if ( $length || $width || $height ) {
                    $dim = array_filter( array( $length, $width, $height ) );
                    if ( ! empty( $dim ) ) {
                        $spec_rows .= '<tr><th>Dimensions</th><td>' . esc_html( implode( ' × ', $dim ) ) . '</td></tr>';
                    }
                }
                
                if ( $spec_rows ) : ?>
                <div class="sp-specs">
                    <p class="sp-specs-label">Specifications</p>
                    <table class="sp-specs-table">
                        <?php echo $spec_rows; ?>
                    </table>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- Full description below the fold -->
    <?php if ( $full_desc ) : ?>
    <div class="sp-full-desc">
        <h2>About this product</h2>
        <div class="sp-full-desc-body"><?php echo $full_desc; ?></div>
    </div>
    <?php endif; ?>

    <!-- You may also like -->
    <?php
    $related = new WP_Query( array(
        'post_type'      => 'anime_product',
        'posts_per_page' => 3,
        'post__not_in'   => array( $id ),
        'orderby'        => 'rand',
    ) );
    if ( $related->have_posts() ) : ?>
    <div class="sp-related">
        <h2 class="sp-related-title">You may also like</h2>
        <div class="boutique-grid">
            <?php while ( $related->have_posts() ) { $related->the_post(); echo anime_shop_render_product_card( get_the_ID() ); } wp_reset_postdata(); ?>
        </div>
    </div>
    <?php endif; ?>
    <style>
        .sp-gallery-slider { display: flex; flex-direction: column; gap: 12px; }
        .sp-main-view { width: 100%; border-radius: 8px; overflow: hidden; background: #f6f7f7; border: 1px solid #eee; aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; }
        .sp-main-view img { width: 100%; height: 100%; object-fit: contain; display: block; transition: opacity 0.2s ease-in-out; }
        .sp-thumb-track { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none; }
        .sp-thumb-track::-webkit-scrollbar { display: none; }
        .sp-thumb-item { width: 70px; height: 70px; flex-shrink: 0; border-radius: 6px; overflow: hidden; cursor: pointer; border: 2px solid transparent; opacity: 0.6; transition: all 0.2s ease; background: #f6f7f7; display: flex; align-items: center; justify-content: center; }
        .sp-thumb-item img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .sp-thumb-item:hover, .sp-thumb-item.active { opacity: 1; }
        .sp-thumb-item.active { border-color: #000; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var thumbs = document.querySelectorAll('.sp-thumb-item');
            var mainImg = document.getElementById('sp-main-img');
            if(!thumbs.length || !mainImg) return;
            
            thumbs.forEach(function(thumb) {
                // Shopee-style: hover immediately triggers the main image change
                thumb.addEventListener('mouseenter', function() {
                    var fullSrc = this.getAttribute('data-full');
                    if(mainImg.src !== fullSrc) {
                        mainImg.style.opacity = 0.6;
                        setTimeout(function(){
                            mainImg.src = fullSrc;
                            mainImg.style.opacity = 1;
                        }, 50);
                    }
                    thumbs.forEach(function(t) { t.classList.remove('active'); });
                    this.classList.add('active');
                });
            });
        });
    </script>
    <?php
}


add_filter( 'template_include', 'anime_shop_global_template_bypass', 99 );
function anime_shop_global_template_bypass( $template ) {
    if ( is_admin() ) return $template;
    return plugin_dir_path( __FILE__ ) . 'anime-shop.php';
}

add_action( 'template_redirect', 'anime_shop_global_render_wrapper' );
function anime_shop_global_render_wrapper() {
    if ( is_admin() ) return;

    // Head
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
        <?php wp_head(); ?>
        <style>
            body { 
                background: #fff; 
                color: #000; 
                margin: 0; 
                font-family: 'Outfit', sans-serif; 
                -webkit-font-smoothing: antialiased; 
            }
            .bespoke-site-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
            .site-main-content { flex: 1; padding: 60px 0; }
            .site-main-content.no-padding { padding: 0; }
        </style>
    </head>
    <body <?php body_class(); ?>>
        <div class="bespoke-site-wrapper">
            <?php echo do_shortcode('[anime_header]'); ?>
            
            <main class="site-main-content <?php echo is_front_page() ? 'no-padding' : ''; ?>">
                <div class="wrap-simple">
                    <?php if ( is_front_page() ) : ?>
                        <!-- Modern Homepage Content -->
                        <section class="simple-hero">
                            <h1>Online Artifact Shop.</h1>
                            <p>A simple, modern curation of premium anime collectibles. Focus on artifacts, zero distractions.</p>
                        </section>

                         <section class="simple-section">
                            <div class="simple-section-head">
                                <h2>Latest Arrivals</h2>
                                <a href="<?php echo home_url('/anime-shop'); ?>" style="font-weight:700; color:#000; text-decoration:none; font-size: 14px;">VIEW ALL</a>
                            </div>
                            
                            <div class="boutique-grid">
                                <?php
                                $query = new WP_Query( array( 'post_type' => 'anime_product', 'posts_per_page' => 12 ) );
                                if ( $query->have_posts() ) {
                                    while ( $query->have_posts() ) { $query->the_post();
                                        echo anime_shop_render_product_card( get_the_ID() );
                                    }
                                    wp_reset_postdata();
                                }
                                ?>
                            </div>
                        </section>
                    <?php elseif ( is_singular( 'anime_product' ) ) : ?>
                        <!-- Single Output -->
                        <?php anime_shop_render_single_product_page(); ?>
                    <?php else: ?>
                        <!-- Standard Page Content -->
                        <div class="page-content">
                            <?php 
                            if ( have_posts() ) :
                                while ( have_posts() ) : the_post();
                                    the_content();
                                endwhile;
                            endif;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <?php echo do_shortcode('[anime_footer]'); ?>
        </div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    exit;
}


add_shortcode( 'anime_homepage', 'anime_shop_homepage_shortcode' );
function anime_shop_homepage_shortcode() {
    // Legacy fallback (actual rendering happens in template_redirect for bespoke feel)
    return '';
}


// --- Frontend: Header & Footer Shortcodes ---
add_shortcode( 'anime_header', 'anime_shop_header_shortcode' );
function anime_shop_header_shortcode() {
    ob_start();
    $cart_count = anime_shop_get_cart_count();
    ?>
    <header class="anime-site-header">
        <div class="anime-header-container">
            <div class="header-left">
                <div class="anime-logo">
                    <a href="<?php echo home_url(); ?>">ANIME<span>SHOP</span></a>
                </div>
                <nav class="anime-nav">
                    <a href="<?php echo home_url(); ?>">Home</a>
                    <a href="<?php echo home_url('/anime-shop'); ?>">Shop</a>
                    <a href="<?php echo home_url('/about'); ?>">About</a>
                </nav>
            </div>

            <div class="anime-user-nav">
                <a href="<?php echo home_url('/cart'); ?>" class="anime-btn anime-btn-outline anime-cart-btn" id="anime-header-cart">
                    <svg class="cart-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    Cart <span class="anime-cart-count"><?php echo $cart_count > 0 ? "($cart_count)" : ""; ?></span>
                </a>

                <?php if ( is_user_logged_in() ): ?>
                    <a href="<?php echo home_url('/account'); ?>" class="anime-btn anime-btn-outline nav-desktop">My Account</a>
                    <a href="<?php echo wp_logout_url( home_url() ); ?>" class="anime-btn anime-btn-primary nav-desktop">Logout</a>
                <?php else: ?>
                    <a href="<?php echo home_url('/login'); ?>" class="anime-btn anime-btn-outline nav-desktop">Login</a>
                    <a href="<?php echo home_url('/register'); ?>" class="anime-btn anime-btn-primary nav-desktop">Sign Up</a>
                <?php endif; ?>

                <div class="mobile-toggle" id="anime-mobile-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <div class="mobile-nav" id="anime-mobile-nav">
            <div class="mobile-nav-inner">
                <ul class="mobile-links">
                    <li><a href="<?php echo home_url('/'); ?>">Home</a></li>
                    <li><a href="<?php echo home_url('/anime-shop'); ?>">Shop</a></li>
                    <li><a href="<?php echo home_url('/about'); ?>">About</a></li>
                    <li><a href="<?php echo home_url('/account'); ?>">My Account</a></li>
                    <?php if ( is_user_logged_in() ): ?>
                        <li><a href="<?php echo wp_logout_url( home_url() ); ?>">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo home_url('/login'); ?>">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <?php
    return ob_get_clean();
}

add_shortcode( 'anime_footer', 'anime_shop_footer_shortcode' );
function anime_shop_footer_shortcode() {
    ob_start();
    ?>
    <footer class="anime-site-footer">
        <div class="anime-footer-container">
            <div class="anime-footer-info">
                <h4>ANIME<span>SHOP</span></h4>
                <p>Curating the finest artifacts for the ultimate enthusiast. Quality, character, and community.</p>
            </div>
            <div class="anime-footer-links">
                <h5>Collect</h5>
                <ul>
                    <li><a href="<?php echo home_url('/anime-shop'); ?>">All Artifacts</a></li>
                    <li><a href="#">Limited Editions</a></li>
                    <li><a href="#">New Drops</a></li>
                    <li><a href="#">Vault</a></li>
                </ul>
            </div>
            <div class="anime-footer-links">
                <h5>Experience</h5>
                <ul>
                    <li><a href="<?php echo home_url('/account'); ?>">My Profile</a></li>
                    <li><a href="<?php echo home_url('/cart'); ?>">My Cart</a></li>
                    <li><a href="<?php echo home_url('/about'); ?>">About Us</a></li>
                    <li><a href="<?php echo home_url('/contact'); ?>">Contact</a></li>
                </ul>
            </div>

            <div class="anime-footer-links">
                <h5>Connect</h5>
                <ul>
                    <li><a href="#">Discord Community</a></li>
                    <li><a href="#">Newsletter</a></li>
                    <li><a href="#">Support</a></li>
                    <li><a href="<?php echo home_url('/terms'); ?>">Terms & Conditions</a></li>
                </ul>
            </div>

        </div>
        <div class="anime-footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Anime Shop. All rights reserved.</p>
            <div class="anime-footer-social">
                <a href="#">TW</a>
                <a href="#">IG</a>
                <a href="#">FB</a>
                <a href="#">YT</a>
            </div>
        </div>
    </footer>
    <?php
    return ob_get_clean();
}

// --- Frontend: Authentication Shortcodes ---
add_shortcode( 'anime_login', 'anime_shop_login_shortcode' );
function anime_shop_login_shortcode() {
    if ( is_user_logged_in() ) {
        wp_redirect( home_url('/account') );
        exit;
    }
    ob_start();
    ?>
    <div class="anime-auth-page">
        <div class="anime-auth-card">
            <h2>Welcome Back</h2>
            <p class="subtitle">Enter your credentials to access your shop account.</p>
            <div id="anime-login-msg" class="anime-auth-message"></div>
            <form id="anime-login-form" class="anime-auth-form">
                <div class="anime-form-group">
                    <label>Username or Email</label>
                    <input type="text" name="user_login" required placeholder="name@example.com" />
                </div>
                <div class="anime-form-group">
                    <label>Password</label>
                    <input type="password" name="user_password" required placeholder="••••••••" />
                </div>
                <div class="anime-form-group" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="rememberme" id="rememberme" style="width:auto;" />
                    <label for="rememberme" style="margin:0;">Remember me</label>
                </div>
                <button type="submit" class="anime-btn anime-btn-primary anime-auth-submit">Log In</button>
            </form>
            <div class="anime-auth-footer">
                Don't have an account? <a href="<?php echo home_url('/register'); ?>">Sign Up</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'anime_register', 'anime_shop_register_shortcode' );
function anime_shop_register_shortcode() {
    if ( is_user_logged_in() ) {
        wp_redirect( home_url('/account') );
        exit;
    }
    ob_start();
    ?>
    <div class="anime-auth-page">
        <div class="anime-auth-card">
            <h2>Join Anime Shop</h2>
            <p class="subtitle">Create an account to start collecting today.</p>
            <div id="anime-register-msg" class="anime-auth-message"></div>
            <form id="anime-register-form" class="anime-auth-form">
                <div class="anime-form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Choose a username" />
                </div>
                <div class="anime-form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com" />
                </div>
                <div class="anime-form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••" />
                </div>
                <button type="submit" class="anime-btn anime-btn-primary anime-auth-submit">Create Account</button>
            </form>
            <div class="anime-auth-footer">
                Already have an account? <a href="<?php echo home_url('/login'); ?>">Log In</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX: Login
add_action( 'wp_ajax_nopriv_anime_login', 'anime_ajax_login_handler' );
function anime_ajax_login_handler() {
    $creds = array(
        'user_login'    => sanitize_text_field( $_POST['user_login'] ),
        'user_password' => $_POST['user_password'],
        'remember'      => isset( $_POST['rememberme'] )
    );
    $user = wp_signon( $creds, false );
    if ( is_wp_error( $user ) ) {
        wp_send_json_error( array( 'message' => $user->get_error_message() ) );
    } else {
        wp_send_json_success( array( 'redirect' => home_url() ) );
    }
}

// AJAX: Register
add_action( 'wp_ajax_nopriv_anime_register', 'anime_ajax_register_handler' );
function anime_ajax_register_handler() {
    $username = sanitize_user( $_POST['username'] );
    $email    = sanitize_email( $_POST['email'] );
    $password = $_POST['password'];

    if ( username_exists( $username ) ) {
        wp_send_json_error( array( 'message' => 'Username already exists.' ) );
    }
    if ( email_exists( $email ) ) {
        wp_send_json_error( array( 'message' => 'Email already registered.' ) );
    }

    $user_id = wp_create_user( $username, $password, $email );
    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
    } else {
        $user = new WP_User( $user_id );
        $user->set_role( 'shop_customer' );
        
        // Auto-login
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );
        
        wp_send_json_success( array( 'redirect' => home_url() ) );
    }
}

add_shortcode( 'anime_about', 'anime_shop_about_shortcode' );
function anime_shop_about_shortcode() {
    ob_start();
    ?>
    <div class="about-page">
        <header class="about-header">
            <h1 class="about-title">Online Artifact Shop <span class="title-sub">(Curation Over Choice)</span></h1>
            <p class="about-tagline">A focused sanctuary for the ultimate enthusiast.</p>
        </header>

        <section class="about-content">
            <div class="about-row">
                <div class="about-text">
                    <h3>The Boutique Discipline</h3>
                    <p>We do not aim to be a catalog. We aim to be a curation. In an era of infinite scrolls and algorithm-driven noise, we choose to speak with silence and selection.</p>
                </div>
                <div class="about-text">
                    <h3>Authenticated Provenance</h3>
                    <p>Every piece in our collection has been vetted for quality and character. We believe that an artifact is not just a product, but a piece of history and craftsmanship.</p>
                </div>
            </div>

            <div class="about-manifesto">
                <h2>Our Standards</h2>
                <div class="manifesto-grid">
                    <div class="manifesto-item">
                        <h4>Zero Distraction</h4>
                        <p>No popups. No urgency timers. Just you and the artifacts.</p>
                    </div>
                    <div class="manifesto-item">
                        <h4>Pure Curation</h4>
                        <p>If it doesn't inspire us, it doesn't enter the shop.</p>
                    </div>
                    <div class="manifesto-item">
                        <h4>Secure Transit</h4>
                        <p>Every artifact is insured and protected for the journey home.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'anime_terms', 'anime_shop_terms_shortcode' );
function anime_shop_terms_shortcode() {
    ob_start();
    ?>
    <div class="terms-page">
        <header class="terms-header">
            <h1>Terms & Conditions</h1>
            <p>Last Updated: May 2026</p>
        </header>

        <section class="terms-content">
            <div class="terms-section">
                <h3>1. Collection Access</h3>
                <p>By accessing the Anime Shop, you agree to respect the curation and provenance of all artifacts displayed. Use of the site for automated scraping or mass-collection of data is prohibited.</p>
            </div>

            <div class="terms-section">
                <h3>2. Authenticity</h3>
                <p>We guarantee the authenticity of every artifact labeled "Platinum Authenticated". If an artifact fails to match its description, we provide a full restoration of value.</p>
            </div>

            <div class="terms-section">
                <h3>3. Secure Acquisitions</h3>
                <p>Orders placed on the shop are final once they enter the "Transition" phase. We ensure secure packaging and insured transit for all premium items.</p>
            </div>

            <div class="terms-section">
                <h3>4. Privacy</h3>
                <p>Your authentication data and collection history are kept strictly private. We do not share your collector status with third-party marketplaces.</p>
            </div>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'anime_success', 'anime_shop_success_shortcode' );
function anime_shop_success_shortcode() {
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    ob_start();
    ?>
    <div class="success-page">
        <div class="success-card">
            <div class="success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 6L9 17l-5-5"/></svg>
            </div>
            <?php 
            $method = $order_id ? get_post_meta($order_id, 'payment_method', true) : '';
            $is_bank = ($method === 'bank_transfer');
            ?>
            <h1><?php echo $is_bank ? 'Awaiting Payment' : 'Acquisition Confirmed'; ?></h1>
            <p><?php echo $is_bank ? 'Your order is held in terminal state. Please complete the transfer below to proceed.' : 'Your artifact\'s journey has begun. Documentation and tracking details will be sent shortly.'; ?></p>

            <?php 
            if ($order_id) {
                $method = get_post_meta($order_id, 'payment_method', true);
                if ($method === 'bank_transfer') {
                    $total = get_post_meta($order_id, 'order_total', true);
                    $qr_url = "https://img.vietqr.io/image/BIDV-8870382887-qr_only.jpg?amount=" . $total . "&addInfo=ANS" . $order_id . "&accountName=ANIME%20SHOP%20CURATION";
                    ?>
                    <div class="bank-transfer-instruction">
                        <h3>Bank Transfer</h3>
                        <p>Scan the VietQR code to finalize your acquisition.</p>
                        <img src="<?php echo esc_url($qr_url); ?>" alt="VietQR" />
                        <div class="bank-info-table">
                            <div class="bank-info-row"><strong>Bank</strong><span>BIDV (Ngân hàng Đầu tư và Phát triển)</span></div>
                            <div class="bank-info-row"><strong>Account</strong><span>8870382887</span></div>
                            <div class="bank-info-row"><strong>Holder</strong><span>ANIME SHOP CURATION</span></div>
                            <div class="bank-info-row"><strong>Content</strong><span>ANS<?php echo $order_id; ?></span></div>
                        </div>
                        <button id="confirm-bank-transfer" class="btn-premium" style="width:100%; margin-top:25px; padding:15px; border-radius:8px;">I Have Completed Transfer</button>
                        <script>
                        document.getElementById('confirm-bank-transfer').addEventListener('click', function() {
                            const btn = this;
                            btn.disabled = true;
                            btn.innerText = 'Verifying...';
                            fetch('/wp-json/anime-shop/v1/confirm-payment', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ order_id: <?php echo $order_id; ?> })
                            }).then(res => res.json()).then(data => {
                                if(data.success) {
                                    alert('Notification sent to admin. Your order status will be updated shortly.');
                                    window.location.reload();
                                }
                            });
                        });
                        </script>
                    </div>
                    <?php
                }
            }
            ?>
            
            <?php if ($order_id) echo do_shortcode('[anime_order_view order_id="'.$order_id.'"]'); ?>

            <div class="success-actions" style="margin-top: 40px;">
                <a href="<?php echo home_url('/account'); ?>" class="anime-btn anime-btn-outline">View Order History</a>
                <a href="<?php echo home_url('/anime-shop'); ?>" class="anime-btn anime-btn-primary">Return to Discovery</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'anime_order_view', 'anime_shop_order_view_shortcode' );
function anime_shop_order_view_shortcode($atts) {
    $atts = shortcode_atts(array('order_id' => 0), $atts);
    $order_id = $atts['order_id'] ?: (isset($_GET['order_id']) ? intval($_GET['order_id']) : 0);
    
    if (!$order_id) return '<p>Artifact record not specified.</p>';
    
    $order = get_post($order_id);
    if (!$order || $order->post_type !== 'anime_order') return '<p>Invalid artifact record.</p>';
    
    // Security check: only order owner or admin
    if (!current_user_can('manage_options')) {
        $customer_email = get_post_meta($order_id, 'customer_email', true);
        $order_user_id = get_post_meta($order_id, 'customer_user_id', true);
        $current_user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        
        $is_owner = false;
        if ($current_user_id && $order_user_id && intval($current_user_id) === intval($order_user_id)) $is_owner = true;
        if ($current_user->user_email === $customer_email) $is_owner = true;
        
        // Also allow if it's the success page and we just placed this order (simple session check or just email match)
        if (!$is_owner) {
             return '<p class="auth-error">Authentication required to view this provenance record.</p>';
        }
    }

    $items = get_post_meta($order_id, 'order_items', true);
    $total = get_post_meta($order_id, 'order_total', true);
    $status = get_post_meta($order_id, 'order_status', true);
    $address = get_post_meta($order_id, 'customer_address', true);

    ob_start();
    ?>
    <div class="order-receipt">
        <div class="receipt-header">
            <h3>Provenance Record #<?php echo $order_id; ?></h3>
            <span class="status-badge status-<?php echo esc_attr($status); ?>"><?php echo ucfirst($status); ?></span>
        </div>
        <div class="receipt-items">
            <?php foreach ($items as $item) : ?>
                <div class="receipt-item">
                    <span class="item-name"><?php echo esc_html($item['title']); ?> × <?php echo $item['quantity']; ?></span>
                    <span class="item-price"><?php echo number_format($item['subtotal'], 0, ',', '.') . ' &#8363;'; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="receipt-footer">
            <div class="receipt-total">
                <span>Total Value</span>
                <strong><?php echo number_format($total, 0, ',', '.') . ' &#8363;'; ?></strong>
            </div>
            <div class="receipt-address">
                <p><strong>Secure Destination:</strong><br/><?php echo nl2br(esc_html($address)); ?></p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'anime_contact', 'anime_shop_contact_shortcode' );
function anime_shop_contact_shortcode() {
    ob_start();
    ?>
    <div class="contact-page">
        <header class="contact-header">
            <h1>Reach Out</h1>
            <p>For inquiries regarding artifact curation or collection authentication.</p>
        </header>
        <form class="contact-form anime-card">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Identity</label>
                    <input type="text" placeholder="Your Name" required />
                </div>
                <div class="form-group full">
                    <label>Authentication Email</label>
                    <input type="email" placeholder="collector@nexus.com" required />
                </div>
                <div class="form-group full">
                    <label>Subject</label>
                    <input type="text" placeholder="Inquiry regarding..." required />
                </div>
                <div class="form-group full">
                    <label>Message</label>
                    <textarea placeholder="Your message to the curators..." required></textarea>
                </div>
            </div>
            <button type="submit" class="anime-btn anime-btn-primary">Transmit Message</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'anime_search', 'anime_shop_search_shortcode' );
function anime_shop_search_shortcode() {
    $query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    ob_start();
    ?>
    <section class="shop-grid-container">
        <header class="shop-header">
            <h1>Discovery: "<?php echo esc_html($query); ?>"</h1>
            <p>Artifacts matching your curation criteria.</p>
        </header>

        <?php
        $args = array(
            'post_type' => 'anime_product',
            'posts_per_page' => -1,
            's' => $query
        );
        $products = get_posts($args);

        if ($products) : ?>
            <div class="product-grid">
                <?php foreach ($products as $post) : setup_postdata($post); ?>
                    <div class="product-card">
                        <a href="<?php echo get_permalink(); ?>" class="product-image">
                            <?php if (has_post_thumbnail()) : ?>
                                <?php echo get_the_post_thumbnail(get_the_ID(), 'large'); ?>
                            <?php else : ?>
                                <img src="https://via.placeholder.com/400x500?text=No+Image" alt="No image" />
                            <?php endif; ?>
                        </a>
                        <div class="product-info">
                            <h3><?php the_title(); ?></h3>
                            <p class="price"><?php echo number_format(get_post_meta(get_the_ID(), 'price', true), 0, ',', '.') . ' &#8363;'; ?></p>
                            <a href="<?php echo get_permalink(); ?>" class="anime-btn anime-btn-outline">View Piece</a>
                        </div>
                    </div>
                <?php endforeach; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <div class="empty-results" style="text-align: center; padding: 60px 0;">
                <p>No artifacts were found matching "<?php echo esc_html($query); ?>".</p>
                <a href="<?php echo home_url('/anime-shop'); ?>" class="anime-btn anime-btn-primary" style="margin-top: 20px;">Return to Discovery</a>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'anime_global_history', 'anime_shop_global_history_shortcode' );
function anime_shop_global_history_shortcode() {
    ob_start();
    ?>
    <div class="global-history-page">
        <header class="history-header">
            <h1>Global Transmission History</h1>
            <p>Real-time acquisition feed from the master database.</p>
        </header>

        <div id="realtime-order-feed" class="order-feed-container">
            <!-- Items injected by Firebase -->
            <div class="feed-placeholder">Establishing secure connection...</div>
        </div>

        <style>
            .global-history-page { max-width: 800px; margin: 60px auto; padding: 0 20px; }
            .history-header { text-align: center; margin-bottom: 40px; }
            .history-header h1 { font-size: 36px; font-weight: 800; text-transform: uppercase; letter-spacing: -1px; margin-bottom: 10px; }
            .history-header p { color: #666; font-size: 16px; }
            
            .order-feed-container { display: flex; flex-direction: column; gap: 15px; }
            .feed-item { 
                background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 20px; 
                display: flex; align-items: center; justify-content: space-between;
                animation: slideIn 0.4s ease-out; box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            }
            .feed-left { display: flex; flex-direction: column; gap: 4px; }
            .feed-customer { font-weight: 700; font-size: 18px; color: #000; }
            .feed-meta { font-size: 13px; color: #888; font-family: monospace; }
            .feed-right { text-align: right; }
            .feed-total { font-weight: 800; font-size: 20px; color: #000; display: block; }
            .feed-badge { 
                display: inline-block; padding: 4px 10px; background: #000; color: #fff; 
                font-size: 10px; font-weight: 700; text-transform: uppercase; border-radius: 4px; margin-top: 6px;
            }

            .feed-placeholder { text-align: center; color: #aaa; padding: 40px; font-style: italic; }

            @keyframes slideIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>

        <script type="module">
            import { initializeApp } from "https://www.gstatic.com/firebasejs/12.13.0/firebase-app.js";
            import { getDatabase, ref, onChildAdded } from "https://www.gstatic.com/firebasejs/12.13.0/firebase-database.js";

            const firebaseConfig = {
                apiKey: "AIzaSyDffqox62axUs3rn2-hxGgW0ySQ4kMDfrU",
                authDomain: "animeshop-d06d1.firebaseapp.com",
                databaseURL: "https://animeshop-d06d1-default-rtdb.asia-southeast1.firebasedatabase.app",
                projectId: "animeshop-d06d1",
                storageBucket: "animeshop-d06d1.firebasestorage.app",
                messagingSenderId: "881369982790",
                appId: "1:881369982790:web:10a1e94aec3f24ad85a747"
            };

            const app = initializeApp(firebaseConfig);
            const db = getDatabase(app);
            const ordersRef = ref(db, 'orders');
            const feedContainer = document.getElementById('realtime-order-feed');

            let firstLoad = true;

            onChildAdded(ordersRef, (snapshot) => {
                const data = snapshot.val();
                if (firstLoad) {
                    feedContainer.innerHTML = '';
                    firstLoad = false;
                }

                const item = document.createElement('div');
                item.className = 'feed-item';
                item.innerHTML = `
                    <div class="feed-left">
                        <span class="feed-customer">${data.customer || 'Anonymous Collector'}</span>
                        <span class="feed-meta">ENTRY ID: #${data.id} • TIME: ${data.time} • DATE: ${data.date}</span>
                    </div>
                    <div class="feed-right">
                        <span class="feed-total">${new Intl.NumberFormat('vi-VN').format(data.total)} ₫</span>
                        <span class="feed-badge">Verified Transaction</span>
                    </div>
                `;
                feedContainer.prepend(item);
            });
        </script>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Security: Restrict Admin Dashboard Access for non-administrators.
 * This ensures that customers stay on the frontend and never see the WP backend.
 */
add_action( 'admin_init', 'anime_shop_restrict_admin_access' );
function anime_shop_restrict_admin_access() {
    // Allow AJAX requests (needed for some frontend features)
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;

    // Redirect anyone who cannot manage options (non-admins) to the home page
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_redirect( home_url() );
        exit;
    }
}

/**
 * Hide the WordPress Admin Bar for non-administrators.
 */
add_filter( 'show_admin_bar', 'anime_shop_hide_admin_bar' );
function anime_shop_hide_admin_bar( $show ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }
    return $show;
}
