"""Shared helpers for the Playwright E2E smoke suite.

Written against the Python `playwright` package (already present in the `ds`
conda env, browsers pre-cached) rather than `@playwright/test`, per project
convention of using `ds` for Python tooling instead of adding a parallel npm
dev-dependency. Run each spec directly, or all of them via `run.py`:

    conda run -n ds python e2e/run.py
    conda run -n ds python e2e/test_auth.py

BASE_URL defaults to local dev; CI overrides it to point at a server booted
against the Preview/Development Supabase project (see #52) — never
production. SEED_* creds match prisma/seed.ts exactly; if that file's demo
data changes, update these too.

Login is rate-limited (5 attempts per email per 15min, #20) — a real
production control, not something to raise just for tests. Specs that need
an authenticated session but aren't testing the login flow itself should use
admin_page()/chorister_page() (a cached, reused session shared across the
whole suite) rather than a fresh login() call each time. Running the full
suite twice in quick succession can still trip the limit (each run costs one
real admin login for test_auth.py's own login-flow test, plus whichever spec
happens to be the first admin_page() consumer) — if that happens locally,
either wait ~15 minutes or delete the relevant `RateLimitBucket` row
directly (not exposed as a helper here deliberately, to avoid the suite
routinely resetting a real security control on itself).
"""

import os
from contextlib import contextmanager

from playwright.sync_api import sync_playwright

BASE_URL = os.environ.get("BASE_URL", "http://localhost:8891").rstrip("/")

SEED_ADMIN_EMAIL = "gitonga@gmail.com"
SEED_ADMIN_PASSWORD = "admin12345"
SEED_CHORISTER_EMAIL = "demo.chorister@example.com"
SEED_CHORISTER_PASSWORD = "chorister12345"
SEED_SONG_TITLE = "Rising Together (demo song)"

# Dedicated to test_profile.py (#73) -- see prisma/seed.ts's comment on this
# account for why it must never be shared with SEED_ADMIN, which the app
# owner also uses for their own manual Preview testing.
SEED_E2E_PROFILE_TEST_EMAIL = "e2e.profile.test@example.com"
SEED_E2E_PROFILE_TEST_PASSWORD = "e2eprofiletest12345"


@contextmanager
def browser_page():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        try:
            page = browser.new_page(base_url=BASE_URL)
            yield page
        finally:
            browser.close()


def login(page, email: str, password: str) -> None:
    page.goto("/login")
    page.get_by_label("Email").fill(email)
    page.get_by_label("Password").fill(password)
    page.get_by_role("button", name="Sign in").click()
    # The client redirects on success; a failed login re-renders /login with
    # an error message and no navigation, so waiting for the URL to change
    # away from /login is a reliable, framework-agnostic success signal.
    page.wait_for_url(lambda url: "/login" not in url, timeout=10_000)


# The login server action is rate-limited (5 per email+IP per 15min, #20) —
# a real, deliberate security control, not something to raise just to suit
# tests. A naive "every test logs in fresh via the UI" pattern trips this
# limit on its own once the suite has more than ~5 admin-requiring specs, so
# any spec that isn't specifically testing the login flow itself should
# reuse a cached authenticated session instead of calling login() again.
# Cached at module scope: shared by every test_*.py file within one
# `run.py` process (or one `python e2e/test_x.py` process), so the whole
# suite only ever does one real admin login and one real chorister login
# via this path, regardless of how many specs need to be authenticated.
_storage_state_cache: dict[str, dict] = {}


@contextmanager
def authenticated_page(email: str, password: str):
    cache_key = email
    with sync_playwright() as p:
        browser = p.chromium.launch()
        try:
            if cache_key not in _storage_state_cache:
                setup_page = browser.new_page(base_url=BASE_URL)
                login(setup_page, email, password)
                _storage_state_cache[cache_key] = setup_page.context.storage_state()
                setup_page.close()

            context = browser.new_context(base_url=BASE_URL, storage_state=_storage_state_cache[cache_key])
            yield context.new_page()
        finally:
            browser.close()


def admin_page():
    return authenticated_page(SEED_ADMIN_EMAIL, SEED_ADMIN_PASSWORD)


def chorister_page():
    return authenticated_page(SEED_CHORISTER_EMAIL, SEED_CHORISTER_PASSWORD)


def profile_test_page():
    return authenticated_page(SEED_E2E_PROFILE_TEST_EMAIL, SEED_E2E_PROFILE_TEST_PASSWORD)
