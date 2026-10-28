<?php
/**
 * Arabic versions of catalog content (products, categories, pages, store labels).
 *
 * Interface strings (buttons, labels, WooCommerce screens) come from gettext
 * translation files. Content that merchants type in wp-admin cannot, so each
 * product, page and category stores an optional Arabic copy in meta, edited
 * in the "Arabic translation" box (see Translation_Admin). When the storefront
 * is in Arabic, these filters swap the English value for the Arabic one and
 * fall back to English whenever a translation is missing.
 *
 * | Hook                                       | Type   | Translates                         |
 * |--------------------------------------------|--------|------------------------------------|
 * | the_title                                  | filter | Product + page titles              |
 * | single_post_title                          | filter | Browser tab / document <title>     |
 * | woocommerce_product_get_name               | filter | Product name (cart, checkout, API) |
 * | woocommerce_short_description              | filter | Product page bullet summary        |
 * | woocommerce_product_get_short_description  | filter | Same, via the product object       |
 * | the_content (priority 1)                   | filter | Product description tab, pages     |
 * | woocommerce_product_get_description        | filter | Same, via the product object       |
 * | woocommerce_display_product_attributes     | filter | "Additional information" table     |
 * | get_product_cat                            | filter | Category name + description        |
 * | nav_menu_item_title                        | filter | Menu items pointing at pages/cats  |
 * | woocommerce_shipping_rate_label            | filter | Seeded shipping method names       |
 * | option_woocommerce_cod_settings            | filter | COD title/description/instructions |
 * | option_blogdescription                     | filter | Site tagline (<title> on home)     |
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end content translation filters (active only when the storefront is Arabic).
 */
class Content_Translation {

	const META_TITLE      = '_nova_ar_title';
	const META_EXCERPT    = '_nova_ar_excerpt';
	const META_CONTENT    = '_nova_ar_content';
	const META_ATTRIBUTES = '_nova_ar_attributes';
	const TERM_NAME       = '_nova_ar_name';
	const TERM_DESC       = '_nova_ar_description';

	/**
	 * Hook into WordPress, only for Arabic storefront requests.
	 */
	public function register(): void {
		if ( ! Languages::is_arabic() ) {
			return;
		}

		add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );
		add_filter( 'single_post_title', array( $this, 'filter_document_title' ), 10, 2 );
		add_filter( 'woocommerce_product_get_name', array( $this, 'filter_product_name' ), 10, 2 );
		add_filter( 'woocommerce_short_description', array( $this, 'filter_short_description' ), 5 );
		add_filter( 'woocommerce_product_get_short_description', array( $this, 'filter_product_excerpt' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 1 );
		add_filter( 'woocommerce_product_get_description', array( $this, 'filter_product_description' ), 10, 2 );
		add_filter( 'woocommerce_display_product_attributes', array( $this, 'filter_attributes' ), 10, 2 );
		add_filter( 'get_product_cat', array( $this, 'filter_category' ) );
		add_filter( 'nav_menu_item_title', array( $this, 'filter_menu_item_title' ), 10, 2 );
		add_filter( 'woocommerce_shipping_rate_label', array( $this, 'translate_store_string' ), 20 );
		add_filter( 'option_woocommerce_cod_settings', array( $this, 'filter_cod_settings' ) );
		add_filter( 'option_blogdescription', array( $this, 'translate_store_string' ) );
	}

	/**
	 * Arabic post meta value, or '' when not translated.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	public static function post_value( int $post_id, string $key ): string {
		$value = get_post_meta( $post_id, $key, true );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Filter: titles of translated products and pages.
	 *
	 * @param string   $title   Title.
	 * @param int|null $post_id Post ID (absent for some legacy callers).
	 * @return string
	 */
	public function filter_title( $title, $post_id = null ) {
		if ( ! $post_id || ! in_array( get_post_type( $post_id ), array( 'product', 'page' ), true ) ) {
			return $title;
		}
		$ar = self::post_value( (int) $post_id, self::META_TITLE );
		return '' !== $ar ? esc_html( $ar ) : $title;
	}

	/**
	 * Filter: document <title> for single products and pages.
	 *
	 * @param string   $title Title.
	 * @param \WP_Post $post  Post.
	 * @return string
	 */
	public function filter_document_title( $title, $post ) {
		return $post instanceof \WP_Post ? $this->filter_title( $title, $post->ID ) : $title;
	}

	/**
	 * Filter: product name used by cart, checkout, emails and the REST API.
	 *
	 * @param string      $name    Name.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public function filter_product_name( $name, $product ) {
		$ar = self::post_value( (int) $product->get_id(), self::META_TITLE );
		return '' !== $ar ? $ar : $name;
	}

	/**
	 * Filter: short description printed by single-product/short-description.php.
	 *
	 * @param string $excerpt Post excerpt.
	 * @return string
	 */
	public function filter_short_description( $excerpt ) {
		$post = get_post();
		if ( ! $post || 'product' !== $post->post_type ) {
			return $excerpt;
		}
		$ar = self::post_value( $post->ID, self::META_EXCERPT );
		return '' !== $ar ? $ar : $excerpt;
	}

