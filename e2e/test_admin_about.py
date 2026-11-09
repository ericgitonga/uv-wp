"""Golden path: admin-configurable About page (#59).

Replaces the old singleton featured-video upload with full section (text)
and media CRUD. Each spec cleans up after itself (deletes what it created)
since this suite runs against a shared Preview database, matching
test_activity_log.py's own convention.

Rows are located via a stable `data-testid` (not visible text) since a
section's title/body move from text content into form-field values once its
edit-toggle is active — `has_text` locators would silently stop matching
mid-test. `.last` targets the just-created row, since new sections/media
are always appended (highest sortOrder). "Add Section" is a plain
`<form action={...}>` (a real page transition, like Links'/Logistics' own
add forms), so it's verified the same way test_audit_history.py verifies
those: click, a short fixed wait, then a fresh `page.goto()` rather than
polling the in-flight page directly.
"""

from _common import admin_page

MARKER_TITLE = "E2E About Section Test"
MARKER_BODY = "This is a temporary section created by the e2e suite."
MARKER_BODY_EDITED = "This section body has been edited by the e2e suite."
MARKER_MEDIA_LABEL = "E2E About Media Test"
MARKER_MEDIA_URL = "https://www.youtube.com/watch?v=dQw4w9WgXcQ"


def test_admin_can_add_edit_and_delete_a_section():
    with admin_page() as page:
        page.goto("/admin/about")
        page.get_by_label("Title (optional)").fill(MARKER_TITLE)
        page.get_by_label("Body").fill(MARKER_BODY)
        page.get_by_role("button", name="Add Section").click()
        page.wait_for_timeout(1500)
        page.goto("/admin/about")

        row = page.locator('[data-testid^="about-section-"]').last
        try:
            assert page.get_by_text(MARKER_TITLE).count() > 0
            page.goto("/about")
            assert page.get_by_text(MARKER_TITLE).count() > 0
            assert page.get_by_text(MARKER_BODY).count() > 0

            page.goto("/admin/about")
            row = page.locator('[data-testid^="about-section-"]').last
            row.get_by_role("button", name="Edit").click()
            row.get_by_label("Body").fill(MARKER_BODY_EDITED)
            row.get_by_role("button", name="Save").click()
            page.wait_for_timeout(1500)

            page.goto("/about")
            assert page.get_by_text(MARKER_BODY_EDITED).count() > 0
        finally:
            page.goto("/admin/about")
            row = page.locator('[data-testid^="about-section-"]').last
            page.on("dialog", lambda d: d.accept())
            # router.refresh() re-renders the list (removing this row) while
            # Playwright's own post-click stability check is still running
            # against it — a real race, not a failed click; the deletion
            # itself already happened by the time this throws. Verified
            # below via a fresh page load rather than trusting click().
            try:
                row.get_by_role("button", name="Delete").click(no_wait_after=True)
            except Exception:
                pass
            page.wait_for_timeout(1500)

        page.goto("/about")
        assert page.get_by_text(MARKER_TITLE).count() == 0


def test_admin_can_add_and_remove_pasted_media():
    with admin_page() as page:
        page.goto("/admin/about")
        page.get_by_placeholder("YouTube, Spotify, Google Drive, or direct URL").fill(MARKER_MEDIA_URL)
        page.get_by_label("Label").fill(MARKER_MEDIA_LABEL)
        page.get_by_role("button", name="Add Media").click()
        page.wait_for_timeout(1500)
        page.goto("/admin/about")

        try:
            assert page.get_by_text(MARKER_MEDIA_LABEL).count() > 0
            page.goto("/about")
            # A YouTube media item renders as an iframe embed, not visible
            # text — confirm the embed itself is present instead.
            assert page.locator("iframe[src*='youtube.com/embed']").count() > 0
        finally:
            page.goto("/admin/about")
            row = page.locator('[data-testid^="about-media-"]').last
            page.on("dialog", lambda d: d.accept())
            # Same router.refresh()-vs-click() race as the section Delete
            # button above — verified via a fresh check below.
            try:
                row.get_by_role("button", name="Remove").click(no_wait_after=True)
            except Exception:
                pass
            page.wait_for_timeout(1500)

        page.goto("/admin/about")
        assert page.get_by_text(MARKER_MEDIA_LABEL).count() == 0


def test_admin_can_reorder_section_and_media():
    # #72: sections and media share one order, so a media item can be moved
    # above a text section (or vice versa) rather than always rendering
    # after every section.
    section_title = "E2E Reorder Section Test"
    media_label = "E2E Reorder Media Test"

    with admin_page() as page:
        page.goto("/admin/about")
        page.get_by_label("Title (optional)").fill(section_title)
        page.get_by_label("Body").fill("Reorder test body.")
        page.get_by_role("button", name="Add Section").click()
        page.wait_for_timeout(1500)
        page.goto("/admin/about")

        page.get_by_placeholder("YouTube, Spotify, Google Drive, or direct URL").fill(MARKER_MEDIA_URL)
        page.get_by_label("Label").fill(media_label)
        page.get_by_role("button", name="Add Media").click()
        page.wait_for_timeout(1500)
        page.goto("/admin/about")

        try:
            # New items append to the end, so the media row's own "Move up"
            # button (not the section's) moves it above the section.
            media_row = page.locator('[data-testid^="about-media-"]').last
            media_row.get_by_role("button", name="Move up").click(no_wait_after=True)
            page.wait_for_timeout(1500)

            page.goto("/about")
            html = page.content()
            assert html.index("youtube.com/embed") < html.index(section_title), (
                "Expected the media item to render before the section after moving it up"
            )
        finally:
            page.goto("/admin/about")
            section_row = page.locator('[data-testid^="about-section-"]', has_text=section_title)
            page.on("dialog", lambda d: d.accept())
            try:
                section_row.get_by_role("button", name="Delete").click(no_wait_after=True)
            except Exception:
                pass
            page.wait_for_timeout(1500)
            page.goto("/admin/about")
            media_row = page.locator('[data-testid^="about-media-"]', has_text=media_label)
            try:
                media_row.get_by_role("button", name="Remove").click(no_wait_after=True)
            except Exception:
                pass
            page.wait_for_timeout(1500)

        page.goto("/about")
        assert page.get_by_text(section_title).count() == 0
        page.goto("/admin/about")
        assert page.get_by_text(media_label).count() == 0


TESTS = [
    test_admin_can_add_edit_and_delete_a_section,
    test_admin_can_add_and_remove_pasted_media,
    test_admin_can_reorder_section_and_media,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
