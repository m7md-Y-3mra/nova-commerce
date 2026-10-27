<?php
/**
 * NOVA Store demo seeder.
 *
 * Run through WP-CLI (bin/setup.sh does this):
 *   docker compose run --rm wpcli eval-file /seed/seed.php --user=admin
 *
 * Idempotent: products are matched by SKU, images by file name, pages/menus
 * by slug/name. Re-running restores the canonical demo catalog (prices, stock).
 *
 * @package NovaStoreSeed
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! class_exists( 'WooCommerce' ) ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

const NOVA_SEED_IMAGES = '/seed/images/';

// ----------------------------------------------------------------------
// 1. Store settings
// ----------------------------------------------------------------------

$nova_options = array(
	'woocommerce_store_address'                => '100 Market Street',
	'woocommerce_store_city'                   => 'San Francisco',
	'woocommerce_default_country'              => 'US:CA',
	'woocommerce_store_postcode'               => '94105',
	'woocommerce_currency'                     => 'USD',
	'woocommerce_price_num_decimals'           => '2',
	'woocommerce_calc_taxes'                   => 'no',
	'woocommerce_manage_stock'                 => 'yes',
	'woocommerce_notify_low_stock_amount'      => '3',
	// No mail server in the demo stack; skip admin stock e-mails.
	'woocommerce_notify_low_stock'             => 'no',
	'woocommerce_notify_no_stock'              => 'no',
	'woocommerce_hide_out_of_stock_items'      => 'no',
	'woocommerce_enable_ajax_add_to_cart'      => 'yes',
	'woocommerce_cart_redirect_after_add'      => 'no',
	'woocommerce_enable_guest_checkout'        => 'yes',
	'woocommerce_enable_reviews'               => 'yes',
	'woocommerce_thumbnail_cropping'           => '1:1',
	// New installs start in "coming soon" mode, which hides the shop from visitors.
	'woocommerce_coming_soon'                  => 'no',
	'woocommerce_store_pages_only'             => 'no',
	'woocommerce_allow_tracking'               => 'no',
	'woocommerce_show_marketplace_suggestions' => 'no',
	'woocommerce_task_list_hidden'             => 'yes',
	'woocommerce_task_list_complete'           => 'yes',
	'blogname'                                 => 'NOVA Store',
	'blogdescription'                          => 'Premium tech accessories for focused work',
);
foreach ( $nova_options as $nova_key => $nova_value ) {
	update_option( $nova_key, $nova_value );
}
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );
WP_CLI::log( 'Store settings updated.' );

// ----------------------------------------------------------------------
// 2. Payments: Cash on Delivery only (safe demo method, no credentials).
// ----------------------------------------------------------------------

update_option(
	'woocommerce_cod_settings',
	array(
		'enabled'            => 'yes',
		'title'              => 'Cash on delivery',
		'description'        => 'Pay in cash when your order arrives. This is a demo store: no real payment is taken.',
		'instructions'       => 'Please have the exact amount ready for the courier.',
		'enable_for_methods' => array(),
		'enable_for_virtual' => 'yes',
	)
);
foreach ( array( 'bacs', 'cheque', 'paypal' ) as $nova_gateway ) {
	$nova_settings            = (array) get_option( "woocommerce_{$nova_gateway}_settings", array() );
	$nova_settings['enabled'] = 'no';
	update_option( "woocommerce_{$nova_gateway}_settings", $nova_settings );
}
WP_CLI::log( 'Payment: Cash on delivery enabled, other gateways disabled.' );

// ----------------------------------------------------------------------
// 3. Shipping: one worldwide zone, free over $99 else flat $9.95.
// ----------------------------------------------------------------------

$nova_zone    = new WC_Shipping_Zone( 0 ); // "Locations not covered by your other zones".
$nova_methods = array();
foreach ( $nova_zone->get_shipping_methods() as $nova_method ) {
	$nova_methods[ $nova_method->id ] = $nova_method->instance_id;
}
if ( ! isset( $nova_methods['free_shipping'] ) ) {
	$nova_methods['free_shipping'] = $nova_zone->add_shipping_method( 'free_shipping' );
}
if ( ! isset( $nova_methods['flat_rate'] ) ) {
	$nova_methods['flat_rate'] = $nova_zone->add_shipping_method( 'flat_rate' );
}
update_option(
	'woocommerce_free_shipping_' . $nova_methods['free_shipping'] . '_settings',
	array(
		'title'            => 'Free shipping',
		'requires'         => 'min_amount',
		'min_amount'       => '99',
		'ignore_discounts' => 'no',
	)
);
update_option(
	'woocommerce_flat_rate_' . $nova_methods['flat_rate'] . '_settings',
	array(
		'title'      => 'Standard shipping',
		'tax_status' => 'none',
		'cost'       => '9.95',
	)
);
WC_Cache_Helper::get_transient_version( 'shipping', true );
WP_CLI::log( 'Shipping: free over $99, otherwise $9.95.' );

// ----------------------------------------------------------------------
// 4. Pages: WooCommerce pages (classic shortcodes), home, shipping info.
// ----------------------------------------------------------------------

WC_Install::create_pages();

// Classic shortcodes keep cart/checkout on the PHP hook pipeline the theme and
// nova-store-core plugin customise (block checkout uses the Store API instead).
$nova_shortcodes = array(
	'cart'     => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->',
	'checkout' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
);
foreach ( $nova_shortcodes as $nova_page => $nova_content ) {
	$nova_page_id = wc_get_page_id( $nova_page );
	if ( $nova_page_id > 0 ) {
		wp_update_post(
			array(
				'ID'           => $nova_page_id,
				'post_content' => $nova_content,
			)
		);
	}
}

/**
 * Create or update a page by slug.
 *
 * @param string $slug    Page slug.
 * @param string $title   Title.
 * @param string $content Content.
 * @return int Page ID.
 */
