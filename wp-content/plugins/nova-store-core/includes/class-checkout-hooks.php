<?php
/**
 * Checkout and order-processing hooks (classic shortcode checkout).
 *
 * | Hook                                             | Type   | Runs                                              |
 * |--------------------------------------------------|--------|---------------------------------------------------|
 * | woocommerce_after_checkout_validation            | action | Server-side, after WC's own field validation      |
 * | woocommerce_checkout_create_order                | action | While the order object is built, before save()    |
 * | woocommerce_order_details_after_order_table      | action | Thank-you page + My Account → order view          |
 * | woocommerce_admin_order_data_after_shipping_address | action | Admin order edit screen (HPOS and legacy)     |
 *
 * None of these touch totals, payment or stock, so order integrity is unchanged.
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Registers checkout/order hooks.
 */
class Checkout_Hooks {

	const META_DISPATCH = '_nova_estimated_dispatch';
	const META_CHANNEL  = '_nova_sales_channel';

	/**
	 * Hook into WooCommerce.
	 */
	public function register(): void {
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_phone' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_order_meta' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_customer_dispatch' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'render_admin_dispatch' ) );
	}

	/**
	 * Require a phone number with 7–15 digits (E.164 length) so the courier can call.
	 *
	 * WooCommerce only checks allowed characters; "+++" would pass. Adding to
	 * $errors (not wc_add_notice) keeps the field highlighted and blocks the order.
	 *
	 * @param array     $data   Posted checkout data, already sanitized by WooCommerce.
	 * @param \WP_Error $errors Validation errors collected so far.
	 */
	public function validate_phone( $data, $errors ): void {
		$phone = isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '';
		if ( '' === $phone ) {
			return; // Empty/required is handled by WooCommerce's own field rules.
		}

		$digits = preg_replace( '/\D+/', '', $phone );
		$length = strlen( (string) $digits );

		if ( $length < 7 || $length > 15 ) {
			$errors->add(
				'billing_phone_validation',
				__( '<strong>Billing phone</strong> must contain between 7 and 15 digits.', 'nova-store-core' ),
				array( 'id' => 'billing_phone' )
			);
		}
	}

	/**
	 * Store the promised dispatch date and sales channel on the order.
	 *
	 * Uses CRUD meta (HPOS-safe). WooCommerce calls $order->save() after this hook.
	 *
	 * @param \WC_Order $order Order being created.
	 * @param array     $data  Posted checkout data.
	 */
	public function add_order_meta( $order, $data ): void {
		unset( $data );
		$order->update_meta_data( self::META_DISPATCH, Delivery::estimated_dispatch_date()->format( 'Y-m-d' ) );
		$order->update_meta_data( self::META_CHANNEL, 'web-storefront' );
	}

	/**
	 * Customer-facing: show the estimated dispatch date under the order table.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function render_customer_dispatch( $order ): void {
		$date = $this->get_dispatch_label( $order );
		if ( ! $date ) {
			return;
		}
		printf(
			'<p class="nova-order-dispatch"><strong>%1$s</strong> %2$s</p>',
			esc_html__( 'Estimated dispatch:', 'nova-store-core' ),
			esc_html( $date )
		);
	}

	/**
	 * Admin-facing: show dispatch date and channel on the order edit screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function render_admin_dispatch( $order ): void {
		$date = $this->get_dispatch_label( $order );
		if ( ! $date ) {
			return;
		}
		printf(
			'<p class="form-field form-field-wide"><strong>%1$s</strong><br />%2$s<br /><small>%3$s %4$s</small></p>',
			esc_html__( 'Estimated dispatch (NOVA):', 'nova-store-core' ),
			esc_html( $date ),
			esc_html__( 'Channel:', 'nova-store-core' ),
			esc_html( (string) $order->get_meta( self::META_CHANNEL ) )
		);
	}

	/**
	 * Human-readable dispatch date from order meta, or '' when absent/invalid.
	 *
	 * @param mixed $order Order object.
	 */
	private function get_dispatch_label( $order ): string {
		if ( ! $order instanceof \WC_Order ) {
			return '';
		}
		$raw  = (string) $order->get_meta( self::META_DISPATCH );
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $raw, wp_timezone() );

		return $date ? wp_date( get_option( 'date_format' ), $date->getTimestamp() ) : '';
	}
}
