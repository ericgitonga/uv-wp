# Changelog

All notable changes to the **Umoja Voices (`uv-wp`)** WordPress theme and companion plugin are documented in this file.

## [0.7.0] - 2026-11-09

### Added
- Provisioned WordPress on Angryhosting (`https://www.umojavoices.co.ke/`) backed by MySQL (`egimba_uv` on `angry-sd-1`), configured `.htaccess` rewrite rules and `.user.ini` 25 MB upload limits, set GitHub Actions FTPS deployment secrets (`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`), uploaded all 51 SATB audio (`.mp3`), video (`.mp4`), and sheet-music (`.pdf`) files to `/wp-content/uploads/uv-storage/`, and built `uv_seed_live_umoja_repertoire()` (`wp-content/plugins/uv-wp-content/includes/schema-and-seed.php`) seeding all 12 real Umoja Voices songs, SATB sections, lyrics, the 2026 Portugal & Germany Cultural Exchange Tour logistics, About sections/videos, and External Links (`#7`).

## [0.6.0] - 2026-11-09

### Added
- Ported the complete Playwright E2E test suite (`e2e/`, 10 modules / 24 golden-path tests) and PHP unit test suite (`tests/run.php`, 17 assertions) verifying 100% green across authentication, public pages, songs, media playback (`Play All` + `Loop` + single-active-audio pause), large video upload, profile editing, About page administration, activity logging, audit history, and REST/route security (`#6`).

## [0.5.0] - 2026-11-09

### Added
- Built `/admin/*` management screens (`wp-content/themes/uv-wp/views/admin-views.php`, `assets/js/app.js`) and native `wp-admin` dashboard integration (`wp-content/plugins/uv-wp-content/includes/wp-admin-pages.php`):
  - `/admin/songs/new`, `/admin/songs/<id>/edit`, and `/songs/<id>/lyrics/edit` (including paste-and-parse `ReplaceLyricsEditor` and `LinkInsertField`).
  - `/admin/members` (invite link generator, password reset link generator, role/status toggles, delete with last-admin protection).
  - `/admin/logistics`, `/admin/links`, `/admin/about`, `/admin/activity`, and `/admin/storage`.
  - Top-level **Umoja Voices** menu in `wp-admin` and **Tools → UV Setup & Seed** (`#5`).

## [0.4.0] - 2026-11-09

### Added
- Built `uv-wp` custom WordPress theme (`wp-content/themes/uv-wp/`) for public and member views:
  - Self-contained utility stylesheet (`style.css`) with the Umoja Voices brand palette (`#f2ede3` cream, `#211005` ink, `#bf7a15` gold, `#625d25` olive, `#a73f13` rust).
  - Custom router (`functions.php`) serving `/about`, `/links`, `/login`, `/forgot-password`, `/auth/confirm`, `/accept-invite`, `/reset-password`, `/songs`, `/songs/<id>`, `/songs/<id>/media`, `/songs/<id>/sheet-music`, `/songs/<id>/lyrics`, `/logistics`, `/profile`, `/manifest.webmanifest`, and `/sw.js` (`#4`).

## [0.3.0] - 2026-11-09

### Added
- Implemented authentication, single-use invite/recovery confirmation flows, RBAC guards, rate limiting, and 7-layer anti-spam protection (`wp-content/plugins/uv-wp-content/includes/auth-and-actions.php`):
  - HMAC-SHA256 signed HTTPOnly `uv_session` and `uv_confirm_session` cookies.
  - Constant-time `/forgot-password` padding (1400ms floor) to prevent timing-based account enumeration.
  - Unauthenticated REST `/wp-json/*` 401 guard (`#3`).

## [0.2.0] - 2026-11-09

### Added
- Built `uv-wp-content` companion plugin database schema (`wp_uv_*` 16 relational tables compatible with MySQL and SQLite), idempotent demo seed (`uv_seed_demo_data()`), local upload buckets (`wp-content/uploads/uv-storage/{audio,video,sheet-music,profile-photos}`), magic-byte audio header verification, EAT timestamp formatter, and activity logger (`#2`).

## [0.1.0] - 2026-11-09

### Added
- Initial repository scaffold (`#1`):
  - `VERSION`, `CHANGELOG.md`, `README.md`, `ONBOARDING.md`, and `.gitignore`.
  - Zero-MySQL local preview environment (`local-preview/setup.sh` and `local-preview/router.php`) running on `http://localhost:8891` with WordPress + SQLite Database Integration and 25 MB upload limits.
  - GitHub Actions CI/CD workflows: `.github/workflows/php-lint.yml`, `.github/workflows/e2e.yml`, and `.github/workflows/deploy-ftp.yml`.
