<?php
/** Deferred routing/cache maintenance. No per-visit rewrite flushes. */
defined( 'ABSPATH' ) || exit;

// Separate routing schema from presentation/theme releases.
define( 'FIKA_CATEGORY_REWRITE_VERSION', '3' );

function fika_mark_category_rules_dirty() {
	// Persist the token so an interrupted save is recovered on a later request.
	update_option( 'fika_category_rules_dirty', uniqid( '', true ), false );
}

function fika_queue_sitemap_refresh( $types ) {
	$pending = (array) get_option( 'fika_sitemap_refresh_pending', array() );
	update_option( 'fika_sitemap_refresh_pending', array_values( array_unique( array_merge( $pending, (array) $types ) ) ), false );
}

function fika_category_terms_changed( $ids = array(), $taxonomy = '' ) {
	// Category count/cache updates are frequent; structural category changes use the explicit hooks below.
	if ( in_array( $taxonomy, array( 'language', 'term_language', 'term_translations' ), true ) ) {
		fika_mark_category_rules_dirty();
		fika_queue_sitemap_refresh( array( 'product_cat' ) );
	}
}
add_action( 'clean_term_cache', 'fika_category_terms_changed', 20, 2 );
add_action( 'created_product_cat', 'fika_mark_category_rules_dirty', 99 );
add_action( 'edited_product_cat', 'fika_mark_category_rules_dirty', 99 );
add_action( 'delete_product_cat', 'fika_mark_category_rules_dirty', 99 );
add_action( 'after_switch_theme', 'fika_mark_category_rules_dirty' );

function fika_maintenance_option_changed( $option ) {
	if ( in_array( $option, array( 'polylang', 'woocommerce_permalinks', 'permalink_structure', 'home', 'siteurl' ), true ) ) {
		fika_mark_category_rules_dirty();
		fika_queue_sitemap_refresh( array( 'page', 'product_cat' ) );
	}
	if ( in_array( $option, array( 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'polylang' ), true ) ) {
		fika_transactions_reset_request_cache();
		fika_queue_sitemap_refresh( array( 'page' ) );
	}
}
add_action( 'updated_option', 'fika_maintenance_option_changed', 20 );
add_action( 'added_option', 'fika_maintenance_option_changed', 20 );
add_action( 'deleted_option', 'fika_maintenance_option_changed', 20 );

/** Detect term-language reassignment even when performed through the Polylang API. */
function fika_maintenance_term_relationships( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( in_array( $taxonomy, array( 'term_language', 'term_translations' ), true ) ) {
		fika_mark_category_rules_dirty();
		fika_queue_sitemap_refresh( array( 'product_cat' ) );
	}
	if ( in_array( $taxonomy, array( 'language', 'post_translations' ), true ) ) {
		fika_transactions_reset_request_cache();
		fika_queue_sitemap_refresh( array( 'page' ) );
		if ( 'page' === get_post_type( $object_id ) ) fika_mark_category_rules_dirty();
	}
}
add_action( 'set_object_terms', 'fika_maintenance_term_relationships', 20, 4 );

/** Page path changes can create or resolve a category/Page collision. */
function fika_maintenance_page_saved( $post_id, $post, $update, $before ) {
	if ( ! is_object( $post ) || 'page' !== $post->post_type ) return;
	fika_transactions_reset_request_cache();
	$changed = ! $update || ! is_object( $before );
	foreach ( array( 'post_name', 'post_parent', 'post_status' ) as $key ) {
		if ( is_object( $before ) && $post->$key !== $before->$key ) $changed = true;
	}
	if ( $changed ) {
		fika_mark_category_rules_dirty();
		fika_queue_sitemap_refresh( array( 'page' ) );
	}
}
add_action( 'wp_after_insert_post', 'fika_maintenance_page_saved', 20, 4 );

function fika_maintenance_deleted_post( $post_id, $post ) {
	if ( is_object( $post ) && 'page' === $post->post_type ) {
		fika_transactions_reset_request_cache();
		fika_mark_category_rules_dirty();
		fika_queue_sitemap_refresh( array( 'page' ) );
	}
}
add_action( 'deleted_post', 'fika_maintenance_deleted_post', 20, 2 );

