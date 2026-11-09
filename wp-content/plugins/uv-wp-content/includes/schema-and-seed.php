<?php
/**
 * Relational database schema (MySQL + SQLite compatible) and idempotent demo seed engine.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return the full table name with WordPress prefix.
 */
function uv_table( string $short_name ): string {
    global $wpdb;
    return $wpdb->prefix . 'uv_' . $short_name;
}

/**
 * Create all Umoja Voices relational tables if they do not exist.
 */
function uv_create_tables(): void {
    global $wpdb;

    $queries = array(
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'users' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            email VARCHAR(191) NOT NULL UNIQUE,
            passwordHash VARCHAR(255) NOT NULL DEFAULT '',
            name VARCHAR(255) NOT NULL,
            role VARCHAR(32) NOT NULL DEFAULT 'chorister',
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            photoUrl TEXT NULL,
            bio TEXT NULL,
            voicePart VARCHAR(16) NULL,
            instrument VARCHAR(255) NULL,
            phone VARCHAR(64) NULL,
            createdAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'songs' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            composer VARCHAR(255) NULL,
            lyricist VARCHAR(255) NULL,
            arranger VARCHAR(255) NULL,
            createdById VARCHAR(64) NOT NULL,
            createdAt DATETIME NOT NULL,
            updatedAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'song_sections' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            songId VARCHAR(64) NOT NULL,
            part VARCHAR(16) NOT NULL,
            sectionLabel VARCHAR(255) NOT NULL,
            labelDescription TEXT NULL,
            sortOrder INT NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'song_media' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            sectionId VARCHAR(64) NOT NULL,
            label VARCHAR(255) NOT NULL,
            mediaUrl TEXT NOT NULL,
            mediaKind VARCHAR(32) NOT NULL,
            sortOrder INT NOT NULL DEFAULT 0,
            createdAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'song_sheet_music' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            songId VARCHAR(64) NOT NULL,
            label VARCHAR(255) NOT NULL,
            fileUrl TEXT NOT NULL,
            sortOrder INT NOT NULL DEFAULT 0,
            createdAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'lyric_sections' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            songId VARCHAR(64) NOT NULL,
            sectionType VARCHAR(32) NOT NULL,
            sectionLabel VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            voiceTags VARCHAR(64) NOT NULL DEFAULT 'SATB',
            sortOrder INT NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'external_links' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            category VARCHAR(32) NOT NULL DEFAULT 'other',
            sortOrder INT NOT NULL DEFAULT 0,
            createdAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'trips' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            destination VARCHAR(255) NOT NULL,
            startDate DATETIME NOT NULL,
            endDate DATETIME NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'upcoming',
            createdById VARCHAR(64) NOT NULL,
            createdAt DATETIME NOT NULL,
            updatedAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'logistics_deadlines' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            tripId VARCHAR(64) NOT NULL,
            category VARCHAR(32) NOT NULL,
            label VARCHAR(255) NOT NULL,
            dueDate DATETIME NOT NULL,
            notes TEXT NULL,
            sortOrder INT NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'itinerary_items' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            tripId VARCHAR(64) NOT NULL,
            date DATETIME NOT NULL,
            time VARCHAR(64) NULL,
            title VARCHAR(255) NOT NULL,
            location VARCHAR(255) NULL,
            notes TEXT NULL,
            sortOrder INT NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'practice_sessions' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            tripId VARCHAR(64) NULL,
            date DATETIME NOT NULL,
            time VARCHAR(64) NOT NULL,
            location VARCHAR(255) NOT NULL,
            notes TEXT NULL,
            sortOrder INT NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'about_sections' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NULL,
            body TEXT NOT NULL,
            sortOrder INT NOT NULL DEFAULT 0,
            createdAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'about_media' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            label VARCHAR(255) NOT NULL,
            mediaUrl TEXT NOT NULL,
            mediaKind VARCHAR(32) NOT NULL,
            sortOrder INT NOT NULL DEFAULT 0,
            createdAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'activity_logs' ) . " (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            userLabel VARCHAR(255) NOT NULL,
            action VARCHAR(64) NOT NULL,
            detail TEXT NULL,
            entityType VARCHAR(64) NULL,
            entityLabel VARCHAR(255) NULL,
            ipAddress VARCHAR(64) NULL,
            userAgent TEXT NULL,
            createdAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'rate_limits' ) . " (
            bucketKey VARCHAR(191) NOT NULL PRIMARY KEY,
            hitCount INT NOT NULL DEFAULT 0,
            resetAt DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS " . uv_table( 'auth_tokens' ) . " (
            tokenHash VARCHAR(128) NOT NULL PRIMARY KEY,
            userId VARCHAR(64) NOT NULL,
            tokenType VARCHAR(32) NOT NULL,
            expiresAt DATETIME NOT NULL,
            usedAt DATETIME NULL,
            createdAt DATETIME NOT NULL
        )",
    );

    foreach ( $queries as $sql ) {
        $wpdb->query( $sql );
    }
}

/**
 * Return ordered About page blocks (interleaving sections and media by sortOrder, then createdAt).
 */
function uv_get_ordered_about_blocks(): array {
    global $wpdb;
    $sections = $wpdb->get_results( "SELECT * FROM " . uv_table( 'about_sections' ) . " ORDER BY sortOrder ASC, createdAt ASC", ARRAY_A ) ?: array();
    $media    = $wpdb->get_results( "SELECT * FROM " . uv_table( 'about_media' ) . " ORDER BY sortOrder ASC, createdAt ASC", ARRAY_A ) ?: array();

    $blocks = array();
    foreach ( $sections as $s ) {
        $blocks[] = array(
            'kind'      => 'section',
            'id'        => $s['id'],
            'title'     => $s['title'],
            'body'      => $s['body'],
            'sortOrder' => (int) $s['sortOrder'],
            'createdAt' => $s['createdAt'],
        );
    }
    foreach ( $media as $m ) {
        $blocks[] = array(
            'kind'      => 'media',
            'id'        => $m['id'],
            'label'     => $m['label'],
            'mediaUrl'  => $m['mediaUrl'],
            'mediaKind' => $m['mediaKind'],
            'sortOrder' => (int) $m['sortOrder'],
            'createdAt' => $m['createdAt'],
        );
    }

    usort( $blocks, function ( $a, $b ) {
        if ( $a['sortOrder'] !== $b['sortOrder'] ) {
            return $a['sortOrder'] <=> $b['sortOrder'];
        }
        return strcmp( (string) $a['createdAt'], (string) $b['createdAt'] );
    } );

    return $blocks;
}

/**
 * Idempotently seed demo accounts and content matching prisma/seed.ts.
 */
