<?php
/**
 * Member-protected views:
 * - /songs
 * - /songs/<id>
 * - /songs/<id>/media
 * - /songs/<id>/sheet-music
 * - /songs/<id>/lyrics
 * - /logistics
 * - /profile
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * /songs view matching src/app/songs/page.tsx.
 */
function uv_view_songs_list( array $user ): string {
    global $wpdb;
    $is_admin = ( $user['role'] === 'admin' );
    $songs    = $wpdb->get_results( "SELECT * FROM " . uv_table( 'songs' ) . " ORDER BY title ASC", ARRAY_A ) ?: array();

    ob_start();
    ?>
    <div class="mx-auto max-w-3xl px-4 py-10 w-full">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-ink">Songs</h1>
            <?php if ( $is_admin ) : ?>
                <a href="<?php echo esc_url( home_url( '/admin/songs/new' ) ); ?>" class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">
                    New song
                </a>
            <?php endif; ?>
        </div>

        <?php if ( empty( $songs ) ) : ?>
            <p class="text-ink/60">No songs added yet.</p>
        <?php else : ?>
            <ul class="flex flex-col gap-3">
                <?php foreach ( $songs as $song ) : ?>
                    <li>
                        <a
                            href="<?php echo esc_url( home_url( '/songs/' . rawurlencode( $song['id'] ) ) ); ?>"
                            class="flex items-center justify-between rounded-lg border border-ink/10 bg-white px-4 py-3 shadow-sm hover:border-ink/30"
                        >
                            <div>
                                <p class="font-medium text-ink"><?php echo esc_html( $song['title'] ); ?></p>
                                <?php if ( ! empty( $song['composer'] ) ) : ?>
                                    <p class="text-xs text-ink/60"><?php echo esc_html( $song['composer'] ); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /songs/<id> view matching src/app/songs/[id]/page.tsx.
 */
function uv_view_song_detail( string $song_id, array $user ): string {
    global $wpdb;
    $song = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'songs' ) . " WHERE id = %s", $song_id ), ARRAY_A );
    if ( ! $song ) {
        status_header( 404 );
        return '<div class="mx-auto max-w-3xl px-4 py-10"><p class="text-ink/60">Song not found.</p></div>';
    }

    $is_admin = ( $user['role'] === 'admin' );

    $media_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(m.id) FROM " . uv_table( 'song_media' ) . " m
             INNER JOIN " . uv_table( 'song_sections' ) . " s ON m.sectionId = s.id
             WHERE s.songId = %s",
            $song_id
        )
    );
    $sheet_count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . uv_table( 'song_sheet_music' ) . " WHERE songId = %s", $song_id ) );
    $lyrics_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . uv_table( 'lyric_sections' ) . " WHERE songId = %s", $song_id ) );

    $credits = array();
    if ( ! empty( $song['composer'] ) ) {
        $credits[] = 'Composer: ' . $song['composer'];
    }
    if ( ! empty( $song['lyricist'] ) ) {
        $credits[] = 'Writer: ' . $song['lyricist'];
    }
    if ( ! empty( $song['arranger'] ) ) {
        $credits[] = 'Arranger: ' . $song['arranger'];
    }

    $cards = array(
        array(
            'href'        => '/songs/' . rawurlencode( $song_id ) . '/media',
            'title'       => 'Media',
            'description' => 'Audio, video, and reference recordings by voice part',
            'count'       => $media_count === 0 ? 'None yet' : $media_count . ' item' . ( $media_count === 1 ? '' : 's' ),
        ),
        array(
            'href'        => '/songs/' . rawurlencode( $song_id ) . '/sheet-music',
            'title'       => 'Sheet Music',
            'description' => 'Scores and part sheets (PDF)',
            'count'       => $sheet_count === 0 ? 'None yet' : $sheet_count . ' PDF' . ( $sheet_count === 1 ? '' : 's' ),
        ),
        array(
            'href'        => '/songs/' . rawurlencode( $song_id ) . '/lyrics',
            'title'       => 'Lyrics',
            'description' => 'Filterable by voice part (S, A, T, B)',
            'count'       => $lyrics_count === 0 ? 'None yet' : $lyrics_count . ' section' . ( $lyrics_count === 1 ? '' : 's' ),
        ),
    );

    ob_start();
    ?>
    <div class="mx-auto max-w-3xl px-4 py-10 w-full">
        <?php echo uv_render_breadcrumb( array( array( 'label' => 'Songs', 'href' => '/songs' ), array( 'label' => $song['title'] ) ) ); ?>

        <div class="mb-8 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-ink"><?php echo esc_html( $song['title'] ); ?></h1>
                <?php if ( ! empty( $credits ) ) : ?>
                    <p class="mt-1 text-sm text-ink/60"><?php echo esc_html( implode( ' · ', $credits ) ); ?></p>
                <?php endif; ?>
            </div>
            <?php if ( $is_admin ) : ?>
                <div class="flex items-center gap-3 text-sm">
                    <a href="<?php echo esc_url( home_url( '/admin/songs/' . rawurlencode( $song_id ) . '/edit' ) ); ?>" class="text-ink underline hover:no-underline">
                        Edit
                    </a>
                    <button
                        type="button"
                        class="text-sm text-red-600 hover:underline uv-delete-song-btn"
                        data-song-id="<?php echo esc_attr( $song_id ); ?>"
                        data-song-title="<?php echo esc_attr( $song['title'] ); ?>"
                    >
                        Delete
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-ink/50">
                Practice &amp; Reference
            </h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach ( $cards as $card ) : ?>
                    <a
                        href="<?php echo esc_url( home_url( $card['href'] ) ); ?>"
                        class="flex flex-col justify-between rounded-lg border border-ink/10 bg-white p-4 shadow-sm hover:border-ink/30"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-ink"><?php echo esc_html( $card['title'] ); ?></h3>
                                <span class="text-xs text-ink/40">&rarr;</span>
                            </div>
                            <p class="mt-1 text-xs text-ink/60"><?php echo esc_html( $card['description'] ); ?></p>
                        </div>
                        <p class="mt-3 text-xs font-medium text-ink/50"><?php echo esc_html( $card['count'] ); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /songs/<id>/media view matching src/app/songs/[id]/media/page.tsx & MediaGroups.tsx.
 */
