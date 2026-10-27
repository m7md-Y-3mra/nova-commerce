<?php
/**
 * Home page: hero, value props, categories, featured products, promo, on-sale picks.
 *
 * Product lists use WooCommerce's [products] shortcode so they inherit
 * native visibility, stock, pricing and add-to-cart behaviour.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main home-main">
	<?php
	get_template_part( 'template-parts/home/hero' );
	get_template_part( 'template-parts/home/values' );

	if ( class_exists( 'WooCommerce' ) ) {
		get_template_part( 'template-parts/home/categories' );
		get_template_part(
			'template-parts/home/products',
			null,
			array(
				'id'        => 'featured',
				'eyebrow'   => __( 'Editor’s picks', 'nova-store' ),
				'title'     => __( 'Featured gear', 'nova-store' ),
				'shortcode' => '[products limit="4" columns="4" visibility="featured" orderby="menu_order" order="ASC"]',
			)
		);
		get_template_part( 'template-parts/home/promo' );
		get_template_part(
			'template-parts/home/products',
			null,
			array(
				'id'        => 'deals',
				'eyebrow'   => __( 'Limited time', 'nova-store' ),
				'title'     => __( 'On sale now', 'nova-store' ),
				'shortcode' => '[products limit="4" columns="4" on_sale="true"]',
			)
		);
	}
	?>
</main>
<?php
get_footer();
