<?php
/**
 * WooCommerce presentation wiring for the theme.
 *
 * Uses WooCommerce's own templates and re-arranges them with hooks instead of
 * copying template files into the theme, so WooCommerce updates never leave
 * stale overrides behind.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

/*
 * Layout wrappers: replace WooCommerce's default content wrapper and sidebar.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/**
 * Open the shop content wrapper.
 */
function nova_store_wc_wrapper_start() {
	echo '<main id="main" class="site-main shop-main"><div class="container">';
}
add_action( 'woocommerce_before_main_content', 'nova_store_wc_wrapper_start', 10 );

/**
 * Close the shop content wrapper.
 */
function nova_store_wc_wrapper_end() {
	echo '</div></main>';
}
add_action( 'woocommerce_after_main_content', 'nova_store_wc_wrapper_end', 10 );

/**
 * Open the single-product top area (gallery + summary).
 *
 * The sticky summary is bounded by its parent. Without this wrapper the parent
 * is the whole product, so a tall summary slid down over the tabs and related
 * products while scrolling.
 */
function nova_store_product_top_start() {
	echo '<div class="product-top">';
}
add_action( 'woocommerce_before_single_product_summary', 'nova_store_product_top_start', 1 );

/**
 * Close the single-product top area, before the tabs.
 */
function nova_store_product_top_end() {
	echo '</div>';
}
add_action( 'woocommerce_after_single_product_summary', 'nova_store_product_top_end', 1 );

/**
 * Breadcrumb markup.
 *
 * @param array $args Breadcrumb defaults.
 * @return array
 */
function nova_store_breadcrumb_defaults( $args ) {
	$args['delimiter']   = '<span class="sep" aria-hidden="true">/</span>';
	$args['wrap_before'] = '<nav class="woocommerce-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'nova-store' ) . '">';
	return $args;
}
add_filter( 'woocommerce_breadcrumb_defaults', 'nova_store_breadcrumb_defaults' );

/*
 * Catalog grid sizing.
 */
add_filter( 'loop_shop_per_page', static fn() => 8 );
add_filter( 'loop_shop_columns', static fn() => 4 );
add_filter(
	'woocommerce_output_related_products_args',
	static function ( $args ) {
		$args['posts_per_page'] = 4;
		$args['columns']        = 4;
		return $args;
	}
);

/**
 * Shop toolbar: product search + category filter pills, shown on every catalog view
 * (including "no products found" and search results).
 */
function nova_store_shop_filters() {
	if ( ! is_shop() && ! is_product_taxonomy() && ! is_search() ) {
		return;
	}

	$current = is_product_category() ? get_queried_object_id() : 0;
	$terms   = nova_store_get_product_categories();
	?>
	<div class="shop-filters">
		<nav class="category-pills" aria-label="<?php esc_attr_e( 'Product categories', 'nova-store' ); ?>">
			<a class="pill<?php echo 0 === $current && ! is_search() ? ' is-active' : ''; ?>" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" <?php echo 0 === $current && ! is_search() ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'nova-store' ); ?></a>
			<?php foreach ( $terms as $term ) : ?>
				<a class="pill<?php echo $current === $term->term_id ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $term ) ); ?>" <?php echo $current === $term->term_id ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $term->name ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php get_product_search_form(); ?>
	</div>
	<?php
}
add_action( 'woocommerce_archive_description', 'nova_store_shop_filters', 20 );

/**
 * Category label above product titles in the grid.
 */
function nova_store_loop_category() {
	global $product;
	$terms = get_the_terms( $product->get_id(), 'product_cat' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		printf( '<span class="product-card__cat">%s</span>', esc_html( $terms[0]->name ) );
	}
}
add_action( 'woocommerce_shop_loop_item_title', 'nova_store_loop_category', 5 );

/**
 * Stock status in the grid. Text comes from woocommerce_get_availability_text,
 * which the nova-store-core plugin filters.
 */
function nova_store_loop_stock() {
	global $product;
	echo wc_get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output, escaped inside.
}
add_action( 'woocommerce_after_shop_loop_item_title', 'nova_store_loop_stock', 15 );

/**
 * Keep the header cart count in sync after AJAX add-to-cart / cart updates.
 *
 * @param array $fragments Selector => HTML map.
 * @return array
 */
function nova_store_cart_fragment( $fragments ) {
	$fragments['a.nova-cart-link'] = nova_store_cart_link();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'nova_store_cart_fragment' );

/**
 * Since WooCommerce 7.8 cart fragments are not loaded globally; the header needs them.
 */
function nova_store_enqueue_cart_fragments() {
	wp_enqueue_script( 'wc-cart-fragments' );
}
add_action( 'wp_enqueue_scripts', 'nova_store_enqueue_cart_fragments' );

/**
 * Friendlier empty-cart state above WooCommerce's "Return to shop" button.
 */
function nova_store_empty_cart_visual() {
	echo '<div class="empty-state__icon">' . nova_store_icon( 'bag', 40 ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
}
add_action( 'woocommerce_cart_is_empty', 'nova_store_empty_cart_visual', 5 );

/**
 * Show popular picks on the empty cart so the page is never a dead end.
 * cart-empty.php has no "after" hook, so CSS moves this below the "Return to shop" button.
 */
function nova_store_empty_cart_suggestions() {
	echo '<section class="empty-cart-suggestions"><h2 class="section-title">' . esc_html__( 'Popular right now', 'nova-store' ) . '</h2>';
	echo do_shortcode( '[products limit="4" columns="4" orderby="popularity" visibility="visible"]' );
	echo '</section>';
}
add_action( 'woocommerce_cart_is_empty', 'nova_store_empty_cart_suggestions', 20 );
