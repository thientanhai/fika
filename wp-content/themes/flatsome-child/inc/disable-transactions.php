<?php
/**
 * Disable WooCommerce storefront transactions without deleting store data.
 * Set FIKA_DISABLE_TRANSACTIONS to false in wp-config.php to roll back.
 * No replacement purchasing or contact flow is introduced here.
 */
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'FIKA_DISABLE_TRANSACTIONS' ) ) {
	define( 'FIKA_DISABLE_TRANSACTIONS', true );
}

function fika_transactions_disabled() {
	return FIKA_DISABLE_TRANSACTIONS && class_exists( 'WooCommerce' );
}

function fika_transactions_message() {
	$locale = function_exists( 'pll_current_language' ) ? pll_current_language( 'locale' ) : '';
	$locale = $locale ? $locale : get_locale();
	return 0 === strpos( (string) $locale, 'vi' )
		? 'Chức năng giao dịch trực tuyến hiện đã được vô hiệu hóa.'
		: 'Online transactions are currently disabled.';
}

/** Only explicit commerce operations, never generic login or admin actions. */
function fika_transactions_ajax_actions() {
	return array( 'add_to_cart', 'remove_from_cart', 'get_refreshed_fragments',
		'apply_coupon', 'remove_coupon', 'update_shipping_method', 'get_cart_totals',
		'update_order_review', 'checkout' );
}

function fika_transactions_is_request( $request ) {
	$keys = array( 'add-to-cart', 'ux-buy-now', 'order_again', 'cancel_order',
		'update_cart', 'apply_coupon', 'remove_coupon', 'remove_item', 'undo_item',
		'woocommerce_checkout_place_order', 'woocommerce-process-checkout-nonce',
		'woocommerce_pay', 'woocommerce-login-nonce', 'woocommerce-register-nonce',
		'woocommerce-lost-password-nonce', 'woocommerce-reset-password-nonce',
		'woocommerce-edit-address-nonce', 'save-account-details-nonce' );
	foreach ( $keys as $key ) {
		if ( array_key_exists( $key, $request ) ) return true;
	}
	return isset( $request['wc-ajax'] ) && is_string( $request['wc-ajax'] )
		&& in_array( $request['wc-ajax'], fika_transactions_ajax_actions(), true );
}

function fika_transactions_reject() {
	nocache_headers();
	if ( ! headers_sent() ) header( 'X-Robots-Tag: noindex, nofollow', true );
	$message = fika_transactions_message();
	if ( wp_doing_ajax() || isset( $_GET['wc-ajax'] ) || isset( $_POST['wc-ajax'] ) ) {
		wp_send_json_error( array( 'code' => 'fika_transactions_disabled', 'message' => $message ), 403 );
	}
	wp_die( esc_html( $message ), esc_html( $message ), array( 'response' => 403 ) );
}

/** Runs before WC's wp_loaded form and persistent-cart handlers. */
function fika_transactions_guard_request() {
	if ( is_admin() && ! wp_doing_ajax() ) return;
	if ( fika_transactions_is_request( array_merge( $_GET, $_POST ) ) ) fika_transactions_reject();
}

/** Block public Store API transactions, including nested checkout and batches. */
function fika_transactions_rest_guard( $result, $server, $request ) {
	$route = rawurldecode( $request->get_route() );
	if ( preg_match( '#^/wc/store(?:/(?:v[0-9]+|__experimental))?/(?:cart|checkout|order|batch)(?:/|$)#i', $route ) ) {
		return new WP_Error( 'fika_transactions_disabled', fika_transactions_message(), array( 'status' => 403 ) );
	}
	// Keep products, Rank Math, Polylang, WP users/me and authenticated admin APIs.
	return $result;
}

/** Prevent checkout even if a plugin calls WC_Checkout directly. */
function fika_transactions_checkout_validation( $data, $errors ) {
	$errors->add( 'fika_transactions_disabled', fika_transactions_message() );
}

