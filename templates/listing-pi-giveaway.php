<?php
defined( 'ABSPATH' ) || exit;
global $post;
$pig_settings = wp_parse_args(
	$pig_settings,
	array(
		'active_heading'       => __( 'Current Giveaways', 'pi-giveaways' ),
		'past_heading'         => __( 'Past Giveaways', 'pi-giveaways' ),
		'listing_sections'     => 'both',
		'archive_layout'       => 'grid',
		'archive_columns'      => 3,
		'archive_card_style'   => 'bordered',
		'archive_image_ratio'  => 'landscape',
		'archive_title_weight' => 800,
		'archive_title_size'   => 20,
		'archive_title_color'  => '#1d2327',
		'archive_show_excerpt' => 1,
		'archive_show_status'  => 1,
		'archive_show_prize'   => 1,
		'accent'               => '#2271b1',
	)
);
$pig_groups   = array(
	'current' => array(),
	'past'    => array(),
);
foreach ( $pig_query->posts as $pig_post ) {
	$details = wp_parse_args(
		get_post_meta( $pig_post->ID, '_pigiveaways_details', true ),
		array(
			'status' => 'draft',
			'start'  => '',
			'end'    => '',
		)
	);
	$state   = $details['status'];
	$now     = time();
	if ( 'active' === $state && $details['start'] && $now < strtotime( $details['start'] ) ) {
		$state = 'upcoming';
	}
	if ( 'active' === $state && $details['end'] && $now >= strtotime( $details['end'] ) ) {
		$state = 'ended';
	}
	if ( in_array( $state, array( 'active', 'upcoming' ), true ) ) {
		$pig_groups['current'][] = array( $pig_post, $state );
	} elseif ( in_array( $state, array( 'ended', 'awarded', 'cancelled' ), true ) ) {
		$pig_groups['past'][] = array( $pig_post, $state );
	}
}
$pig_sections = 'both' === $pig_show ? array( 'current', 'past' ) : array( $pig_show );
?>
<section class="pig-page-listing" style="--pig-accent:<?php echo esc_attr( $pig_settings['accent'] ); ?>;--pig-archive-columns:<?php echo absint( $pig_settings['archive_columns'] ); ?>;--pig-card-title-weight:<?php echo absint( $pig_settings['archive_title_weight'] ); ?>;--pig-card-title-size:<?php echo absint( $pig_settings['archive_title_size'] ); ?>px;--pig-card-title-color:<?php echo esc_attr( $pig_settings['archive_title_color'] ); ?>" aria-label="<?php esc_attr_e( 'Giveaway listings', 'pi-giveaways' ); ?>">
	<?php
	foreach ( $pig_sections as $pig_group_key ) :
		$pig_heading = 'current' === $pig_group_key ? $pig_settings['active_heading'] : $pig_settings['past_heading'];
		$pig_items   = array_slice( $pig_groups[ $pig_group_key ], 0, $pig_per_section );
		?>
		<section class="pig-listing-section pig-listing-section--<?php echo esc_attr( $pig_group_key ); ?>">
			<h2 class="pig-listing-section-title"><?php echo esc_html( $pig_heading ); ?></h2>
			<?php
			if ( $pig_items ) :
				?>
				<div class="pig-archive-items pig-archive-items--<?php echo esc_attr( $pig_settings['archive_layout'] ); ?> pig-cards--<?php echo esc_attr( $pig_settings['archive_card_style'] ); ?> pig-images--<?php echo esc_attr( $pig_settings['archive_image_ratio'] ); ?>">
				<?php
				foreach ( $pig_items as $pig_item ) :
					$pig_current_post = $pig_item[0];
					$state            = $pig_item[1];
					$pig_giveaway_id  = $pig_current_post->ID;
					$details     = wp_parse_args(
						get_post_meta( $pig_giveaway_id, '_pigiveaways_details', true ),
						array(
							'prize' => '',
							'value' => '',
							'start' => '',
							'end'   => '',
						)
					);
					$state_label = 'active' === $state ? __( 'Open', 'pi-giveaways' ) : ucfirst( $state );
					?>
					<article id="pig-giveaway-<?php echo absint( $pig_giveaway_id ); ?>" class="pig-listing-card">
						<a class="pig-listing-image" href="<?php echo esc_url( get_permalink( $pig_giveaway_id ) ); ?>" aria-label="<?php /* translators: %s: Giveaway title. */ echo esc_attr( sprintf( __( 'View %s', 'pi-giveaways' ), get_the_title( $pig_giveaway_id ) ) ); ?>">
						<?php
						if ( has_post_thumbnail( $pig_giveaway_id ) ) {
							echo get_the_post_thumbnail( $pig_giveaway_id, 'large' );
						} else {
							echo '<span class="pig-listing-placeholder" aria-hidden="true">PI</span>';
						}
						?>
						<?php do_action( 'pigiveaways_listing_badges', $pig_giveaway_id ); ?>
					<?php
					if ( ! empty( $pig_settings['archive_show_status'] ) ) :
						?>
															<span class="pig-listing-status pig-listing-status--<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $state_label ); ?></span><?php endif; ?></a>
						<div class="pig-listing-body">
							<h3 class="pig-listing-title"><a href="<?php echo esc_url( get_permalink( $pig_giveaway_id ) ); ?>" style="font-weight:<?php echo absint( $pig_settings['archive_title_weight'] ); ?>!important"><?php echo esc_html( get_the_title( $pig_giveaway_id ) ); ?></a></h3>
							<?php
							if ( ! empty( $pig_settings['archive_show_prize'] ) ) :
								?>
								<dl class="pig-listing-details">
								<?php
								if ( $details['prize'] ) :
									?>
									<div><dt><?php esc_html_e( 'Prize', 'pi-giveaways' ); ?></dt><dd><?php echo esc_html( $details['prize'] ); ?></dd></div><?php endif; ?>
								<?php
								if ( $details['value'] ) :
									?>
									<div><dt><?php esc_html_e( 'Value', 'pi-giveaways' ); ?></dt><dd><?php echo esc_html( $details['value'] ); ?></dd></div><?php endif; ?>
								<?php
								if ( $details['start'] ) :
									?>
									<div><dt><?php esc_html_e( 'Starts', 'pi-giveaways' ); ?></dt><dd><time datetime="<?php echo esc_attr( $details['start'] ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $details['start'] ) ) ); ?></time></dd></div><?php endif; ?>
								<?php
								if ( $details['end'] ) :
									?>
									<div><dt><?php esc_html_e( 'Ends', 'pi-giveaways' ); ?></dt><dd><time datetime="<?php echo esc_attr( $details['end'] ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $details['end'] ) ) ); ?></time></dd></div><?php endif; ?>
							</dl><?php endif; ?>
							<?php
							if ( ! empty( $pig_settings['archive_show_excerpt'] ) ) :
								$pig_excerpt = $pig_current_post->post_excerpt ? $pig_current_post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $pig_current_post->post_content ) );
								?>
								<div class="pig-listing-excerpt"><?php echo esc_html( wp_trim_words( $pig_excerpt, 24 ) ); ?></div><?php endif; ?>
							<a class="pig-listing-link" href="<?php echo esc_url( get_permalink( $pig_giveaway_id ) ); ?>"><?php esc_html_e( 'View giveaway', 'pi-giveaways' ); ?> <span aria-hidden="true">→</span></a>
						</div>
					</article>
					<?php
				endforeach;
				?>
			</div>
				<?php
			else :
				?>
						<p class="pig-archive-empty"><?php echo esc_html( 'current' === $pig_group_key ? __( 'There are no current giveaways at the moment.', 'pi-giveaways' ) : __( 'There are no past giveaways to display yet.', 'pi-giveaways' ) ); ?></p><?php endif; ?>
		</section>
	<?php endforeach; ?>
</section>
