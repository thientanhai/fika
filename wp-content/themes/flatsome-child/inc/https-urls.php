<?php
/**
 * Normalize legacy first-party HTTP URLs in front-end HTML.
 *
 * This is deliberately limited to the site's own host and the historical XFN
 * profile link. Namespace identifiers such as http://www.w3.org/2000/svg are
 * not network resources and must remain unchanged.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Replace legacy first-party HTTP URLs without rewriting third-party links.
 *
 * @param string $html Complete front-end response body.
 * @return string
 */
function fika_normalize_frontend_https_urls( $html ) {
	if ( ! is_string( $html ) || '' === $html || false === stripos( $html, 'http' ) ) {
		return $html;
	}

	$home_url = home_url( '/' );
	$host     = strtolower( (string) wp_parse_url( $home_url, PHP_URL_HOST ) );
	if ( '' === $host ) return $html;

	$canonical_host = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	$search_hosts   = array_unique( array_filter( array( $canonical_host, 'www.' . $canonical_host ) ) );
	$https_origin   = 'https://' . $canonical_host;

	$search  = array( 'http://gmpg.org/xfn/11' );
	$replace = array( 'https://gmpg.org/xfn/11' );

	foreach ( $search_hosts as $search_host ) {
		$search[]  = 'http://' . $search_host;
		$replace[] = $https_origin;
		$search[]  = 'http:\\/\\/' . $search_host;
		$replace[] = str_replace( '/', '\\/', $https_origin );
	}

	return str_ireplace( $search, $replace, $html );
}

/** Start one lightweight final-response buffer on public HTML requests only. */
function fika_start_https_url_buffer() {
	if (
		is_admin()
		|| wp_doing_ajax()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| is_feed()
		|| is_trackback()
		|| is_robots()
	) {
		return;
	}

	static $started = false;
	if ( $started ) return;
	$started = true;

	ob_start( 'fika_normalize_frontend_https_urls' );
}
add_action( 'template_redirect', 'fika_start_https_url_buffer', -999 );
