<?php
/**
 * Fallback template for Umoja Voices theme.
 * All routes are handled via template_redirect in functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_safe_redirect( home_url( '/songs' ) );
exit;
