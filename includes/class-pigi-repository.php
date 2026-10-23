<?php
defined( 'ABSPATH' ) || exit;

// Direct queries are required for the plugin's dedicated entry and award tables.
// Counts and moderation data must remain current, so these operational queries are intentionally not cached.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
final class PIGI_Repository {
	public static function add_entry( $giveaway_id, $data ) {
		global $wpdb;
		$giveaway_id = absint( $giveaway_id );
		$email       = empty( $data['email'] ) ? '' : strtolower( sanitize_email( $data['email'] ) );
		$table       = $wpdb->prefix . 'pigiveaways_entries';
		$maximum     = max( 0, (int) apply_filters( 'pigiveaways_max_entries', 0, $giveaway_id ) );
		if ( $maximum && self::entry_count( $giveaway_id ) >= $maximum ) {
			return new WP_Error( 'giveaway_full', __( 'This giveaway has reached its entry limit.', 'pi-giveaways' ) );
		}
		$per_email = max( 1, (int) apply_filters( 'pigiveaways_entries_per_email', 1, $giveaway_id, $email ) );
		if ( $email ) {
			$email_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE giveaway_id = %d AND email = %s', $table, $giveaway_id, $email ) );
			if ( $email_count >= $per_email ) {
				return new WP_Error( 'email_limit', __( 'This email address has reached the entry limit for this giveaway.', 'pi-giveaways' ) );
			}
		}
		$now           = current_time( 'mysql', true );
		$referral_code = strtolower( wp_generate_password( 12, false, false ) );
		$result        = $wpdb->insert(
			$table,
			array(
				'giveaway_id'       => $giveaway_id,
				'first_name'        => sanitize_text_field( $data['first_name'] ),
				'last_name'         => sanitize_text_field( $data['last_name'] ),
				'email'             => $email ? $email : null,
				'phone'             => sanitize_text_field( $data['phone'] ),
				'answer'            => sanitize_textarea_field( $data['answer'] ),
				'fields'            => wp_json_encode( (array) ( $data['fields'] ?? array() ) ),
				'status'            => 'valid',
				'consent_at'        => $now,
				'bonus_entries'     => absint( $data['bonus_entries'] ?? 0 ),
				'bonus_actions'     => wp_json_encode( (array) ( $data['bonus_actions'] ?? array() ) ),
				'referral_code'     => $referral_code,
				'referrer_entry_id' => absint( $data['referrer_entry_id'] ?? 0 ) ? absint( $data['referrer_entry_id'] ?? 0 ) : null,
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		return false === $result ? new WP_Error( 'entry_failed', __( 'The entry could not be saved.', 'pi-giveaways' ) ) : (int) $wpdb->insert_id;
	}

	public static function entries( $giveaway_id, $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pigiveaways_entries';
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE giveaway_id = %d ORDER BY created_at DESC LIMIT %d', $table, absint( $giveaway_id ), absint( $limit ) ) );
	}

	public static function entry_count( $giveaway_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pigiveaways_entries';
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE giveaway_id = %d', $table, absint( $giveaway_id ) ) );
	}

	public static function winner_count( $giveaway_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pigiveaways_awards';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE giveaway_id = %d AND award_type = 'winner' AND revoked_at IS NULL", $table, absint( $giveaway_id ) ) );
	}

	public static function winner_limit( $giveaway_id ) {
		$details = (array) get_post_meta( absint( $giveaway_id ), '_pigiveaways_details', true );
		return max( 1, min( 100, absint( $details['winners_count'] ?? 1 ) ) );
	}

	public static function expired_giveaways_awaiting_award() {
		$posts   = get_posts(
			array(
				'post_type'   => 'pigiveaways_giveaway',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		$results = array();
		$now     = time();
		foreach ( $posts as $post ) {
			$details = (array) get_post_meta( $post->ID, '_pigiveaways_details', true );
			$end     = empty( $details['end'] ) ? 0 : strtotime( $details['end'] );
			if ( ! $end || $now < $end || ! in_array( $details['status'] ?? '', array( 'active', 'ended' ), true ) ) {
				continue;
			} $entries = self::entry_count( $post->ID );
			$winners   = self::winner_count( $post->ID );
			if ( $entries > 0 && $winners < self::winner_limit( $post->ID ) ) {
				$post->pigiveaways_entry_count  = $entries;
				$post->pigiveaways_winner_count = $winners;
				$post->pigiveaways_end          = $details['end'];
				$results[]                      = $post; }
		}
		return $results;
	}

	public static function by_referral_code( $giveaway_id, $code ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pigiveaways_entries';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE giveaway_id = %d AND referral_code = %s AND status = 'valid'", $table, absint( $giveaway_id ), sanitize_key( $code ) ) );
	}

	public static function add_bonus( $entry_id, $amount, $action ) {
		global $wpdb;
		$entry = self::entry( $entry_id );
		if ( ! $entry || $amount < 1 ) {
			return false;
		} $actions = json_decode( $entry->bonus_actions ?? '', true );
		$actions   = is_array( $actions ) ? $actions : array();
		$actions[] = array(
			'action'  => sanitize_text_field( $action ),
			'entries' => absint( $amount ),
			'at'      => current_time( 'mysql', true ),
		);
		return false !== $wpdb->update(
			$wpdb->prefix . 'pigiveaways_entries',
			array(
				'bonus_entries' => absint( $entry->bonus_entries ) + absint( $amount ),
				'bonus_actions' => wp_json_encode( $actions ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $entry_id ) ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function entry( $entry_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pigiveaways_entries';
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, absint( $entry_id ) ) );
	}

	public static function set_status( $entry_id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'valid', 'flagged', 'disqualified' ), true ) ) {
			return false;
		}
		return false !== $wpdb->update(
			$wpdb->prefix . 'pigiveaways_entries',
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $entry_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function delete_entry( $entry_id ) {
		global $wpdb;
		$entry_id = absint( $entry_id );
		if ( ! $entry_id || ! self::entry( $entry_id ) ) {
			return false;
		}
		$wpdb->delete( $wpdb->prefix . 'pigiveaways_awards', array( 'entry_id' => $entry_id ), array( '%d' ) );
		return false !== $wpdb->delete( $wpdb->prefix . 'pigiveaways_entries', array( 'id' => $entry_id ), array( '%d' ) );
	}

	public static function purge_older_than( $days ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'pigiveaways_entries';
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, absint( $days ) ) * DAY_IN_SECONDS ) );
		$ids     = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE created_at < %s ORDER BY id ASC LIMIT 500', $table, $cutoff ) );
		$deleted = 0;
		foreach ( $ids as $entry_id ) {
			if ( self::delete_entry( $entry_id ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	public static function award( $entry_id, $note = '' ) {
		global $wpdb;
		$entry = self::entry( $entry_id );
		if ( ! $entry || 'valid' !== $entry->status ) {
			return new WP_Error( 'invalid_entry', __( 'Only a valid entry can receive an award.', 'pi-giveaways' ) );
		}
		$permission = apply_filters( 'pigiveaways_can_award_entry', true, $entry );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( false === $permission ) {
			return new WP_Error( 'award_not_allowed', __( 'This entry is not currently eligible to be awarded.', 'pi-giveaways' ) );
		}
		$table    = $wpdb->prefix . 'pigiveaways_awards';
		$winner_count = self::winner_count( $entry->giveaway_id );
		$winner_limit = self::winner_limit( $entry->giveaway_id );
		if ( $winner_count >= $winner_limit ) {
			return new WP_Error( 'winner_limit', sprintf( _n( 'This giveaway is limited to %d winner.', 'This giveaway is limited to %d winners.', $winner_limit, 'pi-giveaways' ), $winner_limit ) );
		}
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE entry_id = %d AND award_type = 'winner' AND revoked_at IS NULL", $table, $entry->id ) );
		if ( $existing ) {
			return new WP_Error( 'already_awarded', __( 'This entry is already recorded as a winner.', 'pi-giveaways' ) );
		}
		$inserted = $wpdb->insert(
			$table,
			array(
				'giveaway_id' => $entry->giveaway_id,
				'entry_id'    => $entry->id,
				'award_type'  => 'winner',
				'note'        => sanitize_textarea_field( $note ),
				'awarded_by'  => get_current_user_id(),
				'awarded_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'award_failed', __( 'The winner award could not be saved.', 'pi-giveaways' ) );
		}
		$award_id = (int) $wpdb->insert_id;
		do_action( 'pigiveaways_winner_awarded', $award_id, $entry );
		return $award_id;
	}

	public static function active_awards( $giveaway_id ) {
		global $wpdb;
		$a = $wpdb->prefix . 'pigiveaways_awards';
		$e = $wpdb->prefix . 'pigiveaways_entries';
		return $wpdb->get_results( $wpdb->prepare( 'SELECT a.*, e.first_name, e.last_name, e.email FROM %i a JOIN %i e ON e.id = a.entry_id WHERE a.giveaway_id = %d AND a.revoked_at IS NULL ORDER BY a.awarded_at DESC', $a, $e, absint( $giveaway_id ) ) );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
