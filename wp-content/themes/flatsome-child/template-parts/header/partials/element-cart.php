<?php
/** Omit the Flatsome header cart while transactions are disabled. */
defined( 'ABSPATH' ) || exit;
if ( function_exists( 'fika_transactions_disabled' ) && fika_transactions_disabled() ) return;
$fika_parent_partial = get_template_directory() . '/template-parts/header/partials/element-cart.php';
if ( is_readable( $fika_parent_partial ) ) require $fika_parent_partial;
