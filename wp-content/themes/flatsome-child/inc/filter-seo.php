<?php
/** Faceted navigation controls for WooCommerce product archives. */
defined( 'ABSPATH' ) || exit;

/**
 * Query keys that create a faceted/sorted product listing rather than a
 * standalone SEO landing page.
 */
function fika_is_faceted_query_key( $key ) {
	$key = sanitize_key( (string) $key );
	if ( 'filtering' === $key || 0 === strpos( $key, 'filter_' ) || 0 === strpos( $key, 'query_type_' ) ) {
		return true;
	}
	return in_array(
		$key,
		array( 'min_price', 'max_price', 'orderby', 'rating_filter', 'min_rating', 'stock_status' ),
		true
	);
}

/** True only for front-end requests that contain a supported facet key. */
function fika_is_faceted_filter_request() {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || empty( $_GET ) ) return false;
	foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing signal.
		if ( fika_is_faceted_query_key( $key ) ) return true;
	}
	return false;
}

/** Limit SEO/cache changes to WooCommerce product archives. */
function fika_is_faceted_product_archive_request() {
	return fika_is_faceted_filter_request()
		&& function_exists( 'fika_is_product_archive_request' )
		&& ( ! empty( $GLOBALS['fika_faceted_error_context'] ) || fika_is_product_archive_request() );
}

/** Parse, deduplicate and numerically sort a comma-separated Brand ID list. */
function fika_normalize_brand_id_string( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) return array( 'valid' => true, 'ids' => array(), 'value' => '' );
	if ( ! preg_match( '/^\d+(?:,\d+)*$/D', $raw ) ) {
		return array( 'valid' => false, 'ids' => array(), 'value' => '' );
	}
	$ids = array_map( 'absint', explode( ',', $raw ) );
	$ids = array_values( array_unique( array_filter( $ids ) ) );
	sort( $ids, SORT_NUMERIC );
	return array( 'valid' => ! empty( $ids ), 'ids' => $ids, 'value' => implode( ',', $ids ) );
}

/** Normalize only local filter-only query strings; leave unrelated/signed URLs intact. */
function fika_normalize_faceted_url( $url, $brand_override = null ) {
	if ( ! is_string( $url ) || '' === $url ) return $url;
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['query'] ) ) return $url;
	if ( ! empty( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) return $url;
	if ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) return $url;
	$values = array();
	foreach ( explode( '&', $parts['query'] ) as $pair ) {
		$pair = explode( '=', $pair, 2 );
		$key = urldecode( $pair[0] );
		// Do not reinterpret arrays, duplicate keys, tracking or signed query parameters.
		if ( $key !== sanitize_key( $key ) || ! fika_is_faceted_query_key( $key ) || array_key_exists( $key, $values ) ) return $url;
		$values[ $key ] = isset( $pair[1] ) ? urldecode( $pair[1] ) : '';
	}
	if ( null !== $brand_override ) $values['filter_product_brand'] = $brand_override;
	if ( isset( $values['filter_product_brand'] ) ) {
		$normalized = fika_normalize_brand_id_string( $values['filter_product_brand'] );
		if ( ! $normalized['valid'] ) return $url;
		if ( '' === $normalized['value'] ) unset( $values['filter_product_brand'] );
		else $values['filter_product_brand'] = $normalized['value'];
	}
	if ( array( 'filtering' ) === array_keys( $values ) ) $values = array();
	// Keep filtering first and the Brand selector last for compatibility with existing links.
	uksort( $values, function ( $a, $b ) {
		$rank = array( 'filtering' => 0, 'filter_product_brand' => 2 );
		$cmp = ( $rank[ $a ] ?? 1 ) <=> ( $rank[ $b ] ?? 1 );
		return $cmp ?: strcmp( $a, $b );
	} );
	$query = http_build_query( $values, '', '&', PHP_QUERY_RFC3986 );
	$fragment = strpos( $url, '#' );
	$tail = false === $fragment ? '' : substr( $url, $fragment );
	$head = false === $fragment ? $url : substr( $url, 0, $fragment );
	$head = substr( $head, 0, strpos( $head, '?' ) );
	return $head . ( '' !== $query ? '?' . $query : '' ) . $tail;
}

function fika_normalize_layered_nav_link( $url ) {
	return fika_normalize_faceted_url( $url );
}
add_filter( 'woocommerce_layered_nav_link', 'fika_normalize_layered_nav_link', 100 );