/** Compare persisted configuration at load and shutdown (including rollback to enabled commerce). */
function fika_check_maintenance_state() {
	if ( ! taxonomy_exists( 'product_cat' ) ) return;
	if ( FIKA_CATEGORY_REWRITE_VERSION !== get_option( 'fika_category_rewrite_schema' ) && ! get_option( 'fika_category_rules_dirty' ) ) {
		fika_mark_category_rules_dirty();
	}
	$ids = fika_transactions_page_ids();
	sort( $ids, SORT_NUMERIC );
	$fingerprint = md5( serialize( array( 'schema' => 1, 'disabled' => fika_transactions_disabled(), 'ids' => $ids ) ) );
	if ( $fingerprint !== get_option( 'fika_transaction_sitemap_state' ) ) {
		fika_queue_sitemap_refresh( array( 'page' ) );
		update_option( 'fika_transaction_sitemap_state', $fingerprint, false );
	}
}
add_action( 'wp_loaded', 'fika_check_maintenance_state', 50 );

/** Acquire a short database-backed lock so concurrent requests cannot all flush. */
function fika_acquire_maintenance_lock() {
	$now  = time();
	$lock = (int) get_option( 'fika_maintenance_lock', 0 );
	if ( $lock && ( $now - $lock ) < 120 ) return false;
	if ( $lock ) delete_option( 'fika_maintenance_lock' );
	return add_option( 'fika_maintenance_lock', $now, '', false );
}

function fika_release_maintenance_lock() {
	delete_option( 'fika_maintenance_lock' );
}

/** Flush at most once per shutdown, after all term/language saves have finished. */
function fika_run_deferred_maintenance() {
	if ( ! did_action( 'wp_loaded' ) || ! taxonomy_exists( 'product_cat' ) ) return;
	$token = get_option( 'fika_category_rules_dirty' );
	$pending = (array) get_option( 'fika_sitemap_refresh_pending', array() );
	if ( ! $token && empty( $pending ) ) return;
	if ( ! fika_acquire_maintenance_lock() ) return;

	try {
		if ( $token ) {
			flush_rewrite_rules( false );
			update_option( 'fika_category_rewrite_schema', FIKA_CATEGORY_REWRITE_VERSION, false );
			// Do not erase a newer change made by another request during this flush.
			if ( $token === get_option( 'fika_category_rules_dirty' ) ) delete_option( 'fika_category_rules_dirty' );
			fika_queue_sitemap_refresh( array( 'product_cat' ) );
			$pending = (array) get_option( 'fika_sitemap_refresh_pending', array() );
		}

		if ( ! empty( $pending ) ) {
			if ( ! is_callable( array( '\\RankMath\\Sitemap\\Cache_Watcher', 'clear' ) ) ) {
				update_option( 'fika_sitemap_refresh_error', 'api_unavailable', false );
				delete_option( 'fika_sitemap_refresh_pending' );
				return;
			}

			\RankMath\Sitemap\Cache_Watcher::clear( $pending );
			if ( $pending === (array) get_option( 'fika_sitemap_refresh_pending', array() ) ) delete_option( 'fika_sitemap_refresh_pending' );
			delete_option( 'fika_sitemap_refresh_error' );
		}
	} catch ( \Throwable $error ) {
		// Plugin API incompatibility must not cause a fatal error on a public page.
		update_option( 'fika_sitemap_refresh_error', 'refresh_failed', false );
		delete_option( 'fika_sitemap_refresh_pending' );
	} finally {
		fika_release_maintenance_lock();
	}
}
add_action( 'shutdown', 'fika_run_deferred_maintenance', 999 );

/** Convert a local permalink into the route used by the base-free rules. */
function fika_category_conflict_route_from_url( $url ) {
	if ( ! is_string( $url ) || '' === $url || wp_parse_url( $url, PHP_URL_QUERY ) ) return '';

	$path      = trim( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ), '/' );
	$home_path = trim( rawurldecode( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ) ), '/' );
	if ( '' !== $home_path ) {
		if ( $path === $home_path ) return '';
		if ( 0 === strpos( $path, $home_path . '/' ) ) $path = substr( $path, strlen( $home_path ) + 1 );
	}

	return trim( $path, '/' );
}

/** Add one collision without duplicating the same diagnostic row. */
function fika_add_category_route_conflict( &$conflicts, $routes, $path, $other ) {
	if ( '' === $path || ! isset( $routes[ $path ] ) ) return;
	$row = array( 'route' => $path, 'category_id' => (int) $routes[ $path ], 'other' => (string) $other );
	$key = md5( wp_json_encode( $row ) );
	$conflicts[ $key ] = $row;
}

