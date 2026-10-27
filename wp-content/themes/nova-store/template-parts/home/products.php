<?php
/**
 * Product row driven by a WooCommerce [products] shortcode.
 *
 * @package NovaStore
 *
 * @var array $args { id, eyebrow, title, shortcode }
 */

defined( 'ABSPATH' ) || exit;

$nova_html = do_shortcode( $args['shortcode'] );

// The shortcode returns an empty wrapper when nothing matches; hide the section then.
if ( false === strpos( $nova_html, '<li' ) ) {
	return;
}
?>
<section class="section product-row" aria-labelledby="<?php echo esc_attr( $args['id'] ); ?>-title">
	<div class="container">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?php echo esc_html( $args['eyebrow'] ); ?></p>
				<h2 id="<?php echo esc_attr( $args['id'] ); ?>-title" class="section-title"><?php echo esc_html( $args['title'] ); ?></h2>
			</div>
			<a class="link-arrow" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
				<?php esc_html_e( 'View all', 'nova-store' ); ?>
				<?php echo nova_store_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</div>
		<?php echo $nova_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce shortcode output. ?>
	</div>
</section>
