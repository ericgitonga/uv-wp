<?php
/**
 * Constants, domain enums, lyrics parser/serializer, linkifier, EAT formatter, and UA parser.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Upload size caps matching Umoja Voices specifications.
define( 'UV_AUDIO_MAX_BYTES', 20 * 1024 * 1024 );        // 20 MB
define( 'UV_VIDEO_MAX_BYTES', 20 * 1024 * 1024 );        // 20 MB
define( 'UV_SHEET_MUSIC_MAX_BYTES', 10 * 1024 * 1024 );  // 10 MB
define( 'UV_PROFILE_PHOTO_MAX_BYTES', 5 * 1024 * 1024 ); // 5 MB
define( 'UV_STORAGE_BUDGET_BYTES', 1024 * 1024 * 1024 ); // 1 GB

define( 'UV_AUDIO_ACCEPT', 'audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,.mp3,.wav,.m4a,.aac,.ogg' );
define( 'UV_VIDEO_ACCEPT', 'video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm' );
define( 'UV_SHEET_MUSIC_ACCEPT', 'application/pdf,.pdf' );
define( 'UV_PROFILE_PHOTO_ACCEPT', 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp' );

/**
 * Return the current VERSION string from the project root.
 */
function uv_get_version(): string {
    $candidates = array(
        ABSPATH . 'VERSION',
        dirname( ABSPATH ) . '/VERSION',
        dirname( dirname( ABSPATH ) ) . '/VERSION',
    );
    foreach ( $candidates as $file ) {
        if ( file_exists( $file ) ) {
            $v = trim( (string) file_get_contents( $file ) );
            if ( $v !== '' ) {
                return $v;
            }
        }
    }
    return '0.2.0';
}

/**
 * Generate a collision-resistant ID string.
 */
function uv_cuid(): string {
    return 'c' . dechex( (int) ( microtime( true ) * 1000 ) ) . bin2hex( random_bytes( 8 ) );
}

/**
 * Voice & section constants.
 */
function uv_song_part_options(): array {
    return array( 'S', 'A', 'T', 'B', 'All' );
}

function uv_song_part_label_text(): array {
    return array(
        'S'   => 'Soprano (S)',
        'A'   => 'Alto (A)',
        'T'   => 'Tenor (T)',
        'B'   => 'Bass (B)',
        'All' => 'All voices (SATB)',
    );
}

function uv_song_part_heading(): array {
    return array(
        'S'   => 'Soprano',
        'A'   => 'Alto',
        'T'   => 'Tenor',
        'B'   => 'Bass',
        'All' => 'All Voices',
    );
}

function uv_voice_parts(): array {
    return array( 'S', 'A', 'T', 'B' );
}

function uv_voice_tags(): array {
    return array( 'S', 'A', 'T', 'B', 'SATB' );
}

function uv_voice_tag_labels(): array {
    return array(
        'S'    => 'Soprano (S)',
        'A'    => 'Alto (A)',
        'T'    => 'Tenor (T)',
        'B'    => 'Bass (B)',
        'SATB' => 'All voices (SATB)',
    );
}

function uv_lyric_section_types(): array {
    return array( 'verse', 'chorus', 'bridge', 'intro', 'outro', 'vamp', 'custom' );
}

function uv_link_categories(): array {
    return array( 'social', 'news', 'media', 'other' );
}

function uv_deadline_categories(): array {
    return array( 'visa', 'tickets', 'payment', 'other' );
}

function uv_activity_action_labels(): array {
    return array(
        'login'                     => 'Logged in',
        'song.create'               => 'Created a song',
        'song.update'               => 'Updated a song',
        'song.delete'               => 'Deleted a song',
        'song.media.add'            => 'Added song media',
        'song.media.remove'         => 'Removed song media',
        'song.sheet_music.add'      => 'Added sheet music',
        'song.sheet_music.remove'   => 'Removed sheet music',
        'song.lyrics.replace'       => 'Replaced song lyrics',
        'member.invite'             => 'Invited a member',
        'member.role_change'        => "Changed a member's role",
        'member.status_change'      => "Changed a member's status",
        'member.delete'             => 'Deleted a member',
        'logistics.trip.create'     => 'Created a trip',
        'logistics.deadline.add'    => 'Added a logistics deadline',
        'logistics.deadline.remove' => 'Removed a logistics deadline',
        'logistics.itinerary.add'   => 'Added an itinerary item',
        'logistics.itinerary.remove'=> 'Removed an itinerary item',
        'logistics.practice.add'    => 'Added a practice session',
        'logistics.practice.remove' => 'Removed a practice session',
    );
}

/**
 * Parse comma-separated voice tags string into array of valid tags.
 */