	/**
	 * Filter: short description read through WC_Product.
	 *
	 * @param string      $excerpt Short description.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public function filter_product_excerpt( $excerpt, $product ) {
		$ar = self::post_value( (int) $product->get_id(), self::META_EXCERPT );
		return '' !== $ar ? $ar : $excerpt;
	}

	/**
	 * Filter: post content for products (Description tab) and pages.
	 * Runs at priority 1 so blocks, shortcodes and autop still process the Arabic copy.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	public function filter_content( $content ) {
		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, array( 'product', 'page' ), true ) ) {
			return $content;
		}
		$ar = self::post_value( $post->ID, self::META_CONTENT );
		return '' !== $ar ? $ar : $content;
	}

	/**
	 * Filter: description read through WC_Product.
	 *
	 * @param string      $description Description.
	 * @param \WC_Product $product     Product.
	 * @return string
	 */
	public function filter_product_description( $description, $product ) {
		$ar = self::post_value( (int) $product->get_id(), self::META_CONTENT );
		return '' !== $ar ? $ar : $description;
	}

	/**
	 * Filter: "Additional information" rows. Meta maps English label => [Arabic label, Arabic value].
	 *
	 * @param array       $rows    Rows keyed by attribute slug, each { label, value }.
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public function filter_attributes( $rows, $product ) {
		$map = get_post_meta( $product->get_id(), self::META_ATTRIBUTES, true );
		if ( ! is_array( $map ) ) {
			return $rows;
		}
		foreach ( $rows as $key => $row ) {
			$label = wp_strip_all_tags( (string) ( $row['label'] ?? '' ) );
			if ( isset( $map[ $label ][0], $map[ $label ][1] ) ) {
				$rows[ $key ]['label'] = esc_html( $map[ $label ][0] );
				$rows[ $key ]['value'] = wpautop( esc_html( $map[ $label ][1] ) );
			}
		}
		return $rows;
	}

	/**
	 * Filter: category name/description everywhere terms are loaded on the storefront.
	 *
	 * @param \WP_Term $term Term.
	 * @return \WP_Term
	 */
	public function filter_category( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return $term;
		}
		$name = get_term_meta( $term->term_id, self::TERM_NAME, true );
		$desc = get_term_meta( $term->term_id, self::TERM_DESC, true );
		if ( ! $name && ! $desc ) {
			return $term;
		}
		$term = clone $term;
		if ( is_string( $name ) && '' !== $name ) {
			$term->name = $name;
		}
		if ( is_string( $desc ) && '' !== $desc ) {
			$term->description = $desc;
		}
		return $term;
	}

	/**
	 * Filter: nav menu labels that point at translated pages or categories.
	 *
	 * @param string   $title Menu item title.
	 * @param \WP_Post $item  Menu item (decorated by wp_setup_nav_menu_item()).
	 * @return string
	 */
	public function filter_menu_item_title( $title, $item ) {
		if ( 'taxonomy' === ( $item->type ?? '' ) && 'product_cat' === ( $item->object ?? '' ) ) {
			$name = get_term_meta( (int) $item->object_id, self::TERM_NAME, true );
			return is_string( $name ) && '' !== $name ? esc_html( $name ) : $title;
		}
		if ( 'post_type' === ( $item->type ?? '' ) ) {
			$ar = self::post_value( (int) $item->object_id, self::META_TITLE );
			return '' !== $ar ? esc_html( $ar ) : $title;
		}
		return $title;
	}

	/**
	 * Filter: Cash on Delivery texts shown at checkout and on the thank-you page.
	 *
	 * @param mixed $settings Gateway settings option.
	 * @return mixed
	 */
	public function filter_cod_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}
		foreach ( array( 'title', 'description', 'instructions' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				$settings[ $key ] = $this->translate_store_string( $settings[ $key ] );
			}
		}
		return $settings;
	}

	/**
	 * Translate store texts that are saved as settings (shipping names, COD copy, tagline).
	 *
	 * They are listed here as gettext strings so the Arabic lives in the plugin's
	 * .po file with everything else. Unknown strings are returned unchanged.
	 *
	 * @param string $text Text saved in settings.
	 * @return string
	 */
	public function translate_store_string( $text ) {
		$strings = array(
			'Free shipping'                             => __( 'Free shipping', 'nova-store-core' ),
			'Standard shipping'                         => __( 'Standard shipping', 'nova-store-core' ),
			'Cash on delivery'                          => __( 'Cash on delivery', 'nova-store-core' ),
			'Pay in cash when your order arrives.'      => __( 'Pay in cash when your order arrives.', 'nova-store-core' ),
			'Please have the exact amount ready for the courier.' => __( 'Please have the exact amount ready for the courier.', 'nova-store-core' ),
			'Premium tech accessories for focused work' => __( 'Premium tech accessories for focused work', 'nova-store-core' ),
		);

		return $strings[ $text ] ?? $text;
	}
}
