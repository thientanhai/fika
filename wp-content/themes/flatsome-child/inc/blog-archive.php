<?php
/**
 * Editorial archive presentation for Fika Pouches.
 *
 * The module enhances only WordPress post archives and search results. Product,
 * product-category and brand archives remain under WooCommerce/Flatsome control.
 *
 * @package Flatsome_Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Determine whether a search belongs to the editorial Post archive.
 *
 * WooCommerce product searches also satisfy is_search(). They must remain
 * under WooCommerce/Flatsome control instead of inheriting the Blog layout,
 * CSS and excerpt filters.
 */
function fika_blog_archive_is_editorial_search() {
	if ( ! is_search() ) return false;

	$post_type = get_query_var( 'post_type' );
	if ( empty( $post_type ) ) return true;

	$post_types = array_values( array_filter( array_map( 'sanitize_key', (array) $post_type ) ) );
	return ! empty( $post_types ) && array( 'post' ) === $post_types;
}

/** Determine whether the current request is an editorial listing. */
function fika_blog_archive_is_context() {
	return is_home() || is_category() || is_tag() || is_date() || is_author() || fika_blog_archive_is_editorial_search();
}

/** Determine the archive language without making Polylang mandatory. */
function fika_blog_archive_is_english() {
	if ( function_exists( 'pll_current_language' ) ) {
		return 'en' === pll_current_language( 'slug' );
	}

	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	return is_string( $path ) && 0 === strpos( trailingslashit( $path ), '/en/' );
}

/** Return a short translated interface label. */
function fika_blog_archive_label( $key ) {
	$labels = array(
		'vi' => array(
			'blog'        => 'Tin tức',
			'home'        => 'Trang chủ',
			'breadcrumb'  => 'Đường dẫn điều hướng',
			'eyebrow'     => 'Kiến thức & hướng dẫn',
			'search'      => 'Kết quả tìm kiếm cho',
			'read'        => 'Đọc bài viết',
			'previous'    => 'Trang trước',
			'next'        => 'Trang sau',
			'pagination'  => 'Điều hướng trang bài viết',
			'empty_title' => 'Chưa có bài viết',
			'empty_text'  => 'Hiện chưa có nội dung phù hợp. Hãy thử từ khóa khác hoặc quay lại sau.',
		),
		'en' => array(
			'blog'        => 'News',
			'home'        => 'Home',
			'breadcrumb'  => 'Breadcrumb',
			'eyebrow'     => 'Insights & guides',
			'search'      => 'Search results for',
			'read'        => 'Read article',
			'previous'    => 'Previous page',
			'next'        => 'Next page',
			'pagination'  => 'Article pagination',
			'empty_title' => 'No articles found',
			'empty_text'  => 'No matching content is available yet. Try another search or check back later.',
		),
	);

	$lang = fika_blog_archive_is_english() ? 'en' : 'vi';
	return isset( $labels[ $lang ][ $key ] ) ? $labels[ $lang ][ $key ] : '';
}

/** Return the single native title used by the custom archive template. */
function fika_blog_archive_title() {
	if ( is_search() ) {
		return fika_blog_archive_label( 'search' ) . ': ' . get_search_query( false );
	}

	if ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page ) {
			$title = get_the_title( $posts_page );
			if ( $title ) {
				return $title;
			}
		}

		return fika_blog_archive_label( 'blog' );
	}

	$title = wp_strip_all_tags( get_the_archive_title() );
	return $title ? $title : fika_blog_archive_label( 'blog' );
}

/** Return the language-specific homepage URL for the visible breadcrumb. */
function fika_blog_archive_home_url() {
	if ( function_exists( 'pll_home_url' ) && function_exists( 'pll_current_language' ) ) {
		$language = pll_current_language( 'slug' );
		if ( $language ) {
			return pll_home_url( $language );
		}
	}

	return home_url( '/' );
}

/** Prevent an editor-supplied archive description from creating a second H1. */
function fika_blog_archive_safe_description( $description ) {
	$description = preg_replace( '/<(\/?)h1\b([^>]*)>/i', '<${1}h2${2}>', (string) $description );
	return is_string( $description ) ? $description : '';
}

/** Add narrowly scoped classes used by the archive stylesheet. */
function fika_blog_archive_body_class( $classes ) {
	if ( fika_blog_archive_is_context() ) {
		$classes[] = 'fika-blog-archive';

		if ( is_search() ) {
			$classes[] = 'fika-blog-search';
		}
	}

	return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'fika_blog_archive_body_class' );

/** Load no JavaScript and enqueue archive CSS only where it is used. */
function fika_blog_archive_enqueue_assets() {
	if ( ! fika_blog_archive_is_context() ) {
		return;
	}

	$file = get_stylesheet_directory() . '/assets/css/blog-archive.css';
	if ( ! is_readable( $file ) ) {
		return;
	}

	wp_enqueue_style(
		'fika-blog-archive',
		get_stylesheet_directory_uri() . '/assets/css/blog-archive.css',
		array(),
		(string) filemtime( $file )
	);
}
add_action( 'wp_enqueue_scripts', 'fika_blog_archive_enqueue_assets', 30 );

/**
 * Remove visible prefixes such as "Category:" while retaining the real term
 * name as the native archive heading. This never creates an additional H1.
 */
function fika_blog_archive_title_prefix( $prefix ) {
	return fika_blog_archive_is_context() ? '' : $prefix;
}
add_filter( 'get_the_archive_title_prefix', 'fika_blog_archive_title_prefix' );

/** Keep long taxonomy introductions on page one only. */
function fika_blog_archive_description( $description ) {
	if ( fika_blog_archive_is_context() && is_paged() ) {
		return '';
	}

	return $description;
}
add_filter( 'get_the_archive_description', 'fika_blog_archive_description' );