/** Only remove WooCommerce frontend handlers; never WP login/password handlers. */
function fika_transactions_remove_handlers() {
	$handlers = array(
		'wp_loaded' => array( 20, array( 'checkout_action', 'process_login', 'process_registration',
			'process_lost_password', 'process_reset_password', 'cancel_order', 'update_cart_action', 'add_to_cart_action' ) ),
		'wp' => array( 20, array( 'pay_action', 'add_payment_method_action', 'delete_payment_method_action', 'set_default_payment_method_action' ) ),
		'template_redirect' => array( 10, array( 'redirect_reset_password_link', 'resend_set_password', 'save_address', 'save_account_details' ) ),
	);
	foreach ( $handlers as $hook => $settings ) {
		foreach ( $settings[1] as $method ) remove_action( $hook, array( 'WC_Form_Handler', $method ), $settings[0] );
	}
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
	remove_action( 'flatsome_single_product_lightbox_summary', 'woocommerce_template_single_add_to_cart', 30 );
	remove_action( 'flatsome_product_box_actions', 'flatsome_product_box_actions_add_to_cart', 1 );
	remove_action( 'flatsome_product_box_after', 'flatsome_woocommerce_shop_loop_button', 100 );
	remove_action( 'wp_footer', 'flatsome_sticky_add_to_cart_template', 10 );
	remove_action( 'wp_footer', 'flatsome_account_login_lightbox', 10 );
}

function fika_transactions_shortcode( $output, $tag, $attr, $match ) {
	$blocked = array( 'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account',
		'woocommerce_order_tracking', 'add_to_cart', 'add_to_cart_url', 'ux_product_add_to_cart' );
	return in_array( $tag, $blocked, true ) ? '' : $output;
}

function fika_transactions_block( $output, $block ) {
	$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	if ( preg_match( '#^woocommerce/(?:cart(?:-|$)|checkout(?:-|$)|mini-cart(?:-|$)|customer-account$|product-button$|(?:product-)?add-to-cart(?:-|$)|order-confirmation(?:-|$))#', $name ) ) return '';
	return $output;
}

/** Invalidate request-local lookups after configuration, translations or permalinks change. */
function fika_transactions_reset_request_cache() {
	$GLOBALS['fika_transactions_lookup_cache'] = array();
}
add_action( 'clean_post_cache', 'fika_transactions_reset_request_cache' );

/** All actual configured page IDs, including both Polylang translations. */
function fika_transactions_page_ids() {
	$raw = array();
	foreach ( array( 'cart', 'checkout', 'myaccount' ) as $slug ) $raw[] = (int) get_option( 'woocommerce_' . $slug . '_page_id', 0 );
	$language = function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : '';
	$key = 'ids:' . $language . ':' . implode( ',', $raw );
	if ( isset( $GLOBALS['fika_transactions_lookup_cache'][ $key ] ) ) return $GLOBALS['fika_transactions_lookup_cache'][ $key ];
	$ids = array();
	foreach ( $raw as $id ) {
		if ( $id <= 0 ) continue;
		$ids[] = $id;
		if ( function_exists( 'pll_get_post_translations' ) ) {
			$translations = pll_get_post_translations( $id );
			if ( is_array( $translations ) ) $ids = array_merge( $ids, array_values( $translations ) );
		}
	}
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	$GLOBALS['fika_transactions_lookup_cache'][ $key ] = $ids;
	return $ids;
}

/** Cache endpoint paths per language and configured page set, for this request only. */
function fika_transactions_endpoint_paths( $ids ) {
	$language = function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : '';
	$key = 'paths:' . $language . ':' . home_url( '/' ) . ':' . implode( ',', $ids );
	if ( isset( $GLOBALS['fika_transactions_lookup_cache'][ $key ] ) ) return $GLOBALS['fika_transactions_lookup_cache'][ $key ];
	$paths = array();
	foreach ( $ids as $id ) {
		$target = wp_parse_url( get_permalink( $id ) );
		if ( ! is_array( $target ) || ! empty( $target['query'] ) ) continue;
		$path = untrailingslashit( isset( $target['path'] ) ? $target['path'] : '' );
		if ( '' !== $path ) $paths[] = $path;
	}
	$GLOBALS['fika_transactions_lookup_cache'][ $key ] = array_values( array_unique( $paths ) );
	return $GLOBALS['fika_transactions_lookup_cache'][ $key ];
}

/**
 * Recognize conservative storefront route aliases used by manual or legacy
 * menu links. This supplements configured WooCommerce page IDs; it never
 * matches wp-login.php or authenticated WordPress administration URLs.
 */