function nova_seed_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	$data     = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	);
	if ( $existing ) {
		$data['ID'] = $existing->ID;
		return (int) wp_update_post( $data );
	}
	return (int) wp_insert_post( $data );
}

$nova_home_id = nova_seed_page( 'home', 'Home', '' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $nova_home_id );

$nova_shipping_page = nova_seed_page(
	'shipping-returns',
	'Shipping & Returns',
	"<!-- wp:heading --><h2>Shipping</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Orders placed before 2pm on a business day are dispatched the next business day. Orders over \$99 ship free; otherwise standard tracked shipping is \$9.95.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Returns</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Changed your mind? Return any item within 30 days in its original condition for a full refund. We send a prepaid label.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Warranty</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Every product carries a 2-year NOVA warranty covering manufacturing defects.</p><!-- /wp:paragraph -->"
);

// Remove WordPress starter content so the demo looks like a real store.
foreach ( array(
	'hello-world' => 'post',
	'sample-page' => 'page',
) as $nova_slug => $nova_type ) {
	$nova_post = get_page_by_path( $nova_slug, OBJECT, $nova_type );
	if ( $nova_post ) {
		wp_delete_post( $nova_post->ID, true );
	}
}
WP_CLI::log( 'Pages ready (home, shop, cart, checkout, my account, shipping & returns).' );

// ----------------------------------------------------------------------
// 5. Media helper
// ----------------------------------------------------------------------

/**
 * Import a bundled image once and return its attachment ID.
 *
 * @param string $file Image file name in /seed/images.
 * @param string $alt  Alt text.
 * @return int Attachment ID or 0.
 */
function nova_seed_image( $file, $alt ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_key'       => '_nova_seed_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off CLI seeding.
			'meta_value'     => $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
			'posts_per_page' => 1,
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}

	$source = NOVA_SEED_IMAGES . $file;
	if ( ! is_readable( $source ) ) {
		WP_CLI::warning( "Missing image {$source}" );
		return 0;
	}

	$tmp = wp_tempnam( $file );
	copy( $source, $tmp );

	$id = media_handle_sideload(
		array(
			'name'     => $file,
			'tmp_name' => $tmp,
		),
		0,
		$alt
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		WP_CLI::warning( "Image import failed for {$file}: " . $id->get_error_message() );
		return 0;
	}

	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_nova_seed_file', $file );

	return (int) $id;
}

// ----------------------------------------------------------------------
// 6. Categories
// ----------------------------------------------------------------------

$nova_categories = array(
	'audio'         => array( 'Audio', 'Headphones, earbuds and speakers tuned for long listening sessions.', 'studio-pro-headphones.jpg' ),
	'keyboards'     => array( 'Keyboards', 'Mechanical and low-profile keyboards that make typing feel deliberate.', 'tactile-75-keyboard.jpg' ),
	'mice'          => array( 'Mice', 'Precise, comfortable mice for work and play.', 'phantom-gaming-mouse.jpg' ),
	'workspace'     => array( 'Workspace', 'Stands, risers and lighting for a healthier, calmer desk.', 'aluminium-laptop-stand.jpg' ),
	'charging-hubs' => array( 'Charging & Hubs', 'Fast chargers and USB-C hubs to power and connect everything.', 'gan-charger.jpg' ),
);

