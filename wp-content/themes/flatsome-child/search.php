<?php
/** Search-results template. @package Flatsome_Child */
defined( 'ABSPATH' ) || exit;

// Product and custom post-type searches belong to their owning templates.
if ( ! function_exists( 'fika_blog_archive_is_context' ) || ! fika_blog_archive_is_context() ) {
	$fika_parent_search = get_template_directory() . '/search.php';
	if ( is_readable( $fika_parent_search ) ) {
		require $fika_parent_search;
		return;
	}
}

get_header();
get_template_part( 'template-parts/fika-blog-archive' );
get_footer();
