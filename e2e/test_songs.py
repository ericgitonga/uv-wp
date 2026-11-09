"""Golden path: song library -> song detail -> media page.

Exercises the seeded demo song (prisma/seed.ts's "Rising Together") rather
than mutating data, since this suite may run against a shared Preview
database (#52) that other work also relies on being in a known state.
"""

from _common import SEED_SONG_TITLE, admin_page


def test_song_library_lists_seed_song():
    with admin_page() as page:
        page.goto("/songs")
        assert page.get_by_text(SEED_SONG_TITLE).count() > 0


def test_song_detail_and_media_page_load():
    with admin_page() as page:
        page.goto("/songs")
        page.get_by_text(SEED_SONG_TITLE).first.click()
        page.wait_for_url("**/songs/**", timeout=10_000)

        page.get_by_role("link", name="Media").click()
        page.wait_for_url("**/songs/**/media", timeout=10_000)
        # Seed data gives every voice part (S/A/T/B/All) one media item each.
        assert page.get_by_text("Soprano").count() > 0
        assert page.get_by_text("Tenor").count() > 0


TESTS = [
    test_song_library_lists_seed_song,
    test_song_detail_and_media_page_load,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
