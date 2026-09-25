<?php
/** Native editor for the existing product_brand / brand_bottom term meta. */
defined( 'ABSPATH' ) || exit;

function fika_sanitize_brand_bottom_meta( $value ) {
	return is_string( $value ) ? wp_kses_post( $value ) : '';
}

function fika_register_brand_bottom_meta() {
	register_term_meta( 'product_brand', 'brand_bottom', array(
		'type' => 'string', 'single' => true,
		'sanitize_callback' => 'fika_sanitize_brand_bottom_meta',
		'show_in_rest' => false,
	) );
}
add_action( 'init', 'fika_register_brand_bottom_meta', 20 );

/** Only yield to ACF when an active matching group owns this exact field. */
function fika_use_native_brand_bottom_editor() {
	if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) return true;
	$groups = acf_get_field_groups( array( 'taxonomy' => 'product_brand' ) );
	if ( ! is_array( $groups ) ) return true;
	foreach ( $groups as $group ) {
		if ( empty( $group['active'] ) ) continue;
		$fields = acf_get_fields( $group );
		if ( ! is_array( $fields ) ) continue;
		foreach ( $fields as $field ) {
			if ( isset( $field['name'] ) && 'brand_bottom' === $field['name'] ) return false;
		}
	}
	return true;
}

function fika_add_brand_bottom_field() {
	if ( ! fika_use_native_brand_bottom_editor() ) return;
	?>
	<div class="form-field term-brand-bottom-wrap">
		<label for="fika_brand_bottom_add"><?php esc_html_e( 'Nội dung cuối trang thương hiệu', 'flatsome-child' ); ?></label>
		<?php
		wp_nonce_field( 'fika_save_brand_bottom', 'fika_brand_bottom_nonce' );
		wp_editor( '', 'fika_brand_bottom_add', array(
			'textarea_name' => 'brand_bottom', 'media_buttons' => true,
			'teeny' => false, 'quicktags' => true, 'textarea_rows' => 10,
		) );
		?>
		<p class="description"><?php esc_html_e( 'Nội dung SEO hiển thị bên dưới danh sách sản phẩm, chỉ ở trang đầu.', 'flatsome-child' ); ?></p>
	</div>
	<?php
}
add_action( 'product_brand_add_form_fields', 'fika_add_brand_bottom_field' );

function fika_edit_brand_bottom_field( $term ) {
	if ( ! $term instanceof WP_Term || ! fika_use_native_brand_bottom_editor() ) return;
	$content = get_term_meta( $term->term_id, 'brand_bottom', true );
	?>
	<tr class="form-field term-brand-bottom-wrap">
		<th scope="row"><label for="fika_brand_bottom_edit"><?php esc_html_e( 'Nội dung cuối trang thương hiệu', 'flatsome-child' ); ?></label></th>
		<td>
			<?php
			wp_nonce_field( 'fika_save_brand_bottom', 'fika_brand_bottom_nonce' );
			wp_editor( is_string( $content ) ? $content : '', 'fika_brand_bottom_edit', array(
				'textarea_name' => 'brand_bottom', 'media_buttons' => true,
				'teeny' => false, 'quicktags' => true, 'textarea_rows' => 14,
			) );
			?>
			<p class="description"><?php esc_html_e( 'Nội dung SEO hiển thị bên dưới danh sách sản phẩm, chỉ ở trang đầu.', 'flatsome-child' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'product_brand_edit_form_fields', 'fika_edit_brand_bottom_field' );

/** Missing fields are not a request to delete existing content. */
function fika_save_brand_bottom_field( $term_id ) {
	if (
		! isset( $_POST['fika_brand_bottom_nonce'], $_POST['brand_bottom'], $_POST['taxonomy'] ) ||
		! is_string( $_POST['fika_brand_bottom_nonce'] ) || ! is_string( $_POST['brand_bottom'] ) ||
		! is_string( $_POST['taxonomy'] ) || 'product_brand' !== $_POST['taxonomy'] ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fika_brand_bottom_nonce'] ) ), 'fika_save_brand_bottom' )
	) return;
	$taxonomy = get_taxonomy( 'product_brand' );
	if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) || ! fika_use_native_brand_bottom_editor() ) return;
	// Do not write an edit request into a secondary translated term.
	if ( isset( $_POST['tag_ID'] ) && ( ! is_scalar( $_POST['tag_ID'] ) || absint( $_POST['tag_ID'] ) !== (int) $term_id ) ) return;
	$content = wp_unslash( $_POST['brand_bottom'] );
	if ( '' === trim( $content ) ) {
		delete_term_meta( $term_id, 'brand_bottom' );
		return;
	}
	// update_metadata strips slashes; preserve literals. Registration sanitizes once.
	update_term_meta( $term_id, 'brand_bottom', wp_slash( $content ) );
}
add_action( 'created_product_brand', 'fika_save_brand_bottom_field' );
add_action( 'edited_product_brand', 'fika_save_brand_bottom_field' );

function fika_enqueue_brand_editor_script() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product_brand' !== $screen->taxonomy || ! fika_use_native_brand_bottom_editor() ) return;
	$path = get_stylesheet_directory() . '/assets/js/brand-editor.js';
	if ( ! is_readable( $path ) ) return;
	wp_enqueue_script( 'fika-brand-editor', get_stylesheet_directory_uri() . '/assets/js/brand-editor.js', array( 'jquery' ), (string) filemtime( $path ), true );
}
add_action( 'admin_enqueue_scripts', 'fika_enqueue_brand_editor_script' );
