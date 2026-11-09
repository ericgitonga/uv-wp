<?php
/**
 * Admin-only views:
 * - /admin/songs/new & /admin/songs/<id>/edit
 * - /songs/<id>/lyrics/edit
 * - /admin/members
 * - /admin/logistics
 * - /admin/links
 * - /admin/about
 * - /admin/activity
 * - /admin/storage
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * /admin/songs/new & /admin/songs/<id>/edit matching SongEditor.tsx.
 */
function uv_view_admin_song_editor( ?string $song_id ): string {
    global $wpdb;
    $song     = null;
    $sections = array();
    $lyrics   = array();

    if ( $song_id !== null ) {
        $song = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'songs' ) . " WHERE id = %s", $song_id ), ARRAY_A );
        if ( ! $song ) {
            status_header( 404 );
            return '<div class="mx-auto max-w-2xl px-4 py-10"><p class="text-ink/60">Song not found.</p></div>';
        }
        $raw_secs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . uv_table( 'song_sections' ) . " WHERE songId = %s ORDER BY sortOrder ASC", $song_id ), ARRAY_A ) ?: array();
        foreach ( $raw_secs as $rs ) {
            $media = $wpdb->get_results( $wpdb->prepare( "SELECT label, mediaUrl FROM " . uv_table( 'song_media' ) . " WHERE sectionId = %s ORDER BY sortOrder ASC", $rs['id'] ), ARRAY_A ) ?: array();
            $sections[] = array(
                'part'             => $rs['part'],
                'sectionLabel'     => $rs['sectionLabel'],
                'labelDescription' => $rs['labelDescription'],
                'media'            => $media,
            );
        }
        $raw_lyrics = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . uv_table( 'lyric_sections' ) . " WHERE songId = %s ORDER BY sortOrder ASC", $song_id ), ARRAY_A ) ?: array();
        foreach ( $raw_lyrics as $rl ) {
            $lyrics[] = array(
                'sectionType'  => $rl['sectionType'],
                'sectionLabel' => $rl['sectionLabel'],
                'content'      => $rl['content'],
                'voiceTags'    => uv_parse_voice_tags( (string) $rl['voiceTags'] ),
            );
        }
    }

    $breadcrumb_items = array(
        array( 'label' => 'Songs', 'href' => '/songs' ),
        array( 'label' => $song ? $song['title'] : 'New song' ),
    );

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10 w-full">
        <?php echo uv_render_breadcrumb( $breadcrumb_items ); ?>
        <h1 class="mb-6 text-2xl font-semibold text-ink"><?php echo $song ? 'Edit song' : 'New song'; ?></h1>

        <form
            id="uv-song-editor-form"
            data-song-id="<?php echo esc_attr( (string) ( $song_id ?? '' ) ); ?>"
            data-initial-sections="<?php echo esc_attr( wp_json_encode( $sections ) ); ?>"
            data-initial-lyrics="<?php echo esc_attr( wp_json_encode( $lyrics ) ); ?>"
            class="flex flex-col gap-8"
        >
            <section class="flex flex-col gap-3 rounded-lg border border-ink/10 bg-white p-4 shadow-sm">
                <h2 class="font-semibold text-ink">Song details</h2>
                <label class="flex flex-col gap-1 text-sm">
                    Title
                    <input
                        type="text"
                        name="title"
                        required
                        value="<?php echo esc_attr( (string) ( $song['title'] ?? '' ) ); ?>"
                        class="rounded border border-ink/20 px-3 py-2 bg-white"
                    />
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex flex-col gap-1 text-sm">
                        Composer
                        <input
                            type="text"
                            name="composer"
                            value="<?php echo esc_attr( (string) ( $song['composer'] ?? '' ) ); ?>"
                            class="rounded border border-ink/20 px-3 py-2 bg-white"
                        />
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        Writer
                        <input
                            type="text"
                            name="lyricist"
                            value="<?php echo esc_attr( (string) ( $song['lyricist'] ?? '' ) ); ?>"
                            class="rounded border border-ink/20 px-3 py-2 bg-white"
                        />
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        Arranger
                        <input
                            type="text"
                            name="arranger"
                            value="<?php echo esc_attr( (string) ( $song['arranger'] ?? '' ) ); ?>"
                            class="rounded border border-ink/20 px-3 py-2 bg-white"
                        />
                    </label>
                </div>
            </section>

            <p id="uv-song-editor-error" class="hidden text-sm text-red-600"></p>

            <div class="flex gap-3">
                <button
                    type="submit"
                    id="uv-song-editor-submit"
                    class="rounded-full bg-ink px-5 py-2 text-white hover:opacity-90 disabled:opacity-60"
                >
                    <?php echo $song ? 'Save changes' : 'Create song'; ?>
                </button>
                <button
                    type="button"
                    id="uv-song-editor-cancel"
                    data-cancel-url="<?php echo esc_attr( $song ? home_url( '/songs/' . rawurlencode( $song_id ) ) : home_url( '/songs' ) ); ?>"
                    class="rounded-full border border-ink/20 px-5 py-2 text-ink"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /songs/<id>/lyrics/edit view matching ReplaceLyricsEditor.tsx.
 */
