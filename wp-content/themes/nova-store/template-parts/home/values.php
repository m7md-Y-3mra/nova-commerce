<?php
/**
 * Value proposition strip.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

$nova_threshold = nova_store_free_shipping_threshold();
$nova_values    = array(
	array(
		'icon'  => 'truck',
		'title' => $nova_threshold
			/* translators: %s: price threshold. */
			? sprintf( __( 'Free shipping over %s', 'nova-store' ), wp_strip_all_tags( wc_price( $nova_threshold, array( 'decimals' => 0 ) ) ) )
			: __( 'Tracked shipping', 'nova-store' ),
		'text'  => __( 'Dispatched within one business day', 'nova-store' ),
	),
	array(
		'icon'  => 'return',
		'title' => __( '30-day free returns', 'nova-store' ),
		'text'  => __( 'Prepaid label, no questions asked', 'nova-store' ),
	),
	array(
		'icon'  => 'shield',
		'title' => __( '2-year warranty', 'nova-store' ),
		'text'  => __( 'On every product we sell', 'nova-store' ),
	),
	array(
		'icon'  => 'lock',
		'title' => __( 'Secure checkout', 'nova-store' ),
		'text'  => __( 'Pay in cash when your order arrives', 'nova-store' ),
	),
);
?>
<section class="values" aria-label="<?php esc_attr_e( 'Why shop with NOVA', 'nova-store' ); ?>">
	<ul class="container values-grid">
		<?php foreach ( $nova_values as $nova_value ) : ?>
			<li class="value">
				<?php echo nova_store_icon( $nova_value['icon'], 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div>
					<strong><?php echo esc_html( $nova_value['title'] ); ?></strong>
					<span><?php echo esc_html( $nova_value['text'] ); ?></span>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
