<?php
/**
 * Homepage editorial cards for Fika Pouches.
 *
 * @package Flatsome_Child
 */

defined( 'ABSPATH' ) || exit;

/** Load the homepage Blog stylesheet only where the section can appear. */
function fika_home_blog_enqueue_assets() {
	if ( ! is_front_page() ) {
		return;
	}

	$file = get_stylesheet_directory() . '/assets/css/home-blog.css';
	if ( ! is_readable( $file ) ) {
		return;
	}

	wp_enqueue_style(
		'fika-home-blog',
		get_stylesheet_directory_uri() . '/assets/css/home-blog.css',
		array(),
		(string) filemtime( $file )
	);
}
add_action( 'wp_enqueue_scripts', 'fika_home_blog_enqueue_assets', 30 );

/**
 * Correct Flatsome's H5 card titles to H3 inside the dedicated homepage grid.
 * The section heading remains H2, producing a logical H1 > H2 > H3 outline.
 */
function fika_home_blog_heading_hierarchy( $output, $tag, $attr ) {
	if ( ! is_front_page() || 'blog_posts' !== $tag || ! is_array( $attr ) || empty( $attr['class'] ) ) {
		return $output;
	}

	$classes = preg_split( '/\s+/', trim( (string) $attr['class'] ) );
	if ( ! in_array( 'fika-home-blog-grid', $classes, true ) ) {
		return $output;
	}

	$updated = preg_replace(
		'/<h5([^>]*\bclass=(?:"[^"]*\bpost-title\b[^"]*"|\'[^\']*\bpost-title\b[^\']*\')[^>]*)>(.*?)<\/h5>/is',
		'<h3$1>$2</h3>',
		$output
	);

	return is_string( $updated ) ? $updated : $output;
}
add_filter( 'do_shortcode_tag', 'fika_home_blog_heading_hierarchy', 20, 3 );
