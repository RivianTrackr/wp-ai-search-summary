<?php
/**
 * Uninstall script for RivianTrackr AI Search Summary
 *
 * This file runs when the plugin is deleted via the WordPress admin.
 * It removes all plugin data including options, transients, and database tables
 * unless the user has enabled the "Preserve Data on Uninstall" option.
 *
 * @package RivianTrackr_AI_Search_Summary
 */

// Exit if accessed directly or not called by WordPress uninstall
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if the user wants to preserve data (check both old and new option names)
$riviantrackr_options = get_option( 'riviantrackr_options', array() );
if ( empty( $riviantrackr_options ) ) {
    $riviantrackr_options = get_option( 'searchlens_options', array() );
}
if ( ! empty( $riviantrackr_options['preserve_data_on_uninstall'] ) ) {
    // User chose to keep data — only clean up transients and object cache
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_riviantrackr_%'
            OR option_name LIKE '_transient_timeout_riviantrackr_%'
            OR option_name LIKE '_transient_searchlens_%'
            OR option_name LIKE '_transient_timeout_searchlens_%'"
    );

    delete_option( 'riviantrackr_models_cache' );
    delete_option( 'riviantrackr_anthropic_models_cache' );
    delete_option( 'riviantrackr_cache_namespace' );
    delete_option( 'riviantrackr_cache_keys' );
    // Clean up legacy searchlens options
    delete_option( 'searchlens_models_cache' );
    delete_option( 'searchlens_cache_namespace' );
    delete_option( 'searchlens_cache_keys' );

    wp_cache_flush();
    return;
}

global $wpdb;

// Delete plugin options (current and legacy prefixes)
delete_option( 'riviantrackr_options' );
delete_option( 'riviantrackr_models_cache' );
delete_option( 'riviantrackr_anthropic_models_cache' );
delete_option( 'riviantrackr_cache_namespace' );
delete_option( 'riviantrackr_cache_keys' );
delete_option( 'riviantrackr_db_version' );
delete_option( 'riviantrackr_version' );
// Legacy searchlens options (from pre-1.0.7 installations)
delete_option( 'searchlens_options' );
delete_option( 'searchlens_models_cache' );
delete_option( 'searchlens_cache_namespace' );
delete_option( 'searchlens_cache_keys' );
delete_option( 'searchlens_db_version' );

// Remove the scheduled log purge and per-user analytics preferences
wp_clear_scheduled_hook( 'riviantrackr_daily_log_purge' );
delete_metadata( 'user', 0, 'riviantrackr_hide_zero', '', true );

// Delete all transients created by the plugin (current and legacy prefixes)
// Transients are stored in options table with _transient_ prefix
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_riviantrackr_%'
        OR option_name LIKE '_transient_timeout_riviantrackr_%'
        OR option_name LIKE '_transient_searchlens_%'
        OR option_name LIKE '_transient_timeout_searchlens_%'"
);

// Drop the logs table (current and legacy names)
$riviantrackr_table_name = $wpdb->prefix . 'riviantrackr_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $riviantrackr_table_name ) );
$riviantrackr_legacy_logs = $wpdb->prefix . 'searchlens_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $riviantrackr_legacy_logs ) );

// Drop the feedback table (current and legacy names)
$riviantrackr_feedback_table = $wpdb->prefix . 'riviantrackr_feedback';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $riviantrackr_feedback_table ) );
$riviantrackr_legacy_feedback = $wpdb->prefix . 'searchlens_feedback';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $riviantrackr_legacy_feedback ) );

// Clear any cached data in object cache (if persistent caching is used)
wp_cache_flush();
