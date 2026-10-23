<?php
defined( 'ABSPATH' ) || exit;
get_header();
$layout   = (array) get_post_meta( get_queried_object_id(), '_pigiveaways_details', true );
$selected = $layout['layout'] ?? 'inherit';
if ( 'inherit' === $selected ) {
	$settings = get_option( 'pigiveaways_settings', array() );
	$selected = $settings['single_layout'] ?? 'full'; }
?>
<main id="primary" class="pig-single-layout pig-single-layout--<?php echo esc_attr( $selected ); ?>">
	<div class="pig-single-content">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="pig-single-title"><h1><?php the_title(); ?></h1></header>
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	</div>
	<?php
	if ( 'sidebar' === $selected ) :
		?>
		<aside class="pig-single-sidebar"><?php get_sidebar(); ?></aside><?php endif; ?>
</main>
<?php get_footer(); ?>