function uv_view_song_media( string $song_id, array $user ): string {
    global $wpdb;
    $song = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'songs' ) . " WHERE id = %s", $song_id ), ARRAY_A );
    if ( ! $song ) {
        status_header( 404 );
        return '<div class="mx-auto max-w-3xl px-4 py-10"><p class="text-ink/60">Song not found.</p></div>';
    }

    $is_admin = ( $user['role'] === 'admin' );
    $sections = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM " . uv_table( 'song_sections' ) . " WHERE songId = %s ORDER BY sortOrder ASC", $song_id ),
        ARRAY_A
    ) ?: array();

    $part_options = uv_song_part_options();
    $headings     = uv_song_part_heading();
    $labels       = uv_song_part_label_text();

    $grouped = array();
    foreach ( $part_options as $pt ) {
        $grouped[ $pt ] = array();
    }

    foreach ( $sections as $sec ) {
        $pt    = isset( $grouped[ $sec['part'] ] ) ? $sec['part'] : 'All';
        $items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM " . uv_table( 'song_media' ) . " WHERE sectionId = %s ORDER BY sortOrder ASC", $sec['id'] ),
            ARRAY_A
        ) ?: array();
        foreach ( $items as $it ) {
            $grouped[ $pt ][] = $it;
        }
    }

    $groups = array();
    foreach ( $part_options as $pt ) {
        if ( ! empty( $grouped[ $pt ] ) ) {
            $groups[] = array(
                'part'    => $pt,
                'heading' => $headings[ $pt ],
                'items'   => $grouped[ $pt ],
            );
        }
    }

    ob_start();
    ?>
    <div class="mx-auto max-w-3xl px-4 py-10 w-full">
        <?php
        echo uv_render_breadcrumb( array(
            array( 'label' => 'Songs', 'href' => '/songs' ),
            array( 'label' => $song['title'], 'href' => '/songs/' . rawurlencode( $song_id ) ),
            array( 'label' => 'Media' ),
        ) );
        ?>

        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-ink/40">Media</p>
        <h1 class="mb-6 text-2xl font-semibold text-ink"><?php echo esc_html( $song['title'] ); ?></h1>

        <div id="uv-media-groups-container">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-2" id="uv-media-filter-pills">
                    <button type="button" data-filter-part="ALL" class="uv-media-filter-btn rounded-full border px-3 py-1 text-xs font-medium border-ink bg-ink text-white">
                        All parts
                    </button>
                    <?php foreach ( $part_options as $pt ) : ?>
                        <button type="button" data-filter-part="<?php echo esc_attr( $pt ); ?>" class="uv-media-filter-btn rounded-full border px-3 py-1 text-xs font-medium border-ink/20 bg-white text-ink hover:border-ink/40">
                            <?php echo esc_html( $labels[ $pt ] ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        id="uv-play-all-btn"
                        aria-pressed="false"
                        title="Play every audio/video item in order"
                        class="rounded-full border px-3 py-1 text-xs font-medium border-ink/20 bg-white text-ink hover:border-ink/40"
                    >
                        Play All
                    </button>
                    <button
                        type="button"
                        id="uv-loop-btn"
                        aria-pressed="false"
                        title="Repeat the current track (or the Play All sequence) when it finishes"
                        class="rounded-full border px-3 py-1 text-xs font-medium border-ink/20 bg-white text-ink hover:border-ink/40"
                    >
                        Loop
                    </button>
                </div>
            </div>

            <?php if ( empty( $groups ) ) : ?>
                <p id="uv-media-empty-msg" class="text-sm text-ink/50">No media uploaded yet.</p>
            <?php else : ?>
                <p id="uv-media-empty-msg" class="hidden text-sm text-ink/50">No media for this voice part.</p>
                <div class="flex flex-col gap-6" id="uv-media-groups-list">
                    <?php foreach ( $groups as $g ) : ?>
                        <section class="uv-media-group-section" data-group-part="<?php echo esc_attr( $g['part'] ); ?>">
                            <h2 class="mb-2 text-lg font-semibold text-ink"><?php echo esc_html( $g['heading'] ); ?></h2>
                            <div class="flex flex-col gap-3">
                                <?php foreach ( $g['items'] as $m ) : ?>
                                    <div
                                        data-testid="song-media-<?php echo esc_attr( $m['id'] ); ?>"
                                        class="uv-media-item-card rounded-lg border border-ink/10 bg-white p-3"
                                    >
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-sm font-medium text-ink"><?php echo esc_html( $m['label'] ); ?></p>
                                            <?php if ( $is_admin ) : ?>
                                                <button
                                                    type="button"
                                                    class="text-xs text-red-600 hover:underline uv-remove-song-media-btn"
                                                    data-media-id="<?php echo esc_attr( $m['id'] ); ?>"
                                                    data-media-label="<?php echo esc_attr( $m['label'] ); ?>"
                                                >
                                                    Remove
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php echo uv_render_media_embed( (string) $m['mediaUrl'], (string) $m['mediaKind'], (string) $m['label'] ); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( $is_admin ) : ?>
            <div class="mt-8">
                <form id="uv-add-song-media-form" data-song-id="<?php echo esc_attr( $song_id ); ?>" class="flex flex-col gap-3 rounded-lg border border-ink/10 bg-white p-4">
                    <h3 class="font-semibold text-ink">Add Media</h3>

                    <div class="flex gap-4 border-b border-ink/10 text-sm">
                        <button type="button" data-mode="paste" class="uv-media-mode-tab border-b-2 border-ink px-1 pb-2 font-medium text-ink">
                            Paste URL
                        </button>
                        <button type="button" data-mode="upload" class="uv-media-mode-tab px-1 pb-2 text-ink/50 hover:text-ink">
                            Upload file
                        </button>
                    </div>

                    <div id="uv-song-media-paste-wrap">
                        <label class="flex flex-col gap-1 text-sm">
                            URL <span class="text-red-600">*</span>
                            <input
                                type="text"
                                name="mediaUrl"
                                placeholder="YouTube, Spotify, Google Drive, or direct URL"
                                class="rounded border border-ink/20 px-3 py-2 bg-white"
                            />
                        </label>
                    </div>

                    <div id="uv-song-media-upload-wrap" class="hidden">
                        <label class="flex flex-col gap-1 text-sm">
                            Audio or video file <span class="text-red-600">*</span>
                            <input
                                type="file"
                                name="file"
                                accept="<?php echo esc_attr( UV_AUDIO_ACCEPT . ',' . UV_VIDEO_ACCEPT ); ?>"
                                class="rounded border border-ink/20 px-3 py-2 bg-white"
                            />
                            <span class="text-xs text-ink/50">
                                MP3/WAV/M4A/OGG audio or MP4/MOV/WEBM video — max 20MB.
                            </span>
                        </label>
                    </div>

                    <label class="flex flex-col gap-1 text-sm">
                        Label <span class="text-red-600">*</span>
                        <input
                            type="text"
                            name="label"
                            required
                            placeholder="e.g. Soprano part, Full choir recording"
                            class="rounded border border-ink/20 px-3 py-2 bg-white"
                        />
                    </label>

                    <label class="flex flex-col gap-1 text-sm">
                        Voice <span class="text-red-600">*</span>
                        <select name="part" class="rounded border border-ink/20 px-3 py-2 bg-white">
                            <?php foreach ( $part_options as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $labels[ $opt ] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div id="uv-add-song-media-error"></div>

                    <button
                        type="submit"
                        id="uv-add-song-media-submit"
                        class="flex items-center gap-2 self-start rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90 disabled:opacity-60"
                    >
                        Add Media
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /songs/<id>/sheet-music view matching src/app/songs/[id]/sheet-music/page.tsx.
 */
function uv_view_song_sheet_music( string $song_id, array $user ): string {
    global $wpdb;
    $song = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'songs' ) . " WHERE id = %s", $song_id ), ARRAY_A );
    if ( ! $song ) {
        status_header( 404 );
        return '<div class="mx-auto max-w-3xl px-4 py-10"><p class="text-ink/60">Song not found.</p></div>';
    }

    $is_admin = ( $user['role'] === 'admin' );
    $sheets   = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM " . uv_table( 'song_sheet_music' ) . " WHERE songId = %s ORDER BY sortOrder ASC", $song_id ),
        ARRAY_A
    ) ?: array();

    ob_start();
    ?>
    <div class="mx-auto max-w-3xl px-4 py-10 w-full">
        <?php
        echo uv_render_breadcrumb( array(
            array( 'label' => 'Songs', 'href' => '/songs' ),
            array( 'label' => $song['title'], 'href' => '/songs/' . rawurlencode( $song_id ) ),
            array( 'label' => 'Sheet Music' ),
        ) );
        ?>

        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-ink/40">Sheet Music</p>
        <h1 class="mb-6 text-2xl font-semibold text-ink"><?php echo esc_html( $song['title'] ); ?></h1>

        <?php if ( empty( $sheets ) ) : ?>
            <p class="text-sm text-ink/50">No sheet music uploaded yet.</p>
        <?php else : ?>
            <div class="flex flex-col gap-6">
                <?php foreach ( $sheets as $item ) : ?>
                    <div class="rounded-lg border border-ink/10 bg-white p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm font-medium text-ink"><?php echo esc_html( $item['label'] ); ?></p>
                            <div class="flex items-center gap-3">
                                <a
                                    href="<?php echo esc_url( $item['fileUrl'] ); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-xs text-ink underline hover:no-underline"
                                >
                                    Open / Download PDF
                                </a>
                                <?php if ( $is_admin ) : ?>
                                    <button
                                        type="button"
                                        class="text-xs text-red-600 hover:underline uv-remove-sheet-btn"
                                        data-sheet-id="<?php echo esc_attr( $item['id'] ); ?>"
                                    >
                                        Remove
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <iframe
                            src="<?php echo esc_url( $item['fileUrl'] ); ?>"
                            title="<?php echo esc_attr( $item['label'] ); ?>"
                            class="w-full rounded border border-ink/10"
                            style="height:70vh;"
                        ></iframe>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( $is_admin ) : ?>
            <div class="mt-8">
                <form id="uv-add-sheet-form" data-song-id="<?php echo esc_attr( $song_id ); ?>" class="flex flex-col gap-3 rounded-lg border border-ink/10 bg-white p-4">
                    <h3 class="font-semibold text-ink">Add Sheet Music</h3>

                    <label class="flex flex-col gap-1 text-sm">
                        PDF file <span class="text-red-600">*</span>
                        <input
                            type="file"
                            name="file"
                            required
                            accept="<?php echo esc_attr( UV_SHEET_MUSIC_ACCEPT ); ?>"
                            class="rounded border border-ink/20 px-3 py-2 bg-white"
                        />
                        <span class="text-xs text-ink/50">PDF only — max 10MB.</span>
                    </label>

                    <label class="flex flex-col gap-1 text-sm">
                        Label <span class="text-red-600">*</span>
                        <input
                            type="text"
                            name="label"
                            required
                            placeholder="e.g. Full score, Soprano part"
                            class="rounded border border-ink/20 px-3 py-2 bg-white"
                        />
                    </label>

                    <p id="uv-add-sheet-error" class="hidden text-sm text-red-600"></p>

                    <button
                        type="submit"
                        id="uv-add-sheet-submit"
                        class="self-start rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90 disabled:opacity-60"
                    >
                        Add Sheet Music
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /songs/<id>/lyrics view matching src/app/songs/[id]/lyrics/page.tsx & LyricsViewer.tsx.
 */
