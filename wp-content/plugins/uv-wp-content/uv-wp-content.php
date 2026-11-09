<?php
/**
 * Plugin Name: Umoja Voices Content & Choir Portal Engine
 * Description: Relational data model, authentication, SATB media/lyrics/sheet-music management, trip logistics, activity audit trail, storage quota tracking, and wp-admin integration for Umoja Voices (https://www.umojavoices.co.ke/).
 * Version: 0.6.0
 * Author: Eric Gitonga
 * Text Domain: uv-wp-content
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'UV_WP_CONTENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'UV_WP_CONTENT_URL', plugin_dir_url( __FILE__ ) );

require_once UV_WP_CONTENT_DIR . 'includes/constants-and-helpers.php';
require_once UV_WP_CONTENT_DIR . 'includes/schema-and-seed.php';
require_once UV_WP_CONTENT_DIR . 'includes/storage-and-activity.php';
require_once UV_WP_CONTENT_DIR . 'includes/auth-and-actions.php';
require_once UV_WP_CONTENT_DIR . 'includes/wp-admin-pages.php';

/**
 * Run database schema creation and optional demo seeding.
 *
 * @param bool $seed_demo Whether to seed default demo accounts & content if tables are empty.
 */
function uv_wp_run_setup( $seed_demo = false ) {
    uv_create_tables();
    if ( $seed_demo ) {
        uv_seed_demo_data();
    }
    flush_rewrite_rules();
}

register_activation_hook( __FILE__, function () {
    uv_wp_run_setup( true );
} );

add_action( 'init', function () {
    // Ensure tables exist on boot if not yet initialized.
    if ( get_option( 'uv_wp_schema_version' ) !== '0.2.0' ) {
        uv_create_tables();
        update_option( 'uv_wp_schema_version', '0.2.0' );
    }
}, 1 );

// Block unauthenticated WordPress REST API user enumeration (/wp-json/wp/v2/users).
add_filter( 'rest_endpoints', function ( $endpoints ) {
    if ( ! is_user_logged_in() && ! uv_get_current_user() ) {
        unset( $endpoints['/wp/v2/users'] );
        unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    }
    return $endpoints;
} );

