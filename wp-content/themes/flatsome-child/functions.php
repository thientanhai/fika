<?php
/** Fika Pouches child theme. @package Flatsome_Child */
defined( 'ABSPATH' ) || exit;

define( 'FIKA_CHILD_THEME_VERSION', '3.10.5' );

require_once __DIR__ . '/inc/https-urls.php';

require_once __DIR__ . '/inc/disable-transactions.php';
require_once __DIR__ . '/inc/product-cards.php';

require_once __DIR__ . '/inc/archive-content.php';
require_once __DIR__ . '/inc/filter-seo.php';
require_once __DIR__ . '/inc/product-category-urls.php';
require_once __DIR__ . '/inc/multilingual-seo.php';
require_once __DIR__ . '/inc/brand-editor.php';
require_once __DIR__ . '/inc/shortcodes.php';
require_once __DIR__ . '/inc/maintenance.php';
require_once __DIR__ . '/inc/single-post.php';
require_once __DIR__ . '/inc/blog-archive.php';
require_once __DIR__ . '/inc/home-blog.php';

// Preserve the requested Classic Editor and Classic Widgets behaviour.
add_filter( 'use_block_editor_for_post', '__return_false', 100 );
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
add_filter( 'use_widgets_block_editor', '__return_false', 100 );

function fika_child_load_textdomain() {
	load_child_theme_textdomain( 'flatsome-child', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'fika_child_load_textdomain' );

function fika_enqueue_login_style() {
	$css_path = get_stylesheet_directory() . '/login.css';
	if ( ! is_readable( $css_path ) ) return;
	wp_enqueue_style( 'fika-login', get_stylesheet_directory_uri() . '/login.css', array(), (string) filemtime( $css_path ) );
}
add_action( 'login_enqueue_scripts', 'fika_enqueue_login_style' );

function fika_hide_product_last_edit_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'product' === $screen->post_type ) {
		echo '<style id="fika-hide-product-last-edit">#last-edit{display:none!important;}</style>';
	}
}
add_action( 'admin_head', 'fika_hide_product_last_edit_notice' );
