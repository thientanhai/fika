<?php
/**
 * Editorial Single Post experience for Fika Pouches.
 *
 * This module uses hooks instead of overriding Flatsome templates. It keeps
 * parent-theme updates safe and limits all changes to singular blog posts.
 *
 * @package Flatsome_Child
 */

defined( 'ABSPATH' ) || exit;

/** Determine the current editorial language without requiring Polylang. */
function fika_single_post_is_english() {
	if ( function_exists( 'pll_current_language' ) ) {
		return 'en' === pll_current_language( 'slug' );
	}

	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	return is_string( $path ) && 0 === strpos( trailingslashit( $path ), '/en/' );
}

/** Return a translated interface label. */
function fika_single_post_label( $key ) {
	$labels = array(
		'vi' => array(
			'home'         => 'Trang chủ',
			'breadcrumb'   => 'Đường dẫn điều hướng',
			'article_info' => 'Thông tin bài viết',
			'toc'          => 'Mục lục bài viết',
			'minute'       => 'phút đọc',
			'updated'      => 'Cập nhật',
			'written_by'   => 'Biên soạn bởi',
			'author_intro' => 'Nội dung được đội ngũ Fika Pouches biên soạn nhằm trình bày thông tin rõ ràng, hữu ích và dễ kiểm chứng.',
			'related'      => 'Bài viết liên quan',
			'read_more'    => 'Đọc bài viết',
		),
		'en' => array(
			'home'         => 'Home',
			'breadcrumb'   => 'Breadcrumb',
			'article_info' => 'Article information',
			'toc'          => 'Table of contents',
			'minute'       => 'min read',
			'updated'      => 'Updated',
			'written_by'   => 'Written by',
			'author_intro' => 'This article is prepared by the Fika Pouches editorial team to present clear, useful and verifiable information.',
			'related'      => 'Related articles',
			'read_more'    => 'Read article',
		),
	);

	$lang = fika_single_post_is_english() ? 'en' : 'vi';
	return isset( $labels[ $lang ][ $key ] ) ? $labels[ $lang ][ $key ] : '';
}

/** True only for the main content of a public blog post. */
function fika_single_post_should_enhance() {
	if ( is_admin() || is_feed() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	return (int) get_queried_object_id() === (int) get_the_ID();
}

/** Add a scoped body class for frontend styling. */
function fika_single_post_body_class( $classes ) {
	if ( is_singular( 'post' ) ) {
		$classes[] = 'fika-editorial-post';
	}

	return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'fika_single_post_body_class' );

/** Load the editorial stylesheet only on blog posts. */
function fika_single_post_enqueue_assets() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$file = get_stylesheet_directory() . '/assets/css/single-post.css';
	if ( ! is_readable( $file ) ) {
		return;
	}

	wp_enqueue_style(
		'fika-single-post',
		get_stylesheet_directory_uri() . '/assets/css/single-post.css',
		array(),
		(string) filemtime( $file )
	);
}
add_action( 'wp_enqueue_scripts', 'fika_single_post_enqueue_assets', 30 );

/** Identify the normal WordPress post editor screen. */
function fika_single_post_is_editor_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && 'post' === $screen->base && 'post' === $screen->post_type;
}

/** Make the Classic Editor canvas resemble the public article. */
function fika_single_post_editor_css( $stylesheets ) {
	if ( ! fika_single_post_is_editor_screen() ) {
		return $stylesheets;
	}

	$file = get_stylesheet_directory() . '/assets/css/editor-content.css';
	if ( ! is_readable( $file ) ) {
		return $stylesheets;
	}

	$url = add_query_arg( 'ver', (string) filemtime( $file ), get_stylesheet_directory_uri() . '/assets/css/editor-content.css' );
	return $stylesheets ? $stylesheets . ',' . $url : $url;
}
add_filter( 'mce_css', 'fika_single_post_editor_css' );

