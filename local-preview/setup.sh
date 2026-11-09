#!/usr/bin/env bash
# Zero-MySQL local preview for the Umoja Voices WordPress site.
# Uses PHP + SQLite via the official WordPress SQLite Database Integration plugin.
#
# Usage:
#   ./local-preview/setup.sh          # bootstrap (first run) and start server on :8891
#   ./local-preview/setup.sh --reset  # wipe SQLite DB and re-seed from scratch

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME_DIR="$PROJECT_DIR/.wp-runtime"
PORT="${PORT:-8891}"
SITE_URL="http://localhost:${PORT}"
WP_CLI="$RUNTIME_DIR/wp-cli.phar"
SHARED_RUNTIME="/home/gitonga/Develop/projects/mlt-wp/.wp-runtime"

if [[ "${1:-}" == "--reset" ]]; then
  echo "Resetting SQLite database..."
  rm -rf "$RUNTIME_DIR/wp/wp-content/database"
fi

mkdir -p "$RUNTIME_DIR"

# 1. Reuse cached WordPress core + WP-CLI + SQLite plugin from mlt-wp if available
if [[ ! -f "$WP_CLI" ]]; then
  if [[ -f "$SHARED_RUNTIME/wp-cli.phar" ]]; then
    cp "$SHARED_RUNTIME/wp-cli.phar" "$WP_CLI"
    chmod +x "$WP_CLI"
  else
    echo "Downloading WP-CLI..."
    curl -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$WP_CLI"
    chmod +x "$WP_CLI"
  fi
fi

if [[ ! -f "$RUNTIME_DIR/wp/wp-load.php" ]]; then
  mkdir -p "$RUNTIME_DIR/wp"
  if [[ -f "$SHARED_RUNTIME/wp-load.php" ]]; then
    echo "Copying cached WordPress core from mlt-wp..."
    cp -a "$SHARED_RUNTIME/." "$RUNTIME_DIR/wp/"
    rm -f "$RUNTIME_DIR/wp/wp-config.php" "$RUNTIME_DIR/wp/router.php" "$RUNTIME_DIR/wp/wp-cli.phar"
    rm -rf "$RUNTIME_DIR/wp/wp-content/database"
    rm -rf "$RUNTIME_DIR/wp/wp-content/uploads"
    rm -rf "$RUNTIME_DIR/wp/wp-content/themes/mlt-wp"
    rm -rf "$RUNTIME_DIR/wp/wp-content/plugins/mlt-wp-content"
  else
    echo "Downloading WordPress core..."
    php "$WP_CLI" core download --path="$RUNTIME_DIR/wp" --skip-content
  fi
fi

# 2. Install SQLite integration plugin if not present
SQLITE_PLUGIN_DIR="$RUNTIME_DIR/wp/wp-content/plugins/sqlite-database-integration"
if [[ ! -d "$SQLITE_PLUGIN_DIR" ]]; then
  echo "Downloading SQLite Database Integration plugin..."
  mkdir -p "$RUNTIME_DIR/wp/wp-content/plugins"
  curl -sL https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip -o "$RUNTIME_DIR/sqlite.zip"
  python3 -m zipfile -e "$RUNTIME_DIR/sqlite.zip" "$RUNTIME_DIR/wp/wp-content/plugins/"
  rm -f "$RUNTIME_DIR/sqlite.zip"
fi

# 3. Symlink our theme and plugin into the runtime wp-content
mkdir -p "$RUNTIME_DIR/wp/wp-content/themes" "$RUNTIME_DIR/wp/wp-content/plugins" "$RUNTIME_DIR/wp/wp-content/uploads"
ln -sfn "$PROJECT_DIR/wp-content/themes/uv-wp" "$RUNTIME_DIR/wp/wp-content/themes/uv-wp"
ln -sfn "$PROJECT_DIR/wp-content/plugins/uv-wp-content" "$RUNTIME_DIR/wp/wp-content/plugins/uv-wp-content"

