<?php
defined( 'ABSPATH' ) || exit;

final class PIGI_Admin {
	private $option = 'pigiveaways_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes_pigiveaways_giveaway', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_pigiveaways_giveaway', array( $this, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'all_admin_notices', array( $this, 'core_screen_header' ), 1 );
		add_action( 'admin_notices', array( $this, 'expired_giveaway_notice' ) );
		add_action( 'admin_notices', array( $this, 'review_notice' ) );
		add_action( 'admin_post_pigiveaways_review_notice', array( $this, 'handle_review_notice' ) );
		add_action( 'admin_post_pigiveaways_entry_action', array( $this, 'entry_action' ) );
		add_action( 'admin_post_pigiveaways_export', array( $this, 'export' ) );
		add_action( 'wp_ajax_pigiveaways_test_recaptcha', array( $this, 'test_recaptcha' ) );
		add_action( 'update_option_' . $this->option, array( $this, 'settings_updated' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( PIGIVEAWAYS_FILE ), array( $this, 'links' ) );
		add_filter( 'post_row_actions', array( $this, 'giveaway_row_actions' ), 10, 2 );
		add_filter( 'manage_pigiveaways_giveaway_posts_columns', array( $this, 'giveaway_columns' ) );
		add_action( 'manage_pigiveaways_giveaway_posts_custom_column', array( $this, 'giveaway_column' ), 10, 2 );
	}

	public function menu() {
		add_menu_page( __( 'PI Giveaways Settings', 'pi-giveaways' ), __( 'PI Giveaways', 'pi-giveaways' ), 'edit_posts', 'pigiveaways', array( $this, 'menu_landing' ), $this->icon(), 81 );
		add_submenu_page( 'pigiveaways', __( 'PI Giveaways Settings', 'pi-giveaways' ), __( 'Settings', 'pi-giveaways' ), 'edit_posts', 'pigiveaways', array( $this, 'menu_landing' ) );
		add_submenu_page( 'pigiveaways', __( 'All Giveaways', 'pi-giveaways' ), __( 'All Giveaways', 'pi-giveaways' ), 'edit_posts', 'edit.php?post_type=pigiveaways_giveaway' );
		add_submenu_page( 'pigiveaways', __( 'Add New Giveaway', 'pi-giveaways' ), __( 'Add New', 'pi-giveaways' ), 'edit_posts', 'post-new.php?post_type=pigiveaways_giveaway' );
		add_submenu_page( 'pigiveaways', __( 'Entries and Awards', 'pi-giveaways' ), __( 'Entries & Awards', 'pi-giveaways' ), 'edit_posts', 'pigiveaways-entries', array( $this, 'entries_page' ) );
		add_submenu_page( 'pigiveaways', __( 'Giveaway Categories', 'pi-giveaways' ), __( 'Categories', 'pi-giveaways' ), 'manage_categories', 'edit-tags.php?taxonomy=pigiveaways_category&post_type=pigiveaways_giveaway' );
	}

	public function menu_landing() {
		if ( current_user_can( 'manage_options' ) ) {
			$this->settings_page();
		} else {
			$this->dashboard();
		}
	}

	private function icon() {
		return 'dashicons-awards';
	}

