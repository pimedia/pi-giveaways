<?php
defined( 'ABSPATH' ) || exit;

final class PIGI_Activator {
	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$entries = $wpdb->prefix . 'pigiveaways_entries';
		$awards  = $wpdb->prefix . 'pigiveaways_awards';
			dbDelta(
				"CREATE TABLE {$entries} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			giveaway_id bigint unsigned NOT NULL,
			first_name varchar(100) NOT NULL,
			last_name varchar(100) NOT NULL DEFAULT '',
				email varchar(190) NULL DEFAULT NULL,
				phone varchar(60) NOT NULL DEFAULT '',
				answer text NULL,
				fields longtext NULL,
				bonus_entries int unsigned NOT NULL DEFAULT 0,
				bonus_actions longtext NULL,
				referral_code varchar(32) NULL,
				referrer_entry_id bigint unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'valid',
			consent_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY giveaway_email (giveaway_id,email),
			UNIQUE KEY referral_code (referral_code),
			KEY giveaway_status (giveaway_id,status),
			KEY created_at (created_at)
		) {$charset};"
			);
		dbDelta(
			"CREATE TABLE {$awards} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			giveaway_id bigint unsigned NOT NULL,
			entry_id bigint unsigned NOT NULL,
			award_type varchar(30) NOT NULL DEFAULT 'winner',
			note text NULL,
			awarded_by bigint unsigned NOT NULL,
			awarded_at datetime NOT NULL,
			revoked_at datetime NULL,
			revoked_by bigint unsigned NULL,
			PRIMARY KEY (id),
			KEY giveaway_award (giveaway_id,award_type),
			KEY entry_id (entry_id)
			) {$charset};"
		);
		self::migrate_legacy_data( $entries, $awards );
		update_option( 'pigiveaways_db_version', PIGIVEAWAYS_VERSION, false );
		add_option( 'pigiveaways_installed_at', time(), '', false );
		PIGI_Plugin::register_content();
		flush_rewrite_rules();
	}

	private static function migrate_legacy_data( $entries, $awards ) {
		global $wpdb;

		if ( version_compare( (string) get_option( 'pigiveaways_legacy_migrated', '0' ), '1.0.2', '>=' ) ) {
			return;
		}

		$legacy_settings = get_option( 'pi_giveaways_settings', false );
		if ( is_array( $legacy_settings ) ) {
			$current_settings = get_option( 'pigiveaways_settings', array() );
			update_option( 'pigiveaways_settings', array_replace( $legacy_settings, (array) $current_settings ), false );
		}

		$legacy_installed = absint( get_option( 'pi_giveaways_installed_at', 0 ) );
		$current_installed = absint( get_option( 'pigiveaways_installed_at', 0 ) );
		if ( $legacy_installed && ( ! $current_installed || $legacy_installed < $current_installed ) ) {
			update_option( 'pigiveaways_installed_at', $legacy_installed, false );
		}

		if ( get_option( 'pi_giveaways_review_notice_dismissed', false ) ) {
			update_option( 'pigiveaways_review_notice_dismissed', '1', false );
		}
		$legacy_review_next = absint( get_option( 'pi_giveaways_review_notice_next', 0 ) );
		if ( $legacy_review_next && ! get_option( 'pigiveaways_review_notice_next', false ) ) {
			update_option( 'pigiveaways_review_notice_next', $legacy_review_next, false );
		}

		$legacy_entries = $wpdb->prefix . 'pi_giveaway_entries';
		$legacy_awards  = $wpdb->prefix . 'pi_giveaway_awards';
		if ( $legacy_entries === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_entries ) ) ) {
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i SELECT * FROM %i', $entries, $legacy_entries ) );
		}
		if ( $legacy_awards === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_awards ) ) ) {
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i SELECT * FROM %i', $awards, $legacy_awards ) );
		}

		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => 'pigiveaways_giveaway' ),
			array( 'post_type' => 'pi_giveaway' ),
			array( '%s' ),
			array( '%s' )
		);
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'taxonomy' => 'pigiveaways_category' ),
			array( 'taxonomy' => 'pi_giveaway_category' ),
			array( '%s' ),
			array( '%s' )
		);

		$legacy_meta_keys = array(
			'_pigiveaways_details'     => array( '_pigi_details', '_pig_details' ),
			'_pigiveaways_form_fields' => array( '_pigi_form_fields', '_pig_form_fields' ),
			'_pigiveaways_form_mode'   => array( '_pigi_form_mode', '_pig_form_mode' ),
		);
		foreach ( $legacy_meta_keys as $current_meta_key => $legacy_keys ) {
			foreach ( $legacy_keys as $legacy_meta_key ) {
				$legacy_rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT post_id, meta_value FROM %i WHERE meta_key = %s',
						$wpdb->postmeta,
						$legacy_meta_key
					)
				);
				foreach ( $legacy_rows as $legacy_row ) {
					$post_id = absint( $legacy_row->post_id );
					if ( $post_id && ! metadata_exists( 'post', $post_id, $current_meta_key ) ) {
						update_post_meta( $post_id, $current_meta_key, maybe_unserialize( $legacy_row->meta_value ) );
					}
				}
			}
		}

		$shortcodes = array(
			'pi_giveaway_form' => 'pigiveaways_form',
			'pi_giveaways'     => 'pigiveaways_giveaways',
			'pi_giveaway'      => 'pigiveaways_giveaway',
		);
		foreach ( $shortcodes as $legacy_shortcode => $current_shortcode ) {
			$like = '%' . $wpdb->esc_like( '[' . $legacy_shortcode ) . '%';
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s',
					$wpdb->posts,
					'[' . $legacy_shortcode,
					'[' . $current_shortcode,
					$like
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s',
					$wpdb->postmeta,
					'[' . $legacy_shortcode,
					'[' . $current_shortcode,
					$like
				)
			);
		}

		wp_clear_scheduled_hook( 'pig_giveaways_daily_retention' );
		update_option( 'pigiveaways_flush_rewrite', 1, false );
		update_option( 'pigiveaways_legacy_migrated', PIGIVEAWAYS_VERSION, false );
	}
}
