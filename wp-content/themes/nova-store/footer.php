<?php
/**
 * Site footer.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="site-footer">
	<div class="container footer-grid">
		<div class="footer-brand">
			<a class="wordmark wordmark--light" href="<?php echo esc_url( home_url( '/' ) ); ?>">NOVA<span class="wordmark__dot" aria-hidden="true">.</span></a>
			<p><?php esc_html_e( 'Considered tech accessories for people who do their best work at a desk. Designed to last, priced fairly, backed by real humans.', 'nova-store' ); ?></p>
		</div>

		<div class="footer-col">
			<h2 class="footer-title"><?php esc_html_e( 'Shop', 'nova-store' ); ?></h2>
			<ul>
				<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
					<li><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'All products', 'nova-store' ); ?></a></li>
				<?php endif; ?>
				<?php foreach ( nova_store_get_product_categories( 5 ) as $nova_term ) : ?>
					<li><a href="<?php echo esc_url( get_term_link( $nova_term ) ); ?>"><?php echo esc_html( $nova_term->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="footer-col">
			<h2 class="footer-title"><?php esc_html_e( 'Help', 'nova-store' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</div>

		<div class="footer-col">
			<h2 class="footer-title"><?php esc_html_e( 'Contact', 'nova-store' ); ?></h2>
			<address>
				<?php esc_html_e( 'NOVA Store', 'nova-store' ); ?><br>
				<?php esc_html_e( 'Mon–Fri, 9:00–17:00 UTC', 'nova-store' ); ?><br>
				<a href="mailto:hello@example.test">hello@example.test</a>
			</address>
		</div>
	</div>

	<div class="container footer-bottom">
		<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'nova-store' ); ?></p>
		<p class="footer-payments"><?php echo nova_store_icon( 'lock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Secure checkout · Cash on delivery', 'nova-store' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