function uv_parse_voice_tags( string $raw ): array {
    $valid = uv_voice_tags();
    $parts = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
    $out   = array();
    foreach ( $parts as $p ) {
        if ( in_array( $p, $valid, true ) ) {
            $out[] = $p;
        }
    }
    return empty( $out ) ? array( 'SATB' ) : array_values( $out );
}

/**
 * Format a UTC/ISO timestamp or DateTime in East Africa Time (Africa/Nairobi).
 * Example output: "09 Nov 2026, 02:30 EAT"
 */
function uv_format_eat( $date_input ): string {
    try {
        if ( $date_input instanceof DateTimeInterface ) {
            $dt = new DateTime( $date_input->format( 'c' ) );
        } else {
            $raw = trim( (string) $date_input );
            // If SQLite/MySQL timestamp has no timezone designator, treat as UTC.
            if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw ) ) {
                $dt = new DateTime( $raw, new DateTimeZone( 'UTC' ) );
            } else {
                $dt = new DateTime( $raw );
            }
        }
        $dt->setTimezone( new DateTimeZone( 'Africa/Nairobi' ) );
        return $dt->format( 'd M Y, H:i' ) . ' EAT';
    } catch ( Exception $e ) {
        return (string) $date_input . ' EAT';
    }
}

/**
 * Lightweight User-Agent parser matching src/lib/user-agent.ts.
 */
function uv_parse_user_agent( ?string $user_agent ): array {
    if ( empty( $user_agent ) ) {
        return array( 'browser' => 'Unknown', 'os' => 'Unknown' );
    }
    $ua = $user_agent;

    if ( strpos( $ua, 'Windows' ) !== false ) {
        $os = 'Windows';
    } elseif ( strpos( $ua, 'Mac OS X' ) !== false && ! preg_match( '/iPhone|iPad|iPod/', $ua ) ) {
        $os = 'macOS';
    } elseif ( strpos( $ua, 'Android' ) !== false ) {
        $os = 'Android';
    } elseif ( preg_match( '/iPhone|iPad|iPod/', $ua ) ) {
        $os = 'iOS';
    } elseif ( strpos( $ua, 'Linux' ) !== false ) {
        $os = 'Linux';
    } else {
        $os = 'Unknown';
    }

    if ( strpos( $ua, 'Edg/' ) !== false ) {
        $browser = 'Edge';
    } elseif ( strpos( $ua, 'OPR/' ) !== false ) {
        $browser = 'Opera';
    } elseif ( strpos( $ua, 'Firefox/' ) !== false ) {
        $browser = 'Firefox';
    } elseif ( strpos( $ua, 'CriOS/' ) !== false ) {
        $browser = 'Chrome';
    } elseif ( strpos( $ua, 'Chrome/' ) !== false ) {
        $browser = 'Chrome';
    } elseif ( strpos( $ua, 'Safari/' ) !== false ) {
        $browser = 'Safari';
    } else {
        $browser = 'Unknown';
    }

    return array( 'browser' => $browser, 'os' => $os );
}

/**
 * Detect mediaKind from URL matching src/lib/constants.ts & MediaEmbed.tsx.
 */
function uv_detect_media_kind( string $url ): string {
    $lower = strtolower( trim( $url ) );
    if ( strpos( $lower, 'youtube.com' ) !== false || strpos( $lower, 'youtu.be' ) !== false ) {
        return 'youtube';
    }
    if ( strpos( $lower, 'drive.google.com' ) !== false ) {
        return 'drive';
    }
    if ( strpos( $lower, 'soundcloud.com' ) !== false ) {
        return 'soundcloud';
    }
    $path = parse_url( $lower, PHP_URL_PATH ) ?: $lower;
    if ( preg_match( '/\.(mp3|wav|m4a|aac|ogg|flac)$/', $path ) ) {
        return 'audio';
    }
    if ( preg_match( '/\.(mp4|mov|webm|m4v)$/', $path ) ) {
        return 'video';
    }
    return 'direct_url';
}

/**
 * Convert a YouTube or Google Drive URL into an embeddable iframe URL.
 */
function uv_get_embed_url( string $url, string $kind ): ?string {
    if ( $kind === 'youtube' ) {
        if ( preg_match( '/(?:v=|youtu\.be\/|embed\/|shorts\/)([A-Za-z0-9_-]{6,})/', $url, $m ) ) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?enablejsapi=1';
        }
        return null;
    }
    if ( $kind === 'drive' ) {
        if ( preg_match( '/\/file\/d\/([^\/]+)/', $url, $m ) ) {
            return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
        }
        return null;
    }
    if ( $kind === 'soundcloud' ) {
        return 'https://w.soundcloud.com/player/?url=' . rawurlencode( $url ) . '&color=%23211005&auto_play=false&show_comments=false';
    }
    return null;
}

