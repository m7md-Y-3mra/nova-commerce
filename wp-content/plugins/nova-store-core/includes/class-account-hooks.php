<?php
/**
 * Customer registration hooks (My Account → Register form).
 *
 * | Hook                             | Type   | Runs                                                 |
 * |----------------------------------|--------|------------------------------------------------------|
 * | woocommerce_register_form_start  | action | Top of the register form, before the e-mail field   |
 * | woocommerce_register_post        | action | Server-side, before the customer is created          |
 * | woocommerce_created_customer     | action | Right after the customer user is inserted            |
 *
 * WooCommerce owns the form, its nonce, password rules and the auto-login.
 * These hooks only add a first and last name so orders and the account
 * dashboard ("Hello Sara") can greet the shopper by name.
 *
 * Checkout also calls woocommerce_register_post when "Create an account?" is
 * ticked; there the billing name fields already exist, so these hooks only act
 * on the My Account form.
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Registers customer-registration hooks.
 */
class Account_Hooks {

	const MAX_NAME_LENGTH = 60;

	/**
	 * Hook into WooCommerce.
	 */
	public function register(): void {
		add_action( 'woocommerce_register_form_start', array( $this, 'render_name_fields' ) );
		add_action( 'woocommerce_register_post', array( $this, 'validate_name_fields' ), 10, 3 );
		add_action( 'woocommerce_created_customer', array( $this, 'save_name_fields' ) );
	}

	/**
	 * First and last name inputs, refilled after a failed submit.
	 */
	public function render_name_fields(): void {
		$fields = array(
			'reg_first_name' => array( __( 'First name', 'nova-store-core' ), 'given-name' ),
			'reg_last_name'  => array( __( 'Last name', 'nova-store-core' ), 'family-name' ),
		);
		echo '<div class="nova-register-names">';
		foreach ( $fields as $name => $field ) {
			printf(
				'<p class="woocommerce-form-row form-row"><label for="%1$s">%2$s&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text">%6$s</span></label><input type="text" class="woocommerce-Input input-text" name="%1$s" id="%1$s" autocomplete="%3$s" maxlength="%4$d" value="%5$s" required aria-required="true" /></p>',
				esc_attr( $name ),
				esc_html( $field[0] ),
				esc_attr( $field[1] ),
				(int) self::MAX_NAME_LENGTH,
				esc_attr( self::posted( $name ) ),
				esc_html__( 'Required', 'nova-store-core' )
			);
		}
		echo '</div>';
	}

	/**
	 * Require both names on the My Account form.
	 *
	 * @param string    $username Username (empty when generated from the e-mail).
	 * @param string    $email    E-mail address.
	 * @param \WP_Error $errors   Registration errors collected so far.
	 */
	public function validate_name_fields( $username, $email, $errors ): void {
		unset( $username, $email );
		if ( ! self::is_account_form() ) {
			return;
		}

		$labels = array(
			'reg_first_name' => __( 'First name', 'nova-store-core' ),
			'reg_last_name'  => __( 'Last name', 'nova-store-core' ),
		);
		foreach ( $labels as $name => $label ) {
			$value = self::posted( $name );
			if ( '' === $value ) {
				/* translators: %s: field label, e.g. "First name". */
				$errors->add( $name . '_required', sprintf( __( '<strong>%s</strong> is required.', 'nova-store-core' ), esc_html( $label ) ) );
			} elseif ( mb_strlen( $value ) > self::MAX_NAME_LENGTH ) {
				/* translators: 1: field label, 2: maximum number of characters. */
				$errors->add( $name . '_length', sprintf( __( '<strong>%1$s</strong> must be %2$d characters or fewer.', 'nova-store-core' ), esc_html( $label ), self::MAX_NAME_LENGTH ) );
			}
		}
	}

	/**
	 * Store the names on the user and as the default billing name.
	 *
	 * @param int $customer_id New customer's user ID.
	 */
	public function save_name_fields( $customer_id ): void {
		if ( ! self::is_account_form() ) {
			return;
		}

		$first = self::posted( 'reg_first_name' );
		$last  = self::posted( 'reg_last_name' );

		$customer = new \WC_Customer( $customer_id );
		$customer->set_first_name( $first );
		$customer->set_last_name( $last );
		$customer->set_billing_first_name( $first );
		$customer->set_billing_last_name( $last );
		$customer->set_display_name( trim( $first . ' ' . $last ) );
		$customer->save();
	}

	/**
	 * Whether this request is the My Account register form (not checkout).
	 *
	 * WC_Form_Handler::process_registration() verifies this nonce before it
	 * fires any of the hooks above.
	 */
	private static function is_account_form(): bool {
		return isset( $_POST['woocommerce-register-nonce'], $_POST['register'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce.
	}

	/**
	 * A sanitized, single-line value from the posted form.
	 *
	 * @param string $name Field name.
	 */
	private static function posted( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before the register hooks run; the form only refills on its own page.
		return isset( $_POST[ $name ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) ) : '';
	}
}
