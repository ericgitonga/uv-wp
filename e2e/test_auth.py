"""Golden path: login + role gating.

Covers: admin login reaches /songs; chorister login reaches /songs but is
redirected away from an admin-only route; an unauthenticated visit to a
protected route redirects to /login.
"""

from _common import (
    SEED_ADMIN_EMAIL,
    SEED_ADMIN_PASSWORD,
    SEED_CHORISTER_EMAIL,
    SEED_CHORISTER_PASSWORD,
    browser_page,
    login,
)


def test_unauthenticated_redirects_to_login():
    with browser_page() as page:
        page.goto("/songs")
        page.wait_for_url("**/login**", timeout=10_000)
        assert "/login" in page.url


def test_admin_login_reaches_songs_and_admin_route():
    with browser_page() as page:
        login(page, SEED_ADMIN_EMAIL, SEED_ADMIN_PASSWORD)
        assert "/songs" in page.url

        page.goto("/admin/songs/new")
        assert "/admin/songs/new" in page.url, "admin should not be bounced from an admin-only route"


def test_chorister_login_reaches_songs_but_not_admin_route():
    with browser_page() as page:
        login(page, SEED_CHORISTER_EMAIL, SEED_CHORISTER_PASSWORD)
        assert "/songs" in page.url

        page.goto("/admin/songs/new")
        assert "/admin/songs/new" not in page.url, "chorister must be blocked from an admin-only route"


TESTS = [
    test_unauthenticated_redirects_to_login,
    test_admin_login_reaches_songs_and_admin_route,
    test_chorister_login_reaches_songs_but_not_admin_route,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
