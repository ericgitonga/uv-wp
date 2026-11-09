<?php
/**
 * Shared UI components: Nav, Breadcrumb, MediaEmbed, Expandable.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the sticky top Navigation bar matching src/components/Nav.tsx.
 */
function uv_render_nav( ?array $user, string $pathname, string $logo_url ): string {
    $is_active = function ( string $href ) use ( $pathname ): bool {
        return $pathname === $href || strpos( $pathname, $href . '/' ) === 0;
    };

    $link_class = function ( string $href ) use ( $is_active ): string {
        return $is_active( $href ) ? 'font-semibold text-gold' : 'text-ink hover:text-gold';
    };

    ob_start();
    if ( ! $user ) :
        ?>
        <header class="sticky top-0 z-20 border-b border-ink/10 bg-cream">
            <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 text-sm">
                <a href="<?php echo esc_url( home_url( '/about' ) ); ?>" class="flex items-center">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="Umoja Voices" class="h-12 w-auto" />
                </a>
                <div class="hidden items-center gap-6 lg:flex">
                    <a href="<?php echo esc_url( home_url( '/about' ) ); ?>" class="<?php echo esc_attr( $link_class( '/about' ) ); ?>">About</a>
                    <a href="<?php echo esc_url( home_url( '/links' ) ); ?>" class="<?php echo esc_attr( $link_class( '/links' ) ); ?>">Links</a>
                    <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="rounded-full bg-ink px-3 py-1 text-white hover:opacity-90">Sign in</a>
                </div>
                <button type="button" id="uv-mobile-menu-toggle" aria-label="Toggle menu" class="rounded p-2 text-lg leading-none text-ink hover:bg-ink/5 lg:hidden">&#9776;</button>
            </nav>
            <div id="uv-mobile-drawer" class="hidden border-t border-ink/10 bg-cream px-4 py-3 text-sm lg:hidden">
                <div class="flex flex-col gap-3">
                    <a href="<?php echo esc_url( home_url( '/about' ) ); ?>" class="<?php echo esc_attr( $link_class( '/about' ) ); ?>">About</a>
                    <a href="<?php echo esc_url( home_url( '/links' ) ); ?>" class="<?php echo esc_attr( $link_class( '/links' ) ); ?>">Links</a>
                    <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="mt-2 self-start rounded-full bg-ink px-3 py-1 text-white hover:opacity-90">Sign in</a>
                </div>
            </div>
        </header>
        <?php
        return (string) ob_get_clean();
    endif;

    $is_admin = ( $user['role'] === 'admin' );
    $in_admin = $is_active( '/admin/members' ) || $is_active( '/admin/storage' ) || $is_active( '/admin/activity' );
    ?>
    <header class="sticky top-0 z-20 border-b border-ink/10 bg-cream">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 text-sm">
            <a href="<?php echo esc_url( home_url( '/songs' ) ); ?>" class="flex items-center">
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="Umoja Voices" class="h-12 w-auto" />
            </a>

            <div class="hidden items-center gap-6 lg:flex">
                <a href="<?php echo esc_url( home_url( '/about' ) ); ?>" class="<?php echo esc_attr( $link_class( '/about' ) ); ?>">About</a>
                <a href="<?php echo esc_url( home_url( '/songs' ) ); ?>" class="<?php echo esc_attr( $link_class( '/songs' ) ); ?>">Songs</a>
                <a href="<?php echo esc_url( home_url( '/logistics' ) ); ?>" class="<?php echo esc_attr( $link_class( '/logistics' ) ); ?>">Logistics</a>
                <a href="<?php echo esc_url( home_url( '/links' ) ); ?>" class="<?php echo esc_attr( $link_class( '/links' ) ); ?>">Links</a>
                <?php if ( $is_admin ) : ?>
                    <div class="relative" id="uv-admin-dropdown-wrap">
                        <button type="button" id="uv-admin-dropdown-btn" class="<?php echo $in_admin ? 'font-semibold text-gold' : 'text-olive hover:text-gold'; ?>">
                            Admin &#9662;
                        </button>
                        <div id="uv-admin-dropdown-menu" class="hidden absolute right-0 z-30 mt-2 flex w-44 flex-col rounded-lg border border-ink/10 bg-white py-1 shadow-lg">
                            <a href="<?php echo esc_url( home_url( '/admin/members' ) ); ?>" class="px-3 py-1.5 <?php echo $is_active( '/admin/members' ) ? 'font-semibold text-gold' : 'text-ink hover:bg-ink/5'; ?>">Members</a>
                            <a href="<?php echo esc_url( home_url( '/admin/storage' ) ); ?>" class="px-3 py-1.5 <?php echo $is_active( '/admin/storage' ) ? 'font-semibold text-gold' : 'text-ink hover:bg-ink/5'; ?>">Storage</a>
                            <a href="<?php echo esc_url( home_url( '/admin/activity' ) ); ?>" class="px-3 py-1.5 <?php echo $is_active( '/admin/activity' ) ? 'font-semibold text-gold' : 'text-ink hover:bg-ink/5'; ?>">Activity</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="hidden items-center gap-3 lg:flex">
                <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" class="text-ink/70 hover:text-gold hover:underline">
                    <?php echo esc_html( $user['name'] ); ?>
                </a>
                <form method="post" action="<?php echo esc_url( home_url( '/login' ) ); ?>">
                    <input type="hidden" name="uv_action" value="logout" />
                    <button type="submit" class="rounded border border-ink/20 px-2.5 py-1 text-ink hover:bg-ink hover:text-white">
                        Sign out
                    </button>
                </form>
            </div>

            <button type="button" id="uv-mobile-menu-toggle" aria-label="Toggle menu" class="rounded p-2 text-lg leading-none text-ink hover:bg-ink/5 lg:hidden">&#9776;</button>
        </nav>

        <div id="uv-mobile-drawer" class="hidden border-t border-ink/10 bg-cream px-4 py-3 text-sm lg:hidden">
            <div class="flex flex-col gap-3">
                <a href="<?php echo esc_url( home_url( '/about' ) ); ?>" class="<?php echo esc_attr( $link_class( '/about' ) ); ?>">About</a>
                <a href="<?php echo esc_url( home_url( '/songs' ) ); ?>" class="<?php echo esc_attr( $link_class( '/songs' ) ); ?>">Songs</a>
                <a href="<?php echo esc_url( home_url( '/logistics' ) ); ?>" class="<?php echo esc_attr( $link_class( '/logistics' ) ); ?>">Logistics</a>
                <a href="<?php echo esc_url( home_url( '/links' ) ); ?>" class="<?php echo esc_attr( $link_class( '/links' ) ); ?>">Links</a>
                <?php if ( $is_admin ) : ?>
                    <span class="mt-2 text-xs font-semibold uppercase tracking-wide text-olive">Admin</span>
                    <a href="<?php echo esc_url( home_url( '/admin/members' ) ); ?>" class="<?php echo esc_attr( $link_class( '/admin/members' ) ); ?>">Members</a>
                    <a href="<?php echo esc_url( home_url( '/admin/storage' ) ); ?>" class="<?php echo esc_attr( $link_class( '/admin/storage' ) ); ?>">Storage</a>
                    <a href="<?php echo esc_url( home_url( '/admin/activity' ) ); ?>" class="<?php echo esc_attr( $link_class( '/admin/activity' ) ); ?>">Activity</a>
                <?php endif; ?>
                <div class="mt-2 flex items-center justify-between border-t border-ink/10 pt-3">
                    <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" class="text-ink/70 hover:text-gold hover:underline">
                        <?php echo esc_html( $user['name'] ); ?>
                    </a>
                    <form method="post" action="<?php echo esc_url( home_url( '/login' ) ); ?>">
                        <input type="hidden" name="uv_action" value="logout" />
                        <button type="submit" class="rounded border border-ink/20 px-2.5 py-1 text-ink hover:bg-ink hover:text-white">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    <?php
    return (string) ob_get_clean();
}