/** Build the current absolute request URL without trusting a supplied Host header. */
function fika_current_request_url() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( '' === $request_uri || '/' !== $request_uri[0] ) return '';
	$home = wp_parse_url( home_url( '/' ) );
	if ( ! is_array( $home ) || empty( $home['scheme'] ) || empty( $home['host'] ) ) return '';
	$origin = $home['scheme'] . '://' . $home['host'];
	if ( isset( $home['port'] ) ) $origin .= ':' . (int) $home['port'];
	return $origin . $request_uri;
}

/** Legacy helper retained for callers; normalization never changes unrelated parameters. */
function fika_build_brand_filter_target_url( $current_url, $normalized_value ) {
	return fika_normalize_faceted_url( $current_url, $normalized_value );
}

/** Validate filter shapes and Brand IDs, then normalize safe read-only requests. */
function fika_normalize_faceted_request() {
	if ( ! fika_is_faceted_product_archive_request() ) return;
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) return;
	foreach ( $_GET as $key => $value ) {
		if ( fika_is_faceted_query_key( $key ) && ! is_scalar( $value ) ) {
			$GLOBALS['fika_invalid_faceted_request'] = true;
			return;
		}
	}
	if ( isset( $_GET['filter_product_brand'] ) ) {
		$normalized = fika_normalize_brand_id_string( wp_unslash( $_GET['filter_product_brand'] ) );
		if ( ! $normalized['valid'] ) {
			$GLOBALS['fika_invalid_faceted_request'] = true;
			return;
		}
		if ( taxonomy_exists( 'product_brand' ) ) {
			foreach ( $normalized['ids'] as $term_id ) {
				if ( ! term_exists( $term_id, 'product_brand' ) ) {
					$GLOBALS['fika_invalid_faceted_request'] = true;
					return;
				}
			}
		}
	}
	$current = fika_current_request_url();
	$target = fika_normalize_faceted_url( $current );
	if ( '' !== $current && $target !== $current ) {
		wp_safe_redirect( $target, 301, 'Fika Filter Normalizer' );
		exit;
	}
}
add_action( 'template_redirect', 'fika_normalize_faceted_request', 1 );

/** Do not let a combinatorial query space fill LiteSpeed or page-cache storage. */
function fika_disable_faceted_page_cache() {
	if ( ! fika_is_faceted_product_archive_request() ) return;
	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	do_action( 'litespeed_control_set_nocache', 'Fika faceted product archive' );
}
add_action( 'template_redirect', 'fika_disable_faceted_page_cache', 2 );

/** Return an actual 404 response for invalid or empty filter combinations. */
function fika_status_for_faceted_request() {
	if ( ! fika_is_faceted_product_archive_request() ) return;
	global $wp_query;
	$invalid = ! empty( $GLOBALS['fika_invalid_faceted_request'] );
	$empty   = isset( $wp_query->found_posts ) && 0 === (int) $wp_query->found_posts;
	if ( ! $invalid && ! $empty ) return;
	// Preserve filter context after set_404() resets the archive conditionals.
	$GLOBALS['fika_faceted_error_context'] = true;
	$wp_query->set_404();
	$wp_query->posts = array();
	$wp_query->post = null;
	$wp_query->post_count = 0;
	$wp_query->found_posts = 0;
	$wp_query->max_num_pages = 0;
	$wp_query->queried_object = null;
	$wp_query->queried_object_id = 0;
	remove_action( 'template_redirect', 'redirect_canonical' );
	remove_action( 'template_redirect', 'wc_template_redirect' );
	status_header( 404 );
	nocache_headers();
	if ( ! headers_sent() ) header( 'X-Robots-Tag: noindex, follow', true );
}
add_action( 'template_redirect', 'fika_status_for_faceted_request', 3 );

/** WordPress robots fallback when Rank Math is unavailable. */
function fika_faceted_wp_robots( $robots ) {
	if ( ! fika_is_faceted_product_archive_request() || defined( 'RANK_MATH_VERSION' ) ) return $robots;
	unset( $robots['index'], $robots['nofollow'] );
	$robots['noindex'] = true;
	$robots['follow']  = true;
	return $robots;
}
add_filter( 'wp_robots', 'fika_faceted_wp_robots', 999 );

/** Rank Math robots output for all supported product filters. */
function fika_faceted_rank_math_robots( $robots ) {
	if ( ! fika_is_faceted_product_archive_request() || ! is_array( $robots ) ) return $robots;
	$robots['index']  = 'noindex';
	$robots['follow'] = 'follow';
	return $robots;
}
add_filter( 'rank_math/frontend/robots', 'fika_faceted_rank_math_robots', 999 );

