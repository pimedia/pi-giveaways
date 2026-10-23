<?php
defined( 'ABSPATH' ) || exit;
$settings = wp_parse_args(
	get_option( 'pigiveaways_settings', array() ),
	array(
		'archive_title'        => __( 'Giveaways', 'pi-giveaways' ),
		'archive_layout'       => 'grid',
		'archive_columns'      => 3,
		'archive_card_style'   => 'bordered',
		'archive_image_ratio'  => 'landscape',
		'archive_show_excerpt' => 1,
		'archive_show_status'  => 1,
		'archive_show_prize'   => 1,
		'accent'               => '#2271b1',
	)
);
wp_enqueue_style( 'pigiveaways' );
get_header();
?>
<main id="primary" class="pig-archive" style="--pig-accent:<?php echo esc_attr( $settings['accent'] ); ?>;--pig-archive-columns:<?php echo absint( $settings['archive_columns'] ); ?>">
	<header class="pig-archive-header"><h1><?php echo esc_html( $settings['archive_title'] ? $settings['archive_title'] : post_type_archive_title( '', false ) ); ?></h1></header>
	<?php if ( have_posts() ) : ?>
		<div class="pig-archive-items pig-archive-items--<?php echo esc_attr( $settings['archive_layout'] ); ?> pig-cards--<?php echo esc_attr( $settings['archive_card_style'] ); ?> pig-images--<?php echo esc_attr( $settings['archive_image_ratio'] ); ?>">
			<?php
			while ( have_posts() ) :
				the_post();
				$details = wp_parse_args(
					get_post_meta( get_the_ID(), '_pigiveaways_details', true ),
					array(
						'status' => 'draft',
						'start'  => '',
						'end'    => '',
						'prize'  => '',
						'value'  => '',
					)
				);
				$state   = $details['status'];
				$now     = time();
				if ( 'active' === $state && $details['start'] && $now < strtotime( $details['start'] ) ) {
					$state = 'upcoming';
				} if ( 'active' === $state && $details['end'] && $now >= strtotime( $details['end'] ) ) {
					$state = 'ended';}
				?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'pig-listing-card' ); ?>>
				<a class="pig-listing-image" href="<?php the_permalink(); ?>" aria-label="<?php /* translators: %s: Giveaway title. */ echo esc_attr( sprintf( __( 'View %s', 'pi-giveaways' ), get_the_title() ) ); ?>">
				<?php
				if ( has_post_thumbnail() ) {
					the_post_thumbnail( 'large' );
				} else {
					echo '<span class="pig-listing-placeholder" aria-hidden="true">PI</span>';
				}
				?>
				<?php do_action( 'pigiveaways_listing_badges', get_the_ID() ); ?></a>
				<div class="pig-listing-body">
					<?php
					if ( ! empty( $settings['archive_show_status'] ) ) :
						?>
						<span class="pig-listing-status pig-listing-status--<?php echo esc_attr( $state ); ?>"><?php echo esc_html( ucfirst( $state ) ); ?></span><?php endif; ?>
					<h2 class="pig-listing-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php
					if ( ! empty( $settings['archive_show_prize'] ) && $details['prize'] ) :
						?>
						<p class="pig-listing-prize"><strong><?php esc_html_e( 'Prize:', 'pi-giveaways' ); ?></strong> <?php echo esc_html( $details['prize'] ); ?>
						<?php
						if ( $details['value'] ) :
							?>
						<span><?php echo esc_html( $details['value'] ); ?></span><?php endif; ?></p><?php endif; ?>
					<?php
					if ( ! empty( $settings['archive_show_excerpt'] ) ) :
						?>
						<div class="pig-listing-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></div><?php endif; ?>
					<a class="pig-listing-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View giveaway', 'pi-giveaways' ); ?> <span aria-hidden="true">→</span></a>
				</div>
			</article>
			<?php endwhile; ?>
		</div>
		<div class="pig-archive-pagination"><?php the_posts_pagination(); ?></div>
		<?php
	else :
		?>
		<p class="pig-archive-empty"><?php esc_html_e( 'There are no giveaways to display yet.', 'pi-giveaways' ); ?></p><?php endif; ?>
</main>
<?php
get_footer();