/** Add a Styles dropdown to the second Classic Editor toolbar. */
function fika_single_post_editor_buttons( $buttons ) {
	if ( fika_single_post_is_editor_screen() && ! in_array( 'styleselect', $buttons, true ) ) {
		array_unshift( $buttons, 'styleselect' );
	}

	return $buttons;
}
add_filter( 'mce_buttons_2', 'fika_single_post_editor_buttons' );

/** Add reusable editorial callout formats without replacing existing formats. */
function fika_single_post_tinymce_settings( $settings ) {
	if ( ! fika_single_post_is_editor_screen() ) {
		return $settings;
	}

	$formats = array(
		array( 'title' => 'Fika – Lưu ý / Note', 'block' => 'div', 'classes' => 'fika-note', 'wrapper' => true ),
		array( 'title' => 'Fika – Mẹo / Tip', 'block' => 'div', 'classes' => 'fika-tip', 'wrapper' => true ),
		array( 'title' => 'Fika – Cảnh báo / Warning', 'block' => 'div', 'classes' => 'fika-warning', 'wrapper' => true ),
		array( 'title' => 'Fika – Tóm tắt / Summary', 'block' => 'div', 'classes' => 'fika-summary', 'wrapper' => true ),
	);

	$settings['style_formats_merge'] = true;
	$settings['style_formats']       = wp_json_encode( $formats );
	$settings['body_class']          = isset( $settings['body_class'] )
		? trim( $settings['body_class'] . ' fika-editor-content' )
		: 'fika-editor-content';

	return $settings;
}
add_filter( 'tiny_mce_before_init', 'fika_single_post_tinymce_settings' );

/** Count readable words in Vietnamese and English text. */
function fika_single_post_word_count( $content ) {
	$text = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $content ) ), ENT_QUOTES, 'UTF-8' );
	if ( ! preg_match_all( '/[\p{L}\p{N}]+/u', $text, $matches ) ) {
		return 0;
	}

	return count( $matches[0] );
}

/** Calculate a conservative reading time. */
function fika_single_post_reading_time( $content ) {
	$words  = fika_single_post_word_count( $content );
	$speed  = fika_single_post_is_english() ? 220 : 200;
	$minute = max( 1, (int) ceil( $words / $speed ) );

	return $minute . ' ' . fika_single_post_label( 'minute' );
}

/**
 * Return Rank Math's Primary Category when it is valid for the post, then
 * fall back to WordPress's first assigned category.
 */
function fika_get_primary_post_category( $post_id = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : (int) get_the_ID();
	if ( ! $post_id ) return null;

	$primary_id = absint( get_post_meta( $post_id, 'rank_math_primary_category', true ) );
	if ( $primary_id && has_term( $primary_id, 'category', $post_id ) ) {
		$primary = get_term( $primary_id, 'category' );
		if ( $primary instanceof WP_Term ) return $primary;
	}

	$categories = get_the_category( $post_id );
	return ! empty( $categories ) && $categories[0] instanceof WP_Term ? $categories[0] : null;
}

/** Build an accessible breadcrumb trail without adding duplicate schema. */
function fika_single_post_breadcrumbs() {
	if ( ! apply_filters( 'fika_single_post_show_breadcrumbs', true ) ) {
		return '';
	}

	$home_url = home_url( '/' );
	if ( function_exists( 'pll_home_url' ) && function_exists( 'pll_current_language' ) ) {
		$language = pll_current_language( 'slug' );
		if ( $language ) {
			$home_url = pll_home_url( $language );
		}
	}

	$items   = array();
	$items[] = '<a href="' . esc_url( $home_url ) . '">' . esc_html( fika_single_post_label( 'home' ) ) . '</a>';

	$category = fika_get_primary_post_category( get_the_ID() );
	if ( $category instanceof WP_Term ) {
		$link     = get_category_link( $category );
		if ( ! is_wp_error( $link ) ) {
			$items[] = '<a href="' . esc_url( $link ) . '">' . esc_html( $category->name ) . '</a>';
		}
	}

	$items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';

	return '<nav class="fika-post-breadcrumbs" aria-label="' . esc_attr( fika_single_post_label( 'breadcrumb' ) ) . '">' . implode( '<span class="fika-breadcrumb-separator" aria-hidden="true">/</span>', $items ) . '</nav>';
}

