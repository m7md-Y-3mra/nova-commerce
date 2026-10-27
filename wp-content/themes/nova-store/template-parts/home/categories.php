<?php
/**
 * Category tiles. Images come from each category's WooCommerce thumbnail.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

$nova_terms = nova_store_get_product_categories( 5 );
if ( ! $nova_terms ) {
	return;
}
?>
<section class="section categories" aria-labelledby="categories-title">
	<div class="container">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Browse', 'nova-store' ); ?></p>
				<h2 id="categories-title" class="section-title"><?php esc_html_e( 'Shop by category', 'nova-store' ); ?></h2>
			</div>
		</div>
		<ul class="category-grid">
			<?php foreach ( $nova_terms as $nova_term ) : ?>
				<?php $nova_thumb = (int) get_term_meta( $nova_term->term_id, 'thumbnail_id', true ); ?>
				<li class="category-card">
					<a href="<?php echo esc_url( get_term_link( $nova_term ) ); ?>">
						<?php if ( $nova_thumb ) : ?>
							<?php
							echo wp_get_attachment_image(
								$nova_thumb,
								'woocommerce_thumbnail',
								false,
								array(
									'alt'     => '',
									'loading' => 'lazy',
								)
							);
							?>
						<?php endif; ?>
						<span class="category-card__label">
							<span class="category-card__name"><?php echo esc_html( $nova_term->name ); ?></span>
							<span class="category-card__count">
								<?php
								/* translators: %d: product count. */
								echo esc_html( sprintf( _n( '%d product', '%d products', $nova_term->count, 'nova-store' ), $nova_term->count ) );
								?>
							</span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