$nova_term_ids = array();
$nova_order    = 0;
foreach ( $nova_categories as $nova_slug => list( $nova_name, $nova_desc, $nova_img ) ) {
	$nova_term = get_term_by( 'slug', $nova_slug, 'product_cat' );
	if ( $nova_term ) {
		wp_update_term(
			$nova_term->term_id,
			'product_cat',
			array(
				'name'        => $nova_name,
				'description' => $nova_desc,
			)
		);
		$nova_term_id = (int) $nova_term->term_id;
	} else {
		$nova_result = wp_insert_term(
			$nova_name,
			'product_cat',
			array(
				'slug'        => $nova_slug,
				'description' => $nova_desc,
			)
		);
		if ( is_wp_error( $nova_result ) ) {
			WP_CLI::error( $nova_result->get_error_message() );
		}
		$nova_term_id = (int) $nova_result['term_id'];
	}
	update_term_meta( $nova_term_id, 'order', $nova_order++ );
	$nova_term_ids[ $nova_slug ] = $nova_term_id;
}
WP_CLI::log( 'Categories ready.' );

// ----------------------------------------------------------------------
// 7. Products
// ----------------------------------------------------------------------

/*
 * sku, name, category, regular, sale, featured, stock qty (null = not managed, 0 = sold out),
 * total sales (drives "popularity" sort), image, short description bullets, description, attributes.
 */