/** Render reading time and a meaningful modified date. */
function fika_single_post_utility( $content ) {
	$items   = array();
	$items[] = '<span class="fika-reading-time">' . esc_html( fika_single_post_reading_time( $content ) ) . '</span>';

	$published = (int) get_the_time( 'U' );
	$modified  = (int) get_the_modified_time( 'U' );
	if ( $modified > ( $published + DAY_IN_SECONDS ) ) {
		$items[] = '<span class="fika-updated-date">' . esc_html( fika_single_post_label( 'updated' ) ) . ': <time datetime="' . esc_attr( get_the_modified_date( DATE_W3C ) ) . '">' . esc_html( get_the_modified_date() ) . '</time></span>';
	}

	return '<div class="fika-post-utility" aria-label="' . esc_attr( fika_single_post_label( 'article_info' ) ) . '">' . implode( '<span class="fika-utility-dot" aria-hidden="true"></span>', $items ) . '</div>';
}

/** Add stable IDs to H2/H3 headings and generate an automatic TOC. */
function fika_single_post_add_toc( $content ) {
	if ( ! apply_filters( 'fika_single_post_enable_toc', true ) || preg_match( '/<[a-z][^>]*\bclass=(["\'])[^"\']*\bfika-toc\b[^"\']*\1/i', $content ) ) {
		return $content;
	}

	$headings = array();
	$used_ids = array();
	$pattern  = '/<h([23])([^>]*)>(.*?)<\/h\1>/is';

	$updated = preg_replace_callback(
		$pattern,
		function ( $matches ) use ( &$headings, &$used_ids ) {
			$level = (int) $matches[1];
			$attrs = $matches[2];
			$inner = $matches[3];

			if ( preg_match( '/\bno-toc\b/i', $attrs ) ) {
				return $matches[0];
			}

			$title = trim( wp_strip_all_tags( $inner ) );
			if ( '' === $title ) {
				return $matches[0];
			}

			$id = '';
			if ( preg_match( '/\sid=(["\'])(.*?)\1/i', $attrs, $id_match ) ) {
				$id = html_entity_decode( $id_match[2], ENT_QUOTES, 'UTF-8' );
				$id = preg_replace( '/\s+/u', '-', trim( $id ) );
			}
			if ( '' === $id ) {
				$id = sanitize_title( $title );
			}
			if ( '' === $id ) {
				$id = 'section-' . ( count( $headings ) + 1 );
			}

			$base   = $id;
			$suffix = 2;
			while ( isset( $used_ids[ $id ] ) ) {
				$id = $base . '-' . $suffix;
				++$suffix;
			}
			$used_ids[ $id ] = true;

			if ( preg_match( '/\sid=(["\'])(.*?)\1/i', $attrs ) ) {
				$attrs = preg_replace( '/\sid=(["\'])(.*?)\1/i', ' id="' . esc_attr( $id ) . '"', $attrs, 1 );
			} else {
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}

			$headings[] = array( 'level' => $level, 'id' => $id, 'title' => $title );
			return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
		},
		$content
	);

	if ( ! is_string( $updated ) || count( $headings ) < 3 ) {
		return $content;
	}

	$links = '';
	foreach ( $headings as $heading ) {
		$links .= '<li class="fika-toc-level-' . (int) $heading['level'] . '"><a href="#' . esc_attr( $heading['id'] ) . '">' . esc_html( $heading['title'] ) . '</a></li>';
	}

	$toc = '<details class="fika-toc" open><summary>' . esc_html( fika_single_post_label( 'toc' ) ) . '</summary><ol>' . $links . '</ol></details>';
	return preg_replace_callback(
		'/<h[23]\b/i',
		function ( $matches ) use ( $toc ) {
			return $toc . $matches[0];
		},
		$updated,
		1
	);
}