function uv_view_song_lyrics( string $song_id, array $user ): string {
    global $wpdb;
    $song = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'songs' ) . " WHERE id = %s", $song_id ), ARRAY_A );
    if ( ! $song ) {
        status_header( 404 );
        return '<div class="mx-auto max-w-3xl px-4 py-10"><p class="text-ink/60">Song not found.</p></div>';
    }

    $is_admin = ( $user['role'] === 'admin' );
    $sections = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM " . uv_table( 'lyric_sections' ) . " WHERE songId = %s ORDER BY sortOrder ASC", $song_id ),
        ARRAY_A
    ) ?: array();

    $voice_tags = uv_voice_tags();
    $tag_labels = uv_voice_tag_labels();

    ob_start();
    ?>
    <div class="mx-auto max-w-3xl px-4 py-10 w-full">
        <?php
        echo uv_render_breadcrumb( array(
            array( 'label' => 'Songs', 'href' => '/songs' ),
            array( 'label' => $song['title'], 'href' => '/songs/' . rawurlencode( $song_id ) ),
            array( 'label' => 'Lyrics' ),
        ) );
        ?>

        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-ink/40">Lyrics</p>
                <h1 class="text-2xl font-semibold text-ink"><?php echo esc_html( $song['title'] ); ?></h1>
            </div>
            <?php if ( $is_admin ) : ?>
                <a
                    href="<?php echo esc_url( home_url( '/songs/' . rawurlencode( $song_id ) . '/lyrics/edit' ) ); ?>"
                    class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90"
                >
                    <?php echo empty( $sections ) ? 'Add Lyrics' : 'Edit Lyrics'; ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ( empty( $sections ) ) : ?>
            <p class="text-sm text-ink/50">No lyrics added yet.</p>
        <?php else : ?>
            <div>
                <div class="mb-4 flex flex-wrap gap-2" id="uv-lyrics-filter-pills">
                    <button type="button" data-voice-filter="ALL" class="uv-lyric-filter-btn rounded-full border px-3 py-1 text-xs font-medium border-ink bg-ink text-white">
                        All voices
                    </button>
                    <?php foreach ( $voice_tags as $tag ) : ?>
                        <button type="button" data-voice-filter="<?php echo esc_attr( $tag ); ?>" class="uv-lyric-filter-btn rounded-full border px-3 py-1 text-xs font-medium border-ink/20 bg-white text-ink hover:border-ink/40">
                            <?php echo esc_html( $tag_labels[ $tag ] ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="flex flex-col gap-6" id="uv-lyrics-sections-list">
                    <?php foreach ( $sections as $sec ) : ?>
                        <?php $tags = uv_parse_voice_tags( (string) $sec['voiceTags'] ); ?>
                        <div class="uv-lyric-section-card rounded-lg border border-ink/10 bg-white p-4" data-voice-tags="<?php echo esc_attr( implode( ',', $tags ) ); ?>">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wide text-ink/50">
                                    <?php echo esc_html( $sec['sectionLabel'] ); ?>
                                </span>
                                <span class="flex gap-1">
                                    <?php foreach ( $tags as $t ) : ?>
                                        <span class="rounded bg-ink/10 px-2 py-0.5 text-xs text-ink"><?php echo esc_html( $t ); ?></span>
                                    <?php endforeach; ?>
                                </span>
                            </div>
                            <pre class="whitespace-pre-line font-sans text-sm leading-relaxed text-ink"><?php echo esc_html( $sec['content'] ); ?></pre>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p id="uv-lyrics-empty-filter" class="hidden text-sm text-ink/50">No lyric sections tagged for this voice.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /logistics view matching src/app/logistics/page.tsx.
 */
function uv_view_logistics( array $user ): string {
    global $wpdb;
    $is_admin = ( $user['role'] === 'admin' );
    $trip     = $wpdb->get_row( "SELECT * FROM " . uv_table( 'trips' ) . " WHERE status = 'upcoming' ORDER BY startDate ASC LIMIT 1", ARRAY_A );

    if ( ! $trip ) {
        ob_start();
        ?>
        <div class="mx-auto max-w-3xl px-4 py-10 w-full">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-ink">Logistics</h1>
                <?php if ( $is_admin ) : ?>
                    <a href="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>" class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">
                        Manage logistics
                    </a>
                <?php endif; ?>
            </div>
            <p class="mt-4 text-ink/60">No upcoming trip scheduled right now.</p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    $deadlines = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . uv_table( 'logistics_deadlines' ) . " WHERE tripId = %s ORDER BY dueDate ASC, sortOrder ASC", $trip['id'] ), ARRAY_A ) ?: array();
    $itinerary = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . uv_table( 'itinerary_items' ) . " WHERE tripId = %s ORDER BY date ASC, sortOrder ASC", $trip['id'] ), ARRAY_A ) ?: array();
    $practices = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . uv_table( 'practice_sessions' ) . " WHERE tripId = %s ORDER BY date ASC, sortOrder ASC", $trip['id'] ), ARRAY_A ) ?: array();

    $fmt = function ( string $dt ): string {
        return substr( $dt, 0, 10 );
    };

    ob_start();
    ?>
    <div class="mx-auto max-w-3xl px-4 py-10 w-full">
        <div class="mb-8 flex items-start justify-between gap-4">
            <div>
                <h1 class="mb-1 text-2xl font-semibold text-ink"><?php echo esc_html( $trip['title'] ); ?></h1>
                <p class="text-sm text-ink/60">
                    <?php echo esc_html( $trip['destination'] ); ?> &middot; <?php echo esc_html( $fmt( $trip['startDate'] ) ); ?> – <?php echo esc_html( $fmt( $trip['endDate'] ) ); ?>
                </p>
            </div>
            <?php if ( $is_admin ) : ?>
                <a href="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>" class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">
                    Manage logistics
                </a>
            <?php endif; ?>
        </div>

        <section class="mb-10">
            <h2 class="mb-3 text-lg font-semibold text-ink">Key dates &amp; deadlines</h2>
            <?php if ( empty( $deadlines ) ) : ?>
                <p class="text-sm text-ink/50">None listed.</p>
            <?php else : ?>
                <ul class="flex flex-col gap-2">
                    <?php foreach ( $deadlines as $d ) : ?>
                        <li class="flex items-baseline justify-between rounded-lg border border-ink/10 bg-white px-4 py-3 shadow-sm">
                            <div>
                                <span class="mr-2 rounded bg-ink/10 px-2 py-0.5 text-xs uppercase text-ink"><?php echo esc_html( $d['category'] ); ?></span>
                                <span class="font-medium text-ink"><?php echo esc_html( $d['label'] ); ?></span>
                                <?php if ( ! empty( $d['notes'] ) ) : ?>
                                    <p class="mt-1 text-xs text-ink/60"><?php echo esc_html( $d['notes'] ); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-sm font-medium text-ink"><?php echo esc_html( $fmt( $d['dueDate'] ) ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="mb-10">
            <h2 class="mb-3 text-lg font-semibold text-ink">Itinerary</h2>
            <?php if ( empty( $itinerary ) ) : ?>
                <p class="text-sm text-ink/50">Itinerary not published yet.</p>
            <?php else : ?>
                <?php
                ob_start();
                ?>
                <ol class="flex flex-col gap-3">
                    <?php foreach ( $itinerary as $item ) : ?>
                        <li class="rounded-lg border border-ink/10 bg-white px-4 py-3 shadow-sm">
                            <div class="text-xs text-ink/50">
                                <?php echo esc_html( $fmt( $item['date'] ) ); ?><?php echo ! empty( $item['time'] ) ? ' &middot; ' . esc_html( $item['time'] ) : ''; ?>
                                <?php echo ! empty( $item['location'] ) ? ' &middot; ' . esc_html( $item['location'] ) : ''; ?>
                            </div>
                            <p class="font-medium text-ink"><?php echo esc_html( $item['title'] ); ?></p>
                            <?php if ( ! empty( $item['notes'] ) ) : ?>
                                <p class="mt-1 text-sm text-ink/70"><?php echo esc_html( $item['notes'] ); ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php
                echo uv_render_expandable( (string) ob_get_clean(), 160 );
                ?>
            <?php endif; ?>
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-ink">Practice schedule</h2>
            <?php if ( empty( $practices ) ) : ?>
                <p class="text-sm text-ink/50">No rehearsals scheduled yet.</p>
            <?php else : ?>
                <ul class="flex flex-col gap-2">
                    <?php foreach ( $practices as $s ) : ?>
                        <li class="rounded-lg border border-ink/10 bg-white px-4 py-3 shadow-sm">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-ink"><?php echo esc_html( $fmt( $s['date'] ) ); ?> &middot; <?php echo esc_html( $s['time'] ); ?></span>
                                <span class="text-ink/70"><?php echo esc_html( $s['location'] ); ?></span>
                            </div>
                            <?php if ( ! empty( $s['notes'] ) ) : ?>
                                <p class="mt-1 text-xs text-ink/60"><?php echo esc_html( $s['notes'] ); ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /profile view matching src/app/profile/page.tsx & ProfileForm.tsx.
 */