	public function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=pigiveaways' ) ) . '">' . esc_html__( 'Settings', 'pi-giveaways' ) . '</a>' );
		return $links;
	}

	public function giveaway_row_actions( $actions, $post ) {
		if ( 'pigiveaways_giveaway' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$url                            = admin_url( 'admin.php?page=pigiveaways-entries&giveaway_id=' . absint( $post->ID ) );
		$actions['pigiveaways_entries'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Entries', 'pi-giveaways' ) . '</a>';
		return $actions;
	}

	public function giveaway_columns( $columns ) {
		$updated = array();
		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;
			if ( 'title' === $key ) {
				$updated['pigiveaways_entries'] = __( 'Entries', 'pi-giveaways' );
			}
		} return $updated;
	}

	public function giveaway_column( $column, $post_id ) {
		if ( 'pigiveaways_entries' !== $column ) {
			return;
		} $count = PIGI_Repository::entry_count( $post_id );
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=pigiveaways-entries&giveaway_id=' . absint( $post_id ) ) ) . '"><strong>' . absint( $count ) . '</strong> ' . esc_html( _n( 'entry', 'entries', $count, 'pi-giveaways' ) ) . '</a>';
	}

	public function expired_giveaway_notice() {
		if ( ! current_user_can( 'edit_posts' ) || ! apply_filters( 'pigiveaways_show_expiry_notice', true ) ) {
			return;
		} $giveaways = PIGI_Repository::expired_giveaways_awaiting_award();
		if ( ! $giveaways ) {
			return;
		} $first     = reset( $giveaways );
		$default_url = admin_url( 'admin.php?page=pigiveaways-entries&giveaway_id=' . absint( $first->ID ) );
		$url         = apply_filters( 'pigiveaways_expiry_notice_url', $default_url, $giveaways );
		/* translators: %d: Number of expired giveaways awaiting a manually selected winner. */
		$notice = sprintf( _n( '%d giveaway has closed and needs a manually selected winner.', '%d giveaways have closed and need manually selected winners.', count( $giveaways ), 'pi-giveaways' ), count( $giveaways ) );
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( $notice ) . '</strong> ' . esc_html__( 'The giveaway pages remain published, but their entry forms are hidden and late submissions are rejected.', 'pi-giveaways' ) . '</p><p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Review entries and choose winner', 'pi-giveaways' ) . '</a></p></div>';
	}

	/**
	 * Show a WordPress.org review request after the plugin has been installed for one week.
	 */
	public function review_notice() {
		if ( ! current_user_can( 'manage_options' ) || get_option( 'pigiveaways_review_notice_dismissed', false ) ) {
			return;
		}

		$installed_at = (int) get_option( 'pigiveaways_installed_at', 0 );
		if ( ! $installed_at ) {
			$installed_at = time();
			add_option( 'pigiveaways_installed_at', $installed_at, '', false );
		}

		$days_to_wait = absint( apply_filters( 'pigiveaways_review_notice_days', 7 ) );
		if ( ( time() - $installed_at ) < ( $days_to_wait * DAY_IN_SECONDS ) ) {
			return;
		}

		$next_notice_at = (int) get_option( 'pigiveaways_review_notice_next', 0 );
		if ( $next_notice_at && time() < $next_notice_at ) {
			return;
		}

		$remind_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'                    => 'pigiveaways_review_notice',
					'pigiveaways_review_action' => 'later',
				),
				admin_url( 'admin-post.php' )
			),
			'pigiveaways_review_notice'
		);
		$rated_url  = wp_nonce_url(
			add_query_arg(
				array(
					'action'                    => 'pigiveaways_review_notice',
					'pigiveaways_review_action' => 'rated',
				),
				admin_url( 'admin-post.php' )
			),
			'pigiveaways_review_notice'
		);
		?>
		<div class="notice notice-info pigiveaways-review-notice">
			<p><?php esc_html_e( 'Enjoying PI Giveaways? A quick 5-star rating on WordPress.org helps other WordPress users discover the free plugin and supports its continued development.', 'pi-giveaways' ); ?></p>
			<p>
				<a class="button button-primary" href="https://wordpress.org/support/plugin/pi-giveaways/reviews/#new-post" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a 5-star review', 'pi-giveaways' ); ?></a>
				<a class="button" href="<?php echo esc_url( $rated_url ); ?>"><?php esc_html_e( 'I’ve rated it', 'pi-giveaways' ); ?></a>
				<a class="button" href="<?php echo esc_url( $remind_url ); ?>"><?php esc_html_e( 'Remind me in one month', 'pi-giveaways' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss the review request permanently or snooze it for one month.
	 */
	public function handle_review_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to dismiss this notice.', 'pi-giveaways' ) );
		}

		check_admin_referer( 'pigiveaways_review_notice' );
		$review_action = isset( $_GET['pigiveaways_review_action'] ) ? sanitize_key( wp_unslash( $_GET['pigiveaways_review_action'] ) ) : 'later';
		if ( 'rated' === $review_action ) {
			update_option( 'pigiveaways_review_notice_dismissed', '1', false );
			delete_option( 'pigiveaways_review_notice_next' );
		} else {
			update_option( 'pigiveaways_review_notice_next', time() + MONTH_IN_SECONDS, false );
		}

		$redirect_url = wp_get_referer();
		wp_safe_redirect( $redirect_url ? $redirect_url : admin_url() );
		exit;
	}

	public function register_settings() {
		register_setting(
			'pigiveaways_group',
			$this->option,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
			)
		);
	}

	private function defaults() {
		return array(
			'archive_slug'              => 'giveaways',
			'archive_page_id'           => 0,
			'listing_position'          => 'after',
			'listing_sections'          => 'both',
			'archive_title'             => __( 'Giveaways', 'pi-giveaways' ),
			'active_heading'            => __( 'Current Giveaways', 'pi-giveaways' ),
			'past_heading'              => __( 'Past Giveaways', 'pi-giveaways' ),
			'archive_layout'            => 'grid',
			'archive_columns'           => 3,
			'archive_card_style'        => 'bordered',
			'archive_image_ratio'       => 'landscape',
			'archive_title_weight'      => 800,
			'archive_title_size'        => 20,
			'archive_title_color'       => '#1d2327',
			'archive_show_excerpt'      => 1,
			'archive_show_status'       => 1,
			'archive_show_prize'        => 1,
			'form_layout'               => 'two',
			'default_form_fields'       => $this->core_fields(),
			'single_layout'             => 'theme',
			'terms'                     => '',
			'privacy_page'              => 0,
			'promoter'                  => '',
			'thank_you'                 => __( 'Thank you. Your entry has been received.', 'pi-giveaways' ),
			'accent'                    => '#2271b1',
			'retention_enabled'         => 0,
			'retention_days'            => 90,
			'recaptcha_enabled'         => 0,
			'recaptcha_site_key'        => '',
			'recaptcha_secret_key'      => '',
			'recaptcha_threshold'       => '0.5',
			'show_countdown'            => 1,
			'email_participant_enabled' => 1,
			'email_participant_subject' => __( 'Your entry for {giveaway_title}', 'pi-giveaways' ),
			'email_participant_body'    => __( "Hello {first_name},\n\nYour entry for {giveaway_title} has been received.\n\nGiveaway: {giveaway_url}\n\nRegards,\n{site_name}", 'pi-giveaways' ),
			'email_admin_enabled'       => 1,
			'email_admin_address'       => get_option( 'admin_email' ),
			'email_admin_subject'       => __( 'New giveaway entry: {giveaway_title}', 'pi-giveaways' ),
			'email_admin_body'          => __( "A new entry has been received.\n\nGiveaway: {giveaway_title}\nEntrant: {first_name} {last_name}\nEmail: {email}\nEntry ID: {entry_id}", 'pi-giveaways' ),
		);
	}

	public function sanitize( $input ) {
		return array(
			'archive_slug'              => $this->sanitize_archive_slug( $input['archive_slug'] ?? 'giveaways' ),
			'archive_page_id'           => absint( $input['archive_page_id'] ?? 0 ),
			'listing_position'          => in_array( $input['listing_position'] ?? '', array( 'before', 'after', 'shortcode' ), true ) ? $input['listing_position'] : 'after',
			'listing_sections'          => in_array( $input['listing_sections'] ?? '', array( 'current', 'past', 'both' ), true ) ? $input['listing_sections'] : 'both',
			'archive_title'             => sanitize_text_field( $input['archive_title'] ?? __( 'Giveaways', 'pi-giveaways' ) ),
			'active_heading'            => sanitize_text_field( $input['active_heading'] ?? __( 'Current Giveaways', 'pi-giveaways' ) ),
			'past_heading'              => sanitize_text_field( $input['past_heading'] ?? __( 'Past Giveaways', 'pi-giveaways' ) ),
			'archive_layout'            => in_array( $input['archive_layout'] ?? '', array( 'grid', 'list', 'masonry' ), true ) ? $input['archive_layout'] : 'grid',
			'archive_columns'           => min( 4, max( 1, absint( $input['archive_columns'] ?? 3 ) ) ),
			'archive_card_style'        => in_array( $input['archive_card_style'] ?? '', array( 'bordered', 'elevated', 'minimal' ), true ) ? $input['archive_card_style'] : 'bordered',
			'archive_image_ratio'       => in_array( $input['archive_image_ratio'] ?? '', array( 'landscape', 'square', 'portrait', 'natural' ), true ) ? $input['archive_image_ratio'] : 'landscape',
			'archive_title_weight'      => in_array( absint( $input['archive_title_weight'] ?? 800 ), array( 600, 700, 800, 900 ), true ) ? absint( $input['archive_title_weight'] ) : 800,
			'archive_title_size'        => min( 36, max( 14, absint( $input['archive_title_size'] ?? 20 ) ) ),
			'archive_title_color'       => sanitize_hex_color( $input['archive_title_color'] ?? '' ) ? sanitize_hex_color( $input['archive_title_color'] ?? '' ) : '#1d2327',
			'archive_show_excerpt'      => empty( $input['archive_show_excerpt'] ) ? 0 : 1,
			'archive_show_status'       => empty( $input['archive_show_status'] ) ? 0 : 1,
			'archive_show_prize'        => empty( $input['archive_show_prize'] ) ? 0 : 1,
			'form_layout'               => in_array( $input['form_layout'] ?? '', array( 'one', 'two' ), true ) ? $input['form_layout'] : 'two',
			'default_form_fields'       => $this->ensure_core_fields( $this->sanitize_form_fields( $input['default_form_fields'] ?? array(), 'default' ) ),
			'single_layout'             => in_array( $input['single_layout'] ?? '', array( 'theme', 'full', 'sidebar' ), true ) ? $input['single_layout'] : 'theme',
			'terms'                     => wp_kses_post( $input['terms'] ?? '' ),
			'privacy_page'              => absint( $input['privacy_page'] ?? 0 ),
			'promoter'                  => sanitize_textarea_field( $input['promoter'] ?? '' ),
			'thank_you'                 => sanitize_text_field( $input['thank_you'] ?? '' ),
			'accent'                    => sanitize_hex_color( $input['accent'] ?? '' ) ? sanitize_hex_color( $input['accent'] ?? '' ) : '#2271b1',
			'retention_days'            => min( 3650, max( 1, absint( $input['retention_days'] ?? 90 ) ) ),
			'retention_enabled'         => empty( $input['retention_enabled'] ) ? 0 : 1,
			'recaptcha_enabled'         => empty( $input['recaptcha_enabled'] ) ? 0 : 1,
			'recaptcha_site_key'        => sanitize_text_field( $input['recaptcha_site_key'] ?? '' ),
			'recaptcha_secret_key'      => sanitize_text_field( $input['recaptcha_secret_key'] ?? '' ),
			'recaptcha_threshold'       => (string) min( 1, max( 0, (float) ( $input['recaptcha_threshold'] ?? 0.5 ) ) ),
			'show_countdown'            => empty( $input['show_countdown'] ) ? 0 : 1,
			'email_participant_enabled' => empty( $input['email_participant_enabled'] ) ? 0 : 1,
			'email_participant_subject' => sanitize_text_field( $input['email_participant_subject'] ?? '' ),
			'email_participant_body'    => sanitize_textarea_field( $input['email_participant_body'] ?? '' ),
			'email_admin_enabled'       => empty( $input['email_admin_enabled'] ) ? 0 : 1,
			'email_admin_address'       => sanitize_email( $input['email_admin_address'] ?? get_option( 'admin_email' ) ),
			'email_admin_subject'       => sanitize_text_field( $input['email_admin_subject'] ?? '' ),
			'email_admin_body'          => sanitize_textarea_field( $input['email_admin_body'] ?? '' ),
		);
	}

	public function settings_updated( $old_value, $value ) {
		if ( ( $old_value['archive_slug'] ?? 'giveaways' ) !== ( $value['archive_slug'] ?? 'giveaways' ) ) {
			update_option( 'pigiveaways_flush_rewrite', 1, false );
		}
		PIGI_Plugin::ensure_listing_page();
		PIGI_Plugin::instance()->sync_retention_schedule();
	}

	public function assets( $hook ) {
		$screen             = get_current_screen();
		$is_giveaway_screen = $screen && ( 'pigiveaways_giveaway' === $screen->post_type || 'pigiveaways_category' === $screen->taxonomy );
		if ( false === strpos( $hook, 'pigiveaways' ) && ! $is_giveaway_screen ) {
			return;
		}
		wp_enqueue_style( 'pigiveaways-admin', PIGIVEAWAYS_URL . 'assets/admin.css', array(), PIGIVEAWAYS_VERSION );
		wp_enqueue_style( 'pigiveaways-admin-preview', PIGIVEAWAYS_URL . 'assets/admin-preview.css', array( 'pigiveaways-admin' ), PIGIVEAWAYS_VERSION );
		wp_enqueue_script( 'pigiveaways-admin', PIGIVEAWAYS_URL . 'assets/admin.js', array(), PIGIVEAWAYS_VERSION, true );
		if ( false !== strpos( $hook, 'pigiveaways' ) ) {
			$settings = wp_parse_args( get_option( $this->option, array() ), $this->defaults() );
			$site_key = sanitize_text_field( $settings['recaptcha_site_key'] ?? '' );
			if ( $site_key ) {
				wp_enqueue_script( 'pigiveaways-recaptcha-v3-admin-test', add_query_arg( 'render', $site_key, 'https://www.google.com/recaptcha/api.js' ), array(), '3', true );
			}
			wp_localize_script(
				'pigiveaways-admin',
				'pigiveawaysRecaptchaTest',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'pigiveaways_test_recaptcha' ),
					'siteKey'    => $site_key,
					'configured' => (bool) ( $site_key && ! empty( $settings['recaptcha_secret_key'] ) ),
					'testing'    => __( 'Testing…', 'pi-giveaways' ),
					'error'      => __( 'The reCAPTCHA test could not be completed. Check the keys, allowed domain, network connection, and browser console, then try again.', 'pi-giveaways' ),
				)
			);
		}
	}

	public function test_recaptcha() {
		check_ajax_referer( 'pigiveaways_test_recaptcha', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to test this configuration.', 'pi-giveaways' ) ), 403 );
		}
		$settings = get_option( $this->option, array() );
		$secret   = sanitize_text_field( $settings['recaptcha_secret_key'] ?? '' );
		$token    = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		if ( ! $secret || ! $token ) {
			wp_send_json_error( array( 'message' => __( 'Save a valid site key and secret key before running the test.', 'pi-giveaways' ) ), 400 );
		}
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 12,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			/* translators: %s: Error returned while contacting Google reCAPTCHA. */
			wp_send_json_error( array( 'message' => sprintf( __( 'Google verification request failed: %s', 'pi-giveaways' ), $response->get_error_message() ) ), 502 );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['success'] ) || 'giveaway_configuration_test' !== ( $data['action'] ?? '' ) ) {
			$codes = implode( ', ', array_map( 'sanitize_text_field', (array) ( $data['error-codes'] ?? array() ) ) );
			/* translators: %s: Error codes returned by Google reCAPTCHA. */
			$message = $codes ? sprintf( __( 'Google rejected the configuration: %s', 'pi-giveaways' ), $codes ) : __( 'Google rejected the reCAPTCHA configuration.', 'pi-giveaways' );
			wp_send_json_error( array( 'message' => $message ), 400 );
		}
		$score     = isset( $data['score'] ) ? (float) $data['score'] : 0;
		$threshold = min( 1, max( 0, (float) ( $settings['recaptcha_threshold'] ?? 0.5 ) ) );
		if ( $score < $threshold ) {
			/* translators: 1: reCAPTCHA score, 2: configured minimum score. */
			wp_send_json_error( array( 'message' => sprintf( __( 'The keys work, but the test score %1$.2f is below your %2$.2f threshold.', 'pi-giveaways' ), $score, $threshold ) ), 400 );
		}
		/* translators: %s: reCAPTCHA test score. */
		wp_send_json_success( array( 'message' => sprintf( __( 'reCAPTCHA v3 is working. Test score: %.2f.', 'pi-giveaways' ), $score ) ) );
	}

	public function meta_boxes() {
		add_meta_box( 'pig-details', __( 'Giveaway Details', 'pi-giveaways' ), array( $this, 'details_box' ), 'pigiveaways_giveaway', 'normal', 'high' );
		add_meta_box( 'pig-form-builder', __( 'Giveaway Entry Form', 'pi-giveaways' ), array( $this, 'form_builder_box' ), 'pigiveaways_giveaway', 'normal', 'default' );
	}

	public function details_box( $post ) {
		wp_nonce_field( 'pigiveaways_save_giveaway', 'pigiveaways_nonce' );
		$v      = wp_parse_args(
			get_post_meta( $post->ID, '_pigiveaways_details', true ),
			array(
				'prize'        => '',
				'value'        => '',
				'start'        => '',
				'end'          => '',
				'winners_count' => 1,
				'status'       => 'draft',
				'question'     => '',
				'terms'        => '',
				'announcement' => '',
			)
		);
		$fields = array(
			'prize'     => 'Prize name',
			'value'     => 'Prize value',
			'start'     => 'Start date and time',
			'end'       => 'Close date and time',
			'event_url' => 'Related event URL',
		);
		echo '<div class="pig-fields">';
		foreach ( $fields as $key => $label ) {
			$type = in_array( $key, array( 'start', 'end' ), true ) ? 'datetime-local' : ( 'event_url' === $key ? 'url' : 'text' );
			echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="pig[' . esc_attr( $key ) . ']" value="' . esc_attr( $v[ $key ] ?? '' ) . '"' . ( 'event_url' === $key ? ' placeholder="https://"' : '' ) . '></label>'; }
		echo '<label><span>' . esc_html__( 'Number of winners', 'pi-giveaways' ) . '</span><input type="number" name="pig[winners_count]" value="' . absint( $v['winners_count'] ) . '" min="1" max="100"><small>' . esc_html__( 'The administrator must select this many winners manually.', 'pi-giveaways' ) . '</small></label>';
		echo '<label><span>' . esc_html__( 'Status', 'pi-giveaways' ) . '</span><select name="pig[status]">';
		foreach ( array(
			'draft'     => 'Draft',
			'active'    => 'Active',
			'ended'     => 'Ended',
			'awarded'   => 'Awarded',
			'cancelled' => 'Cancelled',
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $v['status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label><label><span>' . esc_html__( 'Page layout', 'pi-giveaways' ) . '</span><select name="pig[layout]">';
		foreach ( array(
			'inherit' => 'Use global layout',
			'theme'   => 'Theme default',
			'full'    => 'Full width — no sidebar',
			'sidebar' => 'Content + theme sidebar',
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $v['layout'] ?? 'inherit', $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label><label class="wide"><span>' . esc_html__( 'Terms override', 'pi-giveaways' ) . '</span><textarea name="pig[terms]" rows="5">' . esc_textarea( $v['terms'] ) . '</textarea></label><label class="wide"><span>' . esc_html__( 'Public award announcement', 'pi-giveaways' ) . '</span><textarea name="pig[announcement]" rows="4">' . esc_textarea( $v['announcement'] ) . '</textarea></label></div>';
	}

	public function form_builder_box( $post ) {
		$fields = (array) get_post_meta( $post->ID, '_pigiveaways_form_fields', true );
		$mode   = get_post_meta( $post->ID, '_pigiveaways_form_mode', true );
		$mode   = $mode ? $mode : 'default';
		if ( 'default' === $mode ) {
			$settings = wp_parse_args( get_option( $this->option, array() ), $this->defaults() );
			$fields   = $this->ensure_core_fields( $settings['default_form_fields'] ?? array() ); }
		if ( 'custom' === $mode ) {
			$fields = $this->ensure_core_fields( $fields );
		}
		echo '<div class="pig-shortcode-box"><strong>' . esc_html__( 'Form shortcode', 'pi-giveaways' ) . '</strong><p><code>[pigiveaways_form id=&quot;' . absint( $post->ID ) . '&quot;]</code></p><p class="description">' . esc_html__( 'Paste this shortcode into any WordPress post or page to display only this giveaway’s entry form.', 'pi-giveaways' ) . '</p></div>';
		echo '<p>' . esc_html__( 'The default form is managed in PI Giveaways Settings → Form. Choose Custom, then reorder or change requirements below to save a giveaway-specific form. Extend mode keeps the global form and appends only additional fields. Terms acceptance always remains required.', 'pi-giveaways' ) . '</p>';
		if ( $post->ID ) {
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=pigiveaways-entries&giveaway_id=' . absint( $post->ID ) ) ) . '">' . esc_html__( 'View this giveaway’s entries', 'pi-giveaways' ) . '</a></p>';
		}
		echo '<label class="pig-form-mode"><strong>' . esc_html__( 'Form configuration', 'pi-giveaways' ) . '</strong><select name="pigiveaways_form_mode">';
		foreach ( array(
			'default' => 'Use the global default form',
			'extend'  => 'Use default form + fields below',
			'custom'  => 'Use only the fields below',
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $mode, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label><div class="pig-form-builder" data-form-builder><div class="pig-custom-fields" data-form-fields data-next-index="' . absint( count( $fields ) ) . '">';
		foreach ( $fields as $index => $field ) {
			$this->form_field_row( $index, $field, 'pigiveaways_form_fields' );
		}
		echo '</div><button type="button" class="button" data-add-field>' . esc_html__( '+ Add giveaway field', 'pi-giveaways' ) . '</button><script type="text/html" data-field-template>';
		$this->form_field_row( '__INDEX__', array(), 'pigiveaways_form_fields' );
		echo '</script></div>';
	}

	private function form_field_row( $index, $field, $base_name ) {
		$field = wp_parse_args(
			$field,
			array(
				'id'       => '',
				'label'    => '',
				'type'     => 'text',
				'required' => 0,
				'enabled'  => 1,
				'options'  => '',
				'system'   => 0,
			)
		);
		$name  = $base_name . '[' . $index . ']';
		echo '<div class="pig-form-field-row" data-field-row><span class="pig-order-buttons"><button type="button" class="button-link" data-move-field="up" aria-label="Move field up">↑</button><button type="button" class="button-link" data-move-field="down" aria-label="Move field down">↓</button></span><input type="hidden" name="' . esc_attr( $name ) . '[id]" value="' . esc_attr( $field['id'] ) . '"><input type="hidden" name="' . esc_attr( $name ) . '[system]" value="' . absint( $field['system'] ) . '"><label><span>' . esc_html__( 'Label', 'pi-giveaways' ) . '</span><input type="text" name="' . esc_attr( $name ) . '[label]" value="' . esc_attr( $field['label'] ) . '" required></label><label><span>' . esc_html__( 'Type', 'pi-giveaways' ) . '</span><select name="' . esc_attr( $name ) . '[type]"' . ( $field['system'] ? ' disabled' : '' ) . '>';
		foreach ( array(
			'text'     => 'Text',
			'email'    => 'Email',
			'tel'      => 'Phone',
			'textarea' => 'Textarea',
			'select'   => 'Select',
			'checkbox' => 'Checkbox',
		) as $type => $label ) {
			echo '<option value="' . esc_attr( $type ) . '" ' . selected( $field['type'], $type, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		if ( $field['system'] ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '[type]" value="' . esc_attr( $field['type'] ) . '">';
		} echo '</label><label class="pig-field-options"><span>' . esc_html__( 'Options', 'pi-giveaways' ) . '</span><input type="text" name="' . esc_attr( $name ) . '[options]" value="' . esc_attr( $field['options'] ) . '" placeholder="Option 1, Option 2"></label><label class="pig-required"><input type="checkbox" name="' . esc_attr( $name ) . '[required]" value="1" ' . checked( $field['required'], 1, false ) . ( 'core_terms' === $field['id'] ? ' disabled' : '' ) . '> ' . esc_html__( 'Required', 'pi-giveaways' ) . '</label>';
		if ( 'core_terms' === $field['id'] ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '[required]" value="1"><input type="hidden" name="' . esc_attr( $name ) . '[enabled]" value="1">';
		} elseif ( $field['system'] ) {
			echo '<label class="pig-field-visible"><input type="checkbox" name="' . esc_attr( $name ) . '[enabled]" value="1" ' . checked( $field['enabled'], 1, false ) . '> ' . esc_html__( 'Show field', 'pi-giveaways' ) . '</label>';
		} else {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '[enabled]" value="1">';
		}
		if ( ! $field['system'] ) {
			echo '<button type="button" class="button-link-delete pig-remove-field">' . esc_html__( 'Remove', 'pi-giveaways' ) . '</button>';
		} else {
			echo '<span class="pig-system-badge">' . esc_html__( 'Built in', 'pi-giveaways' ) . '</span>';
		}
		echo '</div>';
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['pigiveaways_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pigiveaways_nonce'] ) ), 'pigiveaways_save_giveaway' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$in     = map_deep( (array) wp_unslash( $_POST['pig'] ?? array() ), 'sanitize_text_field' );
		$status = in_array( $in['status'] ?? '', array( 'draft', 'active', 'ended', 'awarded', 'cancelled' ), true ) ? $in['status'] : 'draft';
		$layout = in_array( $in['layout'] ?? '', array( 'inherit', 'theme', 'full', 'sidebar' ), true ) ? $in['layout'] : 'inherit';
		update_post_meta(
			$post_id,
			'_pigiveaways_details',
			array(
				'prize'        => sanitize_text_field( $in['prize'] ?? '' ),
				'value'        => sanitize_text_field( $in['value'] ?? '' ),
				'start'        => sanitize_text_field( $in['start'] ?? '' ),
				'end'          => sanitize_text_field( $in['end'] ?? '' ),
				'winners_count' => max( 1, min( 100, absint( $in['winners_count'] ?? 1 ) ) ),
				'event_url'    => esc_url_raw( $in['event_url'] ?? '' ),
				'status'       => $status,
				'layout'       => $layout,
				'question'     => sanitize_text_field( $in['question'] ?? '' ),
				'terms'        => wp_kses_post( $in['terms'] ?? '' ),
				'announcement' => wp_kses_post( $in['announcement'] ?? '' ),
			)
		);
		$mode         = sanitize_key( wp_unslash( $_POST['pigiveaways_form_mode'] ?? 'default' ) );
		$mode         = in_array( $mode, array( 'default', 'extend', 'custom' ), true ) ? $mode : 'default';
		$local_fields = $this->sanitize_form_fields( map_deep( (array) wp_unslash( $_POST['pigiveaways_form_fields'] ?? array() ), 'sanitize_text_field' ), 'giveaway' );
		if ( 'custom' === $mode ) {
			$stored_fields = $this->ensure_core_fields( $local_fields );
		} elseif ( 'extend' === $mode ) {
			$stored_fields = array_values(
				array_filter(
					$local_fields,
					static function ( $field ) {
						return empty( $field['system'] ) && 0 !== strpos( $field['id'], 'default_' );
					}
				)
			);
		} else {
			$stored_fields = array();
		}
		update_post_meta( $post_id, '_pigiveaways_form_fields', $stored_fields );
		update_post_meta( $post_id, '_pigiveaways_form_mode', $mode );
	}

	private function brand_header() {
		?>
		<div class="pit-header"><div class="pit-header__logo"><span class="pit-header__icon"><img src="<?php echo esc_url( PIGIVEAWAYS_URL . 'assets/pi-giveaways-logo.svg' ); ?>" alt=""></span><div><h1><?php esc_html_e( 'PI Giveaways', 'pi-giveaways' ); ?></h1><span class="pit-header__meta"><span class="pit-header__version">v<?php echo esc_html( PIGIVEAWAYS_VERSION ); ?></span>
		<?php
		if ( defined( 'PIGPRO_VERSION' ) ) :
			?>
			<span class="pit-header__pro-badge"><?php esc_html_e( 'Pro', 'pi-giveaways' ); ?></span><?php endif; ?></span></div></div><div class="pit-header__actions"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pigiveaways_giveaway' ) ); ?>" class="button"><?php esc_html_e( 'All Giveaways', 'pi-giveaways' ); ?></a><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pigiveaways_giveaway' ) ); ?>" class="button button-primary pit-header__add"><?php esc_html_e( '+ Add Giveaway', 'pi-giveaways' ); ?></a></div></div>
		<?php
	}

	private function header( $title ) {
		echo '<div class="wrap pit-wrap">';
		$this->brand_header();
		echo '<h1 class="pig-page-heading">' . esc_html( $title ) . '</h1>'; }

	public function core_screen_header() {
		$screen = get_current_screen();
		if ( ! $screen || ( 'pigiveaways_giveaway' !== $screen->post_type && 'pigiveaways_category' !== $screen->taxonomy ) ) {
			return;
		}
		echo '<div class="wrap pit-wrap pig-core-screen-header">';
		$this->brand_header();
		echo '</div>';
	}

	public function dashboard() {
		$this->header( __( 'PI Giveaways', 'pi-giveaways' ) );
		echo '<div class="pig-card"><h2>' . esc_html__( 'Giveaway workflow', 'pi-giveaways' ) . '</h2><ol><li>' . esc_html__( 'Create and publish a giveaway.', 'pi-giveaways' ) . '</li><li>' . esc_html__( 'Review and moderate its entries.', 'pi-giveaways' ) . '</li><li>' . esc_html__( 'Manually award a valid entry.', 'pi-giveaways' ) . '</li><li>' . esc_html__( 'Write and publish the announcement when ready.', 'pi-giveaways' ) . '</li></ol><p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=pigiveaways_giveaway' ) ) . '">' . esc_html__( 'Add New Giveaway', 'pi-giveaways' ) . '</a></p></div></div>';
	}

	public function entries_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Access denied.', 'pi-giveaways' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This parameter only filters a read-only admin listing.
		$giveaway_id = absint( wp_unslash( $_GET['giveaway_id'] ?? 0 ) );
		if ( $giveaway_id && ( 'pigiveaways_giveaway' !== get_post_type( $giveaway_id ) || ! current_user_can( 'edit_post', $giveaway_id ) ) ) {
			wp_die( esc_html__( 'You are not allowed to view entries for this giveaway.', 'pi-giveaways' ) );
		}
		$giveaways = get_posts(
			array(
				'post_type'   => 'pigiveaways_giveaway',
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => 100,
			)
		);
		$this->header( __( 'Entries & Awards', 'pi-giveaways' ) );
		if ( isset( $_GET['pigiveaways_notice'] ) ) {
			$notice_type = 'error' === sanitize_key( wp_unslash( $_GET['pigiveaways_notice_type'] ?? '' ) ) ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $notice_type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['pigiveaways_notice'] ) ) ) . '</p></div>';
		}
		echo '<form method="get" class="pig-filter"><input type="hidden" name="page" value="pi-giveaways-entries"><select name="giveaway_id"><option value="0">' . esc_html__( 'Select a giveaway', 'pi-giveaways' ) . '</option>';
		foreach ( $giveaways as $g ) {
			echo '<option value="' . absint( $g->ID ) . '" ' . selected( $giveaway_id, $g->ID, false ) . '>' . esc_html( $g->post_title ) . '</option>';
		}
		echo '</select><button class="button">' . esc_html__( 'View entries', 'pi-giveaways' ) . '</button></form>';
		if ( $giveaway_id ) {
			$entries = PIGI_Repository::entries( $giveaway_id );
			$awards  = PIGI_Repository::active_awards( $giveaway_id );
			$winner_limit = PIGI_Repository::winner_limit( $giveaway_id );
			$winner_limit_reached = count( $awards ) >= $winner_limit;
			echo '<div class="pig-card"><div class="pig-card-head"><h2>' . esc_html( get_the_title( $giveaway_id ) ) . '</h2><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pigiveaways_export&giveaway_id=' . $giveaway_id ), 'pigiveaways_export_' . $giveaway_id ) ) . '">' . esc_html__( 'Export CSV', 'pi-giveaways' ) . '</a></div>';
			echo '<h3>' . esc_html__( 'Current awards', 'pi-giveaways' ) . ' <small>(' . sprintf( esc_html__( '%1$d of %2$d selected', 'pi-giveaways' ), count( $awards ), $winner_limit ) . ')</small></h3>';
			if ( ! $awards ) {
				echo '<p>' . esc_html__( 'No winner has been awarded. Awards are always assigned manually.', 'pi-giveaways' ) . '</p>';
			} else {
				foreach ( $awards as $a ) {
					echo '<p class="pig-award">🏆 ' . esc_html( $a->first_name . ' ' . $a->last_name ) . ' <span>' . esc_html( $a->awarded_at ) . '</span></p>';
				}
			}
			echo '<div class="pig-table"><table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Submitted</th><th>Additional fields</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
			foreach ( $entries as $e ) {
				$extra     = json_decode( $e->fields ?? '', true );
				$submitted = get_date_from_gmt( $e->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
				echo '<tr><td>' . absint( $e->id ) . '</td><td>' . esc_html( $e->first_name . ' ' . $e->last_name ) . '</td><td>' . esc_html( $e->email ) . '</td><td>' . esc_html( $e->phone ?? '' ) . '</td><td><time datetime="' . esc_attr( mysql2date( DATE_ATOM, $e->created_at . ' +0000', false ) ) . '">' . esc_html( $submitted ) . '</time></td><td>';
				foreach ( (array) $extra as $label => $value ) {
					echo '<div><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</div>';
				} echo '</td><td><span class="pig-status pig-status--' . esc_attr( $e->status ) . '">' . esc_html( ucfirst( $e->status ) ) . '</span></td><td>';
				do_action( 'pigiveaways_entry_actions_before', $e, $giveaway_id );
				foreach ( array(
					'valid'        => 'Validate',
					'flagged'      => 'Flag',
					'disqualified' => 'Disqualify',
					'award'        => 'Award winner',
					'delete'       => 'Delete',
				) as $action => $label ) {
					if ( 'award' === $action && ( 'valid' !== $e->status || $winner_limit_reached || ! apply_filters( 'pigiveaways_show_award_action', true, $e ) ) ) {
						continue;
					} $url = wp_nonce_url( admin_url( 'admin-post.php?action=pigiveaways_entry_action&entry_id=' . $e->id . '&giveaway_id=' . $giveaway_id . '&operation=' . $action ), 'pigiveaways_entry_' . $e->id );
					echo '<a' . ( 'delete' === $action ? ' class="submitdelete" onclick="return confirm(\'' . esc_js( __( 'Permanently delete this entry and any award attached to it?', 'pi-giveaways' ) ) . '\')"' : '' ) . ' href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
				} echo '</td></tr>'; }
			echo '</tbody></table></div></div>'; }
		echo '</div>';
	}

	public function entry_action() {
		$entry_id = absint( wp_unslash( $_GET['entry_id'] ?? 0 ) );
		check_admin_referer( 'pigiveaways_entry_' . $entry_id );
		$entry = PIGI_Repository::entry( $entry_id );
		if ( ! $entry || ! current_user_can( 'edit_post', absint( $entry->giveaway_id ) ) ) {
			wp_die( esc_html__( 'Access denied.', 'pi-giveaways' ) );
		}
		$operation = sanitize_key( wp_unslash( $_GET['operation'] ?? '' ) );
		$notice = __( 'Entry updated.', 'pi-giveaways' );
		$notice_type = 'success';
		if ( 'award' === $operation ) {
			$result = PIGI_Repository::award( $entry_id );
			if ( is_wp_error( $result ) ) { $notice = $result->get_error_message(); $notice_type = 'error'; } else { $notice = __( 'Winner awarded manually.', 'pi-giveaways' ); }
		} elseif ( 'delete' === $operation ) {
			PIGI_Repository::delete_entry( $entry_id );
			$notice = __( 'Entry permanently deleted.', 'pi-giveaways' );
		} else {
			PIGI_Repository::set_status( $entry_id, $operation );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'pigiveaways-entries', 'giveaway_id' => absint( $entry->giveaway_id ), 'pigiveaways_notice' => $notice, 'pigiveaways_notice_type' => $notice_type ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function export() {
		$id = absint( wp_unslash( $_GET['giveaway_id'] ?? 0 ) );
		check_admin_referer( 'pigiveaways_export_' . $id );
		if ( 'pigiveaways_giveaway' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Access denied.', 'pi-giveaways' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=pi-giveaway-' . $id . '-entries.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'First name', 'Last name', 'Email', 'Phone', 'Additional fields', 'Status', 'Consent date (UTC)', 'Created (UTC)' ) );
		foreach ( PIGI_Repository::entries( $id, 100000 ) as $e ) {
			$row = array( $e->id, $e->first_name, $e->last_name, $e->email, $e->phone ?? '', $e->fields ?? '', $e->status, $e->consent_at, $e->created_at );
			fputcsv(
				$out,
				array_map(
					static function ( $v ) {
						return preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v;
					},
					$row
				)
			);
		}
		exit;
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s                        = wp_parse_args( get_option( $this->option, array() ), $this->defaults() );
		$s['retention_enabled']   = $s['retention_enabled'] ?? 0;
		$s['recaptcha_threshold'] = $s['recaptcha_threshold'] ?? '0.5';
		$s['default_form_fields'] = $this->ensure_core_fields( $s['default_form_fields'] ?? array() );
		$tabs                     = apply_filters(
			'pigiveaways_settings_tabs',
			array(
				'general'       => 'General',
				'form'          => 'Form',
				'design'        => 'Design',
				'privacy'       => 'Privacy & Spam',
				'notifications' => 'Notifications',
				'support'       => 'Help',
				'upgrade'       => 'Upgrade',
			)
		);
		$icons                    = array(
			'general'       => 'dashicons-admin-page',
			'form'          => 'dashicons-feedback',
			'design'        => 'dashicons-admin-appearance',
			'privacy'       => 'dashicons-shield',
			'notifications' => 'dashicons-email-alt',
			'pro'           => 'dashicons-star-filled',
			'license'       => 'dashicons-lock',
			'support'       => 'dashicons-editor-help',
			'upgrade'       => 'dashicons-unlock',
		);
		$base_url                 = admin_url( 'admin.php?page=pigiveaways' );
		$listing_pages            = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);
		?>
		<div class="wrap pit-wrap">
			<?php $this->brand_header(); ?>
			<nav class="pit-tab-nav" id="pit-tab-nav" role="tablist">
				<?php
				foreach ( $tabs as $slug => $label ) :
					$is_pro = in_array( $slug, array( 'upgrade', 'pro' ), true );
					?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>#pit-panel-<?php echo esc_attr( $slug ); ?>" id="pit-tab-<?php echo esc_attr( $slug ); ?>" class="pit-tab-nav__item<?php echo 'general' === $slug ? ' is-active' : ''; ?><?php echo $is_pro ? ' is-pro' : ''; ?>" data-tab="<?php echo esc_attr( $slug ); ?>" role="tab" aria-controls="pit-panel-<?php echo esc_attr( $slug ); ?>" aria-selected="<?php echo 'general' === $slug ? 'true' : 'false'; ?>" tabindex="<?php echo 'general' === $slug ? '0' : '-1'; ?>"><span class="pit-tab-nav__icon dashicons <?php echo esc_attr( $icons[ $slug ] ?? 'dashicons-admin-generic' ); ?>" aria-hidden="true"></span><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="pit-two-col"><div class="pit-left-col">
				<form method="post" action="options.php" class="pit-form">
					<?php settings_fields( 'pigiveaways_group' ); ?>
					<div class="pit-tab-panel" id="pit-panel-general" data-panel="general" role="tabpanel" aria-labelledby="pit-tab-general"><h2><?php esc_html_e( 'General defaults', 'pi-giveaways' ); ?></h2><?php $this->field( 'archive_slug', 'Giveaway listing URL', $s ); ?><input type="hidden" name="<?php echo esc_attr( $this->option ); ?>[archive_page_id]" value="<?php echo absint( $s['archive_page_id'] ); ?>"><p class="description"><?php echo wp_kses_post( __( 'Default: <code>/giveaways/</code>. The plugin creates a normal WordPress Page at this URL so you can edit its content, featured image, template, and SEO metadata.', 'pi-giveaways' ) ); ?></p>
					<?php
					if ( $s['archive_page_id'] && get_post( $s['archive_page_id'] ) ) :
						?>
						<p><a class="button" href="<?php echo esc_url( get_edit_post_link( $s['archive_page_id'] ) ); ?>"><?php esc_html_e( 'Edit giveaways listing page', 'pi-giveaways' ); ?></a> <a class="button" href="<?php echo esc_url( get_permalink( $s['archive_page_id'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View page', 'pi-giveaways' ); ?></a></p><?php endif; ?><label class="pig-setting"><span><?php esc_html_e( 'Listing placement', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[listing_position]"><option value="after" <?php selected( $s['listing_position'], 'after' ); ?>><?php esc_html_e( 'Automatically after page content', 'pi-giveaways' ); ?></option><option value="before" <?php selected( $s['listing_position'], 'before' ); ?>><?php esc_html_e( 'Automatically before page content', 'pi-giveaways' ); ?></option><option value="shortcode" <?php selected( $s['listing_position'], 'shortcode' ); ?>><?php esc_html_e( 'Shortcode only — place manually', 'pi-giveaways' ); ?></option></select></label><p class="description"><?php esc_html_e( 'Manual shortcode:', 'pi-giveaways' ); ?> <code>[pigiveaways_giveaways]</code> — <?php esc_html_e( 'optional example:', 'pi-giveaways' ); ?> <code>[pigiveaways_giveaways per_page="12"]</code></p>
						<?php
						$this->textarea( 'terms', 'Default terms and conditions', $s );
						$this->textarea( 'promoter', 'Promoter details', $s );
						$this->field( 'thank_you', 'Thank-you message', $s );
						?>
</div>
					<div class="pit-tab-panel" id="pit-panel-form" data-panel="form" role="tabpanel" aria-labelledby="pit-tab-form" hidden><h2><?php esc_html_e( 'Default entry form', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'This ordered form is available to every giveaway. Use the arrow buttons to change field order. Built-in contact fields can be shown or hidden; Terms acceptance always remains visible and required.', 'pi-giveaways' ); ?></p><div class="pig-shortcode-box"><strong><?php esc_html_e( 'Display a giveaway form in a post or page', 'pi-giveaways' ); ?></strong><p><code>[pigiveaways_form id="123"]</code></p><p class="description"><?php esc_html_e( 'Replace 123 with the Giveaway ID. A ready-to-copy shortcode is also shown inside every Giveaway editor.', 'pi-giveaways' ); ?></p></div><div class="pig-form-builder" data-form-builder><div class="pig-custom-fields" data-form-fields data-next-index="<?php echo absint( count( $s['default_form_fields'] ) ); ?>">
					<?php
					foreach ( $s['default_form_fields'] as $index => $field ) {
						$this->form_field_row( $index, $field, $this->option . '[default_form_fields]' );}
					?>
					</div><button type="button" class="button" data-add-field><?php esc_html_e( '+ Add default field', 'pi-giveaways' ); ?></button><script type="text/html" data-field-template><?php $this->form_field_row( '__INDEX__', array(), $this->option . '[default_form_fields]' ); ?></script></div></div>
					<div class="pit-tab-panel" id="pit-panel-design" data-panel="design" role="tabpanel" aria-labelledby="pit-tab-design" hidden data-pig-listing-settings><h2><?php esc_html_e( 'Display & Design', 'pi-giveaways' ); ?></h2><h3><?php esc_html_e( 'Giveaway listing page', 'pi-giveaways' ); ?></h3><?php $this->field( 'archive_title', 'Listing page title', $s ); ?><label class="pig-setting"><span><?php esc_html_e( 'Listing style', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[archive_layout]" data-preview-setting="layout"><option value="grid" <?php selected( $s['archive_layout'], 'grid' ); ?>><?php esc_html_e( 'Grid', 'pi-giveaways' ); ?></option><option value="list" <?php selected( $s['archive_layout'], 'list' ); ?>><?php esc_html_e( 'List', 'pi-giveaways' ); ?></option><option value="masonry" <?php selected( $s['archive_layout'], 'masonry' ); ?>><?php esc_html_e( 'Masonry grid', 'pi-giveaways' ); ?></option></select></label><label class="pig-setting"><span><?php esc_html_e( 'Columns', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[archive_columns]" data-preview-setting="columns">
					<?php
					for ( $column = 1; $column <= 4; $column++ ) :
						?>
						<option value="<?php echo absint( $column ); ?>" <?php selected( $s['archive_columns'], $column ); ?>><?php echo absint( $column ); ?></option><?php endfor; ?></select></label><label class="pig-setting"><span><?php esc_html_e( 'Card style', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[archive_card_style]" data-preview-setting="card"><option value="bordered" <?php selected( $s['archive_card_style'], 'bordered' ); ?>><?php esc_html_e( 'Bordered', 'pi-giveaways' ); ?></option><option value="elevated" <?php selected( $s['archive_card_style'], 'elevated' ); ?>><?php esc_html_e( 'Elevated', 'pi-giveaways' ); ?></option><option value="minimal" <?php selected( $s['archive_card_style'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'pi-giveaways' ); ?></option></select></label><label class="pig-setting"><span><?php esc_html_e( 'Image shape', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[archive_image_ratio]" data-preview-setting="ratio"><option value="landscape" <?php selected( $s['archive_image_ratio'], 'landscape' ); ?>><?php esc_html_e( 'Landscape', 'pi-giveaways' ); ?></option><option value="square" <?php selected( $s['archive_image_ratio'], 'square' ); ?>><?php esc_html_e( 'Square', 'pi-giveaways' ); ?></option><option value="portrait" <?php selected( $s['archive_image_ratio'], 'portrait' ); ?>><?php esc_html_e( 'Portrait', 'pi-giveaways' ); ?></option><option value="natural" <?php selected( $s['archive_image_ratio'], 'natural' ); ?>><?php esc_html_e( 'Natural', 'pi-giveaways' ); ?></option></select></label><label class="pig-setting pig-inline-check"><input type="checkbox" name="<?php echo esc_attr( $this->option ); ?>[archive_show_excerpt]" value="1" <?php checked( $s['archive_show_excerpt'], 1 ); ?> data-preview-setting="excerpt"> <?php esc_html_e( 'Show excerpt', 'pi-giveaways' ); ?></label><label class="pig-setting pig-inline-check"><input type="checkbox" name="<?php echo esc_attr( $this->option ); ?>[archive_show_status]" value="1" <?php checked( $s['archive_show_status'], 1 ); ?> data-preview-setting="status"> <?php esc_html_e( 'Show status badge', 'pi-giveaways' ); ?></label><label class="pig-setting pig-inline-check"><input type="checkbox" name="<?php echo esc_attr( $this->option ); ?>[archive_show_prize]" value="1" <?php checked( $s['archive_show_prize'], 1 ); ?> data-preview-setting="prize"> <?php esc_html_e( 'Show prize details', 'pi-giveaways' ); ?></label><hr><h3><?php esc_html_e( 'Individual giveaway pages', 'pi-giveaways' ); ?></h3><?php $this->field( 'accent', 'Accent colour', $s, 'color' ); ?><label class="pig-setting"><span><?php esc_html_e( 'Default giveaway page layout', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[single_layout]"><option value="theme" <?php selected( $s['single_layout'], 'theme' ); ?>><?php esc_html_e( 'Theme default', 'pi-giveaways' ); ?></option><option value="full" <?php selected( $s['single_layout'], 'full' ); ?>><?php esc_html_e( 'Full width — no sidebar', 'pi-giveaways' ); ?></option><option value="sidebar" <?php selected( $s['single_layout'], 'sidebar' ); ?>><?php esc_html_e( 'Content + theme sidebar', 'pi-giveaways' ); ?></option></select></label><p class="description"><?php esc_html_e( 'Each giveaway can override this setting in its Giveaway Details panel.', 'pi-giveaways' ); ?></p><label class="pig-setting pig-inline-check"><input type="checkbox" name="<?php echo esc_attr( $this->option ); ?>[show_countdown]" value="1" <?php checked( $s['show_countdown'], 1 ); ?>> <?php esc_html_e( 'Show countdown', 'pi-giveaways' ); ?></label></div>
					<div class="pit-tab-panel pig-listing-headings" data-panel="design" role="tabpanel" aria-labelledby="pit-tab-design" hidden><h3><?php esc_html_e( 'Listing sections', 'pi-giveaways' ); ?></h3><label class="pig-setting"><span><?php esc_html_e( 'Show on listing page', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[listing_sections]"><option value="both" <?php selected( $s['listing_sections'], 'both' ); ?>><?php esc_html_e( 'Current and Past Giveaways', 'pi-giveaways' ); ?></option><option value="current" <?php selected( $s['listing_sections'], 'current' ); ?>><?php esc_html_e( 'Current Giveaways only', 'pi-giveaways' ); ?></option><option value="past" <?php selected( $s['listing_sections'], 'past' ); ?>><?php esc_html_e( 'Past Giveaways only', 'pi-giveaways' ); ?></option></select></label><h3><?php esc_html_e( 'Section headings', 'pi-giveaways' ); ?></h3>
					<?php
					$this->field( 'active_heading', 'Current giveaways heading', $s );
					$this->field( 'past_heading', 'Past giveaways heading', $s );
					?>
					<p class="description"><code>[pigiveaways_giveaways show="both"]</code> &nbsp; <code>[pigiveaways_giveaways show="current"]</code> &nbsp; <code>[pigiveaways_giveaways show="past"]</code></p></div>
					<div class="pit-tab-panel pig-page-selector" data-panel="general" role="tabpanel" aria-labelledby="pit-tab-general"><h3><?php esc_html_e( 'Listing page', 'pi-giveaways' ); ?></h3><label class="pig-setting"><span><?php esc_html_e( 'Matching WordPress Page', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[archive_page_id]"><option value="0"><?php esc_html_e( 'Create the matching page automatically', 'pi-giveaways' ); ?></option>
					<?php
					foreach ( $listing_pages as $listing_page ) :
						$matches_slug = trim( get_page_uri( $listing_page ), '/' ) === trim( $s['archive_slug'], '/' );
						?>
						<option value="<?php echo absint( $listing_page->ID ); ?>" <?php selected( $s['archive_page_id'], $listing_page->ID ); ?> <?php disabled( ! $matches_slug ); ?>><?php echo esc_html( $listing_page->post_title . ' — /' . get_page_uri( $listing_page ) . '/' ); ?><?php echo $matches_slug ? '' : ' (' . esc_html__( 'URL does not match', 'pi-giveaways' ) . ')'; ?></option><?php endforeach; ?></select></label><p class="description"><?php esc_html_e( 'Only a Page whose full URL path matches the configured listing URL can be assigned. Other Pages are shown in grey for reference. If no match exists, the plugin creates it.', 'pi-giveaways' ); ?></p></div>
					<div class="pit-tab-panel pig-title-design" data-panel="design" role="tabpanel" aria-labelledby="pit-tab-design" hidden><h3><?php esc_html_e( 'Grid item title', 'pi-giveaways' ); ?></h3><label class="pig-setting"><span><?php esc_html_e( 'Font weight', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[archive_title_weight]" data-preview-setting="titleWeight"><option value="600" <?php selected( $s['archive_title_weight'], 600 ); ?>>600 — Semi-bold</option><option value="700" <?php selected( $s['archive_title_weight'], 700 ); ?>>700 — Bold</option><option value="800" <?php selected( $s['archive_title_weight'], 800 ); ?>>800 — Extra bold</option><option value="900" <?php selected( $s['archive_title_weight'], 900 ); ?>>900 — Black</option></select></label><label class="pig-setting"><span><?php esc_html_e( 'Font size (px)', 'pi-giveaways' ); ?></span><input type="number" min="14" max="36" name="<?php echo esc_attr( $this->option ); ?>[archive_title_size]" value="<?php echo absint( $s['archive_title_size'] ); ?>" data-preview-setting="titleSize"></label><label class="pig-setting"><span><?php esc_html_e( 'Title colour', 'pi-giveaways' ); ?></span><input type="color" name="<?php echo esc_attr( $this->option ); ?>[archive_title_color]" value="<?php echo esc_attr( $s['archive_title_color'] ); ?>" data-preview-setting="titleColor"></label></div>
					<div class="pit-tab-panel pig-form-layout-setting" data-panel="form" role="tabpanel" aria-labelledby="pit-tab-form" hidden><h3><?php esc_html_e( 'Form layout', 'pi-giveaways' ); ?></h3><label class="pig-setting"><span><?php esc_html_e( 'Fields per row', 'pi-giveaways' ); ?></span><select name="<?php echo esc_attr( $this->option ); ?>[form_layout]" data-pig-form-layout><option value="one" <?php selected( $s['form_layout'], 'one' ); ?>><?php esc_html_e( 'One field per line', 'pi-giveaways' ); ?></option><option value="two" <?php selected( $s['form_layout'], 'two' ); ?>><?php esc_html_e( 'Two-column layout', 'pi-giveaways' ); ?></option></select></label></div>
					<div class="pit-tab-panel" id="pit-panel-privacy" data-panel="privacy" role="tabpanel" aria-labelledby="pit-tab-privacy" hidden><h2><?php esc_html_e( 'Privacy & Spam', 'pi-giveaways' ); ?></h2><h3><?php esc_html_e( 'Entry retention', 'pi-giveaways' ); ?></h3><label class="pig-setting pig-inline-check"><input type="checkbox" name="<?php echo esc_attr( $this->option ); ?>[retention_enabled]" value="1" <?php checked( $s['retention_enabled'], 1 ); ?>> <?php esc_html_e( 'Enable automatic entry deletion after a retention period', 'pi-giveaways' ); ?></label><?php $this->field( 'retention_days', 'Retention period in days', $s, 'number' ); ?><p class="description"><strong><?php esc_html_e( 'Entries are kept indefinitely by default.', 'pi-giveaways' ); ?></strong> <?php esc_html_e( 'They must not be deleted automatically unless this option is explicitly enabled. Individual entries can still be deleted manually from Entries & Awards.', 'pi-giveaways' ); ?></p><hr><h3><?php esc_html_e( 'Google reCAPTCHA v3', 'pi-giveaways' ); ?></h3><ol><li><?php /* translators: %s: Link to the Google reCAPTCHA Admin Console. */ echo wp_kses_post( sprintf( __( 'Open the %s and sign in with a Google account.', 'pi-giveaways' ), '<a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google reCAPTCHA Admin Console', 'pi-giveaways' ) . '</a>' ) ); ?></li><li><?php esc_html_e( 'Enter a label, select reCAPTCHA v3, and add every website domain where the form will run.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'Submit the registration, then copy the Site Key and Secret Key into the fields below.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'Enable protection, save the settings, and use Test reCAPTCHA Configuration below.', 'pi-giveaways' ); ?></li></ol><p><a href="https://developers.google.com/recaptcha/docs/v3" target="_blank" rel="noopener noreferrer" class="button"><?php esc_html_e( 'Read Google’s reCAPTCHA v3 guide', 'pi-giveaways' ); ?> →</a></p><label class="pig-setting pig-inline-check"><input type="checkbox" name="<?php echo esc_attr( $this->option ); ?>[recaptcha_enabled]" value="1" <?php checked( $s['recaptcha_enabled'], 1 ); ?>> <?php esc_html_e( 'Enable invisible spam scoring on entry forms', 'pi-giveaways' ); ?></label>
					<?php
					$this->field( 'recaptcha_site_key', 'Site key', $s );
					$this->field( 'recaptcha_secret_key', 'Secret key', $s, 'password' );
					$this->field( 'recaptcha_threshold', 'Minimum score (0.0–1.0)', $s, 'number' );
					?>
					<p class="description"><?php esc_html_e( 'The recommended starting threshold is 0.5. The secret is never printed on the frontend, and the built-in honeypot remains active.', 'pi-giveaways' ); ?></p><p><?php esc_html_e( 'Raw IP addresses and user-agent strings are not stored by PI Giveaways Free.', 'pi-giveaways' ); ?></p></div>
					<div class="pit-tab-panel" id="pit-panel-notifications" data-panel="notifications" role="tabpanel" aria-labelledby="pit-tab-notifications" hidden><h2><?php esc_html_e( 'Entry email notifications', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'Messages are sent through WordPress wp_mail(). No SMTP or Composer dependency is included.', 'pi-giveaways' ); ?></p><h3><?php esc_html_e( 'Participant confirmation', 'pi-giveaways' ); ?></h3><label class="pig-setting pig-inline-check"><input type="checkbox" name="<?php echo esc_attr( $this->option ); ?>[email_participant_enabled]" value="1" <?php checked( $s['email_participant_enabled'], 1 ); ?>> <?php esc_html_e( 'Email the participant after a successful entry', 'pi-giveaways' ); ?></label>
					<?php
					$this->field( 'email_participant_subject', 'Participant email subject', $s );
					$this->textarea( 'email_participant_body', 'Participant email template', $s );
					?>
					<h3><?php esc_html_e( 'Administrator notification', 'pi-giveaways' ); ?></h3><label class="pig-setting pig-inline-check"><input type="checkbox" name="<?php echo esc_attr( $this->option ); ?>[email_admin_enabled]" value="1" <?php checked( $s['email_admin_enabled'], 1 ); ?>> <?php esc_html_e( 'Notify the website administrator after a successful entry', 'pi-giveaways' ); ?></label>
		<?php
		$this->field( 'email_admin_address', 'Notification email address', $s, 'email' );
		$this->field( 'email_admin_subject', 'Administrator email subject', $s );
		$this->textarea( 'email_admin_body', 'Administrator email template', $s );
		?>
<p class="description"><?php esc_html_e( 'Available placeholders: {first_name}, {last_name}, {email}, {giveaway_title}, {giveaway_url}, {entry_id}, {site_name}.', 'pi-giveaways' ); ?></p></div>
					<div id="pit-save-footer" class="pit-save-footer"><?php submit_button( __( 'Save Changes', 'pi-giveaways' ), 'primary', 'submit', false ); ?></div>
				</form>
				<div class="pit-tab-panel pit-support-panel" id="pit-panel-support" data-panel="support" role="tabpanel" aria-labelledby="pit-tab-support" hidden><?php $this->render_help_panel( $s ); ?></div>
				<?php
				if ( isset( $tabs['upgrade'] ) ) :
					?>
					<div class="pit-tab-panel pit-pro-panel" id="pit-panel-upgrade" data-panel="upgrade" role="tabpanel" aria-labelledby="pit-tab-upgrade" hidden><div class="pit-pro-hero"><div><span class="pit-pro-eyebrow"><?php esc_html_e( 'PI Giveaways Pro', 'pi-giveaways' ); ?></span><h2><?php esc_html_e( 'Advanced giveaway workflows', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'Add a separately distributed plugin for sponsor controls, entry limits, bonus actions, advanced moderation, and tracked banner campaigns.', 'pi-giveaways' ); ?></p></div><a class="button button-primary pit-pro-upgrade" href="<?php echo esc_url( PIGIVEAWAYS_PRO_URL ); ?>"><?php esc_html_e( 'View the separate Pro plugin', 'pi-giveaways' ); ?> →</a></div></div><?php endif; ?>
				<?php
				foreach ( $tabs as $slug => $label ) :
					if ( in_array( $slug, array( 'general', 'form', 'design', 'privacy', 'notifications', 'support', 'upgrade' ), true ) ) {
						continue;}
					?>
					<div class="pit-tab-panel" id="pit-panel-<?php echo esc_attr( $slug ); ?>" data-panel="<?php echo esc_attr( $slug ); ?>" role="tabpanel" aria-labelledby="pit-tab-<?php echo esc_attr( $slug ); ?>" hidden><?php do_action( 'pigiveaways_render_settings_panel', $slug, $s ); ?></div><?php endforeach; ?>
			</div><aside class="pit-preview-pane"><div class="pit-preview-inner">
				<div data-pig-preview-panel="form" hidden><span class="pit-preview-label"><?php esc_html_e( 'Live preview', 'pi-giveaways' ); ?></span><h2><?php esc_html_e( 'Entry form', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'Field order, labels, types, and required markers update with the default form.', 'pi-giveaways' ); ?></p><div class="pig-form-preview" data-pig-form-preview></div></div>
				<div data-pig-preview-panel="design" hidden><span class="pit-preview-label"><?php esc_html_e( 'Live preview', 'pi-giveaways' ); ?></span><h2><?php esc_html_e( 'Giveaway listing', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'The preview updates as you change listing design options.', 'pi-giveaways' ); ?></p><div class="pig-listing-preview pig-listing-preview--<?php echo esc_attr( $s['archive_layout'] ); ?> pig-listing-preview--<?php echo esc_attr( $s['archive_card_style'] ); ?> pig-listing-preview--<?php echo esc_attr( $s['archive_image_ratio'] ); ?>" data-pig-listing-preview style="--pig-preview-columns:<?php echo absint( $s['archive_columns'] ); ?>"><article class="pig-preview-card"><div class="pig-preview-image"></div><div class="pig-preview-body"><span class="pig-preview-status"<?php echo empty( $s['archive_show_status'] ) ? ' hidden' : ''; ?>><?php esc_html_e( 'Open', 'pi-giveaways' ); ?></span><h3><?php esc_html_e( 'Win an event double pass', 'pi-giveaways' ); ?></h3><dl class="pig-preview-details"<?php echo empty( $s['archive_show_prize'] ) ? ' hidden' : ''; ?>><div><dt><?php esc_html_e( 'Prize', 'pi-giveaways' ); ?></dt><dd><?php esc_html_e( 'Double pass', 'pi-giveaways' ); ?></dd></div><div><dt><?php esc_html_e( 'Value', 'pi-giveaways' ); ?></dt><dd><?php esc_html_e( '$180', 'pi-giveaways' ); ?></dd></div><div><dt><?php esc_html_e( 'Starts', 'pi-giveaways' ); ?></dt><dd><?php esc_html_e( '1 September 2026', 'pi-giveaways' ); ?></dd></div><div><dt><?php esc_html_e( 'Ends', 'pi-giveaways' ); ?></dt><dd><?php esc_html_e( '30 September 2026', 'pi-giveaways' ); ?></dd></div></dl><p class="pig-preview-excerpt"<?php echo empty( $s['archive_show_excerpt'] ) ? ' hidden' : ''; ?>><?php esc_html_e( 'Enter for your chance to attend this special event.', 'pi-giveaways' ); ?></p><span class="pig-preview-link"><?php esc_html_e( 'View giveaway →', 'pi-giveaways' ); ?></span></div></article><article class="pig-preview-card pig-preview-card--second"><div class="pig-preview-image"></div><div class="pig-preview-body"><span class="pig-preview-status"><?php esc_html_e( 'Upcoming', 'pi-giveaways' ); ?></span><h3><?php esc_html_e( 'Another giveaway', 'pi-giveaways' ); ?></h3><p class="pig-preview-excerpt"><?php esc_html_e( 'A second card demonstrates the selected layout.', 'pi-giveaways' ); ?></p></div></article></div></div>
				<div data-pig-preview-panel="default"><span class="pit-preview-label"><?php esc_html_e( 'PI Giveaways', 'pi-giveaways' ); ?></span><h2><?php esc_html_e( 'Settings', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'Select Form to preview the entry form or Design to preview the listing cards.', 'pi-giveaways' ); ?></p></div>
			</div></aside></div>
		</div>
		<?php
	}

	private function field( $key, $label, $s, $type = 'text' ) {
		echo '<label class="pig-setting"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $this->option ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ) . '"></label>'; }
	private function textarea( $key, $label, $s ) {
		echo '<label class="pig-setting"><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $this->option ) . '[' . esc_attr( $key ) . ']" rows="6">' . esc_textarea( $s[ $key ] ) . '</textarea></label>'; }
	private function render_help_panel( $settings ) {
		$theme = wp_get_theme();
		?>
		<nav class="pitpro-subnav" data-pitpro-subnav-group="help" role="tablist" aria-label="<?php esc_attr_e( 'Help sections', 'pi-giveaways' ); ?>"><button type="button" class="is-active" data-pitpro-subnav-tab="support" role="tab" aria-selected="true"><?php esc_html_e( 'Support & Diagnostics', 'pi-giveaways' ); ?></button><button type="button" data-pitpro-subnav-tab="usage" role="tab" aria-selected="false"><?php esc_html_e( 'Usage & Shortcodes', 'pi-giveaways' ); ?></button><button type="button" data-pitpro-subnav-tab="data" role="tab" aria-selected="false"><?php esc_html_e( 'Entries & Data', 'pi-giveaways' ); ?></button></nav>
		<div data-pitpro-subnav-group="help" data-pitpro-subnav-panel="support"><p class="pit-support-lead"><?php esc_html_e( 'Help, useful links, and diagnostic information for PI Giveaways.', 'pi-giveaways' ); ?></p><div class="pit-support-grid"><article class="pit-support-card"><div class="pit-support-card__icon">✦</div><h2><?php esc_html_e( 'PI Media support', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'Get assistance with setup, forms, entries, giveaway pages, shortcodes, layouts, licensing, and display styling.', 'pi-giveaways' ); ?></p><p class="pit-support-actions"><a href="https://www.parorrey.com/contact/" target="_blank" rel="noopener noreferrer" class="button button-primary"><?php esc_html_e( 'Contact support', 'pi-giveaways' ); ?> →</a><a href="https://www.parorrey.com/" target="_blank" rel="noopener noreferrer" class="button"><?php esc_html_e( 'Visit PI Media', 'pi-giveaways' ); ?> →</a><a href="https://www.parorrey.com/donate/" target="_blank" rel="noopener noreferrer" class="button"><?php esc_html_e( 'Donate', 'pi-giveaways' ); ?> →</a></p></article><article class="pit-support-card pit-support-card--pro"><div class="pit-support-card__icon">★</div><h2><?php esc_html_e( 'PI Giveaways Pro', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'View commercial features, release information, licensing details, and the Pro roadmap.', 'pi-giveaways' ); ?></p><p class="pit-support-actions"><a href="<?php echo esc_url( PIGIVEAWAYS_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary"><?php esc_html_e( 'View Pro product page', 'pi-giveaways' ); ?> →</a></p></article><article class="pit-support-card"><div class="pit-support-card__icon">✓</div><h2><?php esc_html_e( 'Before contacting support', 'pi-giveaways' ); ?></h2><ul class="pit-support-list"><li><?php esc_html_e( 'Include the giveaway title, affected tab, and exact error.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'Temporarily test with a default WordPress theme.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'Clear page, object, and browser caches.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'Check the browser console for JavaScript errors.', 'pi-giveaways' ); ?></li></ul></article><article class="pit-support-card"><div class="pit-support-card__icon">↗</div><h2><?php esc_html_e( 'Useful links', 'pi-giveaways' ); ?></h2><ul class="pit-support-links"><li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pigiveaways_giveaway' ) ); ?>"><?php esc_html_e( 'Manage giveaways', 'pi-giveaways' ); ?></a></li><li><a href="<?php echo esc_url( admin_url( 'admin.php?page=pigiveaways-entries' ) ); ?>"><?php esc_html_e( 'Manage entries and awards', 'pi-giveaways' ); ?></a></li><li><a href="https://www.parorrey.com/contact/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PI Media contact form', 'pi-giveaways' ); ?></a></li><li><a href="https://www.parorrey.com/donate/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support development', 'pi-giveaways' ); ?></a></li></ul></article></div><div class="pit-support-env"><h2><?php esc_html_e( 'Environment information', 'pi-giveaways' ); ?></h2><p><?php esc_html_e( 'Include this information when requesting support. No passwords, licence keys, or entrant information are displayed.', 'pi-giveaways' ); ?></p><table class="widefat striped"><tbody>
		<?php
		foreach ( array(
			'Plugin version'    => PIGIVEAWAYS_VERSION,
			'WordPress version' => get_bloginfo( 'version' ),
			'PHP version'       => PHP_VERSION,
			'Site URL'          => get_site_url(),
			'Active theme'      => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'Archive path'      => '/' . ( $settings['archive_slug'] ?? 'giveaways' ) . '/',
			'Default layout'    => ucfirst( $settings['single_layout'] ?? 'theme' ),
			'Multisite'         => is_multisite() ? 'Yes' : 'No',
		) as $label => $value ) :
			?>
																									<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><code><?php echo esc_html( $value ); ?></code></td></tr><?php endforeach; ?></tbody></table></div></div>
		<div data-pitpro-subnav-group="help" data-pitpro-subnav-panel="usage" hidden><h2><?php esc_html_e( 'Usage & Shortcodes', 'pi-giveaways' ); ?></h2><div class="pit-section"><h3><?php esc_html_e( 'Giveaway entry form only', 'pi-giveaways' ); ?></h3><p><code>[pigiveaways_form id="123"]</code></p><p><?php esc_html_e( 'Use this shortcode inside any WordPress post or page. Replace 123 with the giveaway ID. The form accepts entries only while that giveaway is open.', 'pi-giveaways' ); ?></p></div><div class="pit-section"><h3><?php esc_html_e( 'Complete single giveaway', 'pi-giveaways' ); ?></h3><p><code>[pigiveaways_giveaway id="123"]</code></p><p><?php esc_html_e( 'Displays the giveaway image, status, prize information, related-event link, and entry form.', 'pi-giveaways' ); ?></p></div><div class="pit-section"><h3><?php esc_html_e( 'Giveaway listings', 'pi-giveaways' ); ?></h3><p><code>[pigiveaways_giveaways]</code></p><p><?php esc_html_e( 'Uses the layout, columns, card style, image shape, and title styling saved under Design. You may override the layout for an individual shortcode.', 'pi-giveaways' ); ?></p><p><code>[pigiveaways_giveaways layout="grid" columns="3"]</code><br><code>[pigiveaways_giveaways layout="list"]</code><br><code>[pigiveaways_giveaways layout="masonry" columns="3"]</code><br><code>[pigiveaways_giveaways show="current"]</code><br><code>[pigiveaways_giveaways show="past"]</code></p><p><?php esc_html_e( 'Supported layout values: grid, list, and masonry. Columns accepts 1 to 4 and applies to grid or masonry layouts.', 'pi-giveaways' ); ?></p></div><div class="pit-section"><h3><?php esc_html_e( 'Form configuration', 'pi-giveaways' ); ?></h3><p><?php esc_html_e( 'Configure the reusable form under Settings → Form. A giveaway can inherit it, extend it, or use a custom ordered form.', 'pi-giveaways' ); ?></p></div></div>
		<div data-pitpro-subnav-group="help" data-pitpro-subnav-panel="data" hidden><h2><?php esc_html_e( 'Entries & Data', 'pi-giveaways' ); ?></h2><ul class="pit-support-list"><li><?php esc_html_e( 'Open All Giveaways and select Entries beneath a giveaway title.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'Entries are stored in the dedicated PI Giveaways entries table and separated by giveaway ID.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'CSV export includes standard fields, additional answers, consent time, and entry status.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'Winner awards are manual administrator actions and are recorded separately for auditability.', 'pi-giveaways' ); ?></li><li><?php esc_html_e( 'Raw IP addresses and user-agent strings are not stored by the Free plugin.', 'pi-giveaways' ); ?></li></ul></div>
		<?php
	}
	private function sanitize_form_fields( $fields, $scope = 'field' ) {
		$core  = array_column( $this->core_fields(), null, 'id' );
		$clean = array();
		foreach ( (array) $fields as $field ) {
			$label = sanitize_text_field( $field['label'] ?? '' );
			if ( ! $label ) {
				continue;
			} $posted_id = sanitize_key( $field['id'] ?? '' );
			$system      = isset( $core[ $posted_id ] );
			$type        = $system ? $core[ $posted_id ]['type'] : ( in_array( $field['type'] ?? '', array( 'text', 'email', 'tel', 'textarea', 'select', 'checkbox' ), true ) ? $field['type'] : 'text' );
			$id          = $system ? $posted_id : ( $posted_id ? $posted_id : sanitize_key( $scope ) . '_' . substr( md5( $label . count( $clean ) ), 0, 10 ) );
			$clean[]     = array(
				'id'       => $id,
				'label'    => $label,
				'type'     => $type,
				'required' => 'core_terms' === $id ? 1 : ( empty( $field['required'] ) ? 0 : 1 ),
				'enabled'  => 'core_terms' === $id || ! $system || ! empty( $field['enabled'] ) ? 1 : 0,
				'options'  => sanitize_text_field( $field['options'] ?? '' ),
				'system'   => $system ? 1 : 0,
			);
		} return $clean;
	}
	private function core_fields() {
		return array(
			array(
				'id'       => 'core_first_name',
				'label'    => 'First Name',
				'type'     => 'text',
				'required' => 1,
				'enabled'  => 1,
				'options'  => '',
				'system'   => 1,
			),
			array(
				'id'       => 'core_last_name',
				'label'    => 'Last Name',
				'type'     => 'text',
				'required' => 1,
				'enabled'  => 1,
				'options'  => '',
				'system'   => 1,
			),
			array(
				'id'       => 'core_email',
				'label'    => 'Email',
				'type'     => 'email',
				'required' => 1,
				'enabled'  => 1,
				'options'  => '',
				'system'   => 1,
			),
			array(
				'id'       => 'core_phone',
				'label'    => 'Phone',
				'type'     => 'tel',
				'required' => 1,
				'enabled'  => 1,
				'options'  => '',
				'system'   => 1,
			),
			array(
				'id'       => 'core_terms',
				'label'    => 'I agree to the terms and conditions',
				'type'     => 'checkbox',
				'required' => 1,
				'enabled'  => 1,
				'options'  => '',
				'system'   => 1,
			),
		);
	}
	private function ensure_core_fields( $fields ) {
		$fields = array_values( (array) $fields );
		$core   = array_column( $this->core_fields(), null, 'id' );
		foreach ( $fields as &$field ) {
			if ( isset( $core[ $field['id'] ?? '' ] ) ) {
				$field = wp_parse_args( $field, $core[ $field['id'] ] );
			}
		} unset( $field );
		$seen = array_column( $fields, 'id' );
		foreach ( $core as $id => $definition ) {
			if ( ! in_array( $id, $seen, true ) ) {
				$fields[] = $definition;
			}
		} return array_values( $fields );
	}
	private function sanitize_archive_slug( $value ) {
		$parts = array_filter( array_map( 'sanitize_title', explode( '/', trim( (string) $value, '/' ) ) ) );
		return $parts ? implode( '/', $parts ) : 'giveaways';
	}
}
