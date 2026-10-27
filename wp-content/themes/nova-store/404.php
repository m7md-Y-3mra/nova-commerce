<?php
/**
 * 404 template.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main page-main">
	<div class="container empty-state empty-state--page">
		<p class="eyebrow">404</p>
		<h1 class="page-title"><?php esc_html_e( 'This page took a day off.', 'nova-store' ); ?></h1>
		<p><?php esc_html_e( 'The link may be broken or the product may have moved. Try a search instead.', 'nova-store' ); ?></p>
		<?php
		if ( function_exists( 'get_product_search_form' ) ) {
			get_product_search_form();
		}
		?>
		<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
			<p><a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Browse the shop', 'nova-store' ); ?></a></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