function fika_transactions_is_disabled_route_alias( $path ) {
	$path = trim( rawurldecode( (string) $path ), '/' );
	if ( '' === $path ) return false;

	$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
	if ( empty( $segments ) ) return false;

	$last = sanitize_title( (string) end( $segments ) );
	$slugs = (array) apply_filters(
		'fika_transactions_disabled_route_slugs',
		array( 'cart', 'gio-hang', 'checkout', 'thanh-toan', 'my-account', 'tai-khoan' )
	);
	$slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', $slugs ) ) ) );

	return in_array( $last, $slugs, true );
}

/** Match configured local destinations, not guessed /cart/ or language slugs. */
function fika_transactions_is_url( $url ) {
	if ( ! is_string( $url ) || '' === $url || '#' === $url[0] ) return false;
	$parts = wp_parse_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
	if ( ! is_array( $parts ) ) return false;
	if ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) return false;
	$local_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	if ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( (string) $local_host ) ) return false;
	$query = array();
	if ( ! empty( $parts['query'] ) ) parse_str( $parts['query'], $query );
	if ( isset( $query['add-to-cart'] ) || isset( $query['ux-buy-now'] ) ) return true;
	$ids = fika_transactions_page_ids();
	if ( isset( $query['page_id'] ) && is_scalar( $query['page_id'] ) && in_array( absint( $query['page_id'] ), $ids, true ) ) return true;
	$path = untrailingslashit( isset( $parts['path'] ) ? $parts['path'] : '' );
	if ( fika_transactions_is_disabled_route_alias( $path ) ) return true;
	foreach ( fika_transactions_endpoint_paths( $ids ) as $target_path ) {
		if ( $path === $target_path || 0 === strpos( $path, $target_path . '/' ) ) return true;
	}
	return false;
}

function fika_transactions_menu_items( $items ) {
	$ids = fika_transactions_page_ids();
	$removed = array();
	foreach ( $items as $key => $item ) {
		if ( ( 'page' === $item->object && in_array( (int) $item->object_id, $ids, true ) ) || fika_transactions_is_url( $item->url ) ) {
			$removed[] = (int) $item->ID;
			unset( $items[ $key ] );
		}
	}
	// Keep unrelated child links usable by promoting them out of deleted parents.
	foreach ( $items as $item ) {
		if ( in_array( (int) $item->menu_item_parent, $removed, true ) ) $item->menu_item_parent = 0;
	}
	return array_values( $items );
}

function fika_transactions_navigation_link( $content, $block ) {
	$url = isset( $block['attrs']['url'] ) ? $block['attrs']['url'] : '';
	return fika_transactions_is_url( $url ) ? '' : $content;
}

/** Final render guard for classic menus, including Flatsome mobile/off-canvas. */
function fika_transactions_menu_start_el( $item_output, $menu_item, $depth, $args ) {
	if ( ! is_object( $menu_item ) || empty( $menu_item->url ) ) return $item_output;
	return fika_transactions_is_url( $menu_item->url ) ? '' : $item_output;
}

/** Return a genuine 404 for disabled pages instead of redirecting them to home. */
function fika_transactions_disable_pages() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;
	$ids = fika_transactions_page_ids();
	if ( is_cart() || is_checkout() || is_account_page() || ( ! empty( $ids ) && is_page( $ids ) ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		if ( ! headers_sent() ) header( 'X-Robots-Tag: noindex, follow', true );
		// Avoid canonical guessing or WC's empty-cart redirect on a disabled URL.
		remove_action( 'template_redirect', 'redirect_canonical' );
		remove_action( 'template_redirect', 'wc_template_redirect' );
	}
}

function fika_transactions_sitemap_entry( $entry, $type, $object ) {
	if ( is_object( $object ) && isset( $object->ID ) && in_array( (int) $object->ID, fika_transactions_page_ids(), true ) ) return false;
	return $entry;
}

function fika_transactions_sitemap_query( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$args['post__not_in'] = array_unique( array_merge( isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(), fika_transactions_page_ids() ) );
	}
	return $args;
}

function fika_transactions_body_class( $classes ) {
	$classes[] = 'fika-transactions-disabled';
	return $classes;
}

function fika_transactions_assets() {
	wp_enqueue_style( 'fika-disable-transactions', get_stylesheet_directory_uri() . '/assets/css/disable-transactions.css', array(), FIKA_CHILD_THEME_VERSION );
	fika_transactions_dequeue_scripts();
}

