<?php
/**
 * Product page trust block.
 *
 * Rendered by Product_Hooks::render_trust_block() on
 * `woocommerce_single_product_summary` (priority 35).
 *
 * @package NovaStoreCore
 *
 * @var array<int, array{icon: string, title: string, text: string}> $items
 */

defined( 'ABSPATH' ) || exit;

$nova_icons = array(
	'truck'  => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17.5" cy="17.5" r="1.5"/>',
	'return' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
	'shield' => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
);
?>
<ul class="nova-trust" aria-label="<?php esc_attr_e( 'Delivery and guarantees', 'nova-store-core' ); ?>">
	<?php foreach ( $items as $nova_item ) : ?>
		<li class="nova-trust__item">
			<svg class="nova-trust__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<?php echo $nova_icons[ $nova_item['icon'] ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup defined above. ?>
			</svg>
			<span>
				<strong class="nova-trust__title"><?php echo esc_html( $nova_item['title'] ); ?></strong>
				<span class="nova-trust__text"><?php echo esc_html( $nova_item['text'] ); ?></span>
			</span>
		</li>
	<?php endforeach; ?>
</ul>