function uv_view_profile( array $user ): string {
    $voice_labels = array(
        'S' => 'Soprano (S)',
        'A' => 'Alto (A)',
        'T' => 'Tenor (T)',
        'B' => 'Bass (B)',
    );
    $voice_display = ! empty( $user['voicePart'] ) && isset( $voice_labels[ $user['voicePart'] ] )
        ? $voice_labels[ $user['voicePart'] ]
        : '—';

    ob_start();
    ?>
    <div class="mx-auto max-w-md px-4 py-10 w-full">
        <h1 class="mb-6 text-2xl font-semibold text-ink">Your profile</h1>

        <div
            id="uv-profile-root"
            data-initial-bio="<?php echo esc_attr( (string) ( $user['bio'] ?? '' ) ); ?>"
            data-initial-voice="<?php echo esc_attr( (string) ( $user['voicePart'] ?? '' ) ); ?>"
            data-initial-instrument="<?php echo esc_attr( (string) ( $user['instrument'] ?? '' ) ); ?>"
            data-initial-phone="<?php echo esc_attr( (string) ( $user['phone'] ?? '' ) ); ?>"
            data-initial-photo="<?php echo esc_attr( (string) ( $user['photoUrl'] ?? '' ) ); ?>"
            data-user-name="<?php echo esc_attr( (string) $user['name'] ); ?>"
        >
            <!-- Flat View (shown by default) -->
            <div id="uv-profile-flat-view" class="mb-8 rounded-lg border border-ink/10 bg-white p-4 text-sm shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="uv-profile-avatar-slot">
                            <?php if ( ! empty( $user['photoUrl'] ) ) : ?>
                                <img
                                    src="<?php echo esc_url( $user['photoUrl'] ); ?>"
                                    alt="<?php echo esc_attr( $user['name'] ); ?>"
                                    data-testid="profile-photo"
                                    class="h-16 w-16 rounded-full object-cover"
                                />
                            <?php else : ?>
                                <div
                                    data-testid="profile-photo-placeholder"
                                    class="flex h-16 w-16 items-center justify-center rounded-full bg-ink/10 text-xl font-semibold text-ink/50"
                                >
                                    <?php echo esc_html( strtoupper( substr( (string) $user['name'], 0, 1 ) ) ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="font-medium text-ink"><?php echo esc_html( $user['name'] ); ?></p>
                            <p class="text-xs text-ink/60"><?php echo esc_html( $user['email'] ); ?></p>
                            <p class="text-xs capitalize text-ink/60"><?php echo esc_html( $user['role'] ); ?></p>
                        </div>
                    </div>
                    <button
                        type="button"
                        id="uv-profile-edit-btn"
                        class="rounded-full border border-ink/20 px-4 py-1.5 text-sm text-ink hover:bg-ink/5"
                    >
                        Edit
                    </button>
                </div>

                <dl class="mt-4 flex flex-col gap-3 border-t border-ink/10 pt-4">
                    <div>
                        <dt class="text-xs text-ink/50">Bio</dt>
                        <dd id="uv-flat-bio" class="mt-0.5 whitespace-pre-line text-ink"><?php echo ! empty( $user['bio'] ) ? esc_html( $user['bio'] ) : '—'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink/50">Voice</dt>
                        <dd id="uv-flat-voice" class="mt-0.5 text-ink"><?php echo esc_html( $voice_display ); ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink/50">Instrument</dt>
                        <dd id="uv-flat-instrument" class="mt-0.5 text-ink"><?php echo ! empty( $user['instrument'] ) ? esc_html( $user['instrument'] ) : '—'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink/50">Phone</dt>
                        <dd id="uv-flat-phone" class="mt-0.5 text-ink"><?php echo ! empty( $user['phone'] ) ? esc_html( $user['phone'] ) : '—'; ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Edit Mode View (hidden until Edit is clicked) -->
            <div id="uv-profile-edit-view" class="hidden"></div>
        </div>

        <form id="uv-profile-password-form" class="mt-8 flex flex-col gap-4 border-t border-ink/10 pt-8">
            <h2 class="font-semibold text-ink">Change password</h2>
            <label class="flex flex-col gap-1 text-sm">
                New password
                <input
                    type="password"
                    name="password"
                    required
                    minlength="8"
                    class="rounded border border-ink/20 px-3 py-2 bg-white"
                />
            </label>
            <p id="uv-profile-pw-msg" class="hidden text-sm"></p>
            <button type="submit" class="self-start rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">
                Update password
            </button>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
}