/**
 * Infer lyric section type from label line.
 */
function uv_infer_lyric_section_type( string $label ): string {
    $lower = strtolower( trim( $label ) );
    foreach ( uv_lyric_section_types() as $type ) {
        if ( $type !== 'custom' && strpos( $lower, $type ) === 0 ) {
            return $type;
        }
    }
    return 'custom';
}

/**
 * Parse pasted lyrics text into ordered sections (exact port of src/lib/lyrics-parser.ts).
 */
function uv_parse_lyrics_paste( string $raw ): array {
    $normalized = str_replace( array( "\r\n", "\r" ), "\n", $raw );
    $blocks     = preg_split( '/\n\s*\n/', $normalized );
    $running    = 'SATB';
    $sections   = array();
    $valid_tags = uv_voice_tags();

    foreach ( $blocks as $block ) {
        $block = trim( $block );
        if ( $block === '' ) {
            continue;
        }
        $lines      = array_map( 'trim', explode( "\n", $block ) );
        $label_line = array_shift( $lines );
        if ( $label_line === null || $label_line === '' ) {
            continue;
        }

        $section_tag   = null;
        $content_lines = array();
        foreach ( $lines as $line ) {
            if ( preg_match( '/^\s*\[(satb|all|s|a|t|b)\]\s*/i', $line, $m ) ) {
                $upper = strtoupper( $m[1] );
                $tag   = ( $upper === 'ALL' ) ? 'SATB' : $upper;
                if ( in_array( $tag, $valid_tags, true ) ) {
                    if ( $section_tag === null ) {
                        $section_tag = $tag;
                    }
                    $content_lines[] = substr( $line, strlen( $m[0] ) );
                    continue;
                }
            }
            $content_lines[] = $line;
        }

        $resolved_tag = $section_tag ?? $running;
        $running      = $resolved_tag;

        $sections[] = array(
            'sectionType'  => uv_infer_lyric_section_type( $label_line ),
            'sectionLabel' => $label_line,
            'content'      => trim( implode( "\n", $content_lines ) ),
            'voiceTags'    => array( $resolved_tag ),
        );
    }

    return $sections;
}

/**
 * Serialize stored lyric sections back to editable paste-format text.
 */
function uv_serialize_lyrics_for_edit( array $sections ): string {
    $out = array();
    foreach ( $sections as $s ) {
        $tags  = is_array( $s['voiceTags'] ) ? $s['voiceTags'] : uv_parse_voice_tags( (string) $s['voiceTags'] );
        $tag   = $tags[0] ?? 'SATB';
        $lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", (string) $s['content'] ) );
        $first = array_shift( $lines ) ?? '';
        $tagged_first = trim( '[' . $tag . '] ' . $first );
        $all_lines = array_merge( array( $s['sectionLabel'], $tagged_first ), $lines );
        $out[] = implode( "\n", $all_lines );
    }
    return implode( "\n\n", $out );
}

/**
 * Linkify plain text into paragraphs with [text](url) and bare URLs converted to safe <a> tags.
 */
function uv_linkify_text( string $text ): string {
    $normalized = str_replace( array( "\r\n", "\r" ), "\n", $text );
    $paragraphs = preg_split( '/\n\s*\n/', $normalized );
    $html_parts = array();

    $pattern = '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s]+)/';

    foreach ( $paragraphs as $p ) {
        $p = trim( $p );
        if ( $p === '' ) {
            continue;
        }
        $last_idx = 0;
        $p_html   = '';
        if ( preg_match_all( $pattern, $p, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $full_str = $match[0][0];
                $offset   = $match[0][1];
                if ( $offset > $last_idx ) {
                    $p_html .= '<span>' . esc_html( substr( $p, $last_idx, $offset - $last_idx ) ) . '</span>';
                }
                $link_text = ! empty( $match[1][0] ) ? $match[1][0] : $match[3][0];
                $link_url  = ! empty( $match[2][0] ) ? $match[2][0] : $match[3][0];
                $p_html   .= '<a href="' . esc_url( $link_url ) . '" target="_blank" rel="noopener noreferrer" class="underline hover:no-underline">' . esc_html( $link_text ) . '</a>';
                $last_idx  = $offset + strlen( $full_str );
            }
        }
        if ( $last_idx < strlen( $p ) ) {
            $p_html .= '<span>' . esc_html( substr( $p, $last_idx ) ) . '</span>';
        }
        $html_parts[] = '<p>' . nl2br( $p_html ) . '</p>';
    }

    return implode( "\n", $html_parts );
}