/** A noindex faceted state is not a canonical or structured-data landing page. */
function fika_remove_faceted_canonical( $canonical ) {
	return fika_is_faceted_product_archive_request() ? false : $canonical;
}
add_filter( 'rank_math/frontend/canonical', 'fika_remove_faceted_canonical', 999 );

function fika_remove_faceted_schema( $data ) {
	return fika_is_faceted_product_archive_request() ? array() : $data;
}
add_filter( 'rank_math/json_ld', 'fika_remove_faceted_schema', 999 );

/** Polylang expects an array from this filter. */
function fika_remove_faceted_hreflang( $hreflangs ) {
	return fika_is_faceted_product_archive_request() ? array() : $hreflangs;
}
add_filter( 'pll_rel_hreflang_attributes', 'fika_remove_faceted_hreflang', 999 );

/** Test a URL for filter keys without restricting it to the current request. */
function fika_url_is_faceted( $url ) {
	$url = html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' );
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) return false;
	if ( ! empty( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) return false;
	if ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) return false;
	$query = isset( $parts['query'] ) ? $parts['query'] : '';
	if ( '' === $query ) return false;
	parse_str( $query, $args );
	foreach ( array_keys( $args ) as $key ) {
		if ( fika_is_faceted_query_key( $key ) ) return true;
	}
	return false;
}

/** Add one rel token without losing existing security or relationship tokens. */
function fika_add_rel_token( $rel, $token ) {
	$tokens = preg_split( '/\s+/', strtolower( trim( (string) $rel ) ) );
	$tokens = array_values( array_filter( array_unique( array_merge( (array) $tokens, array( $token ) ) ) ) );
	return implode( ' ', $tokens );
}

/** Add nofollow to server-rendered faceted links in the Brand widget output. */
function fika_nofollow_faceted_links( $html ) {
	if ( ! is_string( $html ) || '' === $html ) return $html;
	if ( false === stripos( $html, '<a' ) || false === strpos( $html, '?' ) ) return $html;

	// The same UX Block can pass through shortcode and content filters. Reuse it.
	static $cache = array();
	$key = md5( $html );
	if ( isset( $cache[ $key ] ) ) return $cache[ $key ];

	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( 'a' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( is_string( $href ) && fika_url_is_faceted( $href ) ) {
				$processor->set_attribute( 'rel', fika_add_rel_token( $processor->get_attribute( 'rel' ), 'nofollow' ) );
			}
		}
		$updated = $processor->get_updated_html();
		if ( count( $cache ) < 50 ) $cache[ $key ] = $updated;
		return $updated;
	}
	$updated = preg_replace_callback(
		'/<a\b[^>]*>/i',
		function ( $match ) {
			$tag = $match[0];
			if ( ! preg_match( '/\bhref=(\"|\')(.*?)\1/i', $tag, $href ) || ! fika_url_is_faceted( $href[2] ) ) return $tag;
			if ( preg_match( '/\brel=(\"|\')(.*?)\1/i', $tag, $rel ) ) {
				$new_rel = 'rel=' . $rel[1] . esc_attr( fika_add_rel_token( $rel[2], 'nofollow' ) ) . $rel[1];
				return preg_replace( '/\brel=(\"|\')(.*?)\1/i', $new_rel, $tag, 1 );
			}
			return substr( $tag, 0, -1 ) . ' rel="nofollow">';
		},
		$html
	);
	$updated = is_string( $updated ) ? $updated : $html;
	if ( count( $cache ) < 50 ) $cache[ $key ] = $updated;
	return $updated;
}

/** Detect the Brand layered-nav widget without depending on load order. */
function fika_is_brand_nav_widget( $widget ) {
	if ( ! is_object( $widget ) ) return false;
	if ( isset( $widget->id_base ) && 'woocommerce_brand_nav' === $widget->id_base ) return true;
	return 'WC_Widget_Brand_Nav' === get_class( $widget );
}

/**
 * Stop the Brand widget from creating impossible Brand-on-Brand URLs or
 * combinatorial Brand selections after the first Brand has been selected.
 */
function fika_control_brand_nav_widget_display( $instance, $widget, $args ) {
	if ( ! fika_is_brand_nav_widget( $widget ) ) return $instance;
	if ( is_tax( 'product_brand' ) ) return false;
	if ( fika_is_faceted_product_archive_request() && isset( $_GET['filter_product_brand'] ) ) return false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return $instance;
}
add_filter( 'widget_display_callback', 'fika_control_brand_nav_widget_display', 100, 3 );

