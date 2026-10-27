<?php
/**
 * Default page template (also used by Cart, Checkout and My Account).
 *
 * @package NovaStore
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main page-main">
	<div class="container">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-article' ); ?>>
				<header class="page-header">
					<?php the_title( '<h1 class="page-title">', '</h1>' ); ?>
				</header>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();
