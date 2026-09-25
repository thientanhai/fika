<?php
/** Multilingual SEO fallbacks for translated archive pagination. */
defined( 'ABSPATH' ) || exit;

/** Build a clean pretty-permalink URL for one archive page number. */
function fika_paginated_archive_url( $base_url, $page_number ) {
	$page_number = absint( $page_number );
	if ( ! is_string( $base_url ) || '' === $base_url || $page_number < 2 ) return '';

	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		return add_query_arg( 'paged', $page_number, $base_url );
	}

	global $wp_rewrite;
	$pagination_base = isset( $wp_rewrite->pagination_base ) && '' !== (string) $wp_rewrite->pagination_base
		? trim( (string) $wp_rewrite->pagination_base, '/' )
		: 'page';

	return trailingslashit( $base_url ) . user_trailingslashit( $pagination_base . '/' . $page_number, 'paged' );
}

/**
 * Return the clean translated URLs for the current taxonomy archive page.
 *
 * Polylang does not render its hreflang block on these page 2+ archives, so
 * filtering its existing array is not sufficient. Keep URL generation in one
 * function for both the document head and the visible language switcher.
 */
function fika_get_paginated_taxonomy_hreflangs() {
	if ( is_admin() || ! is_paged() || ! empty( $_GET ) ) return array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! function_exists( 'pll_get_term_translations' ) || ! function_exists( 'pll_get_term_language' ) ) return array();

	$taxonomies = (array) apply_filters( 'fika_paginated_hreflang_taxonomies', array( 'product_cat', 'product_brand' ) );
	$taxonomies = array_values( array_unique( array_filter( array_map( 'sanitize_key', $taxonomies ) ) ) );
	if ( empty( $taxonomies ) || ! is_tax( $taxonomies ) ) return array();

	$term = get_queried_object();
	if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, $taxonomies, true ) ) return array();

	$page_number = absint( get_query_var( 'paged' ) );
	if ( $page_number < 2 ) return array();

	$translations = pll_get_term_translations( (int) $term->term_id );
	if ( ! is_array( $translations ) || count( $translations ) < 2 ) return array();

	$generated = array();
	foreach ( $translations as $language => $term_id ) {
		$language = sanitize_key( (string) $language );
		$term_id  = absint( $term_id );
		if ( '' === $language || $term_id <= 0 ) continue;

		$translated_term = get_term( $term_id, $term->taxonomy );
		if ( ! $translated_term instanceof WP_Term || is_wp_error( $translated_term ) ) continue;
		if ( $language !== sanitize_key( (string) pll_get_term_language( $term_id, 'slug' ) ) ) continue;

		$base_url = get_term_link( $translated_term, $term->taxonomy );
		if ( is_wp_error( $base_url ) ) continue;

		$url = fika_paginated_archive_url( $base_url, $page_number );
		if ( '' !== $url ) $generated[ $language ] = esc_url_raw( $url );
	}

	return count( $generated ) >= 2 ? $generated : array();
}

/**
 * Prevent duplicate alternate tags if a future Polylang release starts
 * rendering hreflang on the same paginated taxonomy requests.
 */
function fika_suppress_native_paginated_taxonomy_hreflangs( $hreflangs ) {
	return count( fika_get_paginated_taxonomy_hreflangs() ) >= 2 ? array() : $hreflangs;
}
add_filter( 'pll_rel_hreflang_attributes', 'fika_suppress_native_paginated_taxonomy_hreflangs', PHP_INT_MAX );

/** Print one reciprocal, self-referencing alternate pair directly in <head>. */
function fika_output_paginated_taxonomy_hreflangs() {
	$hreflangs = fika_get_paginated_taxonomy_hreflangs();
	if ( count( $hreflangs ) < 2 ) return;

	foreach ( $hreflangs as $language => $url ) {
		printf(
			'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
			esc_attr( $language ),
			esc_url( $url )
		);
	}
}
add_action( 'wp_head', 'fika_output_paginated_taxonomy_hreflangs', 2 );