/** Limit output buffering to the WooCommerce product-filter sidebar. */
function fika_is_product_filter_sidebar( $index ) {
	$index = sanitize_key( (string) $index );
	$match = in_array( $index, array( 'shop-sidebar', 'sidebar-shop', 'product-sidebar', 'sidebar-product' ), true );
	return (bool) apply_filters( 'fika_is_product_filter_sidebar', $match, $index );
}

/** Process our own buffer only; never close a buffer owned by another callback. */
function fika_start_product_sidebar_buffer( $index, $has_widgets ) {
	if ( ! $has_widgets || ! fika_is_product_archive_request() || ! fika_is_product_filter_sidebar( $index ) ) return;
	$state = (object) array( 'level' => ob_get_level() + 1, 'active' => true, 'closed' => false );
	$GLOBALS['fika_sidebar_buffers'][] = $state;
	ob_start( function ( $html, $phase ) use ( $state ) {
		if ( $phase & PHP_OUTPUT_HANDLER_FINAL ) $state->closed = true;
		if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) return '';
		return $state->active ? fika_nofollow_faceted_links( $html ) : $html;
	} );
}
add_action( 'dynamic_sidebar_before', 'fika_start_product_sidebar_buffer', 1, 2 );

function fika_end_product_sidebar_buffer( $index, $has_widgets ) {
	if ( ! $has_widgets || ! fika_is_product_filter_sidebar( $index ) ) return;
	if ( empty( $GLOBALS['fika_sidebar_buffers'] ) ) return;
	$state = array_pop( $GLOBALS['fika_sidebar_buffers'] );
	if ( ! $state->closed && ob_get_level() === $state->level ) {
		ob_end_flush();
	} else {
		// Another component owns the top buffer (or has already closed ours).
		// Leave it untouched and pass through any later output without rewriting it.
		$state->active = false;
	}
}
add_action( 'dynamic_sidebar_after', 'fika_end_product_sidebar_buffer', 999, 2 );

/** Cover rendered archive content/UX blocks in addition to widget sidebars. */
function fika_filter_archive_content_links( $html ) {
	if ( ! fika_is_product_archive_request() || ! is_string( $html ) || false === strpos( $html, '?' ) ) return $html;
	return fika_nofollow_faceted_links( $html );
}
add_filter( 'the_content', 'fika_filter_archive_content_links', 99 );
add_filter( 'fika_brand_bottom_html', 'fika_filter_archive_content_links', 99 );

function fika_filter_archive_shortcode_links( $output, $tag ) {
	return in_array( $tag, array( 'block', 'ux_block', 'ux_html' ), true ) ? fika_filter_archive_content_links( $output ) : $output;
}
add_filter( 'do_shortcode_tag', 'fika_filter_archive_shortcode_links', 99, 2 );

function fika_filter_archive_menu_link( $atts ) {
	if ( fika_is_product_archive_request() && ! empty( $atts['href'] ) && fika_url_is_faceted( $atts['href'] ) ) {
		$atts['rel'] = fika_add_rel_token( $atts['rel'] ?? '', 'nofollow' );
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'fika_filter_archive_menu_link', 99 );

/** Pagination remains usable, but it must not expand the faceted crawl graph. */
function fika_nofollow_faceted_pagination( $html, $args ) {
	if ( ! fika_is_faceted_product_archive_request() ) return $html;
	return fika_nofollow_faceted_links( $html );
}
add_filter( 'paginate_links_output', 'fika_nofollow_faceted_pagination', 100, 2 );

/** Optional final-stage crawl block for WordPress virtual robots.txt. */
function fika_filter_crawl_block_enabled() {
	return defined( 'FIKA_BLOCK_FILTER_CRAWL' ) && FIKA_BLOCK_FILTER_CRAWL;
}

function fika_faceted_robots_txt( $output, $public ) {
	if ( ! fika_filter_crawl_block_enabled() ) return $output;
	$rules = array(
		'Disallow: /*?*filtering=',
		'Disallow: /*?*filter_',
		'Disallow: /*?*query_type_',
		'Disallow: /*?*min_price=',
		'Disallow: /*?*max_price=',
		'Disallow: /*?*orderby=',
		'Disallow: /*?*rating_filter=',
		'Disallow: /*?*min_rating=',
		'Disallow: /*?*stock_status=',
	);
	$missing = array();
	foreach ( $rules as $rule ) {
		if ( false === strpos( $output, $rule ) ) $missing[] = $rule;
	}
	if ( empty( $missing ) ) return $output;
	return rtrim( (string) $output ) . "\n\n# Fika faceted navigation\nUser-agent: *\n" . implode( "\n", $missing ) . "\n";
}
add_filter( 'robots_txt', 'fika_faceted_robots_txt', 100, 2 );
