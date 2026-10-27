<?php
/**
 * Small template helpers used across theme templates.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icon. Markup is static and defined here, so it is safe to echo.
 *
 * @param string $name Icon key.
 * @param int    $size Pixel size.
 * @return string
 */
function nova_store_icon( $name, $size = 22 ) {
	$paths = array(
		'cart'    => '<path d="M5 7h14l-1.2 11.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'truck'   => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17.5" cy="17.5" r="1.5"/>',
		'return'  => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
		'shield'  => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
		'lock'    => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'bag'     => '<path d="M6 8h12l-1 12H7z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="icon icon-%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		absint( $size ),
		$paths[ $name ]
	);
}

/**
 * Free-shipping threshold supplied by the nova-store-core plugin (null if unavailable).
 *
 * The theme never computes business rules itself; it asks via a filter so it
 * still renders when the plugin is disabled.
 *
 * @return float|null
 */
function nova_store_free_shipping_threshold() {
	$value = apply_filters( 'nova_store_free_shipping_threshold', null );
	return is_numeric( $value ) && $value > 0 ? (float) $value : null;
}

/**
 * Header cart link with item count. Re-rendered via WooCommerce cart fragments.
 *
 * @return string
 */
function nova_store_cart_link() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '';
	}

	$count = WC()->cart->get_cart_contents_count();
	$label = sprintf(
		/* translators: %d: number of items in cart. */
		_n( 'Cart, %d item', 'Cart, %d items', $count, 'nova-store' ),
		$count
	);

	return sprintf(
		'<a class="header-action nova-cart-link" href="%1$s" aria-label="%2$s">%3$s<span class="nova-cart-count%4$s" aria-hidden="true">%5$d</span></a>',
		esc_url( wc_get_cart_url() ),
		esc_attr( $label ),
		nova_store_icon( 'cart' ),
		$count ? '' : ' is-empty',
		absint( $count )
	);
}

/**
 * Fallback for the primary menu before an admin assigns one: Shop + top-level categories.
 */
function nova_store_primary_menu_fallback() {
	echo '<ul class="primary-menu">';
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( wc_get_page_permalink( 'shop' ) ), esc_html__( 'Shop all', 'nova-store' ) );
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'number'     => 5,
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( get_term_link( $term ) ), esc_html( $term->name ) );
		}
	}
	echo '</ul>';
}

/**
 * Product categories for the home page and shop filter, excluding "Uncategorized".
 *
 * @param int $number Max terms.
 * @return WP_Term[]
 */
function nova_store_get_product_categories( $number = 0 ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'number'     => $number,
			'orderby'    => 'menu_order', // WooCommerce's drag-and-drop category order.
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}
