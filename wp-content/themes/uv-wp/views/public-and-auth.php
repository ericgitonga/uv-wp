<?php
/**
 * Public & Authentication views:
 * - /about
 * - /links
 * - /login
 * - /forgot-password
 * - /auth/confirm
 * - /accept-invite
 * - /reset-password
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * /about view matching src/app/about/page.tsx.
 */
function uv_view_about(): string {
    $user     = uv_get_current_user();
    $is_admin = $user && $user['role'] === 'admin';
    $blocks   = uv_get_ordered_about_blocks();

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10">
        <?php if ( $is_admin ) : ?>
            <div class="mb-4 flex justify-end">
                <a href="<?php echo esc_url( home_url( '/admin/about' ) ); ?>" class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">
                    Edit About
                </a>
            </div>
        <?php endif; ?>

        <div class="mb-6 flex justify-center">
            <img src="<?php echo esc_url( home_url( '/logo-full.png' ) ); ?>" alt="Umoja Voices" class="h-auto w-64" style="max-width:260px;" />
        </div>

        <div class="flex flex-col gap-6 leading-relaxed text-ink/80">
            <?php foreach ( $blocks as $block ) : ?>
                <?php if ( $block['kind'] === 'section' ) : ?>
                    <section class="flex flex-col gap-2">
                        <?php if ( ! empty( $block['title'] ) ) : ?>
                            <h2 class="text-xl font-semibold text-ink"><?php echo esc_html( $block['title'] ); ?></h2>
                        <?php endif; ?>
                        <?php
                        $section_inner = '<div class="flex flex-col gap-4">' . uv_linkify_text( (string) $block['body'] ) . '</div>';
                        echo uv_render_expandable( $section_inner, 160 );
                        ?>
                    </section>
                <?php else : ?>
                    <div>
                        <?php echo uv_render_media_embed( (string) $block['mediaUrl'], (string) $block['mediaKind'], (string) $block['label'] ); ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /links view matching src/app/links/page.tsx.
 */
function uv_view_links(): string {
    global $wpdb;
    $user     = uv_get_current_user();
    $is_admin = $user && $user['role'] === 'admin';
    $links    = $wpdb->get_results( "SELECT * FROM " . uv_table( 'external_links' ) . " ORDER BY sortOrder ASC", ARRAY_A ) ?: array();

    $by_category = array();
    foreach ( $links as $link ) {
        $cat = $link['category'] ?: 'other';
        $by_category[ $cat ][] = $link;
    }

    ob_start();
    ?>
    <div class="mx-auto max-w-2xl px-4 py-10">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-ink">Links</h1>
            <?php if ( $is_admin ) : ?>
                <a href="<?php echo esc_url( home_url( '/admin/links' ) ); ?>" class="rounded-full bg-ink px-4 py-2 text-sm text-white hover:opacity-90">
                    Manage links
                </a>
            <?php endif; ?>
        </div>

        <?php if ( empty( $links ) ) : ?>
            <p class="text-ink/60">No links published yet.</p>
        <?php endif; ?>

        <?php foreach ( $by_category as $category => $items ) : ?>
            <section class="mb-8">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-ink/50">
                    <?php echo esc_html( $category ); ?>
                </h2>
                <ul class="flex flex-col gap-2">
                    <?php foreach ( $items as $item ) : ?>
                        <li>
                            <a
                                href="<?php echo esc_url( $item['url'] ); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-between rounded-lg border border-ink/10 bg-white px-4 py-3 text-ink shadow-sm hover:border-ink/30"
                            >
                                <span><?php echo esc_html( $item['title'] ); ?></span>
                                <span class="text-xs text-ink/40">&#8599;</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /login view matching src/app/login/page.tsx.
 */
function uv_view_login(): string {
    $err_param = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
    $init_err  = '';
    if ( $err_param === 'invalid-link' ) {
        $init_err = 'That link is invalid or has expired. Ask an admin for a new one.';
    } elseif ( $err_param === 'invalid' ) {
        $init_err = 'Invalid login credentials.';
    } elseif ( $err_param === 'rate-limit' ) {
        $init_err = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    }

    ob_start();
    ?>
    <div class="mx-auto flex max-w-sm flex-1 flex-col justify-center px-4 py-16 w-full">
        <h1 class="mb-2 text-2xl font-semibold text-ink">Umoja Voices</h1>
        <p class="mb-6 text-sm text-ink/60">Sign in to access songs and trip details.</p>

        <form id="uv-login-form" method="post" action="<?php echo esc_url( home_url( '/login' ) ); ?>" class="flex flex-col gap-4">
            <input type="hidden" name="uv_action" value="login" />
            <label class="flex flex-col gap-1 text-sm">
                Email
                <input
                    type="email"
                    name="email"
                    required
                    class="rounded border border-ink/20 px-3 py-2 bg-white"
                />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                Password
                <input
                    type="password"
                    name="password"
                    required
                    class="rounded border border-ink/20 px-3 py-2 bg-white"
                />
            </label>

            <p id="uv-login-error" class="text-sm text-red-600 <?php echo $init_err === '' ? 'hidden' : ''; ?>"><?php echo esc_html( $init_err ); ?></p>

            <button
                type="submit"
                id="uv-login-submit"
                class="rounded-full bg-ink px-4 py-2 text-white hover:opacity-90 disabled:opacity-60"
            >
                Sign in
            </button>
        </form>

        <a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" class="mt-4 text-sm text-ink/60 hover:text-ink hover:underline">
            Forgot password?
        </a>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /forgot-password view matching src/app/forgot-password/page.tsx.
 */
function uv_view_forgot_password(): string {
    $submitted = ! empty( $_GET['sent'] );
    ob_start();
    ?>
    <div class="mx-auto flex max-w-sm flex-1 flex-col justify-center px-4 py-16 w-full">
        <h1 class="mb-2 text-2xl font-semibold text-ink">Forgot password</h1>
        <p class="mb-6 text-sm text-ink/60">
            Enter your email and, if it matches an active account, we&rsquo;ll send a link to reset your password.
        </p>

        <div id="uv-forgot-submitted" class="rounded border border-ink/10 bg-ink/5 p-4 text-sm text-ink/80 <?php echo $submitted ? '' : 'hidden'; ?>">
            <p>If that email matches an account, a reset link is on its way.</p>
        </div>

        <form id="uv-forgot-form" method="post" action="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" class="flex flex-col gap-4 <?php echo $submitted ? 'hidden' : ''; ?>">
            <input type="hidden" name="uv_action" value="forgot_password" />
            <?php echo uv_render_antispam_fields( 'forgot_password' ); ?>
            <label class="flex flex-col gap-1 text-sm">
                Email
                <input
                    type="email"
                    name="email"
                    required
                    class="rounded border border-ink/20 px-3 py-2 bg-white"
                />
            </label>
            <button type="submit" class="rounded-full bg-ink px-4 py-2 text-white hover:opacity-90">
                Send reset link
            </button>
        </form>

        <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="mt-4 text-sm text-ink hover:underline">
            Back to sign in
        </a>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /auth/confirm WhatsApp-safe POST interstitial matching src/app/auth/confirm/page.tsx.
 */
function uv_view_auth_confirm(): string {
    $token_hash = isset( $_GET['token_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['token_hash'] ) ) : '';
    $type       = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
    $next       = isset( $_GET['next'] ) ? sanitize_text_field( wp_unslash( $_GET['next'] ) ) : '/';

    if ( $token_hash === '' || $type === '' ) {
        wp_safe_redirect( home_url( '/login?error=invalid-link' ) );
        exit;
    }

    $copy = array(
        'invite'   => array(
            'heading' => "You've been invited to Umoja Voices",
            'body'    => 'Click Continue to set your password and activate your account.',
        ),
        'recovery' => array(
            'heading' => 'Reset your password',
            'body'    => 'Click Continue to set a new password.',
        ),
    );
    $active_copy = $copy[ $type ] ?? array(
        'heading' => 'Continue',
        'body'    => 'Click Continue to proceed.',
    );

    ob_start();
    ?>
    <div class="mx-auto flex max-w-sm flex-1 flex-col justify-center px-4 py-16 w-full">
        <h1 class="mb-2 text-2xl font-semibold text-ink"><?php echo esc_html( $active_copy['heading'] ); ?></h1>
        <p class="mb-6 text-sm text-ink/60"><?php echo esc_html( $active_copy['body'] ); ?></p>
        <form method="post" action="<?php echo esc_url( home_url( '/auth/confirm' ) ); ?>" class="flex flex-col gap-4">
            <input type="hidden" name="uv_action" value="confirm_auth_link" />
            <input type="hidden" name="token_hash" value="<?php echo esc_attr( $token_hash ); ?>" />
            <input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>" />
            <input type="hidden" name="next" value="<?php echo esc_attr( $next ); ?>" />
            <button type="submit" class="rounded-full bg-ink px-4 py-2 text-white hover:opacity-90">
                Continue
            </button>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /accept-invite view matching src/app/accept-invite/page.tsx.
 */
function uv_view_accept_invite(): string {
    ob_start();
    ?>
    <div class="mx-auto flex max-w-sm flex-1 flex-col justify-center px-4 py-16 w-full">
        <div id="uv-setpw-done" class="hidden">
            <p class="text-ink/80">Account activated — redirecting to sign in…</p>
        </div>
        <div id="uv-setpw-wrap">
            <h1 class="mb-2 text-2xl font-semibold text-ink">Welcome to Umoja Voices</h1>
            <p class="mb-6 text-sm text-ink/60">Set a password to activate your account.</p>
            <form id="uv-setpw-form" class="flex flex-col gap-4">
                <label class="flex flex-col gap-1 text-sm">
                    Password
                    <input
                        type="password"
                        name="password"
                        required
                        minlength="8"
                        class="rounded border border-ink/20 px-3 py-2 bg-white"
                    />
                </label>
                <p id="uv-setpw-error" class="hidden text-sm text-red-600"></p>
                <button type="submit" class="rounded-full bg-ink px-4 py-2 text-white hover:opacity-90">
                    Activate account
                </button>
            </form>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * /reset-password view matching src/app/reset-password/page.tsx.
 */
function uv_view_reset_password(): string {
    ob_start();
    ?>
    <div class="mx-auto flex max-w-sm flex-1 flex-col justify-center px-4 py-16 w-full">
        <div id="uv-setpw-done" class="hidden">
            <p class="text-ink/80">Password updated — redirecting to sign in…</p>
        </div>
        <div id="uv-setpw-wrap">
            <h1 class="mb-6 text-2xl font-semibold text-ink">Set a new password</h1>
            <form id="uv-setpw-form" class="flex flex-col gap-4">
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
                <p id="uv-setpw-error" class="hidden text-sm text-red-600"></p>
                <button type="submit" class="rounded-full bg-ink px-4 py-2 text-white hover:opacity-90">
                    Set password
                </button>
            </form>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
