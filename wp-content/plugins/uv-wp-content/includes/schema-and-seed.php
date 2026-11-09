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