/**
 * Render Breadcrumb navigation matching src/components/Breadcrumb.tsx.
 */
function uv_render_breadcrumb( array $items ): string {
    ob_start();
    ?>
    <nav aria-label="Breadcrumb" class="mb-4 flex flex-wrap items-center gap-1.5 text-sm text-ink/60">
        <?php foreach ( $items as $idx => $item ) : ?>
            <?php if ( $idx > 0 ) : ?>
                <span class="text-ink/30">/</span>
            <?php endif; ?>
            <?php if ( ! empty( $item['href'] ) ) : ?>
                <a href="<?php echo esc_url( home_url( $item['href'] ) ); ?>" class="hover:text-ink hover:underline">
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            <?php else : ?>
                <span class="text-ink"><?php echo esc_html( $item['label'] ); ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
    return (string) ob_get_clean();
}

/**
 * Render MediaEmbed matching src/components/MediaEmbed.tsx.
 */
function uv_render_media_embed( string $url, string $kind, string $title ): string {
    ob_start();
    if ( $kind === 'audio' ) :
        ?>
        <audio controls preload="none" src="<?php echo esc_url( $url ); ?>" class="w-full uv-playable-media" data-media-kind="audio">
            Your browser does not support audio playback.
        </audio>
        <?php
    elseif ( $kind === 'video' ) :
        ?>
        <video controls preload="none" src="<?php echo esc_url( $url ); ?>" class="aspect-video w-full rounded bg-black uv-playable-media" data-media-kind="video">
            Your browser does not support video playback.
        </video>
        <?php
    elseif ( in_array( $kind, array( 'youtube', 'drive', 'soundcloud' ), true ) ) :
        $embed_url = uv_get_embed_url( $url, $kind );
        if ( $embed_url ) :
            ?>
            <iframe
                src="<?php echo esc_url( $embed_url ); ?>"
                title="<?php echo esc_attr( $title ); ?>"
                class="<?php echo $kind === 'soundcloud' ? 'h-24 w-full rounded border-0' : 'aspect-video w-full rounded border-0'; ?>"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
            <?php
        else :
            ?>
            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="text-sm text-gold underline hover:no-underline">
                Open <?php echo esc_html( $title ); ?> &rarr;
            </a>
            <?php
        endif;
    else :
        ?>
        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="text-sm text-gold underline hover:no-underline">
            Open <?php echo esc_html( $title ); ?> &rarr;
        </a>
        <?php
    endif;
    return (string) ob_get_clean();
}

/**
 * Render Expandable ("See more" / "See less") wrapper matching src/components/Expandable.tsx.
 */
function uv_render_expandable( string $inner_html, int $collapsed_height = 160 ): string {
    ob_start();
    ?>
    <div class="uv-expandable" data-collapsed-height="<?php echo (int) $collapsed_height; ?>">
        <div class="uv-expandable-content relative">
            <?php echo $inner_html; ?>
            <div class="uv-expandable-fade hidden pointer-events-none absolute inset-x-0 bottom-0 h-10 bg-gradient-to-t"></div>
        </div>
        <button type="button" class="uv-expandable-btn hidden mt-2 text-sm font-medium text-ink hover:underline">
            See more
        </button>
    </div>
    <?php
    return (string) ob_get_clean();
}
