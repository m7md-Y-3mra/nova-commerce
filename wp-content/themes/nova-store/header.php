<?php
/**
 * Site header.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

$nova_threshold = nova_store_free_shipping_threshold();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'nova-store' ); ?></a>

<?php if ( $nova_threshold ) : ?>
	<div class="announcement">
		<p>
			<?php
			printf(
				/* translators: %s: price threshold. */
				esc_html__( 'Free shipping on orders over %s · 30-day free returns', 'nova-store' ),
				esc_html( wp_strip_all_tags( wc_price( $nova_threshold, array( 'decimals' => 0 ) ) ) )
			);
			?>
		</p>
	</div>
<?php endif; ?>

<header class="site-header" id="site-header">
	<div class="container header-inner">
		<button class="header-action menu-toggle" type="button" aria-controls="site-nav" aria-expanded="false">
			<span class="menu-toggle__open"><?php echo nova_store_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="menu-toggle__close"><?php echo nova_store_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'nova-store' ); ?></span>
		</button>

		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					NOVA<span class="wordmark__dot" aria-hidden="true">.</span>
					<span class="screen-reader-text"><?php bloginfo( 'name' ); ?></span>
				</a>
			<?php endif; ?>
		</div>

		<nav class="site-nav" id="site-nav" aria-label="<?php esc_attr_e( 'Primary', 'nova-store' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_class'     => 'primary-menu',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => 'nova_store_primary_menu_fallback',
				)
			);
			?>
			<?php nova_store_language_switcher( 'nav' ); ?>
			<div class="site-nav__search">
				<?php
				if ( function_exists( 'get_product_search_form' ) ) {
					get_product_search_form();
				}
				?>
			</div>
		</nav>

		<div class="header-actions">
			<?php nova_store_language_switcher( 'header' ); ?>
			<?php if ( function_exists( 'get_product_search_form' ) ) : ?>
				<div class="header-search">
					<?php get_product_search_form(); ?>
				</div>
			<?php endif; ?>
			<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
				<a class="header-action" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
					<?php echo nova_store_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="screen-reader-text"><?php esc_html_e( 'My account', 'nova-store' ); ?></span>
				</a>
			<?php endif; ?>
			<?php echo nova_store_cart_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	</div>
</header>
