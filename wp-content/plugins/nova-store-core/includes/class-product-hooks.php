<?php
/**
 * Product-level WooCommerce customisations.
 *
 * Hooks registered here:
 *
 * | Hook                                   | Type   | Priority | Runs on                           |
 * |----------------------------------------|--------|----------|-----------------------------------|
 * | woocommerce_single_product_summary     | action | 35       | Single product page summary column |
 * | woocommerce_get_availability_text      | filter | 10       | Anywhere stock HTML is rendered    |
 * | woocommerce_get_availability_class     | filter | 10       | Same; adds `low-stock` CSS class   |
 * | woocommerce_sale_flash                 | filter | 10       | Sale badge on loops + product page |
 * | nova_store_free_shipping_threshold     | filter | 10       | Theme-provided extension point     |
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Registers product display hooks.
 */
class Product_Hooks {

	/**
	 * Hook into WooCommerce.
	 */
	public function register(): void {
		// Priority 35: after add-to-cart (30), before SKU/category meta (40).
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_trust_block' ), 35 );
		add_filter( 'woocommerce_get_availability_text', array( $this, 'filter_availability_text' ), 10, 2 );
		add_filter( 'woocommerce_get_availability_class', array( $this, 'filter_availability_class' ), 10, 2 );
		add_filter( 'woocommerce_sale_flash', array( $this, 'filter_sale_flash' ), 10, 3 );

		// Lets the theme show the threshold without depending on this plugin.
		add_filter( 'nova_store_free_shipping_threshold', static fn() => Delivery::free_shipping_threshold() );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Minimal styles so the trust block looks right under any theme.
	 */
	public function enqueue_assets(): void {
		if ( ! is_product() ) {
			return;
		}
		wp_enqueue_style(
			'nova-store-core',
			NOVA_STORE_CORE_URL . 'assets/css/nova-store-core.css',
			array(),
			(string) filemtime( NOVA_STORE_CORE_DIR . 'assets/css/nova-store-core.css' )
		);
	}

	/**
	 * Action: delivery / returns / warranty block on the single product page.
	 */
	public function render_trust_block(): void {
		global $product;

		if ( ! $product instanceof \WC_Product || ! Settings::get( 'show_trust_block' ) ) {
			return;
		}

		$threshold = Delivery::free_shipping_threshold();
		$items     = array();

		if ( $product->is_in_stock() ) {
			$items[] = array(
				'icon'  => 'truck',
				'title' => sprintf(
					/* translators: %s: formatted date, e.g. "Wed, 29 Oct". */
					__( 'Ships by %s', 'nova-store-core' ),
					wp_date( 'D, j M', Delivery::estimated_dispatch_date()->getTimestamp() )
				),
				'text'  => null !== $threshold
					? sprintf(
						/* translators: %s: formatted price. */
						__( 'Free shipping on orders over %s', 'nova-store-core' ),
						wp_strip_all_tags( wc_price( $threshold, array( 'decimals' => 0 ) ) )
					)
					: __( 'Tracked delivery on every order', 'nova-store-core' ),
			);
		}

		$returns = (int) Settings::get( 'returns_days' );
		if ( $returns > 0 ) {
			$items[] = array(
				'icon'  => 'return',
				'title' => sprintf(
					/* translators: %d: number of days. */
					_n( '%d-day free return', '%d-day free returns', $returns, 'nova-store-core' ),
					$returns
				),
				'text'  => __( 'No questions asked, prepaid label', 'nova-store-core' ),
			);
		}

		$warranty = (int) Settings::get( 'warranty_years' );
		if ( $warranty > 0 ) {
			$items[] = array(
				'icon'  => 'shield',
				'title' => sprintf(
					/* translators: %d: number of years. */
					_n( '%d-year warranty', '%d-year warranty', $warranty, 'nova-store-core' ),
					$warranty
				),
				'text'  => __( 'Covered by NOVA, not a third party', 'nova-store-core' ),
			);
		}

		if ( ! $items ) {
			return;
		}

		// Presentation lives in a template; this method only prepares data.
		include NOVA_STORE_CORE_DIR . 'templates/trust-block.php';
	}

	/**
	 * Filter: clearer, more actionable stock messages.
	 *
	 * WooCommerce returns an empty string for in-stock products that do not
	 * manage stock, which hides availability entirely; we always show it.
	 *
	 * @param string      $availability Default text from WC_Product::get_availability_text().
	 * @param \WC_Product $product      Product being displayed.
	 * @return string
	 */
	public function filter_availability_text( $availability, $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return $availability;
		}

		if ( ! $product->is_in_stock() ) {
			return __( 'Sold out — restocking soon', 'nova-store-core' );
		}

		if ( $product->is_on_backorder() ) {
			return $availability;
		}

		if ( self::is_low_stock( $product ) ) {
			$qty = (int) $product->get_stock_quantity();
			return sprintf(
				/* translators: %d: units left. */
				_n( 'Only %d left in stock', 'Only %d left in stock', $qty, 'nova-store-core' ),
				$qty
			);
		}

		return __( 'In stock', 'nova-store-core' );
	}

	/**
	 * Filter: add a `low-stock` CSS class alongside WooCommerce's `in-stock`.
	 *
	 * @param string      $css_class Default class ("in-stock", "out-of-stock", ...).
	 * @param \WC_Product $product Product being displayed.
	 * @return string
	 */
	public function filter_availability_class( $css_class, $product ) {
		if ( $product instanceof \WC_Product && self::is_low_stock( $product ) ) {
			$css_class .= ' low-stock';
		}
		return $css_class;
	}

	/**
	 * In stock, stock-managed, and at or below the configured threshold.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function is_low_stock( \WC_Product $product ): bool {
		if ( ! $product->is_in_stock() || $product->is_on_backorder() || ! $product->managing_stock() ) {
			return false;
		}
		$qty = (int) $product->get_stock_quantity();

		return $qty > 0 && $qty <= (int) Settings::get( 'low_stock_threshold' );
	}

	/**
	 * Filter: replace the generic "Sale!" badge with the actual discount, e.g. "−15%".
	 *
	 * @param string      $html    Default badge HTML.
	 * @param \WP_Post    $post    Product post.
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	public function filter_sale_flash( $html, $post, $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}

		$percent = self::discount_percent( $product );
		if ( $percent < 1 ) {
			return $html;
		}

		return sprintf(
			'<span class="onsale" aria-label="%1$s">−%2$d%%</span>',
			/* translators: %d: discount percentage. */
			esc_attr( sprintf( __( 'On sale: %d percent off', 'nova-store-core' ), $percent ) ),
			$percent
		);
	}

	/**
	 * Largest percentage discount across a product (or its variations).
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function discount_percent( \WC_Product $product ): int {
		$pairs = array();

		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices();
			foreach ( $prices['regular_price'] as $id => $regular ) {
				$pairs[] = array( (float) $regular, (float) ( $prices['sale_price'][ $id ] ?? $regular ) );
			}
		} else {
			$pairs[] = array( (float) $product->get_regular_price(), (float) $product->get_sale_price() );
		}

		$best = 0;
		foreach ( $pairs as list( $regular, $sale ) ) {
			if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
				$best = max( $best, (int) round( ( $regular - $sale ) / $regular * 100 ) );
			}
		}

		return $best;
	}
}