function uv_view_song_lyrics_edit( string $song_id ): string {
    global $wpdb;
    $song = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . uv_table( 'songs' ) . " WHERE id = %s", $song_id ), ARRAY_A );
    if ( ! $song ) {
        status_header( 404 );
        return '<div class="mx-auto max-w-2xl px-4 py-10"><p class="text-ink/60">Song not found.</p></div>';
    }

    $sections = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM " . uv_table( 'lyric_sections' ) . " WHERE songId = %s ORDER BY sortOrder ASC", $song_id ),
        ARRAY_A
    ) ?: array();

    $has_existing = ! empty( $sections );
    $initial_text = $has_existing ? uv_serialize_lyrics_for_edit( $sections ) : '';

    $placeholder = "Verse 1\n[S] Morning breaks over the hills\nOne voice calls, another answers\n\nChorus\n[ALL] Rising, rising together\nFour parts, one song\n\nVerse 2\n[A] Steady as the river flows\nWeave the warmth beneath the high";

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10 w-full">
        <?php
        echo uv_render_breadcrumb( array(
            array( 'label' => 'Songs', 'href' => '/songs' ),
            array( 'label' => $song['title'], 'href' => '/songs/' . rawurlencode( $song_id ) ),
            array( 'label' => 'Lyrics', 'href' => '/songs/' . rawurlencode( $song_id ) . '/lyrics' ),
            array( 'label' => 'Edit' ),
        ) );
        ?>

        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-ink/40">Edit Lyrics</p>
        <h1 class="mb-6 text-2xl font-semibold text-ink"><?php echo esc_html( $song['title'] ); ?></h1>

        <form
            id="uv-replace-lyrics-form"
            data-song-id="<?php echo esc_attr( $song_id ); ?>"
            data-has-existing="<?php echo $has_existing ? '1' : '0'; ?>"
            class="flex flex-col gap-6"
        >
            <div class="rounded-lg border border-ink/10 bg-white p-4">
                <label class="flex flex-col gap-2 text-sm">
                    <span class="font-medium text-ink">Paste lyrics</span>
                    <span class="text-xs text-ink/60">
                        Separate sections with a blank line. Put the section name on the first line (e.g.
                        <code>Verse 1</code>, <code>Chorus</code>). Tag voice parts at the start of a line with
                        <code>[S]</code>, <code>[A]</code>, <code>[T]</code>, <code>[B]</code>, or <code>[ALL]</code>
                        — a tag carries forward to following sections until a new one appears.
                    </span>
                    <textarea
                        name="rawText"
                        id="uv-lyrics-raw-textarea"
                        rows="14"
                        placeholder="<?php echo esc_attr( $placeholder ); ?>"
                        class="rounded border border-ink/20 px-3 py-2 font-mono text-sm bg-white"
                    ><?php echo esc_textarea( $initial_text ); ?></textarea>
                </label>
            </div>

            <p id="uv-replace-lyrics-error" class="hidden text-sm text-red-600"></p>

            <div class="flex gap-3">
                <button
                    type="submit"
                    id="uv-replace-lyrics-submit"
                    class="rounded-full bg-ink px-5 py-2 text-sm text-white hover:opacity-90 disabled:opacity-60"
                >
                    Save lyrics
                </button>
                <a
                    href="<?php echo esc_url( home_url( '/songs/' . rawurlencode( $song_id ) . '/lyrics' ) ); ?>"
                    class="rounded-full border border-ink/20 px-5 py-2 text-sm text-ink"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /admin/members view matching src/app/admin/members/page.tsx, InviteForm.tsx, MembersList.tsx, MemberRow.tsx.
 */