$nova_products = array(
	array(
		'NV-AUD-001',
		'Aura ANC Wireless Headphones',
		'audio',
		'249',
		'219',
		true,
		24,
		180,
		'aura-anc-headphones.jpg',
		array( 'Adaptive noise cancelling with transparency mode', '40-hour battery, 10 min charge = 5 hours', 'Multipoint Bluetooth 5.3 with LDAC' ),
		'Aura blocks out open-plan chatter and cabin hum without the pressure feeling of older ANC headphones. Memory-foam cushions and a 254 g frame mean you forget you are wearing them by mid-morning.',
		array(
			'Connectivity' => 'Bluetooth 5.3, USB-C audio',
			'Battery life' => '40 hours (ANC on)',
			'Weight'       => '254 g',
		),
	),
	array(
		'NV-AUD-002',
		'Studio Pro Over-Ear Headphones',
		'audio',
		'189',
		'',
		true,
		15,
		140,
		'studio-pro-headphones.jpg',
		array( 'Neutral studio tuning with 50 mm drivers', 'Detachable cable plus Bluetooth', 'Fold-flat design with hard case' ),
		'A reference sound you can trust for mixing, editing or simply hearing music the way it was recorded. Wired for zero latency, wireless when you are on the move.',
		array(
			'Driver'       => '50 mm dynamic',
			'Connectivity' => 'Bluetooth 5.2, 3.5 mm',
			'Weight'       => '280 g',
		),
	),
	array(
		'NV-AUD-003',
		'Reference Wired Headphones',
		'audio',
		'99',
		'',
		false,
		0,
		95,
		'reference-wired-headphones.jpg',
		array( 'Open-back acoustic design', 'Replaceable velour ear pads', 'Low 32 Ω impedance, no amp needed' ),
		'Lightweight, open-back and endlessly comfortable. The Reference is our most-loved wired headphone and is currently sold out while the next production run arrives.',
		array(
			'Impedance' => '32 Ω',
			'Cable'     => '1.8 m, 3.5 mm with 6.35 mm adapter',
			'Weight'    => '230 g',
		),
	),
	array(
		'NV-AUD-004',
		'Pulse True Wireless Earbuds',
		'audio',
		'129',
		'109',
		true,
		40,
		210,
		'pulse-earbuds.jpg',
		array( 'Active noise cancelling in a 4.8 g bud', '8 h playback, 32 h with case', 'IPX5 sweat and splash resistant' ),
		'Pulse packs proper ANC and a warm, detailed sound into a tiny, secure-fit earbud. The pocketable case charges wirelessly.',
		array(
			'Battery life'     => '8 h (32 h with case)',
			'Water resistance' => 'IPX5',
			'Charging'         => 'USB-C, Qi wireless',
		),
	),
	array(
		'NV-AUD-005',
		'Heritage Leather Headphones',
		'audio',
		'159',
		'',
		false,
		null,
		60,
		'retro-classic-headphones.jpg',
		array( 'Machined aluminium cups', 'Genuine leather headband and pads', 'Warm, vintage-inspired tuning' ),
		'A modern take on classic hi-fi headphones. Heritage combines brushed aluminium with soft leather that ages beautifully.',
		array(
			'Materials'    => 'Aluminium, leather',
			'Connectivity' => '3.5 mm wired',
			'Weight'       => '310 g',
		),
	),
	array(
		'NV-AUD-006',
		'Mono Bookshelf Speakers (Pair)',
		'audio',
		'199',
		'',
		false,
		8,
		45,
		'bookshelf-speakers.jpg',
		array( 'Built-in 2 × 30 W amplifier', 'Bluetooth, optical and RCA inputs', 'Real wood veneer cabinets' ),
		'Room-filling sound from a compact pair of powered speakers. Plug in your TV, turntable or computer, or stream from your phone.',
		array(
			'Power'  => '2 × 30 W RMS',
			'Inputs' => 'Bluetooth 5.0, optical, RCA',
			'Finish' => 'Walnut veneer',
		),
	),
	array(
		'NV-KEY-001',
		'Tactile 75 Mechanical Keyboard',
		'keyboards',
		'149',
		'',
		true,
		30,
		160,
		'tactile-75-keyboard.jpg',
		array( 'Compact 75% layout with function row', 'Hot-swappable tactile switches', 'Wireless (2.4 GHz + Bluetooth) or USB-C' ),
		'Everything you need, nothing you do not. The Tactile 75 keeps the function row and arrows in a footprint that leaves room for your mouse.',
		array(
			'Layout'   => '75%, ANSI',
			'Switches' => 'Tactile, hot-swap',
			'Battery'  => '4000 mAh',
		),
	),
	array(
		'NV-KEY-002',
		'Chroma 65 Hot-Swap Keyboard',
		'keyboards',
		'169',
		'',
		false,
		3,
		120,
		'chroma-mechanical-keyboard.jpg',
		array( 'Double-shot PBT keycaps in a colourway', 'Gasket-mounted for a soft, quiet feel', 'Linear switches, factory lubed' ),
		'Chroma brings colour back to the desk with thick PBT keycaps and a gasket-mounted case that sounds as good as it feels.',
		array(
			'Layout'   => '65%, ANSI',
			'Switches' => 'Linear, hot-swap',
			'Keycaps'  => 'Double-shot PBT',
		),
	),
	array(
		'NV-KEY-003',
		'Slim Wireless Keyboard',
		'keyboards',
		'79',
		'69',
		false,
		50,
		90,
		'slim-wireless-keyboard.jpg',
		array( 'Low-profile scissor switches', 'Pairs with up to 3 devices', 'Rechargeable, 3-month battery' ),
		'A quiet, slim keyboard for laptops and tablets. Switch between three devices with a single key.',
		array(
			'Connectivity' => 'Bluetooth 5.1',
			'Battery'      => 'Up to 3 months',
			'Weight'       => '420 g',
		),
	),
	array(
		'NV-KEY-004',
		'Essential Wired Keyboard',
		'keyboards',
		'49',
		'',
		false,
		null,
		70,
		'minimal-wired-keyboard.jpg',
		array( 'Full-size layout with numpad', 'Whisper-quiet keys', 'Plug and play over USB-C' ),
		'A clean, dependable full-size keyboard. No drivers, no batteries, no fuss.',
		array(
			'Layout'       => 'Full-size, ANSI',
			'Connectivity' => 'USB-C cable',
			'Weight'       => '560 g',
		),
	),
	array(
		'NV-MOU-001',
		'Glide Wireless Mouse',
		'mice',
		'59',
		'',
		false,
		60,
		150,
		'glide-wireless-mouse.jpg',
		array( 'Sculpted, right-handed shape', 'Silent clicks', '70-day battery, USB-C charging' ),
		'Glide fits the hand naturally and moves silently, so you can work late without waking anyone.',
		array(
			'Sensor'       => '4000 DPI optical',
			'Connectivity' => 'Bluetooth, 2.4 GHz',
			'Battery'      => '70 days',
		),
	),
	array(
		'NV-MOU-002',
		'Phantom Ultralight Mouse',
		'mice',
		'89',
		'',
		true,
		22,
		130,
		'phantom-gaming-mouse.jpg',
		array( '58 g ultralight shell', '26,000 DPI flagship sensor', 'Low-latency 2.4 GHz wireless' ),
		'Built for speed and accuracy, Phantom is light enough to flick and precise enough to trust.',
		array(
			'Weight'       => '58 g',
			'Sensor'       => '26,000 DPI',
			'Polling rate' => '1000 Hz',
		),
	),
	array(
		'NV-MOU-003',
		'Vector RGB Gaming Mouse',
		'mice',
		'69',
		'55',
		false,
		4,
		85,
		'rgb-gaming-mouse.jpg',
		array( 'Customisable RGB lighting', '8 programmable buttons', 'Braided, flexible cable' ),
		'Vector gives you buttons where you need them and lighting that matches your setup.',
		array(
			'Buttons'      => '8 programmable',
			'Sensor'       => '16,000 DPI',
			'Connectivity' => 'USB-A wired',
		),
	),
	array(
		'NV-WRK-001',
		'Aluminium Laptop Stand',
		'workspace',
		'69',
		'',
		true,
		35,
		175,
		'aluminium-laptop-stand.jpg',
		array( 'Raises your screen to eye level', 'Six height positions', 'Folds flat for travel' ),
		'Bring your laptop up to eye level and your shoulders down. The stand is machined from a single sheet of aluminium for a solid, wobble-free base.',
		array(
			'Material' => 'Anodised aluminium',
			'Fits'     => '10–17 inch laptops',
			'Weight'   => '760 g',
		),
	),
	array(
		'NV-WRK-002',
		'Arc LED Desk Lamp',
		'workspace',
		'89',
		'',
		false,
		12,
		50,
		'arc-desk-lamp.jpg',
		array( 'Flicker-free, high-CRI LED', 'Adjustable colour temperature', 'Weighted steel base' ),
		'Even, glare-free light for late sessions. Dial in warm or cool white to suit the time of day.',
		array(
			'Brightness'         => '800 lm',
			'Colour temperature' => '2700–6500 K',
			'Material'           => 'Powder-coated steel',
		),
	),
	array(
		'NV-WRK-003',
		'Oak Monitor Riser',
		'workspace',
		'119',
		'',
		false,
		6,
		40,
		'monitor-riser.jpg',
		array( 'Solid oak with steel legs', 'Space underneath for a keyboard', 'Supports up to 30 kg' ),
		'Lift your monitor and clear your desk in one move. Slide your keyboard underneath when you are done for the day.',
		array(
			'Material'   => 'Solid oak, steel',
			'Dimensions' => '90 × 25 × 12 cm',
			'Max load'   => '30 kg',
		),
	),
	array(
		'NV-CHG-001',
		'Dock Pro USB-C Docking Station',
		'charging-hubs',
		'129',
		'',
		true,
		2,
		190,
		'usb-c-dock.jpg',
		array( 'Dual 4K display output over one cable', '8 ports: HDMI, DisplayPort, USB-A, USB-C, SD, Ethernet', '100 W pass-through laptop charging' ),
		'Plug in one USB-C cable and your laptop becomes a full desktop: two displays, wired network, drives and charging. Aluminium housing stays cool and silent.',
		array(
			'Ports'        => 'HDMI 4K60, 2 × USB-A, USB-C, SD, microSD, Ethernet, PD',
			'Pass-through' => '100 W',
			'Cable'        => '15 cm',
		),
	),
	array(
		'NV-CHG-002',
		'65W GaN USB-C Charger',
		'charging-hubs',
		'49',
		'',
		false,
		80,
		220,
		'gan-charger.jpg',
		array( 'Charges a laptop, phone and earbuds at once', 'Gallium nitride: half the size of standard chargers', 'Foldable plug' ),
		'A single compact charger for everything in your bag. Smart power sharing gives each device what it needs.',
		array(
			'Output' => '65 W total',
			'Ports'  => '2 × USB-C, 1 × USB-A',
			'Size'   => '45 × 45 × 30 mm',
		),
	),
	array(
		'NV-CHG-003',
		'MagDock Wireless Charger',
		'charging-hubs',
		'39',
		'29',
		false,
		45,
		100,
		'wireless-charging-pad.jpg',
		array( 'Magnetic alignment, 15 W fast charging', 'Works through most cases', 'Includes USB-C cable' ),
		'Snap your phone on and forget about cables. MagDock aligns perfectly every time.',
		array(
			'Output'        => '15 W',
			'Compatibility' => 'Qi / Qi2 phones',
			'Cable'         => '1 m USB-C',
		),
	),
);

