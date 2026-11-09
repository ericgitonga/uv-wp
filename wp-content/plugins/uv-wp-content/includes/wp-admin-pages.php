<?php
/**
 * Native wp-admin dashboard integration for Umoja Voices:
 * - Top-level "Umoja Voices" menu in wp-admin showing live summary & quick links to all Choir Portal management screens
 * - "Tools -> UV Setup & Seed" page for one-click schema check & demo seeding
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', function () {
    add_menu_page(
        'Umoja Voices Choir Portal',
        'Umoja Voices',
        'manage_options',
        'uv-wp-dashboard',
        'uv_render_wp_admin_dashboard',
        'dashicons-format-audio',
        3
    );

    add_management_page(
        'Umoja Voices Setup & Seed',
        'UV Setup & Seed',
        'manage_options',
        'uv-wp-setup',
        'uv_render_wp_admin_setup'
    );
} );

function uv_render_wp_admin_dashboard(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    global $wpdb;
    $songs_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'songs' ) );
    $members_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'users' ) );
    $trips_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'trips' ) );
    $links_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'external_links' ) );
    $about_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'about_sections' ) );
    $logs_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'activity_logs' ) );
    ?>
    <div class="wrap">
        <h1>Umoja Voices — Choir Portal Overview (v<?php echo esc_html( uv_get_version() ); ?>)</h1>
        <p>Manage songs, SATB voice media, sheet music PDFs, lyrics, trip logistics, about blocks, external links, and choir members.</p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:24px 0;">
            <div class="card" style="margin:0;padding:16px;">
                <h2 style="margin-top:0;">Songs (<?php echo (int) $songs_count; ?>)</h2>
                <p><a class="button button-primary" href="<?php echo esc_url( home_url( '/songs' ) ); ?>">Open Song Library</a> <a class="button" href="<?php echo esc_url( home_url( '/admin/songs/new' ) ); ?>">New Song</a></p>
            </div>
            <div class="card" style="margin:0;padding:16px;">
                <h2 style="margin-top:0;">Choir Members (<?php echo (int) $members_count; ?>)</h2>
                <p><a class="button button-primary" href="<?php echo esc_url( home_url( '/admin/members' ) ); ?>">Manage Members &amp; Invites</a></p>
            </div>
            <div class="card" style="margin:0;padding:16px;">
                <h2 style="margin-top:0;">Trips &amp; Logistics (<?php echo (int) $trips_count; ?>)</h2>
                <p><a class="button button-primary" href="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>">Manage Logistics</a></p>
            </div>
            <div class="card" style="margin:0;padding:16px;">
                <h2 style="margin-top:0;">About Blocks (<?php echo (int) $about_count; ?>)</h2>
                <p><a class="button button-primary" href="<?php echo esc_url( home_url( '/admin/about' ) ); ?>">Edit About Page</a></p>
            </div>
            <div class="card" style="margin:0;padding:16px;">
                <h2 style="margin-top:0;">External Links (<?php echo (int) $links_count; ?>)</h2>
                <p><a class="button button-primary" href="<?php echo esc_url( home_url( '/admin/links' ) ); ?>">Manage External Links</a></p>
            </div>
            <div class="card" style="margin:0;padding:16px;">
                <h2 style="margin-top:0;">Activity &amp; Storage (<?php echo (int) $logs_count; ?> logs)</h2>
                <p><a class="button" href="<?php echo esc_url( home_url( '/admin/activity' ) ); ?>">Activity Trail</a> <a class="button" href="<?php echo esc_url( home_url( '/admin/storage' ) ); ?>">Storage Quota</a></p>
            </div>
        </div>
    </div>
    <?php
}

function uv_render_wp_admin_setup(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    $message = '';
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['uv_run_setup_nonce'] ) ) {
        check_admin_referer( 'uv_run_setup_action', 'uv_run_setup_nonce' );
        $seed_demo = ! empty( $_POST['seed_demo'] );
        $seed_live = ! empty( $_POST['seed_live_repertoire'] );
        uv_wp_run_setup( $seed_demo );
        if ( $seed_live ) {
            $res = uv_seed_live_umoja_repertoire( true );
            $message = 'Database tables verified and ' . (int) $res['songs'] . ' live Umoja Voices songs + 2026 Europe Tour logistics seeded.';
        } else {
            $message = $seed_demo
                ? 'Database tables verified and demo content re-seeded.'
                : 'Database tables and rewrite rules verified.';
        }
    }
    ?>
    <div class="wrap">
        <h1>Umoja Voices — Setup &amp; Seed</h1>
        <?php if ( $message ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field( 'uv_run_setup_action', 'uv_run_setup_nonce' ); ?>
            <p>
                <label>
                    <input type="checkbox" name="seed_demo" value="1" />
                    Also seed/reset default demo accounts and demo song/trip content
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="seed_live_repertoire" value="1" />
                    Seed full 2026 Umoja Voices repertoire (12 songs, SATB audio/video, PDF sheet music, lyrics, Europe Tour 2026 logistics, About &amp; Links)
                </label>
            </p>
            <p><button type="submit" class="button button-primary">Run Setup</button></p>
        </form>
    </div>
    <?php
}
