# Developer Onboarding (`uv-wp`)

## 1. Overview
`uv-wp` powers **https://www.umojavoices.co.ke/** using a WordPress theme (`wp-content/themes/uv-wp`) and companion plugin (`wp-content/plugins/uv-wp-content`). It provides 1:1 visual and functional parity with the Umoja Voices choir portal while running natively on PHP + MySQL (Angryhosting in production, SQLite in local preview and CI).

## 2. Data Privacy Rule
Never commit real choir member names, email addresses, or phone numbers to git, issues, or PRs. Only the project owner (`Eric Gitonga` / `gitonga@gmail.com`) and the two fictitious E2E seed accounts (`Demo Chorister` / `demo.chorister@example.com` and `E2E Profile Test` / `e2e.profile.test@example.com`) may appear in tracked files.

## 3. Local Development Workflow
1. Start local preview:
   ```bash
   ./local-preview/setup.sh
   ```
2. Reset local database to clean seed state:
   ```bash
   ./local-preview/setup.sh --reset
   ```
3. Run unit & E2E tests before opening a PR:
   ```bash
   php tests/run.php
   BASE_URL=http://localhost:8891 conda run -n ds python e2e/run.py
   ```
