<?php
/**
 * Store settings screen (WooCommerce → NOVA Store).
 *
 * Uses the Settings API, so the save request is protected by the
 * `nova_store_core-options` nonce that `settings_fields()` prints and
 * verified by options.php. A second form demonstrates a hand-rolled
 * privileged action (cache flush) with an explicit nonce + capability check.
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page and option accessors.
 */
class Settings {

	const OPTION     = 'nova_store_core_settings';
	const GROUP      = 'nova_store_core';
	const PAGE_SLUG  = 'nova-store-core';
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Default values, also used to whitelist keys on save.
	 *
	 * @return array<string, int|bool>
	 */
	public static function defaults(): array {
		return array(
			'show_trust_block'    => true,
			'dispatch_days'       => 1,
			'returns_days'        => 30,
			'warranty_years'      => 2,
			'low_stock_threshold' => 5,
		);
	}

	/**
	 * Read one setting, falling back to its default.
	 *
	 * @param string $key Setting key from defaults().
	 * @return int|bool|null
	 */
	public static function get( string $key ) {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );

		return $settings[ $key ] ?? null;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ), 60 );
		add_action( 'admin_post_nova_store_core_flush_cache', array( $this, 'handle_flush_cache' ) );

		// Allow shop managers (not only administrators) to save this option group.
		add_filter(
			'option_page_capability_' . self::GROUP,
			static fn() => self::CAPABILITY
		);
	}

	/**
	 * Register the option with a sanitize callback.
	 */
	public function register_setting(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize and clamp submitted values. Unknown keys are dropped.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string, int|bool>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'show_trust_block'    => ! empty( $input['show_trust_block'] ),
			'dispatch_days'       => self::clamp_int( $input['dispatch_days'] ?? 1, 0, 14 ),
			'returns_days'        => self::clamp_int( $input['returns_days'] ?? 30, 0, 365 ),
			'warranty_years'      => self::clamp_int( $input['warranty_years'] ?? 2, 0, 10 ),
			'low_stock_threshold' => self::clamp_int( $input['low_stock_threshold'] ?? 5, 1, 50 ),
		);
	}

	/**
	 * Convert to int and keep within [min, max].
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Lower bound.
	 * @param int   $max   Upper bound.
	 */
	private static function clamp_int( $value, int $min, int $max ): int {
		return max( $min, min( $max, absint( $value ) ) );
	}

	/**
	 * Add the page under the WooCommerce menu.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'NOVA Store settings', 'nova-store-core' ),
			__( 'NOVA Store', 'nova-store-core' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Admin-post handler: flush the featured-products REST cache.
	 */
	public function handle_flush_cache(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'nova-store-core' ), 403 );
		}
		check_admin_referer( 'nova_store_core_flush_cache' );

		REST_Featured_Products::flush_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'flushed' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$values = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$fields = array(
			'dispatch_days'       => array( __( 'Dispatch time (business days)', 'nova-store-core' ), 0, 14 ),
			'returns_days'        => array( __( 'Free returns window (days)', 'nova-store-core' ), 0, 365 ),
			'warranty_years'      => array( __( 'Warranty (years)', 'nova-store-core' ), 0, 10 ),
			'low_stock_threshold' => array( __( '"Only X left" label at or below', 'nova-store-core' ), 1, 50 ),
		);
		// Read-only flag used purely to choose which notice to show.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flushed = isset( $_GET['flushed'] ) && '1' === $_GET['flushed'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NOVA Store settings', 'nova-store-core' ); ?></h1>

			<?php if ( $flushed ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Featured products cache cleared.', 'nova-store-core' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Product page trust block', 'nova-store-core' ); ?></th>
						<td>
							<label for="nova-show-trust-block">
								<input type="checkbox" id="nova-show-trust-block" name="<?php echo esc_attr( self::OPTION ); ?>[show_trust_block]" value="1" <?php checked( (bool) $values['show_trust_block'] ); ?> />
								<?php esc_html_e( 'Show delivery, returns and warranty information on product pages', 'nova-store-core' ); ?>
							</label>
						</td>
					</tr>
					<?php foreach ( $fields as $key => list( $label, $min, $max ) ) : ?>
						<tr>
							<th scope="row"><label for="nova-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<input type="number" class="small-text" id="nova-<?php echo esc_attr( $key ); ?>"
									name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>"
									value="<?php echo esc_attr( (string) $values[ $key ] ); ?>"
									min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="1" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="description">
					<?php esc_html_e( 'The free-shipping threshold is read from your WooCommerce shipping zones (Free shipping → minimum order amount), so it is never configured twice.', 'nova-store-core' ); ?>
				</p>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'REST API cache', 'nova-store-core' ); ?></h2>
			<p><?php esc_html_e( 'GET /wp-json/nova-store/v1/featured-products is cached for 10 minutes and cleared automatically when products change.', 'nova-store-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nova_store_core_flush_cache" />
				<?php wp_nonce_field( 'nova_store_core_flush_cache' ); ?>
				<?php submit_button( __( 'Clear cache now', 'nova-store-core' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
