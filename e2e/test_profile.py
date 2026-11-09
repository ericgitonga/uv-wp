"""Golden path: richer account profile (#73) — flat view by default, an
Edit button reveals the form (bio/voice/instrument/phone + photo), Save
returns to the flat view, Cancel discards unsaved changes.

This test edits a dedicated e2e-only account's own profile (there's no
throwaway target the way other specs create a throwaway song/section) --
so it restores every field back to blank in a `finally` block, matching
this suite's established convention of cleaning up after itself since it
runs against a shared Preview database. That account (SEED_E2E_PROFILE_
TEST_EMAIL, see _common.py/prisma/seed.ts) exists specifically so this
reset-to-blank cleanup can never collide with a human's own manual
Preview testing again -- it wiped the app owner's real profile data (bio,
voice, instrument, phone, and separately the photo) twice while this test
was being built, back when it ran against the shared seed admin account.
Password reset is untouched here (already covered implicitly by not
exercising it) -- #73 explicitly didn't change that flow.

Photo upload uses a real, tiny (2x2px) decodable JPEG, embedded as base64
rather than pulled in via a Pillow dependency CI doesn't otherwise install
-- deliberately a genuine image, not null bytes: profile photos aren't
content-sniffed server-side, so null bytes would pass the same upload
checks, but this suite also asserts the photo's `naturalWidth` after
upload to confirm the browser actually decoded and rendered it, not just
that an <img> tag with the right src attribute exists in the DOM (the
latter alone would have missed a real CSP img-src gap found manually on
the Preview deployment -- see proxy.ts). Written to a real temp file
rather than passed as an in-memory buffer for the same CDP-transfer-
overhead reason documented in test_video_upload.py.
"""

import base64
import os
import tempfile
import time

from _common import profile_test_page

# A genuine 2x2px red JPEG (not null bytes) -- see module docstring. Verified
# to actually decode (PIL round-trip) before being embedded here -- an
# earlier hand-wrapped version of this string had a transcription error that
# silently produced a corrupted-but-HTTP-200-servable file, which cost a
# long CI debugging detour chasing a nonexistent CSP/network bug (see
# git history / issue #73's comments for the full story). Regenerate via:
#   from PIL import Image; import io, base64
#   img = Image.new("RGB", (2, 2), color=(200, 50, 50))
#   buf = io.BytesIO(); img.save(buf, format="JPEG")
#   base64.b64encode(buf.getvalue()).decode()
# and verify round-trip decodability before ever pasting a new value in.
TINY_JPEG_BASE64 = (
    "/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwg"
    "JC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIy"
    "MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAACAAIDASIAAhEBAxEB/8QA"
    "HwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIh"
    "MUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVW"
    "V1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXG"
    "x8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQF"
    "BgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAV"
    "YnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOE"
    "hYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq"
    "8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDnqKKK8k/Qz//Z"
)

MARKER_BIO = "E2E test bio — temporary, restored after this spec runs."
MARKER_INSTRUMENT = "E2E Test Piano"
MARKER_PHONE = "+254700000000"
MARKER_VOICE = "T"


def _enter_edit_mode(page):
    edit_button = page.get_by_role("button", name="Edit")
    if edit_button.count() > 0:
        edit_button.click()


def _fill_and_save(page, bio, voice_value, instrument, phone):
    _enter_edit_mode(page)
    page.get_by_label("Bio").fill(bio)
    page.get_by_label("Voice").select_option(voice_value)
    page.get_by_label("Instrument").fill(instrument)
    page.get_by_label("Phone").fill(phone)
    page.get_by_role("button", name="Save").click()
    page.wait_for_timeout(1000)


def _wait_for_photo(page, console_logs, photo_responses, timeout_s=15, interval_s=0.5):
    """Waits for the <img> tag to appear, then confirms it actually decoded
    and rendered (naturalWidth > 0) rather than just existing in the DOM --
    a blocked-by-CSP or otherwise-failed-to-load image still has the right
    src/data-testid attribute, so checking DOM presence alone would silently
    pass even when nothing is visually showing (see module docstring)."""
    deadline = time.monotonic() + timeout_s
    photo_img = page.locator('[data-testid="profile-photo"]')
    status = page.locator('[data-testid="profile-status"]')
    while time.monotonic() < deadline:
        if photo_img.count() > 0:
            break
        if status.count() > 0:
            text = status.first.inner_text()
            if text and text != "Saved.":
                raise AssertionError(f"Photo upload failed: {text}")
        page.wait_for_timeout(int(interval_s * 1000))
    else:
        raise TimeoutError(f"Photo <img> did not appear within {timeout_s}s")

    natural_width = photo_img.evaluate(
        "el => new Promise(resolve => { "
        "if (el.complete) resolve(el.naturalWidth); "
        "else { el.onload = () => resolve(el.naturalWidth); el.onerror = () => resolve(0); } "
        "})"
    )
    if not natural_width:
        src = photo_img.evaluate("el => el.src")
        # Diagnostic-only direct fetch from the SAME page context (so it's
        # subject to the same CSP as the <img> tag) -- reports the real
        # HTTP status/error rather than leaving CSP as a guess.
        fetch_result = page.evaluate(
            "url => fetch(url).then(r => "
            "`HTTP ${r.status}, content-type=${r.headers.get('content-type')}, "
            "content-length=${r.headers.get('content-length')}, "
            "corp=${r.headers.get('cross-origin-resource-policy')}`"
            ").catch(e => `fetch error: ${e}`)",
            src,
        )
        # Diagnostic-only: force a fresh reload of the SAME <img> element
        # (rather than just re-reading its already-failed state) after a
        # short delay, to check whether this is a one-shot race against the
        # freshly-uploaded file's read-path availability rather than a
        # permanent failure -- a plain re-read of .naturalWidth wouldn't
        # tell them apart, since a browser never auto-retries a failed <img>.
        page.wait_for_timeout(1500)
        retry_width = photo_img.evaluate(
            "el => new Promise(resolve => { "
            "const url = el.src; el.src = ''; "
            "el.onload = () => resolve(el.naturalWidth); el.onerror = () => resolve(0); "
            "el.src = url; "
            "})"
        )
        raise AssertionError(
            "Photo <img> is present in the DOM but never actually rendered "
            f"(naturalWidth is 0). src={src!r}, direct fetch of that URL from "
            f"the page context: {fetch_result}. Retried reloading the same "
            f"<img> 1.5s later: naturalWidth={retry_width}. Real network "
            f"responses seen for this URL (the actual <img> load attempts, "
            f"not a diagnostic fetch): {photo_responses}. ALL console "
            f"messages captured during this test: {console_logs}"
        )