function uv_seed_demo_data(): void {
    global $wpdb;
    $now = gmdate( 'Y-m-d H:i:s' );

    // 1. Seed accounts (Eric Gitonga admin, Demo Chorister, E2E Profile Test)
    $seed_users = array(
        array(
            'id'           => 'seed-user-admin',
            'email'        => 'gitonga@gmail.com',
            'passwordHash' => wp_hash_password( 'admin12345' ),
            'name'         => 'Eric Gitonga',
            'role'         => 'admin',
            'status'       => 'active',
        ),
        array(
            'id'           => 'seed-user-chorister',
            'email'        => 'demo.chorister@example.com',
            'passwordHash' => wp_hash_password( 'chorister12345' ),
            'name'         => 'Demo Chorister',
            'role'         => 'chorister',
            'status'       => 'active',
        ),
        array(
            'id'           => 'seed-user-e2e-profile',
            'email'        => 'e2e.profile.test@example.com',
            'passwordHash' => wp_hash_password( 'e2eprofiletest12345' ),
            'name'         => 'E2E Profile Test',
            'role'         => 'chorister',
            'status'       => 'active',
        ),
    );

    foreach ( $seed_users as $u ) {
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'users' ) . " WHERE email = %s", $u['email'] ), ARRAY_A );
        if ( $existing ) {
            $wpdb->update(
                uv_table( 'users' ),
                array(
                    'passwordHash' => $u['passwordHash'],
                    'role'         => $u['role'],
                    'status'       => $u['status'],
                ),
                array( 'id' => $existing['id'] )
            );
        } else {
            $wpdb->insert(
                uv_table( 'users' ),
                array(
                    'id'           => $u['id'],
                    'email'        => $u['email'],
                    'passwordHash' => $u['passwordHash'],
                    'name'         => $u['name'],
                    'role'         => $u['role'],
                    'status'       => $u['status'],
                    'createdAt'    => $now,
                )
            );
        }
    }

    $admin_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'users' ) . " WHERE email = %s", 'gitonga@gmail.com' ), ARRAY_A );
    $admin_id  = $admin_row['id'] ?? 'seed-user-admin';

    // 2. Seed Song: Rising Together (demo song)
    $song_id = 'seed-song-rising-together';
    $wpdb->delete( uv_table( 'song_media' ), array( 'sectionId' => 'seed-sec-S' ) );
    $existing_sections = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . uv_table( 'song_sections' ) . " WHERE songId = %s", $song_id ) );
    foreach ( $existing_sections as $sec_id ) {
        $wpdb->delete( uv_table( 'song_media' ), array( 'sectionId' => $sec_id ) );
    }
    $wpdb->delete( uv_table( 'song_sections' ), array( 'songId' => $song_id ) );
    $wpdb->delete( uv_table( 'lyric_sections' ), array( 'songId' => $song_id ) );
    $wpdb->delete( uv_table( 'songs' ), array( 'id' => $song_id ) );

    $wpdb->insert(
        uv_table( 'songs' ),
        array(
            'id'          => $song_id,
            'title'       => 'Rising Together (demo song)',
            'composer'    => 'Amina Wanjiku',
            'lyricist'    => 'Amina Wanjiku',
            'arranger'    => 'Grace Otieno',
            'createdById' => $admin_id,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        )
    );

    $demo_sections = array(
        array( 'S', 'Full song', 'Melody line — leads verses 1 and 2', 'Soprano practice track (demo MP3)', 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3', 0 ),
        array( 'A', 'Full song', 'Lower harmony — enters at bar 5', 'Alto practice track (demo MP3)', 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3', 1 ),
        array( 'T', 'Full song', 'Counter-melody on chorus', 'Tenor practice track (demo MP3)', 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3', 2 ),
        array( 'B', 'Full song', 'Root pedal on chorus and bridge', 'Bass practice track (demo MP3)', 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3', 3 ),
        array( 'All', 'Full song', 'All four parts together — reference mix', 'Full choir reference (demo MP3)', 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3', 4 ),
    );

    foreach ( $demo_sections as $ds ) {
        $sec_id = 'seed-sec-' . $ds[0];
        $wpdb->insert(
            uv_table( 'song_sections' ),
            array(
                'id'               => $sec_id,
                'songId'           => $song_id,
                'part'             => $ds[0],
                'sectionLabel'     => $ds[1],
                'labelDescription' => $ds[2],
                'sortOrder'        => $ds[5],
            )
        );
        $wpdb->insert(
            uv_table( 'song_media' ),
            array(
                'id'        => 'seed-media-' . $ds[0],
                'sectionId' => $sec_id,
                'label'     => $ds[3],
                'mediaUrl'  => $ds[4],
                'mediaKind' => 'audio',
                'sortOrder' => 0,
                'createdAt' => $now,
            )
        );
    }

    $demo_lyrics = array(
        array( 'verse', 'Verse 1', "Morning breaks over the hills\nOne voice calls, another answers\nFrom the valley to the ridge\nWe gather in the light", 'S,A', 0 ),
        array( 'chorus', 'Chorus', "Rising, rising together\nFour parts, one song\nHold the line and lift your neighbour\nThis is where we belong", 'SATB', 1 ),
        array( 'verse', 'Verse 2 (Soprano)', "Soar above the river bend\nCarry the hope into the sky", 'S', 2 ),
        array( 'verse', 'Verse 2 (Alto)', "Steady as the river flows\nWeave the warmth beneath the high", 'A', 3 ),
        array( 'verse', 'Verse 2 (Tenor)', "Step by step along the shore\nEcho every word they sing", 'T', 4 ),
        array( 'verse', 'Verse 2 (Bass)', "Deep and sure the earth below\nAnchor every voice we bring", 'B', 5 ),
    );

    foreach ( $demo_lyrics as $dl ) {
        $wpdb->insert(
            uv_table( 'lyric_sections' ),
            array(
                'id'           => 'seed-lyric-' . $dl[4],
                'songId'       => $song_id,
                'sectionType'  => $dl[0],
                'sectionLabel' => $dl[1],
                'content'      => $dl[2],
                'voiceTags'    => $dl[3],
                'sortOrder'    => $dl[4],
            )
        );
    }

    // 3. Seed Trip: Nairobi Concert Tour (demo trip)
    $trip_id = 'seed-trip-nairobi-concert';
    $wpdb->delete( uv_table( 'logistics_deadlines' ), array( 'tripId' => $trip_id ) );
    $wpdb->delete( uv_table( 'itinerary_items' ), array( 'tripId' => $trip_id ) );
    $wpdb->delete( uv_table( 'practice_sessions' ), array( 'tripId' => $trip_id ) );
    $wpdb->delete( uv_table( 'trips' ), array( 'id' => $trip_id ) );

    $wpdb->insert(
        uv_table( 'trips' ),
        array(
            'id'          => $trip_id,
            'title'       => 'Nairobi Concert Tour (demo trip)',
            'destination' => 'Nairobi, Kenya',
            'startDate'   => '2026-08-14 00:00:00',
            'endDate'     => '2026-08-16 00:00:00',
            'status'      => 'upcoming',
            'createdById' => $admin_id,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        )
    );

    $deadlines = array(
        array( 'payment', 'Tour deposit (KES 5,000)', '2026-07-01 00:00:00', 'M-Pesa paybill — details in WhatsApp group', 0 ),
        array( 'tickets', 'Confirm bus seat & dietary needs', '2026-07-20 00:00:00', null, 1 ),
        array( 'other', 'Concert dress fitting', '2026-08-01 00:00:00', 'Bring black shoes', 2 ),
    );
    foreach ( $deadlines as $d ) {
        $wpdb->insert(
            uv_table( 'logistics_deadlines' ),
            array(
                'id'        => 'seed-deadline-' . $d[4],
                'tripId'    => $trip_id,
                'category'  => $d[0],
                'label'     => $d[1],
                'dueDate'   => $d[2],
                'notes'     => $d[3],
                'sortOrder' => $d[4],
            )
        );
    }

    $itinerary = array(
        array( '2026-08-14 00:00:00', '18:00', 'Soundcheck & welcome dinner', 'Kenya National Theatre', null, 0 ),
        array( '2026-08-15 00:00:00', '19:30', 'Evening benefit concert', 'Kenya National Theatre', 'Call time 17:30 in full dress', 1 ),
        array( '2026-08-16 00:00:00', '11:00', 'Community workshop & wrap-up', 'All Saints Cathedral Hall', null, 2 ),
    );
    foreach ( $itinerary as $it ) {
        $wpdb->insert(
            uv_table( 'itinerary_items' ),
            array(
                'id'        => 'seed-itin-' . $it[5],
                'tripId'    => $trip_id,
                'date'      => $it[0],
                'time'      => $it[1],
                'title'     => $it[2],
                'location'  => $it[3],
                'notes'     => $it[4],
                'sortOrder' => $it[5],
            )
        );
    }

    $practices = array(
        array( '2026-08-02 00:00:00', '14:00–17:00', 'St. Marks Hall, Westlands', 'Focus: Rising Together + sectionals', 0 ),
        array( '2026-08-09 00:00:00', '14:00–17:00', 'St. Marks Hall, Westlands', 'Full dress run-through', 1 ),
    );
    foreach ( $practices as $pr ) {
        $wpdb->insert(
            uv_table( 'practice_sessions' ),
            array(
                'id'        => 'seed-prac-' . $pr[4],
                'tripId'    => $trip_id,
                'date'      => $pr[0],
                'time'      => $pr[1],
                'location'  => $pr[2],
                'notes'     => $pr[3],
                'sortOrder' => $pr[4],
            )
        );
    }

    // 4. External links (only if empty)
    $link_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'external_links' ) );
    if ( $link_count === 0 ) {
        $seed_links = array(
            array( 'seed-link-0', 'Umoja Voices on Instagram', 'https://www.instagram.com/umojavoices/', 'social', 0 ),
            array( 'seed-link-1', 'Umoja Voices on YouTube', 'https://youtube.com', 'media', 1 ),
            array( 'seed-link-2', 'Feature in Daily Nation (demo link)', 'https://nation.africa', 'news', 2 ),
        );
        foreach ( $seed_links as $sl ) {
            $wpdb->insert(
                uv_table( 'external_links' ),
                array(
                    'id'        => $sl[0],
                    'title'     => $sl[1],
                    'url'       => $sl[2],
                    'category'  => $sl[3],
                    'sortOrder' => $sl[4],
                    'createdAt' => $now,
                )
            );
        }
    }

    // 5. About page sections (only if empty)
    $about_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'about_sections' ) );
    if ( $about_count === 0 ) {
        $seed_about = array(
            array(
                'seed-about-0',
                'About Umoja Voices',
                'Umoja Voices is an all-women choir in Nairobi, Kenya, singing in four-part harmony — Soprano, Alto, Tenor, and Bass — to celebrate community, sisterhood, and shared purpose.',
                0,
            ),
            array(
                'seed-about-1',
                'Who We Support',
                'Our performances raise funds and awareness for [White Ribbon Alliance Kenya](https://whiteribbonalliancekenya.org/), advancing reproductive, maternal, and newborn health and rights across Kenya.',
                1,
            ),
            array(
                'seed-about-2',
                'Follow Us',
                'Follow our journey, rehearsal highlights, and upcoming concerts on Instagram at [@umojavoices](https://www.instagram.com/umojavoices/).',
                2,
            ),
        );
        foreach ( $seed_about as $sa ) {
            $wpdb->insert(
                uv_table( 'about_sections' ),
                array(
                    'id'        => $sa[0],
                    'title'     => $sa[1],
                    'body'      => $sa[2],
                    'sortOrder' => $sa[3],
                    'createdAt' => $now,
                )
            );
        }
    }
}

