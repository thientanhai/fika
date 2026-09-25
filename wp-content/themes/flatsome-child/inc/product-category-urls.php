<?php
/** Product-category permalinks compatible with Polylang and Rank Math. */
defined( 'ABSPATH' ) || exit;

/**
 * Remove the WooCommerce category base without joining the language and term
 * slugs. Rank Math 1.0.278 can turn `/en/base/term/` into `/enterm/` while
 * redirecting, so this theme owns product-category redirects instead.
 */
function fika_remove_product_category_base_from_link( $link, $term, $taxonomy ) {
	if ( 'product_cat' !== $taxonomy || ! is_object( $term ) || ! function_exists( 'wc_get_permalink_structure' ) ) {
		return $link;
	}

	$structure = wc_get_permalink_structure();
	$base      = isset( $structure['category_rewrite_slug'] ) ? trim( (string) $structure['category_rewrite_slug'], '/' ) : '';
	if ( '' === $base ) return $link;

	$pattern = '#/' . preg_quote( $base, '#' ) . '/#i';
	return preg_replace( $pattern, '/', (string) $link, 1 );
}
add_filter( 'term_link', 'fika_remove_product_category_base_from_link', 999, 3 );

/** Get all product categories, including terms outside the current language. */
function fika_get_multilingual_product_categories() {
	$args = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	);
	if ( function_exists( 'pll_get_term_language' ) ) $args['lang'] = '';

	$terms = get_terms( $args );
	return is_wp_error( $terms ) || ! is_array( $terms ) ? array() : $terms;
}

/** Build a category's hierarchical slug path without calling term-link filters. */
function fika_product_category_slug_path( $term, $term_map ) {
	if ( ! is_object( $term ) || empty( $term->slug ) ) return '';

	$parts = array( (string) $term->slug );
	$seen  = array( (int) $term->term_id => true );
	$parent_id = isset( $term->parent ) ? (int) $term->parent : 0;

	while ( $parent_id > 0 && isset( $term_map[ $parent_id ] ) && empty( $seen[ $parent_id ] ) ) {
		$parent = $term_map[ $parent_id ];
		if ( ! is_object( $parent ) || empty( $parent->slug ) ) break;
		array_unshift( $parts, (string) $parent->slug );
		$seen[ $parent_id ] = true;
		$parent_id = isset( $parent->parent ) ? (int) $parent->parent : 0;
	}

	return implode( '/', $parts );
}

/**
 * Prepend explicit base-free rewrite rules for every translated category.
 * Examples: `/nicotine-pouches/` and `/en/nicotine-pouches-en/`.
 */
function fika_add_multilingual_product_category_rules( $rules ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) return $rules;

	$terms = fika_get_multilingual_product_categories();
	if ( empty( $terms ) ) {
		update_option( 'fika_category_route_conflicts', array(), false );
		return $rules;
	}

	$term_map = array();
	foreach ( $terms as $term ) {
		if ( is_object( $term ) && isset( $term->term_id ) ) $term_map[ (int) $term->term_id ] = $term;
	}

	if ( function_exists( 'fika_check_category_route_conflicts' ) ) fika_check_category_route_conflicts( $terms, $term_map );

	$default_language = function_exists( 'pll_default_language' ) ? (string) pll_default_language( 'slug' ) : '';
	$pagination_base  = 'page';
	$feed_base        = 'feed';
	$feeds            = array( 'feed', 'rss', 'rss2', 'atom' );
	global $wp_rewrite;
	if ( isset( $wp_rewrite->pagination_base ) && '' !== $wp_rewrite->pagination_base ) $pagination_base = $wp_rewrite->pagination_base;
	if ( isset( $wp_rewrite->feed_base ) && '' !== $wp_rewrite->feed_base ) $feed_base = $wp_rewrite->feed_base;
	if ( isset( $wp_rewrite->feeds ) && is_array( $wp_rewrite->feeds ) && ! empty( $wp_rewrite->feeds ) ) $feeds = $wp_rewrite->feeds;
	$feed_pattern = '(' . implode( '|', array_map( function ( $feed ) { return preg_quote( $feed, '#' ); }, $feeds ) ) . ')';

	$new_rules = array();
	foreach ( $terms as $term ) {
		if ( ! is_object( $term ) || empty( $term->slug ) ) continue;

		$language = function_exists( 'pll_get_term_language' ) ? (string) pll_get_term_language( (int) $term->term_id, 'slug' ) : '';
		$path     = fika_product_category_slug_path( $term, $term_map );
		if ( '' === $path ) continue;

		/* Fika intentionally keeps the default language at the domain root. */
		$route = ( '' !== $language && $language !== $default_language ? $language . '/' : '' ) . $path;
		$route = preg_quote( trim( $route, '/' ), '#' );
		$query = 'index.php?product_cat=' . rawurlencode( (string) $term->slug );
		if ( '' !== $language ) $query .= '&lang=' . rawurlencode( $language );

		$new_rules[ '^' . $route . '/' . preg_quote( $pagination_base, '#' ) . '/?([0-9]{1,})/?$' ] = $query . '&paged=$matches[1]';
		$new_rules[ '^' . $route . '/embed/?$' ] = $query . '&embed=true';
		$new_rules[ '^' . $route . '/' . preg_quote( $feed_base, '#' ) . '/' . $feed_pattern . '/?$' ] = $query . '&feed=$matches[1]';
		$new_rules[ '^' . $route . '/' . $feed_pattern . '/?$' ] = $query . '&feed=$matches[1]';
		$new_rules[ '^' . $route . '/?$' ] = $query;

	}

	return $new_rules + ( is_array( $rules ) ? $rules : array() );
}
add_filter( 'rewrite_rules_array', 'fika_add_multilingual_product_category_rules', 98 );