# 4. Configure wp-config.php and SQLite db.php drop-in
if [[ ! -f "$RUNTIME_DIR/wp/wp-config.php" ]]; then
  cat > "$RUNTIME_DIR/wp/wp-config.php" <<PHP
<?php
define( 'DB_NAME', 'uv_wp_local' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'uv-local-preview-key-1' );
define( 'SECURE_AUTH_KEY',  'uv-local-preview-key-2' );
define( 'LOGGED_IN_KEY',    'uv-local-preview-key-3' );
define( 'NONCE_KEY',        'uv-local-preview-key-4' );
define( 'AUTH_SALT',        'uv-local-preview-salt-1' );
define( 'SECURE_AUTH_SALT', 'uv-local-preview-salt-2' );
define( 'LOGGED_IN_SALT',   'uv-local-preview-salt-3' );
define( 'NONCE_SALT',       'uv-local-preview-salt-4' );

\$table_prefix = 'wp_';
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_HOME', '${SITE_URL}' );
define( 'WP_SITEURL', '${SITE_URL}' );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP
fi

# Wire up SQLite drop-in
cp "$SQLITE_PLUGIN_DIR/db.copy" "$RUNTIME_DIR/wp/wp-content/db.php"
sed -i "s|{SQLITE_IMPLEMENTATION_FOLDER_PATH}|$SQLITE_PLUGIN_DIR|g" "$RUNTIME_DIR/wp/wp-content/db.php"
sed -i "s|{SQLITE_PLUGIN}|sqlite-database-integration/load.php|g" "$RUNTIME_DIR/wp/wp-content/db.php"

# 5. Install WordPress, activate plugin + theme, and run UV setup + demo seed
if ! php "$WP_CLI" core is-installed --path="$RUNTIME_DIR/wp" 2>/dev/null; then
  echo "Installing WordPress (SQLite) and seeding Umoja Voices content..."
  php "$WP_CLI" core install \
    --path="$RUNTIME_DIR/wp" \
    --url="$SITE_URL" \
    --title="Umoja Voices" \
    --admin_user="admin" \
    --admin_password="admin" \
    --admin_email="gitonga@gmail.com" \
    --skip-email

  php "$WP_CLI" option update home "$SITE_URL" --path="$RUNTIME_DIR/wp" >/dev/null
  php "$WP_CLI" option update siteurl "$SITE_URL" --path="$RUNTIME_DIR/wp" >/dev/null
  php "$WP_CLI" plugin activate uv-wp-content --path="$RUNTIME_DIR/wp"
  php "$WP_CLI" theme activate uv-wp --path="$RUNTIME_DIR/wp"
  php "$WP_CLI" rewrite structure '/%postname%/' --path="$RUNTIME_DIR/wp"
  php "$WP_CLI" eval "uv_wp_run_setup(true);" --path="$RUNTIME_DIR/wp"
else
  php "$WP_CLI" option update home "$SITE_URL" --path="$RUNTIME_DIR/wp" >/dev/null
  php "$WP_CLI" option update siteurl "$SITE_URL" --path="$RUNTIME_DIR/wp" >/dev/null
  php "$WP_CLI" eval "uv_wp_run_setup(false);" --path="$RUNTIME_DIR/wp" >/dev/null
fi

echo ""
echo "=================================================================="
echo "  Umoja Voices WordPress Local Preview"
echo "  Site URL:   ${SITE_URL}"
echo "  WP Admin:   ${SITE_URL}/wp-admin/  (admin / admin)"
echo "  UV Login:   ${SITE_URL}/login      (gitonga@gmail.com / admin12345)"
echo "=================================================================="
echo ""

exec php \
  -d upload_max_filesize=25M \
  -d post_max_size=25M \
  -d memory_limit=256M \
  -S "localhost:${PORT}" \
  -t "$RUNTIME_DIR/wp" \
  "$PROJECT_DIR/local-preview/router.php"
