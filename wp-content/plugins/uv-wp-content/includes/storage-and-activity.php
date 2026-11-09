<?php
/**
 * Local storage buckets (audio, video, sheet-music, profile-photos), magic-byte audio verification,
 * rate limiting, and activity audit logging.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the client IP address from request headers.
 */
function uv_get_client_ip(): string {
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        return trim( $parts[0] );
    }
    if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
        return trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) );
    }
    return ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
}

/**
 * Get the raw User-Agent header.
 */
function uv_get_user_agent(): ?string {
    return ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : null;
}

/**
 * Record an entry in uv_activity_logs (best-effort, never throws).
 */
function uv_log_activity( string $action, array $opts = array() ): void {
    global $wpdb;
    try {
        $user = uv_get_current_user();
        $user_label = $opts['userLabel'] ?? ( $user ? ( $user['name'] . ' (' . $user['email'] . ')' ) : 'Unknown' );

        $wpdb->insert(
            uv_table( 'activity_logs' ),
            array(
                'id'          => uv_cuid(),
                'userLabel'   => $user_label,
                'action'      => $action,
                'detail'      => $opts['detail'] ?? null,
                'entityType'  => $opts['entityType'] ?? null,
                'entityLabel' => $opts['entityLabel'] ?? null,
                'ipAddress'   => uv_get_client_ip(),
                'userAgent'   => uv_get_user_agent(),
                'createdAt'   => gmdate( 'Y-m-d H:i:s' ),
            )
        );
    } catch ( Throwable $e ) {
        // Never break user mutations if logging fails.
    }
}

/**
 * Check and increment a rate-limit bucket in uv_rate_limits.
 *
 * @return bool True if allowed, false if rate-limited.
 */
function uv_check_rate_limit( string $key, int $max_hits, int $window_seconds ): bool {
    global $wpdb;
    $now_ts = time();
    $now_dt = gmdate( 'Y-m-d H:i:s', $now_ts );
    $table  = uv_table( 'rate_limits' );

    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE bucketKey = %s", $key ), ARRAY_A );
    if ( ! $row || strtotime( $row['resetAt'] . ' UTC' ) <= $now_ts ) {
        $reset_dt = gmdate( 'Y-m-d H:i:s', $now_ts + $window_seconds );
        if ( $row ) {
            $wpdb->update(
                $table,
                array( 'hitCount' => 1, 'resetAt' => $reset_dt ),
                array( 'bucketKey' => $key )
            );
        } else {
            $wpdb->insert(
                $table,
                array( 'bucketKey' => $key, 'hitCount' => 1, 'resetAt' => $reset_dt )
            );
        }
        return true;
    }

    if ( (int) $row['hitCount'] >= $max_hits ) {
        return false;
    }

    $wpdb->update(
        $table,
        array( 'hitCount' => (int) $row['hitCount'] + 1 ),
        array( 'bucketKey' => $key )
    );
    return true;
}

/**
 * Clear a rate-limit bucket (e.g., on successful login).
 */
function uv_clear_rate_limit( string $key ): void {
    global $wpdb;
    $wpdb->delete( uv_table( 'rate_limits' ), array( 'bucketKey' => $key ) );
}

/**
 * Return filesystem path and public URL base for a storage bucket ('audio', 'video', 'sheet-music', 'profile-photos').
 */
function uv_storage_bucket_info( string $bucket ): array {
    $uploads = wp_upload_dir();
    $dir     = trailingslashit( $uploads['basedir'] ) . 'uv-storage/' . $bucket;
    $url     = trailingslashit( $uploads['baseurl'] ) . 'uv-storage/' . $bucket;
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }
    return array( 'dir' => $dir, 'url' => $url );
}

/**
 * Sniff first 12 bytes of an uploaded audio file to verify genuine audio format (MP3/ID3, WAV, M4A/MP4, OGG, FLAC).
 */
function uv_verify_audio_magic_bytes( string $filepath ): bool {
    if ( ! is_readable( $filepath ) || filesize( $filepath ) < 4 ) {
        return false;
    }
    $fp    = fopen( $filepath, 'rb' );
    $bytes = fread( $fp, 12 );
    fclose( $fp );
    if ( $bytes === false || strlen( $bytes ) < 4 ) {
        return false;
    }

    // ID3v2 tag ("ID3")
    if ( substr( $bytes, 0, 3 ) === 'ID3' ) {
        return true;
    }
    // Raw MPEG frame sync (0xFF followed by 0xE0..0xFF)
    if ( ord( $bytes[0] ) === 0xFF && ( ord( $bytes[1] ) & 0xE0 ) === 0xE0 ) {
        return true;
    }
    // WAV ("RIFF" .... "WAVE")
    if ( strlen( $bytes ) >= 12 && substr( $bytes, 0, 4 ) === 'RIFF' && substr( $bytes, 8, 4 ) === 'WAVE' ) {
        return true;
    }
    // M4A / MP4 container ("ftyp" at offset 4)
    if ( strlen( $bytes ) >= 8 && substr( $bytes, 4, 4 ) === 'ftyp' ) {
        return true;
    }
    // Ogg ("OggS")
    if ( substr( $bytes, 0, 4 ) === 'OggS' ) {
        return true;
    }
    // FLAC ("fLaC")
    if ( substr( $bytes, 0, 4 ) === 'fLaC' ) {
        return true;
    }

    return false;
}

