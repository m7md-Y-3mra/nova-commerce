<?php
/**
 * Public REST endpoint: GET /wp-json/nova-store/v1/featured-products
 *
 * Query args:
 *   per_page  integer 1–12, default 4
 *   category  product_cat slug (optional, must exist)
 *
 * Returns only public catalog fields; no customer, order or config data.
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Featured products controller.
 */
class REST_Featured_Products {

	const NAMESPACE    = 'nova-store/v1';
	const ROUTE        = '/featured-products';
	const MAX_PER_PAGE = 12;
	const CACHE_TTL    = 10 * MINUTE_IN_SECONDS;
	const CACHE_GROUP  = 'nova_store_featured_v'; // Transient prefix; suffixed with a version.
	const VERSION_KEY  = 'nova_store_featured_cache_version';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Invalidate cached responses whenever catalog data that we expose changes.
		foreach ( array(
			'woocommerce_new_product',
			'woocommerce_update_product',
			'woocommerce_delete_product',
			'woocommerce_trash_product',
			'woocommerce_product_set_stock',
			'woocommerce_product_set_stock_status',
			'woocommerce_product_set_visibility',
		) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_cache' ) );
		}
	}

	/**
	 * Register the route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					// Intentionally public: returns the same data any visitor sees on the shop page.
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Request argument definitions. Core validates types/bounds before the callback runs.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return array(
			'per_page' => array(
				'description' => __( 'Maximum number of products to return.', 'nova-store-core' ),
				'type'        => 'integer',
				'default'     => 4,
				'minimum'     => 1,
				'maximum'     => self::MAX_PER_PAGE,
				// No custom callbacks: core's rest_parse_request_arg() validates the
				// schema above (rejects "abc", -5, 999) and then casts to int.
			),
			'lang'     => array(
				'description' => __( 'Response language. Defaults to the visitor\'s saved choice, then English.', 'nova-store-core' ),
				'type'        => 'string',
				'enum'        => array_keys( Languages::available() ),
				// Read by Languages::current() before WordPress loads translations.
			),
			'category' => array(
				'description'       => __( 'Limit to a product category slug.', 'nova-store-core' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'validate_callback' => array( $this, 'validate_category' ),
			),
		);
	}

	/**
	 * Category must be an existing product_cat slug.
	 *
	 * @param mixed            $value   Raw value.
	 * @param \WP_REST_Request $request Request.
	 * @param string           $param   Param name.
	 * @return true|\WP_Error
	 */
	public function validate_category( $value, $request, $param ) {
		$valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! term_exists( sanitize_title( (string) $value ), 'product_cat' ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				/* translators: %s: parameter name. */
				sprintf( __( '%s is not a known product category.', 'nova-store-core' ), $param ),
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * GET handler.
	 *
	 * @param \WP_REST_Request $request Request with validated params.
	 * @return \WP_REST_Response
	 */
	public function get_items( \WP_REST_Request $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$category = (string) $request->get_param( 'category' );

		// Names, descriptions and formatted prices differ per language, so cache per locale.
		$cache_key = self::cache_prefix() . md5( $per_page . '|' . $category . '|' . get_locale() );
		$data      = get_transient( $cache_key );

		if ( false === $data ) {
			$data = $this->query( $per_page, $category );
			set_transient( $cache_key, $data, self::CACHE_TTL );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		$response->header( 'X-WP-Total', (string) $data['count'] );

		return $response;
	}

	/**
	 * Fetch featured, published, catalog-visible products through the WooCommerce CRUD API.
	 *
	 * @param int    $per_page Limit.
	 * @param string $category Optional category slug.
	 * @return array{count: int, currency: string, products: array<int, array<string, mixed>>}
	 */
	private function query( int $per_page, string $category ): array {
		$args = array(
			'status'     => 'publish',
			'featured'   => true,
			'visibility' => 'catalog',
			'limit'      => $per_page,
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
			'return'     => 'objects',
		);
		if ( '' !== $category ) {
			$args['category'] = array( $category );
		}

		$products = array();
		foreach ( wc_get_products( $args ) as $product ) {
			// Skip password-protected products: their details are not public.
			if ( post_password_required( $product->get_id() ) ) {
				continue;
			}
			$products[] = $this->prepare_item( $product );
		}

		return array(
			'count'    => count( $products ),
			'language' => Languages::current(),
			'currency' => get_woocommerce_currency(),
			'products' => $products,
		);
	}

	/**
	 * Whitelisted public fields for one product.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string, mixed>
	 */
	private function prepare_item( \WC_Product $product ): array {
		$image_id = $product->get_image_id();

		return array(
			'id'              => $product->get_id(),
			'name'            => wp_strip_all_tags( $product->get_name() ),
			'price'           => wc_format_decimal( $product->get_price(), wc_get_price_decimals() ),
			'regular_price'   => wc_format_decimal( $product->get_regular_price(), wc_get_price_decimals() ),
			'on_sale'         => $product->is_on_sale(),
			'price_formatted' => html_entity_decode( wp_strip_all_tags( wc_price( (float) $product->get_price() ) ), ENT_QUOTES, 'UTF-8' ),
			'in_stock'        => $product->is_in_stock(),
			'permalink'       => $product->get_permalink(),
			'image'           => $image_id ? array(
				'src' => wp_get_attachment_image_url( $image_id, 'woocommerce_single' ),
				'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
			) : null,
		);
	}

	/**
	 * JSON schema for one product item (shown in OPTIONS / route index).
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'nova-featured-products',
			'type'       => 'object',
			'properties' => array(
				'count'    => array( 'type' => 'integer' ),
				'language' => array(
					'type' => 'string',
					'enum' => array_keys( Languages::available() ),
				),
				'currency' => array( 'type' => 'string' ),
				'products' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'              => array( 'type' => 'integer' ),
							'name'            => array( 'type' => 'string' ),
							'price'           => array( 'type' => 'string' ),
							'regular_price'   => array( 'type' => 'string' ),
							'on_sale'         => array( 'type' => 'boolean' ),
							'price_formatted' => array( 'type' => 'string' ),
							'in_stock'        => array( 'type' => 'boolean' ),
							'permalink'       => array(
								'type'   => 'string',
								'format' => 'uri',
							),
							'image'           => array( 'type' => array( 'object', 'null' ) ),
						),
					),
				),
			),
		);
	}

	/**
	 * Current transient prefix. Bumping the version orphans all old entries,
	 * which then expire on their own; no wildcard deletes or raw SQL needed.
	 */
	private static function cache_prefix(): string {
		return self::CACHE_GROUP . (int) get_option( self::VERSION_KEY, 1 ) . '_';
	}

	/**
	 * Invalidate every cached response.
	 */
	public static function flush_cache(): void {
		update_option( self::VERSION_KEY, (int) get_option( self::VERSION_KEY, 1 ) + 1, false );
	}
}
