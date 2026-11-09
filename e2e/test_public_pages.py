"""Golden path: /about and /links are genuinely public (#43).

Complements test_auth.test_unauthenticated_redirects_to_login, which covers
the opposite case (a protected route still gates correctly).
"""

from _common import browser_page


def test_about_page_loads_without_login():
    # No longer asserts specific copy (#59 made the page's text sections
    # admin-editable, not hardcoded) — test_admin_about.py covers the actual
    # content CRUD flow instead.
    with browser_page() as page:
        page.goto("/about")
        assert "/login" not in page.url


def test_links_page_loads_without_login():
    with browser_page() as page:
        page.goto("/links")
        assert "/login" not in page.url


def test_public_nav_shows_about_links_and_sign_in():
    with browser_page() as page:
        page.goto("/about")
        assert page.get_by_role("link", name="Sign in").count() > 0
        assert page.get_by_role("link", name="Links").count() > 0


TESTS = [
    test_about_page_loads_without_login,
    test_links_page_loads_without_login,
    test_public_nav_shows_about_links_and_sign_in,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
