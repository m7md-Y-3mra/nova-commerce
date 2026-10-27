<?php
/**
 * Admin UI for Arabic content (wp-admin): a meta box on products/pages and extra
 * fields on product categories. Each save checks a nonce and the user's
 * capability for that specific object, then sanitizes per field type.
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Arabic translation editing screens.
 */
class Translation_Admin {

	const NONCE_ACTION = 'nova_store_core_save_translation';
	const NONCE_FIELD  = 'nova_store_core_translation_nonce';

	/**
	 * Hook into WordPress admin.
	 */
	public function register(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_post' ), 10, 2 );
		add_action( 'save_post_page', array( $this, 'save_post' ), 10, 2 );

		add_action( 'product_cat_add_form_fields', array( $this, 'render_term_add_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'render_term_edit_fields' ) );
		add_action( 'created_product_cat', array( $this, 'save_term' ) );
		add_action( 'edited_product_cat', array( $this, 'save_term' ) );
	}

	/**
	 * Register the "Arabic translation" box.
	 */
	public function add_meta_box(): void {
		foreach ( array( 'product', 'page' ) as $screen ) {
			add_meta_box(
				'nova-store-arabic',
				__( 'Arabic translation (العربية)', 'nova-store-core' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Meta box fields. Empty fields fall back to the English content on the storefront.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public function render_meta_box( $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$title   = Content_Translation::post_value( $post->ID, Content_Translation::META_TITLE );
		$excerpt = Content_Translation::post_value( $post->ID, Content_Translation::META_EXCERPT );
		$content = Content_Translation::post_value( $post->ID, Content_Translation::META_CONTENT );
		?>
		<p class="description"><?php esc_html_e( 'Shown when a visitor switches the store to Arabic. Leave a field empty to show the English version.', 'nova-store-core' ); ?></p>
		<p>
			<label for="nova-ar-title"><strong><?php esc_html_e( 'Title', 'nova-store-core' ); ?></strong></label>
			<input type="text" class="widefat" id="nova-ar-title" name="nova_ar[title]" value="<?php echo esc_attr( $title ); ?>" dir="rtl" lang="ar" />
		</p>
		<?php if ( 'product' === $post->post_type ) : ?>
			<p>
				<label for="nova-ar-excerpt"><strong><?php esc_html_e( 'Short description', 'nova-store-core' ); ?></strong></label>
				<textarea class="widefat" rows="4" id="nova-ar-excerpt" name="nova_ar[excerpt]" dir="rtl" lang="ar"><?php echo esc_textarea( $excerpt ); ?></textarea>
			</p>
		<?php endif; ?>
		<p>
			<label for="nova-ar-content"><strong><?php echo 'product' === $post->post_type ? esc_html__( 'Description', 'nova-store-core' ) : esc_html__( 'Content', 'nova-store-core' ); ?></strong></label>
			<textarea class="widefat" rows="6" id="nova-ar-content" name="nova_ar[content]" dir="rtl" lang="ar"><?php echo esc_textarea( $content ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Basic HTML is allowed.', 'nova-store-core' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Save meta box values.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_post( $post_id, $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = isset( $_POST['nova_ar'] ) && is_array( $_POST['nova_ar'] ) ? wp_unslash( $_POST['nova_ar'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.

		$fields = array(
			Content_Translation::META_TITLE   => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
			Content_Translation::META_CONTENT => wp_kses_post( (string) ( $input['content'] ?? '' ) ),
		);
		if ( 'product' === $post->post_type ) {
			$fields[ Content_Translation::META_EXCERPT ] = wp_kses_post( (string) ( $input['excerpt'] ?? '' ) );
		}

		foreach ( $fields as $key => $value ) {
			if ( '' === trim( $value ) ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		if ( 'product' === $post->post_type ) {
			REST_Featured_Products::flush_cache();
		}
	}

	/**
	 * Fields on Products → Categories → "Add new".
	 */
	public function render_term_add_fields(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="form-field">
			<label for="nova-ar-term-name"><?php esc_html_e( 'Arabic name', 'nova-store-core' ); ?></label>
			<input type="text" id="nova-ar-term-name" name="nova_ar_term[name]" dir="rtl" lang="ar" />
		</div>
		<div class="form-field">
			<label for="nova-ar-term-desc"><?php esc_html_e( 'Arabic description', 'nova-store-core' ); ?></label>
			<textarea id="nova-ar-term-desc" name="nova_ar_term[description]" rows="3" dir="rtl" lang="ar"></textarea>
		</div>
		<?php
	}

	/**
	 * Fields on the category edit screen.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public function render_term_edit_fields( $term ): void {
		$name = (string) get_term_meta( $term->term_id, Content_Translation::TERM_NAME, true );
		$desc = (string) get_term_meta( $term->term_id, Content_Translation::TERM_DESC, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="nova-ar-term-name"><?php esc_html_e( 'Arabic name', 'nova-store-core' ); ?></label></th>
			<td>
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="text" id="nova-ar-term-name" name="nova_ar_term[name]" value="<?php echo esc_attr( $name ); ?>" dir="rtl" lang="ar" />
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="nova-ar-term-desc"><?php esc_html_e( 'Arabic description', 'nova-store-core' ); ?></label></th>
			<td><textarea id="nova-ar-term-desc" name="nova_ar_term[description]" rows="3" dir="rtl" lang="ar"><?php echo esc_textarea( $desc ); ?></textarea></td>
		</tr>
		<?php
	}

	/**
	 * Save category fields.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_term( $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$input = isset( $_POST['nova_ar_term'] ) && is_array( $_POST['nova_ar_term'] ) ? wp_unslash( $_POST['nova_ar_term'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.

		$fields = array(
			Content_Translation::TERM_NAME => sanitize_text_field( (string) ( $input['name'] ?? '' ) ),
			Content_Translation::TERM_DESC => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
		);
		foreach ( $fields as $key => $value ) {
			if ( '' === $value ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $value );
			}
		}
	}
}
