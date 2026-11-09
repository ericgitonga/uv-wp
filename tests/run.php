<?php
/**
 * Unit test suite for Umoja Voices (uv-wp) core functions.
 *
 * Usage:
 *   php tests/run.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( string $url ): string {
        return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
    }
}

require_once dirname( __DIR__ ) . '/wp-content/plugins/uv-wp-content/includes/constants-and-helpers.php';
require_once dirname( __DIR__ ) . '/wp-content/plugins/uv-wp-content/includes/storage-and-activity.php';

$passed = 0;
$failed = 0;

function uv_assert( bool $cond, string $message ): void {
    global $passed, $failed;
    if ( $cond ) {
        $passed++;
        echo "PASS: {$message}\n";
    } else {
        $failed++;
        fwrite( STDERR, "FAIL: {$message}\n" );
    }
}

// 1. Lyrics paste parser & round-trip serializer
$paste = "Verse 1\n[S] Morning breaks over the hills\nOne voice calls\n\nChorus\n[ALL] Rising together\nFour parts, one song\n\nBridge\nCarry forward";
$parsed = uv_parse_lyrics_paste( $paste );
uv_assert( count( $parsed ) === 3, 'uv_parse_lyrics_paste extracts 3 sections' );
uv_assert( $parsed[0]['sectionType'] === 'verse' && $parsed[0]['voiceTags'] === array( 'S' ), 'Verse 1 parsed with S tag' );
uv_assert( $parsed[1]['sectionType'] === 'chorus' && $parsed[1]['voiceTags'] === array( 'SATB' ), 'Chorus parsed with [ALL] normalized to SATB' );
uv_assert( $parsed[2]['sectionType'] === 'bridge' && $parsed[2]['voiceTags'] === array( 'SATB' ), 'Bridge carries forward SATB tag' );

$serialized = uv_serialize_lyrics_for_edit( $parsed );
$reparsed   = uv_parse_lyrics_paste( $serialized );
uv_assert( $reparsed === $parsed, 'uv_serialize_lyrics_for_edit round-trips losslessly' );

// 2. EAT Timestamp Formatter
$eat = uv_format_eat( '2026-07-21 09:04:00' );
uv_assert( strpos( $eat, '12:04 EAT' ) !== false && strpos( $eat, '21 Jul 2026' ) !== false, 'uv_format_eat converts UTC to Africa/Nairobi (+3)' );

// 3. User-Agent Parser
$ua1 = uv_parse_user_agent( 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/128.0.0.0 Safari/537.36' );
uv_assert( $ua1['browser'] === 'Chrome' && $ua1['os'] === 'Linux', 'uv_parse_user_agent detects Chrome on Linux' );

$ua2 = uv_parse_user_agent( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Version/17.0 Safari/605.1.15' );
uv_assert( $ua2['browser'] === 'Safari' && $ua2['os'] === 'macOS', 'uv_parse_user_agent detects Safari on macOS' );

// 4. Media Kind Detector & Embed URL
uv_assert( uv_detect_media_kind( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) === 'youtube', 'uv_detect_media_kind detects YouTube' );
uv_assert( uv_detect_media_kind( 'https://drive.google.com/file/d/12345/view' ) === 'drive', 'uv_detect_media_kind detects Google Drive' );
uv_assert( uv_detect_media_kind( 'https://example.com/track.mp3' ) === 'audio', 'uv_detect_media_kind detects MP3 audio' );
uv_assert( uv_detect_media_kind( 'https://example.com/clip.mp4' ) === 'video', 'uv_detect_media_kind detects MP4 video' );
uv_assert( strpos( (string) uv_get_embed_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube' ), 'youtube.com/embed/dQw4w9WgXcQ' ) !== false, 'uv_get_embed_url builds YouTube embed URL' );

// 5. Linkify Text
$linked = uv_linkify_text( 'Support [WRAK](https://whiteribbonalliancekenya.org/) and visit https://umojavoices.co.ke' );
uv_assert( strpos( $linked, 'href="https://whiteribbonalliancekenya.org/"' ) !== false && strpos( $linked, '>WRAK</a>' ) !== false, 'uv_linkify_text converts [text](url)' );
uv_assert( strpos( $linked, 'href="https://umojavoices.co.ke"' ) !== false, 'uv_linkify_text auto-links bare URLs' );

// 6. Audio Magic-Byte Sniffing
$tmp_mp3 = tempnam( sys_get_temp_dir(), 'uv_test_mp3_' );
file_put_contents( $tmp_mp3, "ID3\x04\x00\x00\x00\x00\x00\x00\x00\x00" );
uv_assert( uv_verify_audio_magic_bytes( $tmp_mp3 ) === true, 'uv_verify_audio_magic_bytes accepts ID3 MP3 header' );
file_put_contents( $tmp_mp3, "NOT_AUDIO_BYTES" );
uv_assert( uv_verify_audio_magic_bytes( $tmp_mp3 ) === false, 'uv_verify_audio_magic_bytes rejects non-audio bytes' );
@unlink( $tmp_mp3 );

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
