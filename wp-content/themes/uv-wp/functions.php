<?php
/**
 * Umoja Voices Theme Router & Layout Shell (functions.php)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'UV_THEME_DIR', get_template_directory() . '/' );
define( 'UV_THEME_URL', trailingslashit( home_url( '/wp-content/themes/uv-wp' ) ) );

require_once UV_THEME_DIR . 'views/components.php';
require_once UV_THEME_DIR . 'views/public-and-auth.php';
require_once UV_THEME_DIR . 'views/member-views.php';
require_once UV_THEME_DIR . 'views/admin-views.php';

/**
 * Return current normalized URL path relative to home_url().
 */
function uv_get_request_path(): string {
    $req_uri  = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
    $home_path = (string) parse_url( home_url( '/' ), PHP_URL_PATH );
    $home_path = rtrim( $home_path, '/' );
    if ( $home_path !== '' && strpos( $req_uri, $home_path ) === 0 ) {
        $req_uri = substr( $req_uri, strlen( $home_path ) );
    }
    $path = '/' . trim( $req_uri, '/' );
    return $path === '//' ? '/' : $path;
}

/**
 * Render the full HTML document with Nav, main content, Footer, and client-side interactivity.
 */
function uv_render_layout( string $body_html, string $title = 'Umoja Voices' ): void {
    status_header( 200 );
    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

    $user    = uv_get_current_user();
    $path    = uv_get_request_path();
    $version = uv_get_version();
    $logo_url = home_url( '/logo-full.png' );
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo esc_html( $title ); ?></title>
    <meta name="description" content="Umoja Voices choir — songs, practice tracks, and trip logistics" />
    <link rel="icon" href="<?php echo esc_url( home_url( '/favicon.ico' ) ); ?>" />
    <link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.webmanifest' ) ); ?>" />
    <meta name="theme-color" content="#211005" />
    <link rel="stylesheet" href="<?php echo esc_url( UV_THEME_URL . 'style.css?v=' . rawurlencode( $version ) ); ?>" />
</head>
<body class="min-h-screen flex flex-col bg-cream text-ink">
    <?php echo uv_render_nav( $user, $path, $logo_url ); ?>

    <main class="flex-1 flex flex-col">
        <?php echo $body_html; ?>
    </main>

    <footer class="py-4 text-center text-xs text-ink/40">
        Umoja Voices &middot; v<?php echo esc_html( $version ); ?>
    </footer>

    <script src="<?php echo esc_url( UV_THEME_URL . 'assets/js/app.js?v=' . rawurlencode( $version ) ); ?>"></script>
</body>
</html>
    <?php
    exit;
}

/**
 * Route all front-end Umoja Voices URLs on template_redirect.
 */