/**
 * Create or update one product (matched by SKU).
 *
 * @param array $data       Row from $nova_products.
 * @param int   $menu_order Sort position.
 * @param array $term_ids   Category slug => term ID.
 */
function nova_seed_product( $data, $menu_order, $term_ids ) {
	list( $sku, $name, $cat, $regular, $sale, $featured, $qty, $sales, $img, $bullets, $desc, $attrs ) = $data;

	$product_id = wc_get_product_id_by_sku( $sku );
	$product    = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();

	$product->set_name( $name );
	$product->set_slug( sanitize_title( $name ) );
	$product->set_sku( $sku );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_regular_price( $regular );
	$product->set_sale_price( $sale );
	$product->set_featured( $featured );
	$product->set_menu_order( $menu_order );
	$product->set_category_ids( array( $term_ids[ $cat ] ) );
	$product->set_short_description( '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $bullets ) ) . '</li></ul>' );
	$product->set_description( '<p>' . esc_html( $desc ) . '</p>' );
	$product->set_total_sales( $sales );
	$product->set_reviews_allowed( true );

	if ( null === $qty ) {
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
	} else {
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $qty );
		$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
	}

	$attributes = array();
	foreach ( $attrs as $attr_name => $attr_value ) {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( $attr_name );
		$attribute->set_options( array( $attr_value ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attributes[] = $attribute;
	}
	$product->set_attributes( $attributes );

	$image_id = nova_seed_image( $img, $name );
	if ( $image_id ) {
		$product->set_image_id( $image_id );
	}

	$product->save();
	WP_CLI::log( sprintf( '  %s %s (#%d)', $product_id ? 'updated' : 'created', $name, $product->get_id() ) );
}

foreach ( $nova_products as $nova_index => $nova_p ) {
	nova_seed_product( $nova_p, $nova_index, $nova_term_ids );
}

// Category tiles use images already imported for products.
foreach ( $nova_categories as $nova_slug => list( , , $nova_img ) ) {
	$nova_thumb = nova_seed_image( $nova_img, $nova_categories[ $nova_slug ][0] );
	if ( $nova_thumb ) {
		update_term_meta( $nova_term_ids[ $nova_slug ], 'thumbnail_id', $nova_thumb );
	}
}

// Keep "Uncategorized" out of navigation by leaving it empty.
WP_CLI::log( 'Products ready: ' . count( $nova_products ) . '.' );

// ----------------------------------------------------------------------
// 8. Menus (editable in Appearance → Menus)
// ----------------------------------------------------------------------

/**
 * Create a menu if missing, replace its items, and assign it to a location.
 *
 * @param string $name     Menu name.
 * @param string $location Theme location.
 * @param array  $items    Items: array( 'type' => 'page'|'term', 'id' => int, 'title' => string ).
 */
function nova_seed_menu( $name, $location, $items ) {
	$menu    = wp_get_nav_menu_object( $name );
	$menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $name );

	foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
		wp_delete_post( $item->ID, true );
	}

	foreach ( $items as $position => $item ) {
		$args = array(
			'menu-item-status'   => 'publish',
			'menu-item-position' => $position + 1,
			'menu-item-title'    => $item['title'],
		);
		if ( 'term' === $item['type'] ) {
			$args += array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'product_cat',
				'menu-item-object-id' => $item['id'],
			);
		} else {
			$args += array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $item['id'],
			);
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}

	$locations              = (array) get_theme_mod( 'nav_menu_locations', array() );
	$locations[ $location ] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

$nova_primary = array(
	array(
		'type'  => 'page',
		'id'    => wc_get_page_id( 'shop' ),
		'title' => 'Shop all',
	),
);
foreach ( $nova_categories as $nova_slug => list( $nova_name ) ) {
	$nova_primary[] = array(
		'type'  => 'term',
		'id'    => $nova_term_ids[ $nova_slug ],
		'title' => $nova_name,
	);
}
nova_seed_menu( 'Primary', 'primary', $nova_primary );

nova_seed_menu(
	'Help',
	'footer',
	array(
		array(
			'type'  => 'page',
			'id'    => $nova_shipping_page,
			'title' => 'Shipping & returns',
		),
		array(
			'type'  => 'page',
			'id'    => wc_get_page_id( 'myaccount' ),
			'title' => 'My account',
		),
		array(
			'type'  => 'page',
			'id'    => wc_get_page_id( 'cart' ),
			'title' => 'Cart',
		),
		array(
			'type'  => 'page',
			'id'    => wc_get_page_id( 'checkout' ),
			'title' => 'Checkout',
		),
	)
);
WP_CLI::log( 'Menus ready.' );

WP_CLI::success( 'NOVA Store demo data seeded.' );
