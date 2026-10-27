<?php
/**
 * Fallback template for posts, archives and search results.
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main page-main">
	<div class="container">
		<header class="page-header">
			<h1 class="page-title">
				<?php
				if ( is_search() ) {
					/* translators: %s: search query. */
					printf( esc_html__( 'Results for “%s”', 'nova-store' ), esc_html( get_search_query() ) );
				} elseif ( is_archive() ) {
					echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
				} else {
					echo esc_html( get_bloginfo( 'name' ) );
				}
				?>
			</h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="post-list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
						<?php the_title( '<h2 class="post-card__title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' ); ?>
						<div class="post-card__excerpt"><?php the_excerpt(); ?></div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<div class="empty-state">
				<p><?php esc_html_e( 'Nothing found here yet.', 'nova-store' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
