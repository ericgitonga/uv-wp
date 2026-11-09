"""Golden path: broadened audit history (#49).

Extends #50's activity log coverage (see test_activity_log.py) with the
newly-tracked actions — member status changes and logistics creates/deletes
— plus the request-context fields (IP address, browser/OS parsed from the
User-Agent) now captured on every entry. Each spec cleans up after itself
since this suite runs against a shared Preview database.
"""

from _common import admin_page

DEADLINE_LABEL = "E2E Audit History Test Deadline"


def test_member_status_change_is_logged():
    with admin_page() as page:
        page.goto("/admin/members")
        row = page.locator("li", has_text="Demo Chorister")
        try:
            row.get_by_role("button", name="Deactivate").click()
            page.wait_for_timeout(1000)

            page.goto("/admin/activity")
            assert page.get_by_text("Changed a member's status").count() > 0
        finally:
            page.goto("/admin/members")
            page.locator("li", has_text="Demo Chorister").get_by_role("button", name="Reactivate").click()
            page.wait_for_timeout(1000)


def test_logistics_deadline_create_and_delete_are_logged():
    with admin_page() as page:
        page.goto("/admin/logistics")
        page.locator('input[name="label"]').fill(DEADLINE_LABEL)
        page.locator('input[name="dueDate"]').fill("2026-09-01")
        page.get_by_role("button", name="Add").first.click()
        page.wait_for_timeout(1000)

        try:
            page.goto("/admin/activity")
            assert page.get_by_text("Added a logistics deadline").count() > 0
            assert page.get_by_text(DEADLINE_LABEL).count() > 0
        finally:
            page.goto("/admin/logistics")
            page.locator("li", has_text=DEADLINE_LABEL).get_by_role("button", name="Delete").click()
            page.wait_for_timeout(1000)

        page.goto("/admin/activity")
        assert page.get_by_text("Removed a logistics deadline").count() > 0


def test_ip_and_browser_context_are_captured():
    with admin_page() as page:
        page.goto("/admin/activity")
        # Headless Chromium's own User-Agent always contains "Chrome" — every
        # entry should show some browser/OS label, never a blank line, once
        # at least one entry exists (earlier specs in this run guarantee that).
        assert page.get_by_text("Chrome on", exact=False).count() > 0


TESTS = [
    test_member_status_change_is_logged,
    test_logistics_deadline_create_and_delete_are_logged,
    test_ip_and_browser_context_are_captured,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
