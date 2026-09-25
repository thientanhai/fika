<?php
/** Storefront shortcodes, with legacy aliases preserved. */
defined( 'ABSPATH' ) || exit;

function fika_brand_list_shortcode( $atts, $content = null, $tag = 'brand_list' ) {
	$atts = shortcode_atts( array(
		'taxonomy' => 'product_brand', 'hide_empty' => false,
		'include' => '', 'exclude' => '', 'loading' => 'auto',
	), $atts, $tag );
	$taxonomy = sanitize_key( $atts['taxonomy'] );
	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) return '';
	$args = array( 'taxonomy' => $taxonomy, 'hide_empty' => filter_var( $atts['hide_empty'], FILTER_VALIDATE_BOOLEAN ) );
	if ( '' !== $atts['include'] ) $args['include'] = wp_parse_id_list( $atts['include'] );
	if ( '' !== $atts['exclude'] ) $args['exclude'] = wp_parse_id_list( $atts['exclude'] );
	$terms = get_terms( $args );
	if ( empty( $terms ) || is_wp_error( $terms ) ) return '<p>' . esc_html__( 'Không có thương hiệu.', 'flatsome-child' ) . '</p>';
	$html = '<div class="brand-list row">';
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) continue;
		$term_link = get_term_link( $term );
		if ( is_wp_error( $term_link ) ) continue;
		$image_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
		$attrs = array( 'class' => 'fika-brand-image', 'alt' => $term->name, 'decoding' => 'async' );
		// 'auto' omits the attribute and lets WordPress select the loading policy.
		if ( in_array( $atts['loading'], array( 'lazy', 'eager' ), true ) ) $attrs['loading'] = $atts['loading'];
		$image_html = '';
		if ( $image_id ) $image_html = wp_get_attachment_image( $image_id, 'medium', false, $attrs );
		if ( ! $image_html && function_exists( 'wc_placeholder_img' ) ) $image_html = wc_placeholder_img( 'medium', $attrs );
		$html .= '<div class="brand-item col large-2 medium-3 small-6"><a href="' . esc_url( $term_link ) . '">';
		$html .= '<div class="brand_img">' . wp_kses_post( $image_html ) . '</div>';
		$html .= '<div class="brand_text text-center">' . esc_html( $term->name ) . '</div></a></div>';
	}
	return $html . '</div>';
}

function fika_language_switcher_shortcode() {
	if ( ! function_exists( 'pll_the_languages' ) ) return '';
	$languages = pll_the_languages( array( 'raw' => 1, 'hide_current' => 0, 'hide_if_no_translation' => 1 ) );
	if ( empty( $languages ) || ! is_array( $languages ) ) return '';
	$paginated_urls = function_exists( 'fika_get_paginated_taxonomy_hreflangs' ) ? fika_get_paginated_taxonomy_hreflangs() : array();
	$links           = '';
	foreach ( $languages as $language ) {
		if ( empty( $language['url'] ) || empty( $language['slug'] ) || ! empty( $language['no_translation'] ) ) continue;
		$slug = sanitize_key( $language['slug'] );
		if ( '' === $slug ) continue;
		if ( isset( $paginated_urls[ $slug ] ) ) $language['url'] = $paginated_urls[ $slug ];
		$current = ! empty( $language['current_lang'] );
		// A URL slug is not necessarily a valid language code.
		$code = isset( $language['locale'] ) && is_string( $language['locale'] ) ? str_replace( '_', '-', $language['locale'] ) : '';
		if ( '' === $code && in_array( $slug, array( 'en', 'vi' ), true ) ) $code = $slug;
		$code = (string) apply_filters( 'fika_language_html_code', $code, $language );
		$lang_attrs = preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i', $code ) ? ' lang="' . esc_attr( $code ) . '" hreflang="' . esc_attr( $code ) . '"' : '';
		$links .= '<a href="' . esc_url( $language['url'] ) . '" class="' . ( $current ? 'active' : '' ) . '"' . $lang_attrs;
		$links .= $current ? ' aria-current="page"' : '';
		$links .= '>' . esc_html( strtoupper( $slug ) ) . '</a>';
	}
	if ( '' === $links ) return '';
	return '<nav class="fika-lang" aria-label="' . esc_attr__( 'Language switcher', 'flatsome-child' ) . '">' . $links . '</nav>';
}

function fika_register_child_shortcodes() {
	add_shortcode( 'fika_brand_list', 'fika_brand_list_shortcode' );
	if ( ! shortcode_exists( 'brand_list' ) ) add_shortcode( 'brand_list', 'fika_brand_list_shortcode' );
	add_shortcode( 'fika_lang', 'fika_language_switcher_shortcode' );
	add_shortcode( 'fika_first_page', 'fika_first_page_shortcode' );
}
add_action( 'init', 'fika_register_child_shortcodes', 20 );