/** Store diagnostics only. Existing slugs/URLs are never silently reassigned. */
function fika_check_category_route_conflicts( $terms, $term_map ) {
	$default = function_exists( 'pll_default_language' ) ? (string) pll_default_language( 'slug' ) : '';
	$routes = array();
	$conflicts = array();
	foreach ( $terms as $term ) {
		$language = function_exists( 'pll_get_term_language' ) ? (string) pll_get_term_language( $term->term_id, 'slug' ) : '';
		$path = fika_product_category_slug_path( $term, $term_map );
		if ( '' === $path ) continue;
		$route = trim( ( '' !== $language && $language !== $default ? $language . '/' : '' ) . $path, '/' );
		$route = rawurldecode( $route );
		if ( isset( $routes[ $route ] ) ) {
			$row = array( 'route' => $route, 'category_id' => (int) $term->term_id, 'other' => 'product_cat #' . $routes[ $route ] );
			$conflicts[ md5( wp_json_encode( $row ) ) ] = $row;
		}
		$routes[ $route ] = (int) $term->term_id;
	}

	// Compare against every public content permalink, not only Pages.
	$post_types = get_post_types( array( 'public' => true ), 'names' );
	$post_types = array_values( array_diff( (array) $post_types, array( 'attachment' ) ) );
	if ( $post_types ) {
		$query = new WP_Query( array(
			'post_type'              => $post_types,
			'post_status'            => array( 'publish', 'private' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		foreach ( $query->posts as $post_id ) {
			$path = fika_category_conflict_route_from_url( get_permalink( $post_id ) );
			fika_add_category_route_conflict( $conflicts, $routes, $path, get_post_type( $post_id ) . ' #' . (int) $post_id );
		}
	}

	// Compare against public taxonomy archives such as Brand, Category and Tag.
	$taxonomy_objects = get_taxonomies( array( 'public' => true ), 'objects' );
	$skip_taxonomies  = array( 'product_cat', 'post_format', 'language', 'term_language', 'term_translations' );
	foreach ( (array) $taxonomy_objects as $taxonomy => $object ) {
		if ( in_array( $taxonomy, $skip_taxonomies, true ) ) continue;
		$args = array( 'taxonomy' => $taxonomy, 'hide_empty' => false );
		if ( function_exists( 'pll_get_term_language' ) ) $args['lang'] = '';
		$other_terms = get_terms( $args );
		if ( is_wp_error( $other_terms ) || ! is_array( $other_terms ) ) continue;
		foreach ( $other_terms as $other_term ) {
			$url = get_term_link( $other_term, $taxonomy );
			if ( is_wp_error( $url ) ) continue;
			$path = fika_category_conflict_route_from_url( $url );
			fika_add_category_route_conflict( $conflicts, $routes, $path, $taxonomy . ' #' . (int) $other_term->term_id );
		}
	}

	// Protect core routes that do not necessarily belong to a stored object.
	$reserved = array( 'wp-admin', 'wp-login.php', 'wp-json', 'wp-cron.php', 'xmlrpc.php', 'feed', 'comments', 'search', 'author', 'category', 'tag', 'sitemap_index.xml', 'wp-sitemap.xml' );
	foreach ( array_keys( $routes ) as $route ) {
		foreach ( $reserved as $path ) {
			if ( $route === $path || 0 === strpos( $route, $path . '/' ) ) {
				fika_add_category_route_conflict( $conflicts, $routes, $route, 'reserved route /' . $path . '/' );
			}
		}
	}

	update_option( 'fika_category_route_conflicts', array_values( $conflicts ), false );
}

function fika_maintenance_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$conflicts = (array) get_option( 'fika_category_route_conflicts', array() );
	if ( $conflicts ) {
		echo '<div class="notice notice-warning"><p><strong>Fika: Phát hiện đường dẫn trùng / URL collisions detected.</strong> Cần kiểm tra và chọn slug riêng; theme chưa tự đổi URL.</p><ul>';
		foreach ( array_slice( $conflicts, 0, 20 ) as $conflict ) {
			echo '<li>' . esc_html( '/' . $conflict['route'] . '/ — product_cat #' . $conflict['category_id'] . ' / ' . $conflict['other'] ) . '</li>';
		}
		echo '</ul></div>';
	}
	if ( get_option( 'fika_sitemap_refresh_error' ) ) {
		echo '<div class="notice notice-warning"><p>Fika: Chưa xóa được cache sitemap Rank Math. Hãy lưu lại Sitemap Settings, kiểm tra sitemap và phiên bản plugin. / Rank Math sitemap cache refresh failed; review the plugin and regenerate the sitemap.</p></div>';
	}
	if ( function_exists( 'fika_filter_crawl_block_enabled' ) && fika_filter_crawl_block_enabled() ) {
		echo '<div class="notice notice-warning"><p>Fika SEO: FIKA_BLOCK_FILTER_CRAWL đang bật. Robots.txt có thể ngăn công cụ tìm kiếm đọc thẻ noindex của URL bộ lọc. Chỉ duy trì thiết lập này sau khi các URL filter cũ đã được loại khỏi chỉ mục.</p></div>';
	}
}
add_action( 'admin_notices', 'fika_maintenance_admin_notice' );
