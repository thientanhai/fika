<?php
/** Product-card presentation and links to product information. */
defined( 'ABSPATH' ) || exit;

/** Resolve language from Polylang first, then WordPress locale. */
function fika_product_card_view_label() {
	$locale = function_exists( 'pll_current_language' ) ? pll_current_language( 'locale' ) : '';
	$locale = $locale ? $locale : get_locale();
	return 0 === strpos( strtolower( (string) $locale ), 'en' ) ? 'View Now' : 'Xem Ngay';
}

/** A normal detail-page link, independent of WooCommerce's purchase hooks. */
function fika_product_card_view_link() {
	global $product;
	if ( ! $product instanceof WC_Product || ! $product->is_visible() ) return;
	$product_id = $product->get_id();
	$language   = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : '';
	if ( $language && function_exists( 'pll_get_post' ) ) {
		$translated_id = pll_get_post( $product_id, $language );
		if ( $translated_id && 'publish' === get_post_status( $translated_id ) ) {
			$product_id = (int) $translated_id;
		}
	}
	$url = get_permalink( $product_id );
	if ( ! $url ) return;
	$label = fika_product_card_view_label();
	$name  = wp_strip_all_tags( get_the_title( $product_id ) );
	echo '<div class="fika-product-view"><a class="button fika-product-view-button" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $label . ': ' . $name ) . '">';
	echo esc_html( $label );
	echo '</a></div>';
}

function fika_product_card_assets() {
	wp_enqueue_style( 'fika-product-cards', get_stylesheet_directory_uri() . '/assets/css/product-cards.css', array(), FIKA_CHILD_THEME_VERSION );
}

function fika_product_cards_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) return;
	// Flatsome can deliberately render five empty stars when this option is on.
	add_filter( 'theme_mod_product_box_empty_rating', '__return_false', PHP_INT_MAX );
	// The Flatsome hook is inside box-text, following the name, price and excerpt.
	// Do not also use woocommerce_after_shop_loop_item: both fire for the same card.
	add_action( 'flatsome_product_box_after', 'fika_product_card_view_link', 110 );
	add_action( 'wp_enqueue_scripts', 'fika_product_card_assets', 100 );
}
add_action( 'after_setup_theme', 'fika_product_cards_boot', 110 );