/** Render a local author card without external avatar requests. */
function fika_single_post_author_box() {
	if ( ! apply_filters( 'fika_single_post_show_author_box', true ) ) {
		return '';
	}

	$author_id = (int) get_the_author_meta( 'ID' );
	$name      = trim( (string) get_the_author_meta( 'display_name', $author_id ) );
	$bio       = trim( (string) get_the_author_meta( 'description', $author_id ) );
	$name      = $name ? $name : get_bloginfo( 'name' );
	$bio       = $bio ? $bio : fika_single_post_label( 'author_intro' );
	$initial   = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1, 'UTF-8' ) : substr( $name, 0, 1 );

	return '<aside class="fika-author-box" aria-label="' . esc_attr( fika_single_post_label( 'written_by' ) ) . '"><span class="fika-author-mark" aria-hidden="true">' . esc_html( $initial ) . '</span><div><p class="fika-author-eyebrow">' . esc_html( fika_single_post_label( 'written_by' ) ) . '</p><p class="fika-author-name">' . esc_html( $name ) . '</p><p class="fika-author-bio">' . esc_html( $bio ) . '</p></div></aside>';
}

/** Return the current Polylang slug for language-safe post queries. */
function fika_single_post_query_language() {
	return function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : '';
}

/** Build three same-language related article cards. */
function fika_single_post_related() {
	if ( ! apply_filters( 'fika_single_post_show_related', true ) ) {
		return '';
	}

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'post__not_in'        => array( get_the_ID() ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
	);

	$categories = wp_get_post_categories( get_the_ID() );
	if ( $categories ) {
		$args['category__in'] = array_map( 'intval', $categories );
	}

	$language = fika_single_post_query_language();
	if ( $language ) {
		$args['lang'] = $language;
	}

	$query = new WP_Query( $args );
	if ( ! $query->have_posts() ) {
		return '';
	}

	$cards = '';
	foreach ( $query->posts as $related_post ) {
		$post_id   = (int) $related_post->ID;
		$permalink = get_permalink( $post_id );
		$image     = get_the_post_thumbnail(
			$post_id,
			'medium_large',
			array( 'class' => 'fika-related-image', 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' )
		);
		if ( ! $image ) {
			$image = '<span class="fika-related-placeholder" aria-hidden="true"></span>';
		}

		$cards .= '<article class="fika-related-card"><a class="fika-related-card-link" href="' . esc_url( $permalink ) . '"><span class="fika-related-media">' . $image . '</span><div class="fika-related-body"><time datetime="' . esc_attr( get_the_date( DATE_W3C, $post_id ) ) . '">' . esc_html( get_the_date( '', $post_id ) ) . '</time><h3>' . esc_html( get_the_title( $post_id ) ) . '</h3><span class="fika-related-link">' . esc_html( fika_single_post_label( 'read_more' ) ) . '<span aria-hidden="true"> →</span></span></div></a></article>';
	}

	return '<section class="fika-related-posts" aria-labelledby="fika-related-heading"><h2 id="fika-related-heading">' . esc_html( fika_single_post_label( 'related' ) ) . '</h2><div class="fika-related-grid">' . $cards . '</div></section>';
}

/** Assemble the complete editorial enhancement around the original content. */
function fika_single_post_enhance_content( $content ) {
	static $running = false;

	if ( $running || ! fika_single_post_should_enhance() ) {
		return $content;
	}

	$running = true;
	$body    = fika_single_post_add_toc( $content );
	$before  = fika_single_post_breadcrumbs() . fika_single_post_utility( $content );
	$after   = fika_single_post_author_box() . fika_single_post_related();
	$running = false;

	return $before . $body . $after;
}
add_filter( 'the_content', 'fika_single_post_enhance_content', 30 );
