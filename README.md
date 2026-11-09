# Umoja Voices — WordPress Theme & Companion Plugin (`uv-wp`)

Production WordPress implementation of **Umoja Voices** (`https://www.umojavoices.co.ke/`), an all-women choir in Nairobi, Kenya singing in four-part harmony (Soprano, Alto, Tenor, Bass) to raise funds for [White Ribbon Alliance Kenya](https://whiteribbonalliancekenya.org/).

Converted from the original Next.js + Prisma + Supabase application into a self-contained WordPress theme (`wp-content/themes/uv-wp`) and companion plugin (`wp-content/plugins/uv-wp-content`) hosted on Angryhosting (`https://www.umojavoices.co.ke/`).

---

## Architecture

| Directory | Purpose |
| :--- | :--- |
| `wp-content/themes/uv-wp/` | Custom WordPress theme rendering all 22 Umoja Voices front-end routes, SATB media player (`Play All` + `Loop`), sheet music PDF blob viewer, lyrics filter/editor, logistics, profile, and `/admin/*` screens. |
| `wp-content/plugins/uv-wp-content/` | Companion WordPress plugin managing relational tables (`wp_uv_*`), authentication/sessions/invites, upload buckets (`wp-content/uploads/uv-storage/`), magic-byte audio verification, rate limiting, 7-layer anti-spam, activity logging, demo seeding, and native `wp-admin` integration. |
| `local-preview/` | Zero-MySQL local preview (`setup.sh` + `router.php`) powered by WordPress + SQLite on `http://localhost:8891`. |
| `e2e/` | Python Playwright E2E test suite verifying authentication, RBAC, public pages, song library & media playback, profile editing & photo uploads, About page CRUD & reordering, activity log, audit history, and >10 MB video uploads. |
| `tests/` | PHP unit test suite (`tests/run.php`) covering lyrics parsing/serialization, EAT formatting, User-Agent parsing, media URL detection, and rate limiting. |

---

## Quick Start (Local Preview)

```bash
./local-preview/setup.sh
```

- **Public Site**: `http://localhost:8891/about`
- **Choir Portal Sign-In**: `http://localhost:8891/login`
  - Seed Admin: `gitonga@gmail.com` / `admin12345`
  - Seed Chorister: `demo.chorister@example.com` / `chorister12345`
- **WordPress Admin**: `http://localhost:8891/wp-admin/` (`admin` / `admin`)

---

## Running Tests

```bash
# 1. PHP syntax lint
find wp-content local-preview tests -name '*.php' -print0 | xargs -0 -n1 php -l

# 2. PHP unit tests
php tests/run.php

# 3. Playwright E2E suite (with local preview running on :8891)
BASE_URL=http://localhost:8891 conda run -n ds python e2e/run.py
```