/**
 * Handle an uploaded file in $_FILES and store it in the specified UV bucket.
 *
 * @return array ['url' => string] on success or ['error' => string] on failure.
 */
function uv_handle_file_upload( array $file, string $bucket ): array {
    if ( ! empty( $file['error'] ) && $file['error'] !== UPLOAD_ERR_OK ) {
        if ( in_array( $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
            return array( 'error' => 'File exceeds maximum upload size limit.' );
        }
        return array( 'error' => 'Upload failed — please try again.' );
    }

    if ( empty( $file['tmp_name'] ) || ! file_exists( $file['tmp_name'] ) ) {
        return array( 'error' => 'No file received.' );
    }

    $size = (int) $file['size'];
    $name = sanitize_file_name( (string) $file['name'] );
    $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

    $rules = array(
        'audio' => array(
            'max'  => UV_AUDIO_MAX_BYTES,
            'exts' => array( 'mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac' ),
            'label'=> '20MB',
        ),
        'video' => array(
            'max'  => UV_VIDEO_MAX_BYTES,
            'exts' => array( 'mp4', 'mov', 'webm', 'm4v' ),
            'label'=> '20MB',
        ),
        'sheet-music' => array(
            'max'  => UV_SHEET_MUSIC_MAX_BYTES,
            'exts' => array( 'pdf' ),
            'label'=> '10MB',
        ),
        'profile-photos' => array(
            'max'  => UV_PROFILE_PHOTO_MAX_BYTES,
            'exts' => array( 'jpg', 'jpeg', 'png', 'webp' ),
            'label'=> '5MB',
        ),
    );

    if ( ! isset( $rules[ $bucket ] ) ) {
        return array( 'error' => 'Invalid upload bucket.' );
    }
    $rule = $rules[ $bucket ];

    if ( $size <= 0 || $size > $rule['max'] ) {
        return array( 'error' => "File is too large — max {$rule['label']}." );
    }
    if ( ! in_array( $ext, $rule['exts'], true ) ) {
        return array( 'error' => 'Unsupported file format.' );
    }

    if ( $bucket === 'audio' && ! uv_verify_audio_magic_bytes( $file['tmp_name'] ) ) {
        return array( 'error' => 'Uploaded file is not a valid audio recording.' );
    }

    $info     = uv_storage_bucket_info( $bucket );
    $filename = uv_cuid() . '-' . preg_replace( '/[^A-Za-z0-9._-]/', '_', $name );
    $dest     = trailingslashit( $info['dir'] ) . $filename;

    $moved = @move_uploaded_file( $file['tmp_name'], $dest );
    if ( ! $moved ) {
        $moved = @copy( $file['tmp_name'], $dest );
    }
    if ( ! $moved ) {
        return array( 'error' => 'Could not save uploaded file.' );
    }

    return array( 'url' => trailingslashit( $info['url'] ) . rawurlencode( $filename ) );
}

/**
 * Delete a file from local uv-storage if its URL points to one of our buckets.
 */
function uv_delete_storage_file_by_url( ?string $url ): void {
    if ( empty( $url ) ) {
        return;
    }
    foreach ( array( 'audio', 'video', 'sheet-music', 'profile-photos' ) as $bucket ) {
        $info = uv_storage_bucket_info( $bucket );
        if ( strpos( $url, '/uv-storage/' . $bucket . '/' ) !== false ) {
            $basename = basename( rawurldecode( (string) parse_url( $url, PHP_URL_PATH ) ) );
            $filepath = trailingslashit( $info['dir'] ) . $basename;
            if ( file_exists( $filepath ) && is_file( $filepath ) ) {
                @unlink( $filepath );
            }
        }
    }
}

/**
 * Compute storage usage for a bucket matching AdminStoragePage expectations.
 */
function uv_get_storage_usage( string $bucket ): array {
    $info        = uv_storage_bucket_info( $bucket );
    $files       = array();
    $total_bytes = 0;

    if ( is_dir( $info['dir'] ) ) {
        $entries = scandir( $info['dir'] );
        if ( is_array( $entries ) ) {
            foreach ( $entries as $entry ) {
                if ( $entry === '.' || $entry === '..' ) {
                    continue;
                }
                $full = trailingslashit( $info['dir'] ) . $entry;
                if ( is_file( $full ) ) {
                    $sz           = (int) filesize( $full );
                    $total_bytes += $sz;
                    $files[]      = array(
                        'path'  => $entry,
                        'url'   => trailingslashit( $info['url'] ) . rawurlencode( $entry ),
                        'bytes' => $sz,
                    );
                }
            }
        }
    }

    return array(
        'totalBytes' => $total_bytes,
        'fileCount'  => count( $files ),
        'files'      => $files,
    );
}