/**
 * Idempotently seed the complete real Umoja Voices 2026 repertoire, SATB tracks,
 * sheet music PDFs, structured lyrics, Portugal & Germany Tour 2026 logistics,
 * About page blocks/videos, and External Links.
 */
function uv_seed_live_umoja_repertoire( bool $replace_demo = false ): array {
    global $wpdb;
    $now = gmdate( 'Y-m-d H:i:s' );

    // Ensure admin user exists
    uv_seed_demo_data();
    $admin_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'users' ) . " WHERE email = %s", 'gitonga@gmail.com' ), ARRAY_A );
    $admin_id  = $admin_row['id'] ?? 'seed-user-admin';

    if ( $replace_demo ) {
        $demo_song = 'seed-song-rising-together';
        $sec_ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . uv_table( 'song_sections' ) . " WHERE songId = %s", $demo_song ) );
        foreach ( $sec_ids as $sid ) {
            $wpdb->delete( uv_table( 'song_media' ), array( 'sectionId' => $sid ) );
        }
        $wpdb->delete( uv_table( 'song_sections' ), array( 'songId' => $demo_song ) );
        $wpdb->delete( uv_table( 'lyric_sections' ), array( 'songId' => $demo_song ) );
        $wpdb->delete( uv_table( 'songs' ), array( 'id' => $demo_song ) );

        $demo_trip = 'seed-trip-nairobi-concert';
        $wpdb->delete( uv_table( 'logistics_deadlines' ), array( 'tripId' => $demo_trip ) );
        $wpdb->delete( uv_table( 'itinerary_items' ), array( 'tripId' => $demo_trip ) );
        $wpdb->delete( uv_table( 'practice_sessions' ), array( 'tripId' => $demo_trip ) );
        $wpdb->delete( uv_table( 'trips' ), array( 'id' => $demo_trip ) );
    }

    $audio_base = home_url( '/wp-content/uploads/uv-storage/audio/' );
    $video_base = home_url( '/wp-content/uploads/uv-storage/video/' );
    $pdf_base   = home_url( '/wp-content/uploads/uv-storage/sheet-music/' );

    $repertoire = array(
        array(
            'id'       => 'uv-song-sing-sing-sing',
            'title'    => 'Sing, Sing, Sing',
            'composer' => 'Louis Prima',
            'lyricist' => 'Louis Prima',
            'arranger' => 'Phillip Kern',
            'sections' => array(
                array(
                    'part'  => 'S',
                    'label' => 'Soprano',
                    'desc'  => 'Soprano vocal part — joint arrangement with Lisbon Community Choir',
                    'media' => array(
                        array( 'Soprano Practice Track (MP3)', $audio_base . 'sing-sing-sing-soprano.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'A',
                    'label' => 'Alto',
                    'desc'  => 'Alto vocal part — swing inner harmony',
                    'media' => array(
                        array( 'Alto Practice Track (MP3)', $audio_base . 'sing-sing-sing-alto.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'T',
                    'label' => 'Tenor',
                    'desc'  => 'Tenor vocal part — rhythm & counter-line',
                    'media' => array(
                        array( 'Tenor Practice Track (MP3)', $audio_base . 'sing-sing-sing-tenor.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'B',
                    'label' => 'Bass',
                    'desc'  => 'Bass vocal part — walking bass & foundation',
                    'media' => array(
                        array( 'Bass Practice Track (MP3)', $audio_base . 'sing-sing-sing-bass.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full choir SATB reference mix & shared resources',
                    'media' => array(
                        array( 'Full Choir SATB Reference (MP3)', $audio_base . 'sing-sing-sing-satb.mp3', 'audio' ),
                        array( 'LCC Shared Rehearsal Folder (Google Drive)', 'https://drive.google.com/drive/folders/1yOekCQ54sJtVHwM3m0OI3ndn7pPr_HyI?usp=drive_link', 'drive' ),
                    ),
                ),
            ),
            'sheets'   => array(
                array( 'Sing, Sing, Sing — Full Choral Score (PDF)', $pdf_base . 'sing-sing-sing.pdf' ),
            ),
            'lyrics'   => array(
                array( 'chorus', 'Main Refrain', "Sing, sing, sing, sing\nEverybody start to sing\nLa dee da, ho ho\nNow you're singing with a swing!", 'SATB' ),
                array( 'verse', 'Verse', "When the music goes around\nEverybody goes to town\nBut here is something you should know\nHo, ho, baby, ho, ho, ho!", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-can-you-feel-the-love-tonight',
            'title'    => 'Can You Feel The Love Tonight',
            'composer' => 'Elton John',
            'lyricist' => 'Tim Rice',
            'arranger' => 'Keith Christopher (SATB)',
            'sections' => array(
                array(
                    'part'  => 'S',
                    'label' => 'Soprano',
                    'desc'  => 'Soprano line — audio track, guide video & YouTube reference',
                    'media' => array(
                        array( 'Soprano Practice Audio (MP3)', $audio_base . 'can-you-feel-the-love-tonight-soprano.mp3', 'audio' ),
                        array( 'Soprano Practice Video (MP4)', $video_base . 'can-you-feel-the-love-tonight-soprano.mp4', 'video' ),
                        array( 'Soprano Guide (YouTube)', 'https://www.youtube.com/watch?v=INcW3OfJNCg', 'youtube' ),
                    ),
                ),
                array(
                    'part'  => 'A',
                    'label' => 'Alto',
                    'desc'  => 'Alto line — audio track, guide video & YouTube reference',
                    'media' => array(
                        array( 'Alto Practice Audio (MP3)', $audio_base . 'can-you-feel-the-love-tonight-alto.mp3', 'audio' ),
                        array( 'Alto Practice Video (MP4)', $video_base . 'can-you-feel-the-love-tonight-alto.mp4', 'video' ),
                        array( 'Alto Guide (YouTube)', 'https://www.youtube.com/watch?v=S-xcLbxcpcs', 'youtube' ),
                    ),
                ),
                array(
                    'part'  => 'T',
                    'label' => 'Tenor',
                    'desc'  => 'Tenor line — audio track, guide video & YouTube reference',
                    'media' => array(
                        array( 'Tenor Practice Audio (MP3)', $audio_base . 'can-you-feel-the-love-tonight-tenor.mp3', 'audio' ),
                        array( 'Tenor Practice Video (MP4)', $video_base . 'can-you-feel-the-love-tonight-tenor.mp4', 'video' ),
                        array( 'Tenor Guide (YouTube)', 'https://www.youtube.com/watch?v=HWxgoPR7OD4', 'youtube' ),
                    ),
                ),
                array(
                    'part'  => 'B',
                    'label' => 'Bass',
                    'desc'  => 'Bass line — audio track, guide video & YouTube reference',
                    'media' => array(
                        array( 'Bass Practice Audio (MP3)', $audio_base . 'can-you-feel-the-love-tonight-bass.mp3', 'audio' ),
                        array( 'Bass Practice Video (MP4)', $video_base . 'can-you-feel-the-love-tonight-bass.mp4', 'video' ),
                        array( 'Bass Guide (YouTube)', 'https://www.youtube.com/watch?v=K5qNBwv8w4g', 'youtube' ),
                    ),
                ),
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full SATB Tutti mix & choral performance',
                    'media' => array(
                        array( 'SATB Full Mix (MP3)', $audio_base . 'can-you-feel-the-love-tonight-satb.mp3', 'audio' ),
                        array( 'SATB Choral Performance (MP3)', $audio_base . 'can-you-feel-the-love-tonight-satb-choral.mp3', 'audio' ),
                        array( 'SATB Full Video (MP4)', $video_base . 'can-you-feel-the-love-tonight-satb.mp4', 'video' ),
                        array( 'Tutti Reference (YouTube)', 'https://www.youtube.com/watch?v=RmvcMH0xbm8', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'verse', 'Verse 1', "There's a calm surrender to the rush of day\nWhen the heat of a rolling wind can be turned away\nAn enchanted moment, and it sees me through\nIt's enough for this restless warrior just to be with you", 'SATB' ),
                array( 'chorus', 'Chorus', "And can you feel the love tonight?\nIt is where we are\nIt's enough for this wide-eyed wanderer\nThat we got this far\nAnd can you feel the love tonight\nHow it's laid to rest?\nIt's enough to make kings and vagabonds\nBelieve the very best", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-brighter-days',
            'title'    => 'Brighter Days',
            'composer' => 'Sauti Sol',
            'lyricist' => 'Sauti Sol',
            'arranger' => 'Umoja Voices & LCC Collabo Arrangement',
            'sections' => array(
                array(
                    'part'  => 'S',
                    'label' => 'Soprano',
                    'desc'  => 'Soprano vocal guide (F Major)',
                    'media' => array(
                        array( 'Brighter Days — Soprano (MP3)', $audio_base . 'brighter-days-soprano.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'A',
                    'label' => 'Alto',
                    'desc'  => 'Alto vocal guide (F Major)',
                    'media' => array(
                        array( 'Brighter Days — Alto (MP3)', $audio_base . 'brighter-days-alto.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'T',
                    'label' => 'Tenor',
                    'desc'  => 'Tenor vocal guide (F Major)',
                    'media' => array(
                        array( 'Brighter Days — Tenor (MP3)', $audio_base . 'brighter-days-tenor.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'B',
                    'label' => 'Bass',
                    'desc'  => 'Corrected Bass vocal guide (F Major)',
                    'media' => array(
                        array( 'Brighter Days — Bass (MP3)', $audio_base . 'brighter-days-bass.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full SATB reference & original Sauti Sol video',
                    'media' => array(
                        array( 'Brighter Days — SATB Full Mix (MP3)', $audio_base . 'brighter-days-satb.mp3', 'audio' ),
                        array( 'Brighter Days — Reference Video (YouTube)', 'https://youtu.be/OQEByF4dtY0', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'chorus', 'Chorus', "Higher, higher\nTaking me higher\nNo longer afraid of the night\nLooking forward to brighter days", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-baba-yetu',
            'title'    => 'Baba Yetu',
            'composer' => 'Christopher Tin',
            'lyricist' => 'Chris Kiagiri (Swahili Lord\'s Prayer)',
            'arranger' => 'Christopher Tin',
            'sections' => array(
                array(
                    'part'  => 'S',
                    'label' => 'Soprano 1 & 2',
                    'desc'  => 'Soprano 1 & Soprano 2 sectional rehearsal guides',
                    'media' => array(
                        array( 'Soprano 1 Guide (YouTube)', 'https://youtu.be/ren_OOQ4VgQ', 'youtube' ),
                        array( 'Soprano 2 Guide (YouTube)', 'https://youtu.be/XGjqdUVrqAo', 'youtube' ),
                    ),
                ),
                array(
                    'part'  => 'A',
                    'label' => 'Alto',
                    'desc'  => 'Alto sectional rehearsal guide',
                    'media' => array(
                        array( 'Alto Guide (YouTube)', 'https://www.youtube.com/watch?v=WUemy2nTUiE', 'youtube' ),
                    ),
                ),
                array(
                    'part'  => 'T',
                    'label' => 'Tenor & Solos',
                    'desc'  => 'Tenor & Soloist rehearsal guides',
                    'media' => array(
                        array( 'Tenor Guide (YouTube)', 'https://youtu.be/5Y2P38xhV-I', 'youtube' ),
                        array( 'Solos Guide (YouTube)', 'https://youtu.be/P_NRylI8HuM', 'youtube' ),
                    ),
                ),
                array(
                    'part'  => 'B',
                    'label' => 'Baritone & Bass',
                    'desc'  => 'Baritone & Bass sectional rehearsal guides',
                    'media' => array(
                        array( 'Bass Guide (YouTube)', 'https://youtu.be/glbzv8oFB3o', 'youtube' ),
                        array( 'Baritone Guide (YouTube)', 'https://youtu.be/Sx9Y3P86b34', 'youtube' ),
                    ),
                ),
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full SATB audio, video & reference performance',
                    'media' => array(
                        array( 'Baba Yetu — SATB Audio (MP3)', $audio_base . 'baba-yetu-satb.mp3', 'audio' ),
                        array( 'Baba Yetu — SATB Video (MP4)', $video_base . 'baba-yetu-satb.mp4', 'video' ),
                        array( 'Baba Yetu — Full Choir Reference (YouTube)', 'https://youtu.be/Or19_sb-vWY', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'chorus', 'Chorus', "Baba yetu, yetu uliye\nMbinguni yetu, yetu, amina\nBaba yetu, yetu, uliye\nJina lako litukuzwe", 'SATB' ),
                array( 'verse', 'Verse 1', "Utupe leo chakula chetu\nTunachohitaji utusamehe\nMakosa yetu, hey!\nKama nasi tunavyowasamehe\nWaliotukosea, usitutie\nKatika majaribu, lakini\nUtuokoe, na yule, milele na milele!", 'SATB' ),
                array( 'bridge', 'Bridge', "Ufalme wako ufike utakalo\nLifanyike duniani kama mbinguni, amina", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-the-blessing',
            'title'    => 'The Blessing (English, Portuguese & German)',
            'composer' => 'Chris Brown, Cody Carnes, Kari Jobe & Steven Furtick',
            'lyricist' => 'Numbers 6:24–26',
            'arranger' => 'SAB / SATB Multilingual Tour Finale',
            'sections' => array(
                array(
                    'part'  => 'A',
                    'label' => 'Alto & Tenor Harmony',
                    'desc'  => 'Combined Tenor & Alto harmony study track',
                    'media' => array(
                        array( 'The Blessing — Tenor & Alto (MP3)', $audio_base . 'the-blessing-tenor-alto.mp3', 'audio' ),
                    ),
                ),
                array(
                    'part'  => 'All',
                    'label' => 'SATB (English, Portuguese & German)',
                    'desc'  => 'Multilingual closing anthem for Lisbon & Berlin/Cologne joint concerts',
                    'media' => array(
                        array( 'English Version — SATB (MP3)', $audio_base . 'the-blessing-english-satb.mp3', 'audio' ),
                        array( 'English Version — SATB Video (MP4)', $video_base . 'the-blessing-english-satb.mp4', 'video' ),
                        array( 'Portuguese Version — SATB (MP3)', $audio_base . 'the-blessing-portuguese-satb.mp3', 'audio' ),
                        array( 'Portuguese Version — SATB Video (MP4)', $video_base . 'the-blessing-portuguese-satb.mp4', 'video' ),
                        array( 'German Version — SATB (MP3)', $audio_base . 'the-blessing-german-satb.mp3', 'audio' ),
                        array( 'German Version — SATB Video (MP4)', $video_base . 'the-blessing-german-satb.mp4', 'video' ),
                        array( 'SAB Reference (YouTube)', 'https://www.youtube.com/watch?v=TQvL8qmmkfI', 'youtube' ),
                        array( 'Portuguese Reference (YouTube)', 'https://youtu.be/ZGGYWw96xFU', 'youtube' ),
                        array( 'German Reference (YouTube)', 'https://youtu.be/Wf74CI_4-Mg', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'verse', 'English Verse', "The Lord bless you and keep you\nMake His face shine upon you and be gracious to you\nThe Lord turn His face toward you\nAnd give you peace", 'SATB' ),
                array( 'chorus', 'Chorus', "Amen, Amen, Amen\nAmen, Amen, Amen", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-rhumba',
            'title'    => 'Rhumba',
            'composer' => 'Wanavokali',
            'lyricist' => 'Wanavokali',
            'arranger' => 'Umoja Voices Kenyan Set',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full choir audio, lyric video & Wanavokali references',
                    'media' => array(
                        array( 'Rhumba — SATB Audio (MP3)', $audio_base . 'rhumba-satb.mp3', 'audio' ),
                        array( 'Rhumba — SATB Lyrics Video (MP4)', $video_base . 'rhumba-satb-lyrics.mp4', 'video' ),
                        array( 'Rhumba Lyrics Video (YouTube)', 'https://youtu.be/5OE4kOV0KM0', 'youtube' ),
                        array( 'Rhumba Official Video (YouTube)', 'https://youtu.be/6Pps4Y_CnrM', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'intro', 'Intro', "My love, I want you\nMy love, I need you\nEvery time I see you (Inadi club)\nEvery time I see you (You light it up!)\nHey, hey, hey...", 'SATB' ),
                array( 'verse', 'Verse 1', "Naomba nafasi mi nicheze na we\nNjoo karibu, usinicheki toka mbali\nHatujuani, but tonight you could be my bae\nSonga nami, hii form imekubali\nHata kama ni mara ya kwanza sisi kuonana\nUsiogope nipe macho yako ana kwa ana", 'SATB' ),
                array( 'chorus', 'Chorus', "Ngoma zalia na mi nataka kucheza\nKucha kutwa, nishazoea kesha\nSakata Rhumba, achilia pressure\nTutacheza hata kama hatuna pesa\nNgoma zalia na mi nataka kucheza\nKucha kutwa, nishazoea kesha\nSakata Rhumba, achilia pressure\nHatujui kama tutaonana tena", 'SATB' ),
                array( 'verse', 'Verse 2', "Hey, hey, hey...\nNapenda unavyong'aa kama taa\nHapa we ndio star\nYou've got me feeling things that I can't explain\nTu vitu, Ma vitu\nI think I'm falling for you just a little", 'SATB' ),
                array( 'bridge', 'Bridge', "Vunja mifupa, kaza mshipi na tuzikwende\nTukizunguka, kaende, kaende\nTeremka, kata kiuno jionyeshe\nInuka, Inuka", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-siyabonga-jesu',
            'title'    => 'Siyabonga Jesu (Wahamba Nathi)',
            'composer' => 'Traditional South African Gospel',
            'lyricist' => 'Traditional Zulu / Sotho',
            'arranger' => 'Umoja Voices & European Choirs Collabo',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full choir audio & video reference',
                    'media' => array(
                        array( 'Siyabonga Jesu — SATB Audio (MP3)', $audio_base . 'siyabonga-jesu-satb.mp3', 'audio' ),
                        array( 'Siyabonga Jesu — SATB Video (MP4)', $video_base . 'siyabonga-jesu-satb.mp4', 'video' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'verse', 'Verse 1 (Zulu)', "Siyabonga Jesu\nWahamba nathi, oh wahamba nathi (You walked with us, oh you walked with us)\nOh wahamba nathi, siyabonga (Oh you walked with us, we thank you)\nWahamba nathi, oh wahamba nathi\nOh wahamba nathi, siyabonga", 'SATB' ),
                array( 'chorus', 'Chorus (Zulu)', "Siyabonga Jesu, Siyabonga ngonyama yezulu (We thank you Jesus, we thank you Lion of heaven)\nSiyabonga Jesu, Siyabonga\nSiyabonga Jesu, Siyabonga ngonyama yezulu\nSiyabonga Jesu, Siyabonga", 'SATB' ),
                array( 'verse', 'Verse 2', "Wakhamba nathi, oh wakhamba nathi\nOh wakhamba nathi, Siyathokoza\nSiyathokoza Jesu, Siyathokoza kakaramba (We thank you Jesus, we thank you mighty God)\nSiyathokoza Jesu, Siyathokoza", 'SATB' ),
                array( 'verse', 'Verse 3 (Sesotho)', "Wa tsamaya le rona, oh wa tsamaya le rona\nOh wa tsamaya le rona, re a boka (Oh you walked with us, we thank you)", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-niwara-nono',
            'title'    => 'Niwara Nono',
            'composer' => 'Traditional Kenyan Arrangement',
            'lyricist' => 'Traditional',
            'arranger' => 'Umoja Voices & LCC Collabo',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full SATB audio, score playthrough & lyric video',
                    'media' => array(
                        array( 'Niwara Nono — SATB Audio (MP3)', $audio_base . 'niwara-nono-satb.mp3', 'audio' ),
                        array( 'Niwara Nono — SATB Score Audio (MP3)', $audio_base . 'niwara-nono-satb-score.mp3', 'audio' ),
                        array( 'Niwara Nono — SATB Video (MP4)', $video_base . 'niwara-nono-satb.mp4', 'video' ),
                        array( 'Niwara Nono — SATB Score Video (MP4)', $video_base . 'niwara-nono-satb-score.mp4', 'video' ),
                        array( 'Video with Lyrics (YouTube)', 'https://youtu.be/NRvzp-i76rE', 'youtube' ),
                        array( 'Animated Music Sheet (YouTube)', 'https://youtu.be/_cQDmKVIMCI', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(),
        ),
        array(
            'id'       => 'uv-song-natamani-kutembea-nawe',
            'title'    => 'Natamani Kutembea Nawe',
            'composer' => 'Rebecca Dawn',
            'lyricist' => 'Rebecca Dawn',
            'arranger' => 'Umoja Voices & LCC Collabo',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full choir audio & Rebecca Dawn reference',
                    'media' => array(
                        array( 'Natamani Kutembea Nawe — SATB (MP3)', $audio_base . 'natamani-kutembea-nawe-satb.mp3', 'audio' ),
                        array( 'Rebecca Dawn Original (YouTube)', 'https://youtu.be/loa5hSLP-_0', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'chorus', 'Chorus', "Natamani kutembea nawe Bwana\nNatamani kuishi nawe siku zote", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-kuliko-jana',
            'title'    => 'Kuliko Jana',
            'composer' => 'Sauti Sol',
            'lyricist' => 'Sauti Sol',
            'arranger' => 'Umoja Voices Kenyan Set',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full SATB audio, video & Sauti Sol reference',
                    'media' => array(
                        array( 'Kuliko Jana — SATB Audio (MP3)', $audio_base . 'kuliko-jana-satb.mp3', 'audio' ),
                        array( 'Kuliko Jana — SATB Video (MP4)', $video_base . 'kuliko-jana-satb.mp4', 'video' ),
                        array( 'Kuliko Jana Reference (YouTube)', 'https://youtu.be/ojvz49lSfM4', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'chorus', 'Chorus', "Bwana ni mwokozi wangu, tena ni kiongozi wangu\nAnanipenda leo kuliko jana\nBaraka zake hazikwishi, si kama binadamu habadiliki\nAnanipenda leo kuliko jana\nKuliko jana, kuliko jana\nYesu nipende, leo kuliko jana", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-ebenezer',
            'title'    => 'Ebenezer',
            'composer' => 'Noel Nderitu',
            'lyricist' => 'Noel Nderitu',
            'arranger' => 'Umoja Voices Kenyan Set',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full SATB audio, video & Noel Nderitu reference',
                    'media' => array(
                        array( 'Ebenezer — SATB Audio (MP3)', $audio_base . 'ebenezer-satb.mp3', 'audio' ),
                        array( 'Ebenezer — SATB Video (MP4)', $video_base . 'ebenezer-satb.mp4', 'video' ),
                        array( 'Ebenezer — Noel Nderitu (YouTube)', 'https://youtu.be/odU0MqLarew', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(),
        ),
        array(
            'id'       => 'uv-song-jambo-bwana',
            'title'    => 'Jambo Bwana',
            'composer' => 'Teddy Kalanda Harrison (Them Mushrooms)',
            'lyricist' => 'Teddy Kalanda Harrison',
            'arranger' => 'Umoja Voices Kenyan Set',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full SATB audio, video & Them Mushrooms reference',
                    'media' => array(
                        array( 'Jambo Bwana — SATB Audio (MP3)', $audio_base . 'jambo-satb.mp3', 'audio' ),
                        array( 'Jambo Bwana — SATB Video (MP4)', $video_base . 'jambo-satb.mp4', 'video' ),
                        array( 'Jambo Bwana Reference (YouTube)', 'https://youtu.be/5mL5UuqyGA4', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'chorus', 'Chorus', "Jambo, Jambo Bwana\nHabari gani, mzuri sana\nWageni, mwakaribishwa\nKenya yetu, hakuna matata", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-this-little-light-of-mine',
            'title'    => 'This Little Light of Mine',
            'composer' => 'Traditional Gospel',
            'lyricist' => 'Traditional',
            'arranger' => 'Tour Finale Arrangement',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Full SATB audio, video & reference',
                    'media' => array(
                        array( 'This Little Light of Mine — SATB Audio (MP3)', $audio_base . 'this-little-light-of-mine-satb.mp3', 'audio' ),
                        array( 'This Little Light of Mine — SATB Video (MP4)', $video_base . 'this-little-light-of-mine-satb.mp4', 'video' ),
                        array( 'Reference Performance (YouTube)', 'https://youtu.be/1yUK0S_cEXY', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(
                array( 'verse', 'Verse 1', "This little light of mine, I'm gonna let it shine\nThis little light of mine, I'm gonna let it shine\nThis little light of mine, I'm gonna let it shine\nLet it shine, let it shine, let it shine!", 'SATB' ),
            ),
        ),
        array(
            'id'       => 'uv-song-sounds-of-kenya',
            'title'    => 'Sounds of Kenya (Intro Medley)',
            'composer' => 'Traditional Kenyan Medley',
            'lyricist' => 'Traditional',
            'arranger' => 'Kilonzo & Umoja Voices',
            'sections' => array(
                array(
                    'part'  => 'All',
                    'label' => 'SATB',
                    'desc'  => 'Concert opener — Kenyan Sound intro medley',
                    'media' => array(
                        array( 'Sounds of Kenya — Rehearsal Video (MP4)', $video_base . 'sounds-of-kenya.mp4', 'video' ),
                        array( 'Intro — Kenyan Sound (YouTube)', 'https://youtu.be/__yvrxmpDh4', 'youtube' ),
                    ),
                ),
            ),
            'sheets'   => array(),
            'lyrics'   => array(),
        ),
    );

    $seeded_songs = 0;
    foreach ( $repertoire as $song ) {
        $sid = $song['id'];
        $existing_secs = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . uv_table( 'song_sections' ) . " WHERE songId = %s", $sid ) );
        foreach ( $existing_secs as $sec_id ) {
            $wpdb->delete( uv_table( 'song_media' ), array( 'sectionId' => $sec_id ) );
        }
        $wpdb->delete( uv_table( 'song_sections' ), array( 'songId' => $sid ) );
        $wpdb->delete( uv_table( 'song_sheet_music' ), array( 'songId' => $sid ) );
        $wpdb->delete( uv_table( 'lyric_sections' ), array( 'songId' => $sid ) );
        $wpdb->delete( uv_table( 'songs' ), array( 'id' => $sid ) );

        $wpdb->insert(
            uv_table( 'songs' ),
            array(
                'id'          => $sid,
                'title'       => $song['title'],
                'composer'    => $song['composer'],
                'lyricist'    => $song['lyricist'],
                'arranger'    => $song['arranger'],
                'createdById' => $admin_id,
                'createdAt'   => $now,
                'updatedAt'   => $now,
            )
        );

        foreach ( $song['sections'] as $s_idx => $sec ) {
            $sec_id = $sid . '-sec-' . $s_idx;
            $wpdb->insert(
                uv_table( 'song_sections' ),
                array(
                    'id'               => $sec_id,
                    'songId'           => $sid,
                    'part'             => $sec['part'],
                    'sectionLabel'     => $sec['label'],
                    'labelDescription' => $sec['desc'],
                    'sortOrder'        => $s_idx,
                )
            );
            foreach ( $sec['media'] as $m_idx => $m ) {
                $wpdb->insert(
                    uv_table( 'song_media' ),
                    array(
                        'id'        => $sec_id . '-m-' . $m_idx,
                        'sectionId' => $sec_id,
                        'label'     => $m[0],
                        'mediaUrl'  => $m[1],
                        'mediaKind' => $m[2],
                        'sortOrder' => $m_idx,
                        'createdAt' => $now,
                    )
                );
            }
        }

        foreach ( $song['sheets'] as $sh_idx => $sh ) {
            $wpdb->insert(
                uv_table( 'song_sheet_music' ),
                array(
                    'id'        => $sid . '-sheet-' . $sh_idx,
                    'songId'    => $sid,
                    'label'     => $sh[0],
                    'fileUrl'   => $sh[1],
                    'sortOrder' => $sh_idx,
                    'createdAt' => $now,
                )
            );
        }

        foreach ( $song['lyrics'] as $l_idx => $ly ) {
            $wpdb->insert(
                uv_table( 'lyric_sections' ),
                array(
                    'id'           => $sid . '-lyric-' . $l_idx,
                    'songId'       => $sid,
                    'sectionType'  => $ly[0],
                    'sectionLabel' => $ly[1],
                    'content'      => $ly[2],
                    'voiceTags'    => $ly[3],
                    'sortOrder'    => $l_idx,
                )
            );
        }
        $seeded_songs++;
    }

    // Seed real Portugal & Germany Tour 2026 trip
    $tour_id = 'uv-trip-europe-tour-2026';
    $wpdb->delete( uv_table( 'logistics_deadlines' ), array( 'tripId' => $tour_id ) );
    $wpdb->delete( uv_table( 'itinerary_items' ), array( 'tripId' => $tour_id ) );
    $wpdb->delete( uv_table( 'practice_sessions' ), array( 'tripId' => $tour_id ) );
    $wpdb->delete( uv_table( 'trips' ), array( 'id' => $tour_id ) );

    $wpdb->insert(
        uv_table( 'trips' ),
        array(
            'id'          => $tour_id,
            'title'       => 'Portugal & Germany Cultural Exchange Tour 2026',
            'destination' => 'Lisbon, Paris, Cologne & Berlin',
            'startDate'   => '2026-10-07 00:00:00',
            'endDate'     => '2026-10-18 00:00:00',
            'status'      => 'upcoming',
            'createdById' => $admin_id,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        )
    );

    $tour_deadlines = array(
        array( 'visa', 'Schengen Visa Application & Support Letters', '2026-09-04 00:00:00', 'Ensure active bank account statement covering May–September 2026; visa support letters provided by tour coordinators.', 0 ),
        array( 'payment', 'Flights & Intra-Europe Travel Contribution', '2026-09-15 00:00:00', 'Accommodation is provided in Portugal and Germany; budget KES 100,000 – 180,000 for flights and intra-Europe rail/air.', 1 ),
        array( 'tickets', 'Final Concert Dress & Repertoire Sign-Off', '2026-09-25 00:00:00', '5 Kenyan choir set pieces + 3 joint collaboration pieces with Lisbon & German host choirs.', 2 ),
    );
    foreach ( $tour_deadlines as $td ) {
        $wpdb->insert(
            uv_table( 'logistics_deadlines' ),
            array(
                'id'        => 'uv-tour-dl-' . $td[4],
                'tripId'    => $tour_id,
                'category'  => $td[0],
                'label'     => $td[1],
                'dueDate'   => $td[2],
                'notes'     => $td[3],
                'sortOrder' => $td[4],
            )
        );
    }

    $tour_itinerary = array(
        array( '2026-10-07 00:00:00', '22:00', 'Depart Nairobi (JKIA) for Lisbon', 'Jomo Kenyatta International Airport, Nairobi', 'Group check-in 3 hours prior to departure', 0 ),
        array( '2026-10-09 00:00:00', '18:30', 'Joint Rehearsal & Concert with Lisbon Community Choir', 'St. Joana Auditorium, Lisbon, Portugal', 'Collabo pieces: Sing Sing Sing, Can You Feel The Love Tonight, Brighter Days, Natamani Kutembea Nawe & Niwara Nono', 1 ),
        array( '2026-10-12 00:00:00', '19:00', 'Paris Cultural Exchange Showcase', 'Paris, France', 'Maximum 2 concerts per city', 2 ),
        array( '2026-10-14 00:00:00', '19:00', 'Cologne Joint Choral Evening', 'Cologne, Germany', 'Kenyan Choir Set + German Choir Collabo', 3 ),
        array( '2026-10-16 00:00:00', '19:30', 'Berlin Grand Finale Concert', 'Berlin, Germany', 'Closing with This Little Light of Mine & The Blessing (English, Portuguese & German)', 4 ),
        array( '2026-10-18 00:00:00', '10:00', 'Return Flight to Nairobi', 'Berlin Brandenburg Airport (BER)', null, 5 ),
    );
    foreach ( $tour_itinerary as $ti ) {
        $wpdb->insert(
            uv_table( 'itinerary_items' ),
            array(
                'id'        => 'uv-tour-itin-' . $ti[5],
                'tripId'    => $tour_id,
                'date'      => $ti[0],
                'time'      => $ti[1],
                'title'     => $ti[2],
                'location'  => $ti[3],
                'notes'     => $ti[4],
                'sortOrder' => $ti[5],
            )
        );
    }

    $tour_practices = array(
        array( '2026-07-17 00:00:00', '20:00–22:00', 'Rose Avenue, Kilimani (Apt D5, next to Carrefour Market exit)', 'Alternate Friday evening rehearsal — Kenyan set & SATB harmonies', 0 ),
        array( '2026-07-31 00:00:00', '20:00–22:00', 'Rose Avenue, Kilimani (Apt D5, next to Carrefour Market exit)', 'Alternate Friday evening rehearsal — Collabo songs with LCC & German Choirs', 1 ),
        array( '2026-08-14 00:00:00', '20:00–22:00', 'Rose Avenue, Kilimani (Apt D5, next to Carrefour Market exit)', 'Weekly August/September tour intensive rehearsal', 2 ),
    );
    foreach ( $tour_practices as $tp ) {
        $wpdb->insert(
            uv_table( 'practice_sessions' ),
            array(
                'id'        => 'uv-tour-prac-' . $tp[4],
                'tripId'    => $tour_id,
                'date'      => $tp[0],
                'time'      => $tp[1],
                'location'  => $tp[2],
                'notes'     => $tp[3],
                'sortOrder' => $tp[4],
            )
        );
    }

    // Seed About Page Sections & Media
    $wpdb->query( "DELETE FROM " . uv_table( 'about_sections' ) );
    $wpdb->query( "DELETE FROM " . uv_table( 'about_media' ) );

    $about_sections = array(
        array(
            'uv-about-sec-0',
            'About Umoja Voices',
            "Umoja Voices is a vibrant Kenyan choir bringing together Kenyan and European singers to collaborate in joint concerts while experiencing diverse cultures and geographies.\n\nPerforming in rich four-part SATB harmony — Soprano, Alto, Tenor, and Bass — our repertoire blends celebrated Kenyan classics (Sauti Sol, Wanavokali, Noel Nderitu, Them Mushrooms, and traditional arrangements) with international choral favourites.",
            0,
        ),
        array(
            'uv-about-sec-1',
            '2026 Portugal & Germany Cultural Exchange Tour',
            "In October 2026, Umoja Voices embarks on a 14-day European tour across Lisbon, Paris, Cologne, and Berlin, performing alongside host choirs including the [Lisbon Community Choir](https://www.instagram.com/lisboncommunitychoir).\n\nOur vision is a lasting two-way cultural exchange where Kenyan and European choirs regularly share stages in both Europe and Kenya.",
            2,
        ),
        array(
            'uv-about-sec-2',
            'Our Deeper Purpose: Supporting Women & Maternal Health in Kenya',
            "Every Umoja Voices concert sings for a cause greater than ourselves. For our inaugural 2026 tour, our performances raise funds and awareness in partnership with [White Ribbon Alliance Kenya](https://whiteribbonalliancekenya.org/) — advancing reproductive, maternal, and newborn health and rights — and [Lifecycle Wellness](https://www.instagram.com/reel/DObXiuyiB79/) to support vulnerable women in Korogocho, Nairobi who provide full-time care to their children with disabilities.\n\nProceeds support tailoring and knitting equipment alongside technical and financial literacy training, empowering mothers in Korogocho to build sustainable livelihoods and regain dignity.",
            4,
        ),
    );
    foreach ( $about_sections as $as ) {
        $wpdb->insert(
            uv_table( 'about_sections' ),
            array(
                'id'        => $as[0],
                'title'     => $as[1],
                'body'      => $as[2],
                'sortOrder' => $as[3],
                'createdAt' => $now,
            )
        );
    }

    $about_media = array(
        array(
            'uv-about-media-0',
            'Umoja Voices — First Meet & Rehearsal Highlights',
            $video_base . 'umoja-voices-first-meet.mp4',
            'video',
            1,
        ),
        array(
            'uv-about-media-1',
            'Community Impact in Korogocho — White Ribbon Alliance Kenya & Lifecycle Wellness',
            $video_base . 'wra-korogocho.mp4',
            'video',
            3,
        ),
    );
    foreach ( $about_media as $am ) {
        $wpdb->insert(
            uv_table( 'about_media' ),
            array(
                'id'        => $am[0],
                'label'     => $am[1],
                'mediaUrl'  => $am[2],
                'mediaKind' => $am[3],
                'sortOrder' => $am[4],
                'createdAt' => $now,
            )
        );
    }

    // Seed External Links
    $wpdb->query( "DELETE FROM " . uv_table( 'external_links' ) );
    $live_links = array(
        array( 'uv-link-0', 'White Ribbon Alliance Kenya', 'https://whiteribbonalliancekenya.org/', 'other', 0 ),
        array( 'uv-link-1', 'Lifecycle Wellness — Korogocho Women Empowerment Reel', 'https://www.instagram.com/reel/DObXiuyiB79/', 'social', 1 ),
        array( 'uv-link-2', 'Lisbon Community Choir on Instagram', 'https://www.instagram.com/lisboncommunitychoir', 'social', 2 ),
        array( 'uv-link-3', 'Lisbon Community Choir on YouTube', 'https://youtube.com/@lisboncommunitychoir586', 'media', 3 ),
        array( 'uv-link-4', 'Lisbon & Oslo Community Choirs Joint Concert Highlight', 'https://youtu.be/F4pWOw28uxE', 'media', 4 ),
        array( 'uv-link-5', 'Kilimani Rehearsal Venue (Google Maps)', 'https://maps.app.goo.gl/X2vAYBsZG7fBspry8', 'other', 5 ),
    );
    foreach ( $live_links as $ll ) {
        $wpdb->insert(
            uv_table( 'external_links' ),
            array(
                'id'        => $ll[0],
                'title'     => $ll[1],
                'url'       => $ll[2],
                'category'  => $ll[3],
                'sortOrder' => $ll[4],
                'createdAt' => $now,
            )
        );
    }

    return array(
        'songs' => $seeded_songs,
        'trip'  => $tour_id,
    );
}
