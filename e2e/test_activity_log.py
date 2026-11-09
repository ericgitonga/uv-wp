"""Golden path: activity logging (#50).

A narrow proof-of-concept slice — login events plus a handful of high-value
admin mutations, not a full audit trail (see #49). Each spec cleans up after
itself (deletes the test song it created, resets the chorister's role back)
since this suite runs against a shared Preview database.
"""

from _common import admin_page


def test_login_is_logged():
    # Uses the shared admin_page() session rather than a dedicated fresh
    # login — the assertion holds regardless of whether this happens to be
    # the suite's first admin_page() call (a real login, logged) or a later
    # one (a cache hit, but some earlier real login was still logged).
    # Keeping this off a dedicated login() call matters: the login server
    # action is rate-limited (5/15min per email, #20), and test_auth.py
    # already spends one of those 5 on a real, from-scratch admin login to
    # verify the login flow itself.
    with admin_page() as page:
        page.goto("/admin/activity")
        assert page.get_by_text("Logged in").count() > 0


def test_song_create_and_delete_are_logged():
    with admin_page() as page:
        page.goto("/admin/songs/new")
        page.get_by_label("Title").fill("E2E Activity Log Test Song")
        page.get_by_role("button", name="Create song").click()
        # Create song now goes straight back to the list (#80), not an
        # intermediate edit page.
        page.wait_for_url("**/songs", timeout=10_000)

        try:
            page.goto("/admin/activity")
            assert page.get_by_text("Created a song").count() > 0
            assert page.get_by_text("E2E Activity Log Test Song").count() > 0
        finally:
            page.goto("/songs")
            page.get_by_text("E2E Activity Log Test Song").first.click()
            page.wait_for_url("**/songs/**", timeout=10_000)
            page.on("dialog", lambda d: d.accept())
            page.get_by_role("button", name="Delete").click()
            page.wait_for_url("**/songs", timeout=10_000)

        page.goto("/admin/activity")
        assert page.get_by_text("Deleted a song").count() > 0


def test_member_role_change_is_logged():
    with admin_page() as page:
        page.goto("/admin/members")
        row = page.locator("li", has_text="Demo Chorister")
        try:
            row.locator("select").select_option("admin")
            page.wait_for_timeout(1000)

            page.goto("/admin/activity")
            assert page.get_by_text("Changed a member's role").count() > 0
        finally:
            page.goto("/admin/members")
            page.locator("li", has_text="Demo Chorister").locator("select").select_option("chorister")
            page.wait_for_timeout(1000)


TESTS = [
    test_login_is_logged,
    test_song_create_and_delete_are_logged,
    test_member_role_change_is_logged,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
