<?php
/**
 * Promotional band.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

$nova_cat = get_term_by( 'slug', 'workspace', 'product_cat' );
$nova_url = $nova_cat instanceof WP_Term ? get_term_link( $nova_cat ) : wc_get_page_permalink( 'shop' );
?>
<section class="section promo" aria-labelledby="promo-title">
	<div class="container">
		<div class="promo-card">
			<img class="promo-card__img" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/promo.jpg' ); ?>" alt="" width="1600" height="1067" loading="lazy" decoding="async">
			<div class="promo-card__body">
				<p class="eyebrow eyebrow--light"><?php esc_html_e( 'The desk edit', 'nova-store' ); ?></p>
				<h2 id="promo-title" class="promo-card__title"><?php esc_html_e( 'Build a setup you actually want to sit at.', 'nova-store' ); ?></h2>
				<p><?php esc_html_e( 'Stands, risers and lighting that raise your screen, clear your desk and save your neck.', 'nova-store' ); ?></p>
				<a class="button button--light" href="<?php echo esc_url( $nova_url ); ?>"><?php esc_html_e( 'Shop workspace', 'nova-store' ); ?></a>
			</div>
		</div>
	</div>
</section>
