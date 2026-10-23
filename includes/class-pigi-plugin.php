<?php
defined( 'ABSPATH' ) || exit;

require_once PIGIVEAWAYS_DIR . 'includes/class-pigi-repository.php';
require_once PIGIVEAWAYS_DIR . 'includes/class-pigi-admin.php';
require_once PIGIVEAWAYS_DIR . 'includes/class-pigi-frontend.php';

final class PIGI_Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_content' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );
		add_action( 'init', array( $this, 'sync_retention_schedule' ), 20 );
		add_action( 'pigiveaways_daily_retention', array( $this, 'purge_expired_entries' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_listing_page' ) );
		new PIGI_Admin();
		new PIGI_Frontend();
	}

	public static function register_content() {
		$settings     = get_option( 'pigiveaways_settings', array() );
		$archive_slug = implode( '/', array_filter( array_map( 'sanitize_title', explode( '/', trim( (string) ( $settings['archive_slug'] ?? 'giveaways' ), '/' ) ) ) ) );
		$archive_slug = $archive_slug ? $archive_slug : 'giveaways';
		register_post_type(
			'pigiveaways_giveaway',
			array(
				'labels'       => array(
					'name'          => __( 'Giveaways', 'pi-giveaways' ),
					'singular_name' => __( 'Giveaway', 'pi-giveaways' ),
					'add_new_item'  => __( 'Add New Giveaway', 'pi-giveaways' ),
					'edit_item'     => __( 'Edit Giveaway', 'pi-giveaways' ),
					'view_item'     => __( 'View Giveaway', 'pi-giveaways' ),
					'search_items'  => __( 'Search Giveaways', 'pi-giveaways' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'show_in_menu' => false,
				'has_archive'  => false,
				'rewrite'      => array(
					'slug'       => $archive_slug,
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			)
		);
		register_taxonomy(
			'pigiveaways_category',
			'pigiveaways_giveaway',
			array(
				'labels'       => array(
					'name'          => __( 'Giveaway Categories', 'pi-giveaways' ),
					'singular_name' => __( 'Giveaway Category', 'pi-giveaways' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'giveaway-category' ),
			)
		);
	}

	public static function ensure_listing_page() {
		$settings  = get_option( 'pigiveaways_settings', array() );
		$slug      = trim( (string) ( $settings['archive_slug'] ?? 'giveaways' ), '/' );
		$slug      = $slug ? $slug : 'giveaways';
		$stored_id = absint( $settings['archive_page_id'] ?? 0 );
		$page      = $stored_id ? get_post( $stored_id ) : null;
		if ( $page && ( 'page' !== $page->post_type || trim( get_page_uri( $page ), '/' ) !== $slug ) ) {
			$page = null;
		}
		if ( ! $page ) {
			$page = get_page_by_path( $slug );
		}
		$parts     = array_values( array_filter( array_map( 'sanitize_title', explode( '/', $slug ) ) ) );
		$leaf      = array_pop( $parts );
		$parent_id = 0;
		$path      = '';
		foreach ( $parts as $part ) {
			$path   = $path ? $path . '/' . $part : $part;
			$parent = get_page_by_path( $path );
			if ( ! $parent ) {
				$parent_id = wp_insert_post(
					array(
						'post_type'   => 'page',
						'post_status' => 'publish',
						'post_title'  => ucwords( str_replace( '-', ' ', $part ) ),
						'post_name'   => $part,
						'post_parent' => $parent_id,
					)
				);
				if ( is_wp_error( $parent_id ) ) {
					return;
				}
			} else {
				$parent_id = $parent->ID;
			}
		}
		if ( ! $page ) {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => sanitize_text_field( $settings['archive_title'] ?? __( 'Giveaways', 'pi-giveaways' ) ),
					'post_name'    => $leaf,
					'post_parent'  => $parent_id,
					'post_content' => '',
				)
			);
			if ( is_wp_error( $page_id ) ) {
				return;
			}
		} else {
			$page_id = $page->ID;
		}
		if ( $stored_id !== (int) $page_id ) {
			$settings['archive_page_id'] = (int) $page_id;
			update_option( 'pigiveaways_settings', $settings, false );
			update_option( 'pigiveaways_flush_rewrite', 1, false );
		}
	}

	public function maybe_upgrade() {
		if ( PIGIVEAWAYS_VERSION !== get_option( 'pigiveaways_db_version' ) ) {
			PIGI_Activator::activate();
			$this->purge_style_caches();
		}
	}

	private function purge_style_caches() {
		wp_cache_flush();
		do_action( 'autoptimize_action_cachepurged' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
		}
	}

	public function maybe_flush_rewrite() {
		if ( get_option( 'pigiveaways_flush_rewrite' ) ) {
			flush_rewrite_rules( false );
			delete_option( 'pigiveaways_flush_rewrite' );
		}
	}

	public function sync_retention_schedule() {
		$settings  = get_option( 'pigiveaways_settings', array() );
		$scheduled = wp_next_scheduled( 'pigiveaways_daily_retention' );
		if ( ! empty( $settings['retention_enabled'] ) && ! $scheduled ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pigiveaways_daily_retention' );
		}
		if ( empty( $settings['retention_enabled'] ) && $scheduled ) {
			wp_clear_scheduled_hook( 'pigiveaways_daily_retention' );
		}
	}

	public function purge_expired_entries() {
		$settings = get_option( 'pigiveaways_settings', array() );
		if ( empty( $settings['retention_enabled'] ) ) {
			return;
		}
		PIGI_Repository::purge_older_than( min( 3650, max( 1, absint( $settings['retention_days'] ?? 90 ) ) ) );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'pigiveaways_daily_retention' );
		flush_rewrite_rules();
	}
}
