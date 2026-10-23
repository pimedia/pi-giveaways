<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
// Entry and award records are deliberately retained. A future explicit deletion setting may remove them.
wp_clear_scheduled_hook( 'pigiveaways_daily_retention' );
delete_option( 'pigiveaways_settings' );
delete_option( 'pigiveaways_db_version' );
delete_option( 'pigiveaways_installed_at' );
delete_option( 'pigiveaways_review_notice_dismissed' );
delete_option( 'pigiveaways_review_notice_next' );
delete_option( 'pigiveaways_legacy_migrated' );