function uv_view_admin_members(): string {
    global $wpdb;
    $members = $wpdb->get_results( "SELECT id, name, email, role, status FROM " . uv_table( 'users' ) . " ORDER BY createdAt DESC", ARRAY_A ) ?: array();

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10 w-full">
        <h1 class="mb-6 text-2xl font-semibold text-ink">Members</h1>

        <div class="rounded-lg border border-ink/10 bg-white p-4 shadow-sm">
            <h2 class="mb-3 font-semibold text-ink">Invite a member</h2>
            <form id="uv-invite-member-form" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="flex flex-1 flex-col gap-1 text-sm">
                    Name
                    <input name="name" required class="rounded border border-ink/20 px-3 py-2 bg-white" />
                </label>
                <label class="flex flex-1 flex-col gap-1 text-sm">
                    Email
                    <input name="email" type="email" required class="rounded border border-ink/20 px-3 py-2 bg-white" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Role
                    <select name="role" class="rounded border border-ink/20 px-3 py-2 bg-white">
                        <option value="chorister" selected>Chorister</option>
                        <option value="admin">Admin</option>
                    </select>
                </label>
                <button type="submit" class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">
                    Send invite
                </button>
            </form>
            <p id="uv-invite-error" class="hidden mt-2 text-sm text-red-600"></p>
            <p id="uv-invite-link-box" class="hidden mt-3 rounded bg-amber-50 p-2 text-xs text-amber-800">
                Invite created — share this link with them manually (real email delivery isn&rsquo;t wired up yet): <code id="uv-invite-link-code" class="break-all"></code>
            </p>
        </div>

        <div id="uv-members-list-root">
            <ul class="mt-8 flex flex-col gap-2" id="uv-members-ul">
                <?php if ( empty( $members ) ) : ?>
                    <p class="text-sm text-ink/50">No members yet.</p>
                <?php else : ?>
                    <?php foreach ( $members as $m ) : ?>
                        <li
                            class="uv-member-row flex flex-col gap-2 rounded-lg border border-ink/10 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                            data-member-id="<?php echo esc_attr( $m['id'] ); ?>"
                            data-member-name="<?php echo esc_attr( $m['name'] ); ?>"
                            data-member-role="<?php echo esc_attr( $m['role'] ); ?>"
                            data-member-status="<?php echo esc_attr( $m['status'] ); ?>"
                        >
                            <div>
                                <p class="font-medium text-ink"><?php echo esc_html( $m['name'] ); ?></p>
                                <p class="text-xs text-ink/50">
                                    <?php echo esc_html( $m['email'] ); ?> &middot; <span class="uv-member-status-label"><?php echo esc_html( $m['status'] ); ?></span>
                                </p>
                                <p class="uv-member-error hidden mt-1 text-xs text-red-600"></p>
                                <p class="uv-member-reset-box hidden mt-2 rounded bg-amber-50 p-2 text-xs text-amber-800">
                                    Reset link — share manually (real email delivery isn&rsquo;t wired up yet): <code class="uv-member-reset-code break-all"></code>
                                </p>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <?php if ( $m['status'] === 'active' ) : ?>
                                    <button type="button" class="uv-member-reset-btn text-xs text-ink hover:underline disabled:opacity-60">
                                        Reset link
                                    </button>
                                <?php endif; ?>
                                <select class="uv-member-role-select rounded border border-ink/20 px-2 py-1 text-xs bg-white">
                                    <option value="chorister" <?php selected( $m['role'], 'chorister' ); ?>>Chorister</option>
                                    <option value="admin" <?php selected( $m['role'], 'admin' ); ?>>Admin</option>
                                </select>
                                <button type="button" class="uv-member-status-btn text-xs text-red-600 hover:underline">
                                    <?php echo $m['status'] === 'disabled' ? 'Reactivate' : 'Deactivate'; ?>
                                </button>
                                <button type="button" class="uv-member-delete-btn text-xs text-red-600 hover:underline">
                                    Delete
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /admin/logistics view matching src/app/admin/logistics/page.tsx.
 */
function uv_view_admin_logistics(): string {
    global $wpdb;
    $trip = $wpdb->get_row( "SELECT * FROM " . uv_table( 'trips' ) . " WHERE status = 'upcoming' ORDER BY startDate ASC LIMIT 1", ARRAY_A );

    if ( ! $trip ) {
        ob_start();
        ?>
        <div class="mx-auto max-w-lg px-4 py-10 w-full">
            <h1 class="mb-6 text-2xl font-semibold text-ink">Logistics</h1>
            <form method="post" action="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>" class="flex flex-col gap-4 rounded-lg border border-ink/10 bg-white p-4 shadow-sm">
                <input type="hidden" name="uv_action" value="create_trip" />
                <label class="flex flex-col gap-1 text-sm">
                    Trip title
                    <input name="title" required class="rounded border border-ink/20 px-3 py-2 bg-white" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Destination
                    <input name="destination" required class="rounded border border-ink/20 px-3 py-2 bg-white" />
                </label>
                <div class="flex gap-4">
                    <label class="flex flex-1 flex-col gap-1 text-sm">
                        Start date
                        <input name="startDate" type="date" required class="rounded border border-ink/20 px-3 py-2 bg-white" />
                    </label>
                    <label class="flex flex-1 flex-col gap-1 text-sm">
                        End date
                        <input name="endDate" type="date" required class="rounded border border-ink/20 px-3 py-2 bg-white" />
                    </label>
                </div>
                <button type="submit" class="rounded-full bg-ink px-4 py-2 text-white hover:opacity-90">
                    Create trip
                </button>
            </form>
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
        <h1 class="mb-1 text-2xl font-semibold text-ink"><?php echo esc_html( $trip['title'] ); ?></h1>
        <p class="mb-8 text-sm text-ink/60">
            <?php echo esc_html( $trip['destination'] ); ?> &middot; <?php echo esc_html( $fmt( $trip['startDate'] ) ); ?> – <?php echo esc_html( $fmt( $trip['endDate'] ) ); ?>
        </p>

        <section class="mb-10">
            <h2 class="mb-3 text-lg font-semibold text-ink">Key dates &amp; deadlines</h2>
            <form method="post" action="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>" class="mb-4 flex flex-col gap-2 rounded-lg border border-ink/10 bg-white p-3 shadow-sm sm:flex-row sm:items-end">
                <input type="hidden" name="uv_action" value="add_deadline" />
                <input type="hidden" name="tripId" value="<?php echo esc_attr( $trip['id'] ); ?>" />
                <input name="label" placeholder="Label" required class="flex-1 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <select name="category" class="rounded border border-ink/20 px-2 py-1 text-sm bg-white">
                    <?php foreach ( uv_deadline_categories() as $c ) : ?>
                        <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input name="dueDate" type="date" required class="rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <input name="notes" placeholder="Notes (optional)" class="flex-1 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <button type="submit" class="rounded-full bg-ink px-3 py-1 text-sm text-white hover:opacity-90">Add</button>
            </form>
            <ul class="flex flex-col gap-2">
                <?php foreach ( $deadlines as $d ) : ?>
                    <li class="flex items-center justify-between rounded-lg border border-ink/10 bg-white px-3 py-2 text-sm shadow-sm">
                        <span><?php echo esc_html( $d['label'] . ' — ' . $fmt( $d['dueDate'] ) . ' (' . $d['category'] . ')' . ( ! empty( $d['notes'] ) ? ' · ' . $d['notes'] : '' ) ); ?></span>
                        <form method="post" action="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>">
                            <input type="hidden" name="uv_action" value="delete_logistics_item" />
                            <input type="hidden" name="kind" value="deadline" />
                            <input type="hidden" name="id" value="<?php echo esc_attr( $d['id'] ); ?>" />
                            <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="mb-10">
            <h2 class="mb-3 text-lg font-semibold text-ink">Itinerary</h2>
            <form method="post" action="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>" class="mb-4 flex flex-col gap-2 rounded-lg border border-ink/10 bg-white p-3 shadow-sm sm:flex-row sm:items-end">
                <input type="hidden" name="uv_action" value="add_itinerary_item" />
                <input type="hidden" name="tripId" value="<?php echo esc_attr( $trip['id'] ); ?>" />
                <input name="title" placeholder="Title" required class="flex-1 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <input name="date" type="date" required class="rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <input name="time" placeholder="Time" class="w-24 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <input name="location" placeholder="Location" class="flex-1 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <input name="notes" placeholder="Notes" class="flex-1 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <button type="submit" class="rounded-full bg-ink px-3 py-1 text-sm text-white hover:opacity-90">Add</button>
            </form>
            <ul class="flex flex-col gap-2">
                <?php foreach ( $itinerary as $item ) : ?>
                    <li class="flex items-center justify-between rounded-lg border border-ink/10 bg-white px-3 py-2 text-sm shadow-sm">
                        <span>
                            <?php echo esc_html( $fmt( $item['date'] ) . ( ! empty( $item['time'] ) ? ' · ' . $item['time'] : '' ) . ' — ' . $item['title'] . ( ! empty( $item['location'] ) ? ' @ ' . $item['location'] : '' ) ); ?>
                        </span>
                        <form method="post" action="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>">
                            <input type="hidden" name="uv_action" value="delete_logistics_item" />
                            <input type="hidden" name="kind" value="itinerary" />
                            <input type="hidden" name="id" value="<?php echo esc_attr( $item['id'] ); ?>" />
                            <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-ink">Practice schedule</h2>
            <form method="post" action="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>" class="mb-4 flex flex-col gap-2 rounded-lg border border-ink/10 bg-white p-3 shadow-sm sm:flex-row sm:items-end">
                <input type="hidden" name="uv_action" value="add_practice_session" />
                <input type="hidden" name="tripId" value="<?php echo esc_attr( $trip['id'] ); ?>" />
                <input name="date" type="date" required class="rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <input name="time" placeholder="Time" required class="w-24 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <input name="location" placeholder="Location" required class="flex-1 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <input name="notes" placeholder="Notes" class="flex-1 rounded border border-ink/20 px-2 py-1 text-sm bg-white" />
                <button type="submit" class="rounded-full bg-ink px-3 py-1 text-sm text-white hover:opacity-90">Add</button>
            </form>
            <ul class="flex flex-col gap-2">
                <?php foreach ( $practices as $s ) : ?>
                    <li class="flex items-center justify-between rounded-lg border border-ink/10 bg-white px-3 py-2 text-sm shadow-sm">
                        <span><?php echo esc_html( $fmt( $s['date'] ) . ' · ' . $s['time'] . ' — ' . $s['location'] . ( ! empty( $s['notes'] ) ? ' · ' . $s['notes'] : '' ) ); ?></span>
                        <form method="post" action="<?php echo esc_url( home_url( '/admin/logistics' ) ); ?>">
                            <input type="hidden" name="uv_action" value="delete_logistics_item" />
                            <input type="hidden" name="kind" value="practice" />
                            <input type="hidden" name="id" value="<?php echo esc_attr( $s['id'] ); ?>" />
                            <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /admin/links view matching src/app/admin/links/page.tsx.
 */
function uv_view_admin_links(): string {
    global $wpdb;
    $links = $wpdb->get_results( "SELECT * FROM " . uv_table( 'external_links' ) . " ORDER BY sortOrder ASC", ARRAY_A ) ?: array();

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10 w-full">
        <h1 class="mb-6 text-2xl font-semibold text-ink">External links</h1>

        <form method="post" action="<?php echo esc_url( home_url( '/admin/links' ) ); ?>" class="mb-8 flex flex-col gap-3 rounded-lg border border-ink/10 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
            <input type="hidden" name="uv_action" value="create_link" />
            <label class="flex flex-1 flex-col gap-1 text-sm">
                Title
                <input name="title" required class="rounded border border-ink/20 px-3 py-2 bg-white" />
            </label>
            <label class="flex flex-1 flex-col gap-1 text-sm">
                URL
                <input name="url" type="url" required class="rounded border border-ink/20 px-3 py-2 bg-white" />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                Category
                <select name="category" class="rounded border border-ink/20 px-3 py-2 bg-white">
                    <?php foreach ( uv_link_categories() as $c ) : ?>
                        <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">
                Add
            </button>
        </form>

        <ul class="flex flex-col gap-2">
            <?php foreach ( $links as $link ) : ?>
                <li class="flex items-center justify-between rounded-lg border border-ink/10 bg-white px-4 py-3 shadow-sm">
                    <div>
                        <p class="font-medium text-ink"><?php echo esc_html( $link['title'] ); ?></p>
                        <p class="text-xs text-ink/50">
                            <?php echo esc_html( $link['category'] ); ?> &middot; <?php echo esc_html( $link['url'] ); ?>
                        </p>
                    </div>
                    <form method="post" action="<?php echo esc_url( home_url( '/admin/links' ) ); ?>">
                        <input type="hidden" name="uv_action" value="delete_link" />
                        <input type="hidden" name="id" value="<?php echo esc_attr( $link['id'] ); ?>" />
                        <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if ( empty( $links ) ) : ?>
                <p class="text-ink/50">No links yet.</p>
            <?php endif; ?>
        </ul>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /admin/about view matching src/app/admin/about/page.tsx, AboutSectionEditor.tsx, AddAboutSectionForm.tsx, AboutMediaForm.tsx.
 */
function uv_view_admin_about(): string {
    $blocks = uv_get_ordered_about_blocks();

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10 w-full">
        <h1 class="mb-6 text-2xl font-semibold text-ink">About page</h1>

        <section class="mb-10">
            <h2 class="mb-3 text-lg font-semibold text-ink">Content</h2>
            <p class="mb-3 text-sm text-ink/60">
                Sections and media share one order — use &uarr;/&darr; to interleave a video or recording between paragraphs, or reorder text.
            </p>
            <div class="flex flex-col gap-3" id="uv-about-blocks-list">
                <?php if ( empty( $blocks ) ) : ?>
                    <p class="text-ink/50">No content yet.</p>
                <?php else : ?>
                    <?php foreach ( $blocks as $block ) : ?>
                        <?php if ( $block['kind'] === 'section' ) : ?>
                            <div
                                data-testid="about-section-<?php echo esc_attr( $block['id'] ); ?>"
                                data-section-id="<?php echo esc_attr( $block['id'] ); ?>"
                                data-initial-title="<?php echo esc_attr( (string) ( $block['title'] ?? '' ) ); ?>"
                                data-initial-body="<?php echo esc_attr( (string) $block['body'] ); ?>"
                                class="uv-about-section-card rounded-lg border border-ink/10 bg-white p-4"
                            >
                                <div class="uv-about-section-display">
                                    <?php if ( ! empty( $block['title'] ) ) : ?>
                                        <h3 class="font-semibold text-ink"><?php echo esc_html( $block['title'] ); ?></h3>
                                    <?php endif; ?>
                                    <p class="mt-1 whitespace-pre-line text-sm text-ink/70"><?php echo esc_html( $block['body'] ); ?></p>
                                    <div class="mt-3 flex items-center gap-3">
                                        <button type="button" class="uv-about-sec-edit-btn text-sm text-ink underline hover:no-underline">
                                            Edit
                                        </button>
                                        <button type="button" class="uv-about-sec-delete-btn text-sm text-red-600 hover:underline">
                                            Delete
                                        </button>
                                        <span class="ml-auto flex gap-1">
                                            <button type="button" aria-label="Move up" data-dir="up" class="uv-about-move-btn rounded border border-ink/20 px-2 py-0.5 text-sm text-ink hover:bg-ink/5">&uarr;</button>
                                            <button type="button" aria-label="Move down" data-dir="down" class="uv-about-move-btn rounded border border-ink/20 px-2 py-0.5 text-sm text-ink hover:bg-ink/5">&darr;</button>
                                        </span>
                                    </div>
                                </div>
                                <div class="uv-about-section-editor hidden flex-col gap-3"></div>
                            </div>
                        <?php else : ?>
                            <div
                                data-testid="about-media-<?php echo esc_attr( $block['id'] ); ?>"
                                data-media-id="<?php echo esc_attr( $block['id'] ); ?>"
                                class="uv-about-media-card flex items-center justify-between rounded-lg border border-ink/10 bg-white px-4 py-3 shadow-sm"
                            >
                                <div>
                                    <p class="font-medium text-ink"><?php echo esc_html( $block['label'] ); ?></p>
                                    <p class="text-xs text-ink/50"><?php echo esc_html( $block['mediaKind'] ); ?></p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="uv-about-media-remove-btn text-sm text-red-600 hover:underline">
                                        Remove
                                    </button>
                                    <span class="flex gap-1">
                                        <button type="button" aria-label="Move up" data-dir="up" class="uv-about-media-move-btn rounded border border-ink/20 px-2 py-0.5 text-sm text-ink hover:bg-ink/5">&uarr;</button>
                                        <button type="button" aria-label="Move down" data-dir="down" class="uv-about-media-move-btn rounded border border-ink/20 px-2 py-0.5 text-sm text-ink hover:bg-ink/5">&darr;</button>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Add Section Form -->
            <form id="uv-add-about-section-form" class="mt-4 flex flex-col gap-3 rounded-lg border border-ink/10 bg-white p-4">
                <h3 class="font-semibold text-ink">Add Section</h3>
                <label class="flex flex-col gap-1 text-sm">
                    Title (optional)
                    <input type="text" name="title" class="rounded border border-ink/20 px-3 py-2 bg-white" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Body <span class="text-red-600">*</span>
                    <textarea name="body" required rows="4" class="rounded border border-ink/20 px-3 py-2 bg-white"></textarea>
                    <span class="text-xs text-ink/50">Blank lines start a new paragraph. A pasted URL auto-links.</span>
                </label>
                <div class="uv-link-insert-container">
                    <button type="button" class="uv-open-link-insert self-start text-xs text-ink underline hover:no-underline">+ Insert link</button>
                </div>
                <p id="uv-add-about-sec-error" class="hidden text-sm text-red-600"></p>
                <button type="submit" class="self-start rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90 disabled:opacity-60">
                    Add Section
                </button>
            </form>
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-ink">Add Media</h2>
            <form id="uv-add-about-media-form" class="flex flex-col gap-3 rounded-lg border border-ink/10 bg-white p-4">
                <h3 class="font-semibold text-ink">Add Media</h3>

                <div class="flex gap-4 border-b border-ink/10 text-sm">
                    <button type="button" data-mode="paste" class="uv-about-media-tab border-b-2 border-ink px-1 pb-2 font-medium text-ink">
                        Paste URL
                    </button>
                    <button type="button" data-mode="upload" class="uv-about-media-tab px-1 pb-2 text-ink/50 hover:text-ink">
                        Upload file
                    </button>
                </div>

                <div id="uv-about-media-paste-wrap">
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

                <div id="uv-about-media-upload-wrap" class="hidden">
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
                        placeholder="e.g. Featured Video, Choir intro recording"
                        class="rounded border border-ink/20 px-3 py-2 bg-white"
                    />
                </label>

                <p id="uv-add-about-media-error" class="hidden text-sm text-red-600"></p>

                <button
                    type="submit"
                    id="uv-add-about-media-submit"
                    class="flex items-center gap-2 self-start rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90 disabled:opacity-60"
                >
                    Add Media
                </button>
            </form>
        </section>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /admin/activity view matching src/app/admin/activity/page.tsx.
 */
function uv_view_admin_activity(): string {
    global $wpdb;
    $raw_ps    = isset( $_GET['pageSize'] ) ? sanitize_text_field( wp_unslash( $_GET['pageSize'] ) ) : '10';
    $page_size = ( $raw_ps === 'all' ) ? 'all' : ( in_array( (int) $raw_ps, array( 10, 25, 50, 100 ), true ) ? (int) $raw_ps : 10 );
    $page      = max( 1, isset( $_GET['page'] ) ? (int) $_GET['page'] : 1 );
    $view_cap  = 1000;

    $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . uv_table( 'activity_logs' ) );
    if ( $page_size === 'all' ) {
        $entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . uv_table( 'activity_logs' ) . " ORDER BY createdAt DESC, id DESC LIMIT %d", $view_cap ), ARRAY_A ) ?: array();
    } else {
        $offset  = ( $page - 1 ) * $page_size;
        $entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . uv_table( 'activity_logs' ) . " ORDER BY createdAt DESC, id DESC LIMIT %d OFFSET %d", $page_size, $offset ), ARRAY_A ) ?: array();
    }

    $labels      = uv_activity_action_labels();
    $total_pages = ( $page_size === 'all' ) ? 1 : max( 1, (int) ceil( $total_items / $page_size ) );
    $curr_page   = min( $page, $total_pages );

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10 w-full">
        <h1 class="mb-2 text-2xl font-semibold text-ink">Activity</h1>
        <p class="mb-6 text-sm text-ink/60">
            Logins, song/member/logistics changes, and their request context (IP, browser, OS).
            Timestamps shown in East Africa Time.
            <?php if ( $page_size === 'all' && $total_items > $view_cap ) : ?>
                Showing the most recent <?php echo (int) $view_cap; ?> of <?php echo (int) $total_items; ?> events.
            <?php endif; ?>
        </p>

        <ul class="flex flex-col gap-2">
            <?php foreach ( $entries as $entry ) : ?>
                <?php
                $ua_info      = uv_parse_user_agent( $entry['userAgent'] ?? null );
                $action_label = $labels[ $entry['action'] ] ?? $entry['action'];
                ?>
                <li class="rounded-lg border border-ink/10 bg-white p-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-ink"><?php echo esc_html( $action_label ); ?></span>
                        <span class="text-ink/50"><?php echo esc_html( uv_format_eat( $entry['createdAt'] ) ); ?></span>
                    </div>
                    <p class="text-ink/70"><?php echo esc_html( $entry['userLabel'] ); ?></p>
                    <?php if ( ! empty( $entry['entityType'] ) ) : ?>
                        <p class="text-ink/50">
                            <?php echo esc_html( $entry['entityType'] . ( ! empty( $entry['entityLabel'] ) ? ': ' . $entry['entityLabel'] : '' ) ); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ( ! empty( $entry['detail'] ) ) : ?>
                        <p class="text-ink/50"><?php echo esc_html( $entry['detail'] ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $entry['ipAddress'] ) || ! empty( $entry['userAgent'] ) ) : ?>
                        <p class="mt-1 text-xs text-ink/40">
                            <?php echo esc_html( ( $entry['ipAddress'] ?: 'unknown IP' ) . ' — ' . $ua_info['browser'] . ' on ' . $ua_info['os'] ); ?>
                        </p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if ( empty( $entries ) ) : ?>
                <p class="text-ink/50">No activity recorded yet.</p>
            <?php endif; ?>
        </ul>

        <?php if ( $total_items > 0 ) : ?>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-ink/60">
                <label class="flex items-center gap-2">
                    Show
                    <select
                        id="uv-activity-pagesize-select"
                        class="rounded border border-ink/20 px-2 py-1 text-sm text-ink bg-white"
                    >
                        <?php foreach ( array( 10, 25, 50, 100, 'all' ) as $opt ) : ?>
                            <option value="<?php echo esc_attr( (string) $opt ); ?>" <?php selected( (string) $page_size, (string) $opt ); ?>>
                                <?php echo $opt === 'all' ? 'View all' : esc_html( (string) $opt ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    per page
                </label>

                <?php if ( $page_size !== 'all' && $total_pages > 1 ) : ?>
                    <div class="flex items-center gap-2">
                        <a
                            href="<?php echo esc_url( add_query_arg( array( 'page' => max( 1, $curr_page - 1 ), 'pageSize' => $page_size ), home_url( '/admin/activity' ) ) ); ?>"
                            class="rounded border border-ink/20 px-2 py-1 text-ink <?php echo $curr_page <= 1 ? 'pointer-events-none disabled:opacity-40' : ''; ?>"
                        >
                            Prev
                        </a>
                        <span>Page <?php echo (int) $curr_page; ?> of <?php echo (int) $total_pages; ?></span>
                        <a
                            href="<?php echo esc_url( add_query_arg( array( 'page' => min( $total_pages, $curr_page + 1 ), 'pageSize' => $page_size ), home_url( '/admin/activity' ) ) ); ?>"
                            class="rounded border border-ink/20 px-2 py-1 text-ink <?php echo $curr_page >= $total_pages ? 'pointer-events-none disabled:opacity-40' : ''; ?>"
                        >
                            Next
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /admin/storage view matching src/app/admin/storage/page.tsx & StorageFileList.tsx.
 */
function uv_view_admin_storage(): string {
    global $wpdb;
    $audio_usage = uv_get_storage_usage( 'audio' );
    $sheet_usage = uv_get_storage_usage( 'sheet-music' );
    $video_usage = uv_get_storage_usage( 'video' );
    $photo_usage = uv_get_storage_usage( 'profile-photos' );

    // Map URLs to owner metadata
    $media_rows = $wpdb->get_results(
        "SELECT m.mediaUrl, m.label, s.part, sg.title AS songTitle
         FROM " . uv_table( 'song_media' ) . " m
         INNER JOIN " . uv_table( 'song_sections' ) . " s ON m.sectionId = s.id
         INNER JOIN " . uv_table( 'songs' ) . " sg ON s.songId = sg.id",
        ARRAY_A
    ) ?: array();
    $media_by_url = array();
    foreach ( $media_rows as $mr ) {
        $media_by_url[ $mr['mediaUrl'] ] = $mr;
    }

    $sheet_rows = $wpdb->get_results(
        "SELECT sm.fileUrl, sm.label, sg.title AS songTitle
         FROM " . uv_table( 'song_sheet_music' ) . " sm
         INNER JOIN " . uv_table( 'songs' ) . " sg ON sm.songId = sg.id",
        ARRAY_A
    ) ?: array();
    $sheet_by_url = array();
    foreach ( $sheet_rows as $sr ) {
        $sheet_by_url[ $sr['fileUrl'] ] = $sr;
    }

    $about_rows = $wpdb->get_results( "SELECT mediaUrl, label FROM " . uv_table( 'about_media' ), ARRAY_A ) ?: array();
    $about_by_url = array();
    foreach ( $about_rows as $ar ) {
        $about_by_url[ $ar['mediaUrl'] ] = $ar;
    }

    $user_rows = $wpdb->get_results( "SELECT photoUrl, name FROM " . uv_table( 'users' ) . " WHERE photoUrl IS NOT NULL", ARRAY_A ) ?: array();
    $user_by_url = array();
    foreach ( $user_rows as $ur ) {
        $user_by_url[ $ur['photoUrl'] ] = $ur;
    }

    $files = array();
    foreach ( $audio_usage['files'] as $f ) {
        $owner       = $media_by_url[ $f['url'] ] ?? null;
        $about_owner = $about_by_url[ $f['url'] ] ?? null;
        $files[]     = array(
            'url'       => $f['url'],
            'bytes'     => $f['bytes'],
            'kind'      => 'Audio',
            'name'      => $owner['label'] ?? ( $about_owner['label'] ?? $f['path'] ),
            'songTitle' => $owner['songTitle'] ?? ( $about_owner ? 'About page' : 'Not attached to any song' ),
            'part'      => $owner['part'] ?? null,
        );
    }
    foreach ( $sheet_usage['files'] as $f ) {
        $owner   = $sheet_by_url[ $f['url'] ] ?? null;
        $files[] = array(
            'url'       => $f['url'],
            'bytes'     => $f['bytes'],
            'kind'      => 'Sheet Music',
            'name'      => $owner['label'] ?? $f['path'],
            'songTitle' => $owner['songTitle'] ?? 'Not attached to any song',
            'part'      => null,
        );
    }
    foreach ( $video_usage['files'] as $f ) {
        $owner       = $media_by_url[ $f['url'] ] ?? null;
        $about_owner = $about_by_url[ $f['url'] ] ?? null;
        $files[]     = array(
            'url'       => $f['url'],
            'bytes'     => $f['bytes'],
            'kind'      => 'Video',
            'name'      => $owner['label'] ?? ( $about_owner['label'] ?? $f['path'] ),
            'songTitle' => $owner['songTitle'] ?? ( $about_owner ? 'About page' : 'Not attached to any song' ),
            'part'      => $owner['part'] ?? null,
        );
    }
    foreach ( $photo_usage['files'] as $f ) {
        $owner   = $user_by_url[ $f['url'] ] ?? null;
        $files[] = array(
            'url'       => $f['url'],
            'bytes'     => $f['bytes'],
            'kind'      => 'Profile Photo',
            'name'      => $owner['name'] ?? $f['path'],
            'songTitle' => $owner['name'] ?? 'Not attached to any user',
            'part'      => null,
        );
    }

    $total_bytes = $audio_usage['totalBytes'] + $sheet_usage['totalBytes'] + $video_usage['totalBytes'] + $photo_usage['totalBytes'];
    $file_count  = $audio_usage['fileCount'] + $sheet_usage['fileCount'] + $video_usage['fileCount'] + $photo_usage['fileCount'];
    $pct_used    = min( 100, (int) round( ( $total_bytes / UV_STORAGE_BUDGET_BYTES ) * 100 ) );
    $fmt_mb      = function ( int $b ): string {
        return number_format( $b / ( 1024 * 1024 ), 1 ) . 'MB';
    };

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10 w-full">
        <h1 class="mb-2 text-2xl font-semibold text-ink">Storage</h1>
        <p class="mb-6 text-sm text-ink/60">
            Direct audio, video, and sheet-music uploads only — pasted links (YouTube, Drive, SoundCloud) don&rsquo;t count against this budget.
        </p>

        <div class="rounded-lg border border-ink/10 bg-white p-4">
            <div class="mb-2 flex items-baseline justify-between">
                <span class="text-sm font-medium text-ink">
                    <?php echo esc_html( $fmt_mb( $total_bytes ) . ' of ' . $fmt_mb( UV_STORAGE_BUDGET_BYTES ) . ' used' ); ?>
                </span>
                <span class="text-xs text-ink/50"><?php echo (int) $file_count; ?> file<?php echo $file_count === 1 ? '' : 's'; ?></span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-ink/10">
                <div
                    style="width: <?php echo (int) $pct_used; ?>%;"
                    class="h-full rounded-full <?php echo $pct_used >= 90 ? 'bg-red-600' : ( $pct_used >= 70 ? 'bg-amber-500' : 'bg-ink' ); ?>"
                ></div>
            </div>
        </div>

        <div class="mt-6" id="uv-storage-files-root" data-files="<?php echo esc_attr( wp_json_encode( $files ) ); ?>">
            <?php if ( empty( $files ) ) : ?>
                <p class="text-sm text-ink/50">No uploaded files yet.</p>
            <?php else : ?>
                <ul class="flex flex-col gap-2">
                    <?php foreach ( $files as $f ) : ?>
                        <li class="flex items-center justify-between rounded border border-ink/10 bg-white px-3 py-2 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-ink"><?php echo esc_html( $f['name'] ); ?></p>
                                <p class="truncate text-xs text-ink/50">
                                    <?php echo esc_html( $f['songTitle'] . ( ! empty( $f['part'] ) ? ' — ' . $f['part'] : '' ) ); ?>
                                </p>
                            </div>
                            <div class="ml-3 flex shrink-0 items-center gap-3 text-xs text-ink/60">
                                <span class="rounded bg-ink/10 px-1.5 py-0.5 text-ink/70"><?php echo esc_html( $f['kind'] ); ?></span>
                                <span><?php echo esc_html( $fmt_mb( (int) $f['bytes'] ) ); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <p class="mt-6 text-xs text-ink/40">
            App-level per-file caps: 20MB audio, 20MB video, 10MB sheet music, 5MB profile photo.
        </p>
    </div>
    <?php
    return (string) ob_get_clean();
}
