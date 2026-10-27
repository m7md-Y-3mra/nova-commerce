<?php
/**
 * Home hero.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

$nova_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$nova_cat      = get_term_by( 'slug', 'audio', 'product_cat' );
?>
<section class="hero" aria-labelledby="hero-title">
	<div class="container hero-inner">
		<div class="hero-copy">
			<p class="eyebrow"><?php esc_html_e( 'New season · Workspace collection', 'nova-store' ); ?></p>
			<h1 id="hero-title" class="hero-title"><?php esc_html_e( 'Quiet tools for focused work.', 'nova-store' ); ?></h1>
			<p class="hero-lede"><?php esc_html_e( 'Audio, keyboards and desk essentials chosen for how they feel after eight hours — not how they look on a spec sheet.', 'nova-store' ); ?></p>
			<div class="hero-ctas">
				<a class="button button--primary" href="<?php echo esc_url( $nova_shop_url ); ?>">
					<?php esc_html_e( 'Shop the collection', 'nova-store' ); ?>
					<?php echo nova_store_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
				<?php if ( $nova_cat instanceof WP_Term ) : ?>
					<a class="button button--ghost" href="<?php echo esc_url( get_term_link( $nova_cat ) ); ?>"><?php esc_html_e( 'Explore audio', 'nova-store' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<figure class="hero-media">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/hero.jpg' ); ?>"
				alt="<?php esc_attr_e( 'A minimal wooden desk with a laptop on a stand, lit by a warm lamp', 'nova-store' ); ?>"
				width="1800" height="1200" fetchpriority="high" decoding="async">
		</figure>
	</div>
</section>
