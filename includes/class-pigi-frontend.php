<?php
defined( 'ABSPATH' ) || exit;

final class PIGI_Frontend {
	public function __construct() {
		add_action( 'init', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_nopriv_pigiveaways_enter', array( $this, 'submit' ) );
		add_action( 'admin_post_pigiveaways_enter', array( $this, 'submit' ) );
		add_filter( 'the_content', array( $this, 'single_content' ) );
		add_filter( 'the_content', array( $this, 'listing_page_content' ), 20 );
		add_filter( 'template_include', array( $this, 'single_template' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_edit_link' ), 80 );
		add_action( 'wp_footer', array( $this, 'render_outcome_modal' ), 5 );
	}

	public function shortcode() {
		add_shortcode( 'pigiveaways_giveaway', array( $this, 'render_shortcode' ) );
		add_shortcode( 'pigiveaways_form', array( $this, 'form_shortcode' ) );
		add_shortcode( 'pigiveaways_giveaways', array( $this, 'listing_shortcode' ) ); }
	public function assets() {
		wp_register_style( 'pigiveaways', PIGIVEAWAYS_URL . 'assets/public.css', array(), PIGIVEAWAYS_VERSION );
		wp_register_style( 'pigiveaways-form', PIGIVEAWAYS_URL . 'assets/form.css', array( 'pigiveaways' ), PIGIVEAWAYS_VERSION );
		wp_add_inline_style( 'pigiveaways-form', '.pig-outcome-modal[hidden]{display:none!important}.pig-outcome-modal .pig-outcome-backdrop{z-index:0}.pig-outcome-modal .pig-outcome-dialog{z-index:1}.pig-outcome-modal .pig-outcome-close{z-index:2}' );
		wp_register_style( 'pigiveaways-listing', PIGIVEAWAYS_URL . 'assets/listing.css', array( 'pigiveaways' ), PIGIVEAWAYS_VERSION );
		wp_register_script( 'pigiveaways', PIGIVEAWAYS_URL . 'assets/public.js', array(), PIGIVEAWAYS_VERSION, true ); }

	public function single_content( $content ) {
		if ( is_singular( 'pigiveaways_giveaway' ) && in_the_loop() && is_main_query() ) {
			return $content . $this->render( get_the_ID() );
		}
		return $content;
	}

	public function render_shortcode( $atts ) {
		$a = shortcode_atts( array( 'id' => 0 ), $atts, 'pigiveaways_giveaway' );
		return $this->render( absint( $a['id'] ) );
	}

	public function form_shortcode( $atts ) {
		$a    = shortcode_atts( array( 'id' => 0 ), $atts, 'pigiveaways_form' );
		$id   = absint( $a['id'] );
		$post = get_post( $id );
		if ( ! $post || 'pigiveaways_giveaway' !== $post->post_type || ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) ) {
			return '';
		}
		wp_enqueue_style( 'pigiveaways-form' );
		wp_enqueue_script( 'pigiveaways' );
		$d = wp_parse_args(
			get_post_meta( $id, '_pigiveaways_details', true ),
			array(
				'status' => 'draft',
				'start'  => '',
				'end'    => '',
				'terms'  => '',
			)
		);
		if ( 'active' !== $this->state( $d ) ) {
			return '<div class="pig-notice pig-error">' . esc_html__( 'This giveaway is not currently accepting entries.', 'pi-giveaways' ) . '</div>';
		}
		$settings  = get_option( 'pigiveaways_settings', array() );
		$countdown = ! empty( $settings['show_countdown'] ) && $d['end'] ? '<span class="pig-countdown" data-pig-countdown data-end="' . absint( strtotime( $d['end'] ) ) . '">' . esc_html__( 'Calculating closing time…', 'pi-giveaways' ) . '</span>' : '';
		ob_start();
		echo '<section class="pig-giveaway pig-giveaway-form-embed" data-giveaway-id="' . absint( $id ) . '">';
		if ( has_post_thumbnail( $id ) ) {
			echo get_the_post_thumbnail( $id, 'large', array( 'class' => 'pig-prize-image' ) );
		} echo '<div class="pig-giveaway-body"><div class="pig-status-line"><span class="pig-state">' . esc_html__( 'Open', 'pi-giveaways' ) . '</span>' . wp_kses(
			$countdown,
			array(
				'span' => array(
					'class'              => true,
					'data-pig-countdown' => true,
					'data-end'           => true,
				),
			)
		) . '</div><h2 class="pig-form-giveaway-title">' . esc_html( $post->post_title ) . '</h2>';
		do_action( 'pigiveaways_render_form_widget_details', $id );
		$this->form( $id, $d, $settings );
		echo '</div></section>';
		return ob_get_clean();
	}

	public function listing_shortcode( $atts ) {
		$a      = shortcode_atts(
			array(
				'per_page' => (int) get_option( 'posts_per_page', 10 ),
				'show'     => '',
				'layout'   => '',
				'columns'  => 0,
			),
			$atts,
			'pigiveaways_giveaways'
		);
		$show   = in_array( $a['show'], array( 'current', 'past', 'both' ), true ) ? $a['show'] : '';
		$layout = in_array( $a['layout'], array( 'grid', 'list', 'masonry' ), true ) ? $a['layout'] : '';
		return $this->render_listing( max( 1, absint( $a['per_page'] ) ), $show, $layout, absint( $a['columns'] ) );
	}

	public function admin_bar_edit_link( $admin_bar ) {
		if ( is_admin() || ! is_singular( 'pigiveaways_giveaway' ) ) {
			return;
		}
		$id = get_queried_object_id();
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$admin_bar->add_node(
			array(
				'id'    => 'pig-edit-giveaway',
				'title' => __( 'Edit Giveaway', 'pi-giveaways' ),
				'href'  => get_edit_post_link( $id, '' ),
				'meta'  => array( 'class' => 'pig-admin-bar-edit-giveaway' ),
			)
		);
	}

	public function listing_page_content( $content ) {
		static $rendering_listing = false;
		if ( $rendering_listing ) {
			return $content;
		}
		if ( is_admin() || ! is_page() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$settings = get_option( 'pigiveaways_settings', array() );
		if ( get_the_ID() !== absint( $settings['archive_page_id'] ?? 0 ) || has_shortcode( $content, 'pigiveaways_giveaways' ) ) {
			return $content;
		}
		if ( 'shortcode' === ( $settings['listing_position'] ?? 'after' ) ) {
			return $content;
		}
		$rendering_listing = true;
		$listing           = $this->render_listing( (int) get_option( 'posts_per_page', 10 ) );
		$rendering_listing = false;
		return 'before' === ( $settings['listing_position'] ?? 'after' ) ? $listing . $content : $content . $listing;
	}

	private function render_listing( $per_page, $show = '', $layout = '', $columns = 0 ) {
		wp_enqueue_style( 'pigiveaways-listing' );
		$pig_query       = new WP_Query(
			array(
				'post_type'      => 'pigiveaways_giveaway',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		$pig_per_section = $per_page;
		$pig_settings    = get_option( 'pigiveaways_settings', array() );
		if ( $layout ) {
			$pig_settings['archive_layout'] = $layout;
		}
		if ( $columns ) {
			$pig_settings['archive_columns'] = min( 4, max( 1, $columns ) );
		}
		$pig_show = $show ? $show : ( $pig_settings['listing_sections'] ?? 'both' );
		if ( ! in_array( $pig_show, array( 'current', 'past', 'both' ), true ) ) {
			$pig_show = 'both';
		}
		ob_start();
		include PIGIVEAWAYS_DIR . 'templates/listing-pi-giveaway.php';
		return ob_get_clean();
	}

	public function single_template( $template ) {
		if ( is_post_type_archive( 'pigiveaways_giveaway' ) ) {
			return PIGIVEAWAYS_DIR . 'templates/archive-pi-giveaway.php';
		}
		if ( ! is_singular( 'pigiveaways_giveaway' ) ) {
			return $template;
		} $layout = $this->page_layout( get_queried_object_id() );
		return 'theme' === $layout ? $template : PIGIVEAWAYS_DIR . 'templates/single-pi-giveaway.php';
	}

	public function page_layout( $giveaway_id ) {
		$details = (array) get_post_meta( $giveaway_id, '_pigiveaways_details', true );
		$local   = $details['layout'] ?? 'inherit';
		if ( in_array( $local, array( 'theme', 'full', 'sidebar' ), true ) ) {
			return $local;
		} $settings = get_option( 'pigiveaways_settings', array() );
		return in_array( $settings['single_layout'] ?? '', array( 'theme', 'full', 'sidebar' ), true ) ? $settings['single_layout'] : 'theme';
	}

	private function state( $details ) {
		if ( in_array( $details['status'], array( 'cancelled', 'awarded', 'ended' ), true ) ) {
			return $details['status'];
		}
		$now   = time();
		$start = $details['start'] ? strtotime( $details['start'] ) : 0;
		$end   = $details['end'] ? strtotime( $details['end'] ) : 0;
		if ( $start && $now < $start ) {
			return 'upcoming';
		} if ( $end && $now >= $end ) {
			return 'ended';
		} return 'active' === $details['status'] ? 'active' : 'draft';
	}

	private function render( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'pigiveaways_giveaway' !== $post->post_type ) {
			return '';
		}
		wp_enqueue_style( 'pigiveaways-form' );
		wp_enqueue_script( 'pigiveaways' );
		$d           = wp_parse_args(
			get_post_meta( $id, '_pigiveaways_details', true ),
			array(
				'prize'        => '',
				'value'        => '',
				'start'        => '',
				'end'          => '',
				'event_url'    => '',
				'status'       => 'draft',
				'question'     => '',
				'terms'        => '',
				'announcement' => '',
			)
		);
		$state       = $this->state( $d );
		$settings    = get_option( 'pigiveaways_settings', array() );
		$state_label = 'active' === $state ? __( 'Open', 'pi-giveaways' ) : ucfirst( $state );
		$countdown   = 'active' === $state && ! empty( $settings['show_countdown'] ) && $d['end'] ? '<span class="pig-countdown" data-pig-countdown data-end="' . absint( strtotime( $d['end'] ) ) . '">' . esc_html__( 'Calculating closing time…', 'pi-giveaways' ) . '</span>' : '';
		ob_start();
		echo '<section class="pig-giveaway pig-state-' . esc_attr( $state ) . '">';
		if ( has_post_thumbnail( $id ) ) {
			echo get_the_post_thumbnail( $id, 'large', array( 'class' => 'pig-prize-image' ) );
		} echo '<div class="pig-giveaway-body"><div class="pig-status-line"><span class="pig-state">' . esc_html( $state_label ) . '</span>' . wp_kses(
			$countdown,
			array(
				'span' => array(
					'class'              => true,
					'data-pig-countdown' => true,
					'data-end'           => true,
				),
			)
		) . '</div><h2>' . esc_html( $post->post_title ) . '</h2>';
		if ( $d['prize'] ) {
			echo '<p class="pig-prize"><strong>Prize:</strong> ' . esc_html( $d['prize'] ) . ( $d['value'] ? ' <span>' . esc_html( $d['value'] ) . '</span>' : '' ) . '</p>';
		} do_action( 'pigiveaways_render_details', $id );
		if ( $d['event_url'] ) {
			echo '<p class="pig-related-event"><a href="' . esc_url( $d['event_url'] ) . '">' . esc_html__( 'View related event', 'pi-giveaways' ) . ' →</a></p>';
		}
		if ( 'active' === $state ) {
			$this->form( $id, $d, $settings );
		} elseif ( 'upcoming' === $state ) {
			echo '<p>' . esc_html__( 'This giveaway is coming soon.', 'pi-giveaways' ) . '</p>';
		} elseif ( 'ended' === $state ) {
			echo '<p>' . esc_html__( 'Entries are closed. Any award will be assigned manually by the promoter.', 'pi-giveaways' ) . '</p>';
		} elseif ( 'awarded' === $state ) {
				$announcement = $d['announcement'] ? $d['announcement'] : __( 'The award has been announced.', 'pi-giveaways' );
				echo '<div class="pig-announcement">' . wp_kses_post( wpautop( $announcement ) ) . '</div>';
		} elseif ( 'cancelled' === $state ) {
			echo '<p>' . esc_html__( 'This giveaway has been cancelled.', 'pi-giveaways' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'This giveaway is not accepting entries.', 'pi-giveaways' ) . '</p>';
		}
		echo '</div></section>';
		return ob_get_clean();
	}

	private function form( $id, $d, $settings ) {
		if ( ! apply_filters( 'pigiveaways_accepting_entries', true, $id ) ) {
			echo '<div class="pig-notice pig-error">' . esc_html__( 'This giveaway has reached its entry limit and is no longer accepting entries.', 'pi-giveaways' ) . '</div>';
			return; }
		$fields      = $this->form_fields( $id );
		$form_layout = 'one' === ( $settings['form_layout'] ?? 'two' ) ? 'one' : 'two';
		$recaptcha   = ! empty( $settings['recaptcha_enabled'] ) && ! empty( $settings['recaptcha_site_key'] ) && ! empty( $settings['recaptcha_secret_key'] );
		if ( $recaptcha ) {
			$site_key = sanitize_text_field( $settings['recaptcha_site_key'] );
			wp_enqueue_script( 'pigiveaways-recaptcha-v3-entry', add_query_arg( 'render', $site_key, 'https://www.google.com/recaptcha/api.js' ), array(), '3', true );
			wp_localize_script(
				'pigiveaways',
				'pigiveawaysRecaptcha',
				array(
					'siteKey'    => $site_key,
					'action'     => 'giveaway_entry',
					'submitting' => __( 'Submitting…', 'pi-giveaways' ),
					'error'      => __( 'reCAPTCHA could not verify your entry. Please check your connection and try again.', 'pi-giveaways' ),
				)
			); }
		do_action( 'pigiveaways_before_entry_form', $id );
		echo '<form class="pig-entry-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="pigiveaways_enter"><input type="hidden" name="giveaway_id" value="' . absint( $id ) . '">';
		wp_nonce_field( 'pigiveaways_enter_' . $id, 'pigiveaways_entry_nonce' );
		echo '<input class="pig-hp" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true"><div class="pig-form-grid pig-form-grid--' . esc_attr( $form_layout ) . '">';
		foreach ( $fields as $field ) {
			$this->render_custom_field( $field );
		}
		echo '</div>';
		do_action( 'pigiveaways_render_bonus_actions', $id );
		if ( $recaptcha ) {
			echo '<input type="hidden" name="g-recaptcha-response" value="">';
		}
			$terms = $d['terms'] ? $d['terms'] : ( $settings['terms'] ?? '' );
		if ( $terms ) {
			echo '<section class="pig-terms" aria-labelledby="pig-terms-title-' . absint( $id ) . '"><h3 id="pig-terms-title-' . absint( $id ) . '" class="pig-terms-title">' . esc_html__( 'Giveaway terms', 'pi-giveaways' ) . '</h3><div class="pig-terms-content">' . wp_kses_post( wpautop( $terms ) ) . '</div></section>';
		} echo '<button type="submit" class="pig-submit">' . esc_html__( 'Submit Entry', 'pi-giveaways' ) . '</button></form>';
	}

	public function render_outcome_modal() {
		$result_nonce = sanitize_text_field( wp_unslash( $_GET['pigiveaways_result_nonce'] ?? '' ) );
		$result       = sanitize_key( wp_unslash( $_GET['pigiveaways_result'] ?? '' ) );
		$giveaway_id  = absint( $_GET['pigiveaways_giveaway'] ?? 0 );
		if ( ! wp_verify_nonce( $result_nonce, 'pigiveaways_result_' . $giveaway_id ) ) {
			return;
		}
		if ( ! $result || ! $giveaway_id || 'pigiveaways_giveaway' !== get_post_type( $giveaway_id ) ) {
			return;
		}
		$success  = 'success' === $result;
		$settings = get_option( 'pigiveaways_settings', array() );
		$messages = array(
			'duplicate' => __( 'This email address has reached the entry limit for this giveaway.', 'pi-giveaways' ),
			'full'      => __( 'This giveaway has reached its maximum number of entries.', 'pi-giveaways' ),
			'closed'    => __( 'This giveaway is no longer accepting entries.', 'pi-giveaways' ),
			'spam'      => __( 'Spam verification was not completed. Please try again.', 'pi-giveaways' ),
			'error'     => __( 'The entry could not be accepted. Check the form and try again.', 'pi-giveaways' ),
		);
		$message  = $success ? ( $settings['thank_you'] ?? __( 'Thank you. Your entry has been received.', 'pi-giveaways' ) ) : ( $messages[ $result ] ?? $messages['error'] );
		echo '<div class="pig-outcome-modal pig-outcome-modal--' . esc_attr( $success ? 'success' : 'error' ) . '" data-pig-outcome-modal role="dialog" aria-modal="true" aria-labelledby="pig-outcome-title"><div class="pig-outcome-backdrop" data-pig-outcome-close></div><div class="pig-outcome-dialog"><button type="button" class="pig-outcome-close" data-pig-outcome-close aria-label="' . esc_attr__( 'Close', 'pi-giveaways' ) . '">×</button><span class="pig-outcome-icon" aria-hidden="true">' . ( $success ? '✓' : '!' ) . '</span><h2 id="pig-outcome-title">' . esc_html( $success ? __( 'Entry received', 'pi-giveaways' ) : __( 'Entry not submitted', 'pi-giveaways' ) ) . '</h2><p>' . esc_html( $message ) . '</p>';
		$code = sanitize_key( wp_unslash( $_GET['pigiveaways_referral'] ?? '' ) );
		if ( $success && $code && apply_filters( 'pigiveaways_show_referral_link', false, $giveaway_id ) ) {
			echo '<div class="pig-referral-link"><strong>' . esc_html__( 'Your referral link:', 'pi-giveaways' ) . '</strong><br><code>' . esc_html( add_query_arg( 'pigiveaways_ref', $code, get_permalink( $giveaway_id ) ) ) . '</code></div>';
		}
		echo '<button type="button" class="pig-outcome-done" data-pig-outcome-close>' . esc_html( $success ? __( 'Continue', 'pi-giveaways' ) : __( 'Close', 'pi-giveaways' ) ) . '</button></div></div>';
	}

	private function render_custom_field( $field ) {
		$id = sanitize_key( $field['id'] ?? '' );
		if ( ! $id ) {
			return;
		} $label  = sanitize_text_field( $field['label'] ?? '' );
		$type     = $field['type'] ?? 'text';
		$required = ! empty( $field['required'] );
		$mark     = $required ? ' *' : '';
		$wide     = in_array( $type, array( 'textarea', 'checkbox' ), true );
		if ( 'checkbox' === $type ) {
			echo '<label class="wide pig-consent"><span><input type="checkbox" name="entry[' . esc_attr( $id ) . ']" value="Yes"' . ( $required ? ' required' : '' ) . '> ' . esc_html( $label . $mark ) . '</span></label>';
			return; }
		echo '<label' . ( $wide ? ' class="wide"' : '' ) . '><span>' . esc_html( $label . $mark ) . '</span>';
		if ( 'textarea' === $type ) {
			echo '<textarea name="entry[' . esc_attr( $id ) . ']" rows="4"' . ( $required ? ' required' : '' ) . '></textarea>';
		} elseif ( 'select' === $type ) {
			echo '<select name="entry[' . esc_attr( $id ) . ']"' . ( $required ? ' required' : '' ) . '><option value="">' . esc_html__( 'Select…', 'pi-giveaways' ) . '</option>';
			foreach ( array_filter( array_map( 'trim', explode( ',', $field['options'] ?? '' ) ) ) as $option ) {
				echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
			} echo '</select>'; } else {
			$autocomplete = array(
				'core_first_name' => 'given-name',
				'core_last_name'  => 'family-name',
				'core_email'      => 'email',
				'core_phone'      => 'tel',
			);
			echo '<input type="' . esc_attr( in_array( $type, array( 'email', 'tel' ), true ) ? $type : 'text' ) . '" name="entry[' . esc_attr( $id ) . ']"' . ( $required ? ' required' : '' ) . ( isset( $autocomplete[ $id ] ) ? ' autocomplete="' . esc_attr( $autocomplete[ $id ] ) . '"' : '' ) . '>'; }
			echo '</label>';
	}

	public function submit() {
		$id = absint( wp_unslash( $_POST['giveaway_id'] ?? 0 ) );
		if ( ! isset( $_POST['pigiveaways_entry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pigiveaways_entry_nonce'] ) ), 'pigiveaways_enter_' . $id ) || ! empty( $_POST['website'] ) ) {
			$this->redirect( $id, 'error' );
		}
		if ( ! $this->verify_recaptcha() ) {
			$this->redirect( $id, 'spam' );
		}
		$d = wp_parse_args(
			get_post_meta( $id, '_pigiveaways_details', true ),
			array(
				'status' => 'draft',
				'start'  => '',
				'end'    => '',
			)
		);
		if ( 'active' !== $this->state( $d ) ) {
			$this->redirect( $id, 'closed' );
		}
		$data      = array(
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'phone'      => '',
			'answer'     => '',
			'fields'     => array(),
		);
		$submitted = map_deep( (array) wp_unslash( $_POST['entry'] ?? array() ), 'sanitize_text_field' );
		foreach ( $this->form_fields( $id ) as $field ) {
			$field_id = sanitize_key( $field['id'] ?? '' );
			$value    = $submitted[ $field_id ] ?? '';
			if ( ! empty( $field['required'] ) && '' === trim( (string) $value ) ) {
				$this->redirect( $id, 'error' );
			}
			if ( 'email' === ( $field['type'] ?? '' ) && $value && ! is_email( $value ) ) {
				$this->redirect( $id, 'error' );
			}
			$value    = 'textarea' === ( $field['type'] ?? '' ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			$core_map = array(
				'core_first_name' => 'first_name',
				'core_last_name'  => 'last_name',
				'core_email'      => 'email',
				'core_phone'      => 'phone',
			);
			if ( isset( $core_map[ $field_id ] ) ) {
				$data[ $core_map[ $field_id ] ] = $value;
			} elseif ( 'core_terms' !== $field_id ) {
				$data['fields'][ sanitize_text_field( $field['label'] ?? $field_id ) ] = $value;
			}
		}
		if ( empty( $submitted['core_terms'] ) ) {
			$this->redirect( $id, 'error' );
		}
		$data = apply_filters( 'pigiveaways_prepare_entry_data', $data, $id, $submitted );
		if ( is_wp_error( $data ) ) {
			$this->redirect( $id, 'error' );
		}
		$result = PIGI_Repository::add_entry( $id, $data );
		if ( is_wp_error( $result ) ) {
			$this->redirect( $id, 'giveaway_full' === $result->get_error_code() ? 'full' : ( 'email_limit' === $result->get_error_code() ? 'duplicate' : 'error' ) );
		} do_action( 'pigiveaways_entry_created', $result, $id, $data );
		$this->send_entry_notifications( $result, $id, $data );
		$entry = PIGI_Repository::entry( $result );
		$this->redirect( $id, 'success', $entry->referral_code ?? '' );
	}

	private function verify_recaptcha() {
		// The entry nonce is verified in submit() before this method is called.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$settings = get_option( 'pigiveaways_settings', array() );
		if ( empty( $settings['recaptcha_enabled'] ) ) {
			return true;
		}
		$secret = sanitize_text_field( $settings['recaptcha_secret_key'] ?? '' );
		$token  = sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $secret || ! $token ) {
			return false;
		}
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$data      = json_decode( wp_remote_retrieve_body( $response ), true );
		$threshold = min( 1, max( 0, (float) ( $settings['recaptcha_threshold'] ?? 0.5 ) ) );
		return ! empty( $data['success'] ) && 'giveaway_entry' === ( $data['action'] ?? '' ) && isset( $data['score'] ) && (float) $data['score'] >= $threshold;
	}

	private function send_entry_notifications( $entry_id, $giveaway_id, $data ) {
		$settings            = wp_parse_args(
			get_option( 'pigiveaways_settings', array() ),
			array(
				'email_participant_enabled' => 1,
				'email_participant_subject' => __( 'Your entry for {giveaway_title}', 'pi-giveaways' ),
				'email_participant_body'    => __( "Hello {first_name},\n\nYour entry for {giveaway_title} has been received.\n\nGiveaway: {giveaway_url}\n\nRegards,\n{site_name}", 'pi-giveaways' ),
				'email_admin_enabled'       => 1,
				'email_admin_address'       => get_option( 'admin_email' ),
				'email_admin_subject'       => __( 'New giveaway entry: {giveaway_title}', 'pi-giveaways' ),
				'email_admin_body'          => __( "A new entry has been received.\n\nGiveaway: {giveaway_title}\nEntrant: {first_name} {last_name}\nEmail: {email}\nEntry ID: {entry_id}", 'pi-giveaways' ),
			)
		);
		$tokens              = array(
			'{first_name}'     => sanitize_text_field( $data['first_name'] ?? '' ),
			'{last_name}'      => sanitize_text_field( $data['last_name'] ?? '' ),
			'{email}'          => sanitize_email( $data['email'] ?? '' ),
			'{giveaway_title}' => wp_specialchars_decode( get_the_title( $giveaway_id ), ENT_QUOTES ),
			'{giveaway_url}'   => get_permalink( $giveaway_id ),
			'{entry_id}'       => (string) absint( $entry_id ),
			'{site_name}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		$render              = static function ( $template ) use ( $tokens ) {
			return strtr( (string) $template, $tokens );
		};
		$participant_subject = $settings['email_participant_subject'] ? $settings['email_participant_subject'] : __( 'Your entry for {giveaway_title}', 'pi-giveaways' );
		$participant_body    = $settings['email_participant_body'] ? $settings['email_participant_body'] : __( "Hello {first_name},\n\nYour entry for {giveaway_title} has been received.\n\nGiveaway: {giveaway_url}\n\nRegards,\n{site_name}", 'pi-giveaways' );
		$admin_subject       = $settings['email_admin_subject'] ? $settings['email_admin_subject'] : __( 'New giveaway entry: {giveaway_title}', 'pi-giveaways' );
		$admin_body          = $settings['email_admin_body'] ? $settings['email_admin_body'] : __( "A new entry has been received.\n\nGiveaway: {giveaway_title}\nEntrant: {first_name} {last_name}\nEmail: {email}\nEntry ID: {entry_id}", 'pi-giveaways' );
		$admin_address       = is_email( $settings['email_admin_address'] ) ? $settings['email_admin_address'] : get_option( 'admin_email' );
		if ( ! empty( $settings['email_participant_enabled'] ) && is_email( $tokens['{email}'] ) ) {
			wp_mail( $tokens['{email}'], $render( $participant_subject ), $render( $participant_body ) ); }
		if ( ! empty( $settings['email_admin_enabled'] ) && is_email( $admin_address ) ) {
			wp_mail( $admin_address, $render( $admin_subject ), $render( $admin_body ) );
		}
	}

	private function form_fields( $giveaway_id ) {
		$settings = get_option( 'pigiveaways_settings', array() );
		$default  = $this->ensure_core_fields( (array) ( $settings['default_form_fields'] ?? array() ) );
		$local    = (array) get_post_meta( $giveaway_id, '_pigiveaways_form_fields', true );
		$mode     = get_post_meta( $giveaway_id, '_pigiveaways_form_mode', true );
		$mode     = $mode ? $mode : 'default';
		if ( 'custom' === $mode ) {
			$fields = $this->ensure_core_fields( $local );
		} elseif ( 'extend' === $mode ) {
			$fields = array_merge( $default, $local );
		} else {
			$fields = $default;
		}
		return array_values(
			array_filter(
				$fields,
				static function ( $field ) {
					return ! isset( $field['enabled'] ) || ! empty( $field['enabled'] );
				}
			)
		);
	}
	private function ensure_core_fields( $fields ) {
		$core        = array(
			array(
				'id'       => 'core_first_name',
				'label'    => 'First Name',
				'type'     => 'text',
				'required' => 1,
				'enabled'  => 1,
				'system'   => 1,
			),
			array(
				'id'       => 'core_last_name',
				'label'    => 'Last Name',
				'type'     => 'text',
				'required' => 1,
				'enabled'  => 1,
				'system'   => 1,
			),
			array(
				'id'       => 'core_email',
				'label'    => 'Email',
				'type'     => 'email',
				'required' => 1,
				'enabled'  => 1,
				'system'   => 1,
			),
			array(
				'id'       => 'core_phone',
				'label'    => 'Phone',
				'type'     => 'tel',
				'required' => 1,
				'enabled'  => 1,
				'system'   => 1,
			),
			array(
				'id'       => 'core_terms',
				'label'    => 'I agree to the terms and conditions',
				'type'     => 'checkbox',
				'required' => 1,
				'enabled'  => 1,
				'system'   => 1,
			),
		);
		$definitions = array_column( $core, null, 'id' );
		$fields      = array_values( (array) $fields );
		foreach ( $fields as &$field ) {
			if ( isset( $definitions[ $field['id'] ?? '' ] ) ) {
				$field = wp_parse_args( $field, $definitions[ $field['id'] ] );
			}
		} unset( $field );
		$seen = array_column( $fields, 'id' );
		foreach ( $core as $field ) {
			if ( ! in_array( $field['id'], $seen, true ) ) {
				$fields[] = $field;
			}
		} return array_values( $fields ); }

	private function redirect( $id, $result, $referral = '' ) {
		$url  = wp_get_referer();
		$url  = $url ? $url : get_permalink( $id );
		$args = array(
			'pigiveaways_result'       => sanitize_key( $result ),
			'pigiveaways_giveaway'     => absint( $id ),
			'pigiveaways_result_nonce' => wp_create_nonce( 'pigiveaways_result_' . absint( $id ) ),
		);
		if ( $referral ) {
			$args['pigiveaways_referral'] = sanitize_key( $referral );
		} wp_safe_redirect( add_query_arg( $args, $url ) );
		exit; }
}
