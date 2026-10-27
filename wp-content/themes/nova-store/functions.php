<?php
/**
 * NOVA Store theme bootstrap.
 *
 * Presentation only: layout, assets, menus and WooCommerce template wiring.
 * Business rules (labels, delivery, REST API) live in the nova-store-core plugin.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

define( 'NOVA_STORE_THEME_VERSION', '1.0.0' );

require get_template_directory() . '/inc/template-tags.php';

if ( class_exists( 'WooCommerce' ) ) {
	require get_template_directory() . '/inc/woocommerce.php';
}

/**
 * Theme supports, menus and image sizes.
 */
function nova_store_setup() {
	load_theme_textdomain( 'nova-store', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 40,
			'width'       => 160,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 600,
			'single_image_width'    => 900,
			'product_grid'          => array(
				'default_columns' => 4,
				'min_columns'     => 2,
				'max_columns'     => 4,
			),
		)
	);
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'nova-store' ),
			'footer'  => __( 'Footer help links', 'nova-store' ),
		)
	);
}
add_action( 'after_setup_theme', 'nova_store_setup' );

/**
 * Enqueue fonts, styles and scripts. Versions use filemtime so edits bust caches.
 */
function nova_store_enqueue_assets() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	// Arabic pages add IBM Plex Sans Arabic; Latin text (prices, SKUs) keeps Inter/Manrope.
	$families = 'family=Inter:wght@400;500;600&family=Manrope:wght@500;600;700;800';
	if ( is_rtl() ) {
		$families .= '&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700';
	}

	wp_enqueue_style(
		'nova-store-fonts',
		'https://fonts.googleapis.com/css2?' . $families . '&display=swap',
		array(),
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL is versioned by its query.
	);

	// Load after WooCommerce's own stylesheets so overrides win without !important.
	$deps = array( 'nova-store-fonts' );
	foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen' ) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			$deps[] = $handle;
		}
	}

	wp_enqueue_style( 'nova-store', $uri . '/assets/css/main.css', $deps, (string) filemtime( $dir . '/assets/css/main.css' ) );

	wp_enqueue_script(
		'nova-store',
		$uri . '/assets/js/main.js',
		array(),
		(string) filemtime( $dir . '/assets/js/main.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'nova_store_enqueue_assets', 20 );

/**
 * Preconnect to Google Fonts.
 *
 * @param array  $urls          URLs to print for resource hints.
 * @param string $relation_type The relation type the URLs are printed for.
 * @return array
 */
function nova_store_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'nova_store_resource_hints', 10, 2 );