function fika_transactions_dequeue_scripts() {
	foreach ( array( 'wc-cart-fragments', 'wc-add-to-cart', 'wc-cart', 'wc-checkout', 'wc-add-payment-method', 'wc-credit-card-form' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
}

function fika_transactions_widget( $instance, $widget ) {
	return $widget instanceof WC_Widget_Cart ? false : $instance;
}

function fika_transactions_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'themes' ), true ) ) return;
	$message = 0 === strpos( get_locale(), 'vi' )
		? 'Fika ' . FIKA_CHILD_THEME_VERSION . ': Đã bật chế độ tắt giao dịch WooCommerce. Hãy xóa cache và kiểm tra cả VN/EN. Đăng nhập quản trị vẫn dùng wp-login.php. Xem HUONG-DAN-3.10.5.txt trong child theme để kiểm tra và hoàn tác.'
		: 'Fika ' . FIKA_CHILD_THEME_VERSION . ': WooCommerce storefront transactions are disabled. Purge caches and check both languages. Admin login remains available at wp-login.php. See HUONG-DAN-3.10.5.txt in the child theme for checks and rollback.';
	echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
}

function fika_transactions_boot() {
	if ( ! fika_transactions_disabled() ) return;
	$last = PHP_INT_MAX;
	add_filter( 'woocommerce_is_purchasable', '__return_false', $last );
	add_filter( 'woocommerce_variation_is_purchasable', '__return_false', $last );
	add_filter( 'woocommerce_add_to_cart_validation', '__return_false', $last );
	add_filter( 'woocommerce_loop_add_to_cart_link', '__return_empty_string', $last );
	add_filter( 'woocommerce_add_to_cart_fragments', '__return_empty_array', $last );
	add_filter( 'flatsome_show_buy_now_button', '__return_false', $last );
	add_filter( 'flatsome_sticky_add_to_cart_enabled', '__return_false', $last );
	add_filter( 'theme_mod_product_buy_now', '__return_false', $last );
	add_filter( 'theme_mod_product_sticky_cart', '__return_false', $last );
	add_filter( 'woocommerce_enable_order_notes_field', '__return_false', $last );
	add_filter( 'woocommerce_checkout_registration_enabled', '__return_false', $last );
	add_filter( 'option_woocommerce_enable_myaccount_registration', 'fika_transactions_option_no', $last );
	add_filter( 'woocommerce_account_menu_items', '__return_empty_array', $last );
	add_action( 'woocommerce_after_checkout_validation', 'fika_transactions_checkout_validation', $last, 2 );
	add_action( 'wp_loaded', 'fika_transactions_guard_request', -100 );
	add_filter( 'rest_pre_dispatch', 'fika_transactions_rest_guard', -100, 3 );
	foreach ( fika_transactions_ajax_actions() as $action ) {
		foreach ( array( 'wc_ajax_', 'wp_ajax_woocommerce_', 'wp_ajax_nopriv_woocommerce_' ) as $prefix ) {
			add_action( $prefix . $action, 'fika_transactions_reject', -100 );
		}
	}
	fika_transactions_remove_handlers();
	add_action( 'init', 'fika_transactions_remove_handlers', $last );
	add_filter( 'pre_do_shortcode_tag', 'fika_transactions_shortcode', $last, 4 );
	add_filter( 'pre_render_block', 'fika_transactions_block', $last, 2 );
	add_filter( 'render_block_core/navigation-link', 'fika_transactions_navigation_link', $last, 2 );
	add_filter( 'wp_nav_menu_objects', 'fika_transactions_menu_items', $last );
	add_filter( 'walker_nav_menu_start_el', 'fika_transactions_menu_start_el', $last, 4 );
	add_action( 'template_redirect', 'fika_transactions_disable_pages', -100 );
	add_filter( 'rank_math/sitemap/entry', 'fika_transactions_sitemap_entry', $last, 3 );
	add_filter( 'wp_sitemaps_posts_query_args', 'fika_transactions_sitemap_query', $last, 2 );
	add_filter( 'body_class', 'fika_transactions_body_class' );
	add_filter( 'widget_display_callback', 'fika_transactions_widget', $last, 2 );
	add_action( 'wp_enqueue_scripts', 'fika_transactions_assets', $last );
	add_action( 'wp_print_footer_scripts', 'fika_transactions_dequeue_scripts', 0 );
	add_action( 'admin_notices', 'fika_transactions_admin_notice' );
}

function fika_transactions_option_no() { return 'no'; }
add_action( 'after_setup_theme', 'fika_transactions_boot', 100 );