def _ensure_no_photo(page):
    """Self-healing guard against leftover state from a prior run that
    failed before reaching its own cleanup (this suite shares a Preview
    database, so a photo left over from an earlier failed run would
    otherwise break every subsequent run's placeholder assertions, not
    just report a false failure once)."""
    _enter_edit_mode(page)
    remove_button = page.get_by_role("button", name="Remove photo")
    if remove_button.count() > 0:
        remove_button.click()
        page.wait_for_timeout(1500)
    page.get_by_role("button", name="Cancel").click()


def test_profile_view_edit_toggle_fields_and_photo():
    with profile_test_page() as page:
        page.set_default_timeout(8_000)
        console_logs = []
        photo_responses = []
        page.on("console", lambda msg: console_logs.append(msg.text))
        page.on("pageerror", lambda exc: console_logs.append(f"pageerror: {exc}"))
        page.on(
            "response",
            lambda resp: photo_responses.append(
                f"{resp.status} {resp.url} content-type={resp.headers.get('content-type')} "
                f"corp={resp.headers.get('cross-origin-resource-policy')}"
            )
            if "profile-photos" in resp.url
            else None,
        )
        page.goto("/profile")
        _ensure_no_photo(page)  # self-heal any leftover photo from a prior failed run

        try:
            # Flat view by default, no form fields visible.
            assert page.get_by_role("button", name="Edit").count() == 1
            assert page.get_by_label("Bio").count() == 0
            assert page.locator('[data-testid="profile-photo-placeholder"]').count() == 1

            # Cancel discards an unsaved edit and returns to the flat view.
            page.get_by_role("button", name="Edit").click()
            page.get_by_label("Bio").fill("this edit should be discarded")
            page.get_by_role("button", name="Cancel").click()
            assert page.get_by_role("button", name="Edit").count() == 1
            assert page.get_by_text("this edit should be discarded").count() == 0

            # Save persists and returns to the flat view showing the new values.
            _fill_and_save(page, MARKER_BIO, MARKER_VOICE, MARKER_INSTRUMENT, MARKER_PHONE)
            assert page.get_by_role("button", name="Edit").count() == 1
            assert page.get_by_text(MARKER_BIO).count() > 0
            assert page.get_by_text(MARKER_INSTRUMENT).count() > 0
            assert page.get_by_text(MARKER_PHONE).count() > 0

            # A fresh page load persists the same values, both in the flat
            # view and pre-filled once Edit is reopened.
            page.goto("/profile")
            assert page.get_by_text(MARKER_BIO).count() > 0
            page.get_by_role("button", name="Edit").click()
            assert page.get_by_label("Bio").input_value() == MARKER_BIO
            assert page.get_by_label("Voice").input_value() == MARKER_VOICE
            assert page.get_by_label("Instrument").input_value() == MARKER_INSTRUMENT
            assert page.get_by_label("Phone").input_value() == MARKER_PHONE

            # Photo upload/removal (only available in edit mode, already open).
            assert page.locator('[data-testid="profile-photo-placeholder"]').count() == 1
            with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as f:
                f.write(base64.b64decode(TINY_JPEG_BASE64))
                photo_path = f.name
            try:
                page.set_input_files('input[type="file"]', photo_path)
                _wait_for_photo(page, console_logs, photo_responses)
                assert page.locator('[data-testid="profile-photo"]').count() == 1
                assert page.locator('[data-testid="profile-photo-placeholder"]').count() == 0
            finally:
                os.unlink(photo_path)

            page.get_by_role("button", name="Remove photo").click()
            page.wait_for_timeout(1500)
            assert page.locator('[data-testid="profile-photo-placeholder"]').count() == 1
            assert page.locator('[data-testid="profile-photo"]').count() == 0
        finally:
            _fill_and_save(page, "", "", "", "")
            _ensure_no_photo(page)


TESTS = [
    test_profile_view_edit_toggle_fields_and_photo,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
