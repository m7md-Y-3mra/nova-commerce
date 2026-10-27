<?php
/**
 * Plugin Name:       NOVA Store Core
 * Description:       Business logic for the NOVA Store demo: English/Arabic storefront, product trust block, stock and sale labels, checkout validation, order metadata and a public featured-products REST endpoint.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            NOVA Store
 * License:           GPL-2.0-or-later
 * Text Domain:       nova-store-core
 * Domain Path:       /languages
 *
 * WC requires at least: 9.0
 * WC tested up to:      11.2
 *
 * @package NovaStoreCore
 */

defined( 'ABSPATH' ) || exit;

define( 'NOVA_STORE_CORE_VERSION', '1.0.0' );
define( 'NOVA_STORE_CORE_FILE', __FILE__ );
define( 'NOVA_STORE_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOVA_STORE_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once NOVA_STORE_CORE_DIR . 'includes/class-languages.php';
require_once NOVA_STORE_CORE_DIR . 'includes/class-settings.php';
require_once NOVA_STORE_CORE_DIR . 'includes/class-delivery.php';
require_once NOVA_STORE_CORE_DIR . 'includes/class-product-hooks.php';
require_once NOVA_STORE_CORE_DIR . 'includes/class-checkout-hooks.php';
require_once NOVA_STORE_CORE_DIR . 'includes/class-rest-featured-products.php';
require_once NOVA_STORE_CORE_DIR . 'includes/class-content-translation.php';
require_once NOVA_STORE_CORE_DIR . 'includes/class-translation-admin.php';

// Must run before WordPress loads translations (load_default_textdomain()).
\NovaStoreCore\Languages::register_early();

/**
 * Load the plugin's own translations (languages/nova-store-core-ar.mo).
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'nova-store-core', false, dirname( plugin_basename( NOVA_STORE_CORE_FILE ) ) . '/languages' );
	}
);

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 * Order data is only read/written through WC_Order CRUD methods, never post meta.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NOVA_STORE_CORE_FILE, true );
		}
	}
);

/**
 * Boot once all plugins are loaded so WooCommerce classes are available.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'NOVA Store Core requires WooCommerce to be installed and active.', 'nova-store-core' ) . '</p></div>';
				}
			);
			return;
		}

		( new \NovaStoreCore\Languages() )->register();
		( new \NovaStoreCore\Content_Translation() )->register();
		( new \NovaStoreCore\Translation_Admin() )->register();
		( new \NovaStoreCore\Settings() )->register();
		( new \NovaStoreCore\Product_Hooks() )->register();
		( new \NovaStoreCore\Checkout_Hooks() )->register();
		( new \NovaStoreCore\REST_Featured_Products() )->register();
	}
);
