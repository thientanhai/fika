<?php
/** Suppress mini-cart HTML even when requested directly by a theme component. */
defined( 'ABSPATH' ) || exit;
if ( function_exists( 'fika_transactions_disabled' ) && fika_transactions_disabled() ) return;
$fika_parent_cart = get_template_directory() . '/woocommerce/cart/mini-cart.php';
if ( is_readable( $fika_parent_cart ) ) {
	require $fika_parent_cart;
} elseif ( function_exists( 'WC' ) ) {
	$fika_core_cart = WC()->plugin_path() . '/templates/cart/mini-cart.php';
	if ( is_readable( $fika_core_cart ) ) require $fika_core_cart;
}