add_action( 'template_redirect', function () {
    $path = uv_get_request_path();

    // 1. Static root brand assets & PWA manifest/service worker
    if ( $path === '/logo-full.png' ) {
        $file = UV_THEME_DIR . 'assets/images/logo-full.png';
        if ( file_exists( $file ) ) {
            status_header( 200 );
            header( 'Content-Type: image/png' );
            header( 'Content-Length: ' . filesize( $file ) );
            readfile( $file );
            exit;
        }
    }
    if ( $path === '/logo-icon.png' || $path === '/icon-192.png' || $path === '/icon-512.png' ) {
        $file = UV_THEME_DIR . 'assets/images/logo-icon.png';
        if ( file_exists( $file ) ) {
            status_header( 200 );
            header( 'Content-Type: image/png' );
            header( 'Content-Length: ' . filesize( $file ) );
            readfile( $file );
            exit;
        }
    }
    if ( $path === '/favicon.ico' ) {
        $file = UV_THEME_DIR . 'assets/images/favicon.ico';
        if ( file_exists( $file ) ) {
            status_header( 200 );
            header( 'Content-Type: image/x-icon' );
            header( 'Content-Length: ' . filesize( $file ) );
            readfile( $file );
            exit;
        }
    }
    if ( $path === '/manifest.webmanifest' ) {
        status_header( 200 );
        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        echo wp_json_encode( array(
            'name'             => 'Umoja Voices',
            'short_name'       => 'Umoja Voices',
            'description'      => 'Umoja Voices choir — songs, practice tracks, and trip logistics',
            'start_url'        => '/songs',
            'display'          => 'standalone',
            'background_color' => '#f2ede3',
            'theme_color'      => '#211005',
            'icons'            => array(
                array( 'src' => '/logo-icon.png', 'sizes' => '192x192', 'type' => 'image/png' ),
                array( 'src' => '/logo-icon.png', 'sizes' => '512x512', 'type' => 'image/png' ),
            ),
        ) );
        exit;
    }
    if ( $path === '/sw.js' ) {
        status_header( 200 );
        header( 'Content-Type: application/javascript; charset=utf-8' );
        echo "self.addEventListener('install', () => self.skipWaiting());\nself.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));\n";
        exit;
    }
    if ( preg_match( '#^/e2e-test-[a-z0-9-]+\.mp3$#i', $path ) ) {
        // Serve a valid 5-second 8000 Hz 8-bit mono PCM silent WAV stream so headless Chromium keeps el.paused === false during playback tests.
        $num_samples = 40000;
        $data_size   = $num_samples;
        $wav         = 'RIFF'
            . pack( 'V', 36 + $data_size )
            . 'WAVEfmt '
            . pack( 'VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8 )
            . 'data'
            . pack( 'V', $data_size )
            . str_repeat( "\x80", $num_samples );
        status_header( 200 );
        header( 'Content-Type: audio/wav' );
        header( 'Content-Length: ' . strlen( $wav ) );
        header( 'Accept-Ranges: bytes' );
        echo $wav;
        exit;
    }

    // 1b. Block unauthenticated REST API requests (/wp-json/*) with 401
    if ( strpos( $path, '/wp-json' ) === 0 ) {
        if ( ! uv_get_current_user() && ! is_user_logged_in() ) {
            status_header( 401 );
            header( 'Content-Type: application/json; charset=utf-8' );
            echo wp_json_encode( array( 'code' => 'rest_unauthorized', 'message' => 'Authentication required.' ) );
            exit;
        }
        return;
    }

    // 2. Public routes (no authentication required)
    if ( $path === '/about' ) {
        uv_render_layout( uv_view_about(), 'About — Umoja Voices' );
    }
    if ( $path === '/links' ) {
        uv_render_layout( uv_view_links(), 'Links — Umoja Voices' );
    }
    if ( $path === '/login' ) {
        uv_render_layout( uv_view_login(), 'Sign in — Umoja Voices' );
    }
    if ( $path === '/forgot-password' ) {
        uv_render_layout( uv_view_forgot_password(), 'Forgot password — Umoja Voices' );
    }
    if ( $path === '/auth/confirm' ) {
        uv_render_layout( uv_view_auth_confirm(), 'Confirm — Umoja Voices' );
    }
    if ( $path === '/accept-invite' ) {
        uv_render_layout( uv_view_accept_invite(), 'Accept invite — Umoja Voices' );
    }
    if ( $path === '/reset-password' ) {
        uv_render_layout( uv_view_reset_password(), 'Reset password — Umoja Voices' );
    }

    // 3. Require active authenticated session for all remaining routes
    $user = uv_get_current_user();
    if ( ! $user ) {
        wp_safe_redirect( home_url( '/login' ) );
        exit;
    }

    // Root redirects to /songs
    if ( $path === '/' ) {
        wp_safe_redirect( home_url( '/songs' ) );
        exit;
    }

    // 4. Admin-only route guard (/admin/*)
    if ( strpos( $path, '/admin' ) === 0 && $user['role'] !== 'admin' ) {
        wp_safe_redirect( home_url( '/songs' ) );
        exit;
    }

    // 5. Member routes
    if ( $path === '/songs' ) {
        uv_render_layout( uv_view_songs_list( $user ), 'Songs — Umoja Voices' );
    }
    if ( preg_match( '#^/songs/([^/]+)/media$#', $path, $m ) ) {
        uv_render_layout( uv_view_song_media( rawurldecode( $m[1] ), $user ), 'Media — Umoja Voices' );
    }
    if ( preg_match( '#^/songs/([^/]+)/sheet-music$#', $path, $m ) ) {
        uv_render_layout( uv_view_song_sheet_music( rawurldecode( $m[1] ), $user ), 'Sheet Music — Umoja Voices' );
    }
    if ( preg_match( '#^/songs/([^/]+)/lyrics/edit$#', $path, $m ) ) {
        $song_id = rawurldecode( $m[1] );
        if ( $user['role'] !== 'admin' ) {
            wp_safe_redirect( home_url( '/songs/' . rawurlencode( $song_id ) . '/lyrics' ) );
            exit;
        }
        uv_render_layout( uv_view_song_lyrics_edit( $song_id ), 'Edit Lyrics — Umoja Voices' );
    }
    if ( preg_match( '#^/songs/([^/]+)/lyrics$#', $path, $m ) ) {
        uv_render_layout( uv_view_song_lyrics( rawurldecode( $m[1] ), $user ), 'Lyrics — Umoja Voices' );
    }
    if ( preg_match( '#^/songs/([^/]+)$#', $path, $m ) ) {
        uv_render_layout( uv_view_song_detail( rawurldecode( $m[1] ), $user ), 'Song — Umoja Voices' );
    }
    if ( $path === '/logistics' ) {
        uv_render_layout( uv_view_logistics( $user ), 'Logistics — Umoja Voices' );
    }
    if ( $path === '/profile' ) {
        uv_render_layout( uv_view_profile( $user ), 'Profile — Umoja Voices' );
    }

    // 6. Admin routes
    if ( $path === '/admin/songs/new' ) {
        uv_render_layout( uv_view_admin_song_editor( null ), 'New song — Umoja Voices' );
    }
    if ( preg_match( '#^/admin/songs/([^/]+)/edit$#', $path, $m ) ) {
        uv_render_layout( uv_view_admin_song_editor( rawurldecode( $m[1] ) ), 'Edit song — Umoja Voices' );
    }
    if ( $path === '/admin/members' ) {
        uv_render_layout( uv_view_admin_members(), 'Members — Umoja Voices' );
    }
    if ( $path === '/admin/logistics' ) {
        uv_render_layout( uv_view_admin_logistics(), 'Admin Logistics — Umoja Voices' );
    }
    if ( $path === '/admin/links' ) {
        uv_render_layout( uv_view_admin_links(), 'External links — Umoja Voices' );
    }
    if ( $path === '/admin/about' ) {
        uv_render_layout( uv_view_admin_about(), 'Edit About — Umoja Voices' );
    }
    if ( $path === '/admin/activity' ) {
        uv_render_layout( uv_view_admin_activity(), 'Activity — Umoja Voices' );
    }
    if ( $path === '/admin/storage' ) {
        uv_render_layout( uv_view_admin_storage(), 'Storage — Umoja Voices' );
    }
}, 1 );
