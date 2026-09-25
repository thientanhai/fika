<?php
/** Archive display rules. Never modify stored descriptions or SEO metadata. */
defined( 'ABSPATH' ) || exit;

function fika_is_product_archive_request() {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return false;
	if ( function_exists( 'is_shop' ) && is_shop() ) return true;
	if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) return true;
	return is_tax( array( 'product_cat', 'product_tag', 'product_brand' ) );
}

function fika_is_paged_product_archive() {
	return is_paged() && fika_is_product_archive_request();
}

/** Hide reusable SEO copy anywhere except the clean first archive page. */
function fika_should_hide_product_archive_descriptions() {
	if ( fika_is_paged_product_archive() ) return true;
	return function_exists( 'fika_is_faceted_product_archive_request' ) && fika_is_faceted_product_archive_request();
}

/**
 * Link page 1 directly to the archive root instead of a redirecting /page/1/.
 * The final paginate_links filter catches WooCommerce and Flatsome pagination
 * without changing canonical URLs, rewrite rules or valid page 2+ links.
 */
function fika_normalize_product_archive_page_one_link( $url ) {
	if ( ! is_string( $url ) || '' === $url || ! fika_is_product_archive_request() ) return $url;
	global $wp_rewrite;
	$pagination_base = isset( $wp_rewrite->pagination_base ) && is_string( $wp_rewrite->pagination_base )
		? trim( $wp_rewrite->pagination_base, '/' )
		: 'page';
	if ( '' === $pagination_base ) $pagination_base = 'page';
	$normalized = preg_replace(
		'~/' . preg_quote( $pagination_base, '~' ) . '/1/?(?=[?#]|$)~i',
		'/',
		$url
	);
	return is_string( $normalized ) ? $normalized : $url;
}
add_filter( 'paginate_links', 'fika_normalize_product_archive_page_one_link', 20 );

/** Run after the main query and parent registrations exist. */
function fika_disable_paged_archive_descriptions() {
	if ( ! fika_should_hide_product_archive_descriptions() ) return;
	remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
	remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );
	remove_action( 'flatsome_products_after', 'flatsome_products_footer_content', 10 );
}
add_action( 'wp', 'fika_disable_paged_archive_descriptions', 100 );

/**
 * Optional wrapper for description text inside Flatsome Top Content.
 * Use [fika_first_page]...[/fika_first_page] around the description only.
 * Banners, titles and other unwrapped Top Content remain on page 2.
 */
function fika_first_page_shortcode( $atts, $content = '' ) {
	if ( fika_should_hide_product_archive_descriptions() ) return '';
	return is_string( $content ) ? do_shortcode( $content ) : '';
}

/** Filter stored HTML before executing trusted registered shortcodes. */
function fika_format_brand_bottom_content( $content, $term ) {
	if ( ! is_string( $content ) || '' === trim( $content ) ) return '';
	$html = wp_kses_post( $content );
	$html = shortcode_unautop( wpautop( wptexturize( $html ) ) );
	$html = do_shortcode( $html );
	if ( function_exists( 'wp_filter_content_tags' ) ) {
		$html = wp_filter_content_tags( $html, 'fika_brand_bottom' );
	}
	return (string) apply_filters( 'fika_brand_bottom_html', $html, $term );
}

/** Show brand text once, including archives with no visible products. */
function fika_render_brand_bottom_content() {
	static $rendered = array();
	if ( is_admin() || is_feed() || ! is_tax( 'product_brand' ) || fika_should_hide_product_archive_descriptions() ) return;
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term || isset( $rendered[ $term->term_id ] ) ) return;
	$content = get_term_meta( $term->term_id, 'brand_bottom', true );
	if ( ! is_string( $content ) || '' === trim( $content ) ) return;
	// Set before rendering so a nested shortcode cannot re-enter the renderer.
	$rendered[ $term->term_id ] = true;
	$html = fika_format_brand_bottom_content( $content, $term );
	if ( '' !== trim( $html ) ) {
		echo '<div class="brand-bottom-content">';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw HTML filtered before trusted shortcode rendering.
		echo '</div>';
	}
}
add_action( 'woocommerce_after_shop_loop', 'fika_render_brand_bottom_content', 15 );
add_action( 'woocommerce_no_products_found', 'fika_render_brand_bottom_content', 15 );