/** Prevent Rank Math from producing the malformed Polylang redirect target. */
function fika_disable_rank_math_product_category_redirect( $allow ) {
	return function_exists( 'is_product_category' ) && is_product_category() ? false : $allow;
}
add_filter( 'rank_math/woocommerce/product_redirection', 'fika_disable_rank_math_product_category_redirect', 999 );

/** True while Rank Math is building the WooCommerce product-category sitemap. */
function fika_is_product_category_sitemap_request() {
	$sitemap = function_exists( 'get_query_var' ) ? (string) get_query_var( 'sitemap' ) : '';
	if ( 'product_cat' === $sitemap ) return true;

	if ( isset( $GLOBALS['wp_query']->query['sitemap'] ) && 'product_cat' === (string) $GLOBALS['wp_query']->query['sitemap'] ) {
		return true;
	}

	/* Fallback for early term queries before public query vars are populated. */
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	return (bool) preg_match( '#/product_cat-sitemap(?:[0-9]+)?\.xml$#i', $path );
}

/**
 * Rank Math and Polylang can otherwise limit product_cat-sitemap.xml to the
 * current language. Explicitly request every configured language, only for
 * that taxonomy sitemap.
 */
function fika_include_all_languages_in_product_category_sitemap( $args, $taxonomies ) {
	if ( ! fika_is_product_category_sitemap_request() || ! function_exists( 'pll_languages_list' ) ) return $args;

	$taxonomies = (array) $taxonomies;
	if ( ! in_array( 'product_cat', $taxonomies, true ) ) return $args;

	$languages = pll_languages_list( array( 'fields' => 'slug' ) );
	if ( ! is_array( $languages ) ) return $args;
	$languages = array_values( array_unique( array_filter( array_map( 'sanitize_key', $languages ) ) ) );
	if ( empty( $languages ) ) return $args;

	$args['lang'] = implode( ',', $languages );
	return $args;
}
add_filter( 'get_terms_args', 'fika_include_all_languages_in_product_category_sitemap', 999, 2 );

/** Add pagination and the original query string to a category's short URL. */
function fika_product_category_redirect_target( $canonical, $current, $paged = 0 ) {
	if ( ! is_string( $canonical ) || '' === $canonical || ! is_string( $current ) || '' === $current ) return '';

	if ( (int) $paged > 1 ) {
		global $wp_rewrite;
		$pagination_base = isset( $wp_rewrite->pagination_base ) && '' !== $wp_rewrite->pagination_base ? $wp_rewrite->pagination_base : 'page';
		$canonical = trailingslashit( $canonical ) . user_trailingslashit( $pagination_base . '/' . (int) $paged );
	}

	$current_path   = (string) wp_parse_url( $current, PHP_URL_PATH );
	$canonical_path = (string) wp_parse_url( $canonical, PHP_URL_PATH );
	if ( untrailingslashit( rawurldecode( $current_path ) ) === untrailingslashit( rawurldecode( $canonical_path ) ) ) return '';

	$query  = wp_parse_url( $current, PHP_URL_QUERY );
	$target = $canonical . ( is_string( $query ) && '' !== $query ? '?' . $query : '' );

	// A legacy base URL with filters should resolve in one 301, not two hops.
	if ( function_exists( 'fika_normalize_faceted_url' ) ) {
		$target = fika_normalize_faceted_url( $target );
	}

	return $target;
}

/** Redirect native category-base URLs to one short canonical. */
function fika_redirect_product_category_to_short_url() {
	if ( is_admin() || ! function_exists( 'is_product_category' ) || ! is_product_category() || is_feed() || ( function_exists( 'is_embed' ) && is_embed() ) ) return;

	$term = get_queried_object();
	if ( ! is_object( $term ) || empty( $term->term_id ) ) return;
	$canonical = get_term_link( $term, 'product_cat' );
	if ( is_wp_error( $canonical ) ) return;
	$current = function_exists( 'fika_current_request_url' ) ? fika_current_request_url() : '';
	$paged   = function_exists( 'get_query_var' ) ? absint( get_query_var( 'paged' ) ) : 0;
	$target  = fika_product_category_redirect_target( $canonical, $current, $paged );
	if ( '' === $target ) return;

	wp_safe_redirect( $target, 301, 'Fika Product Category Canonicalizer' );
	exit;
}
add_action( 'template_redirect', 'fika_redirect_product_category_to_short_url', 0 );
