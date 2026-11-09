"""Golden path + regression: direct video upload (#55), including the
>10MB body-size bug found and fixed at #58. src/proxy.ts (this app's
global middleware) buffers every request body it sees, capped at 10MB by
default — separately from, and in front of, serverActions.bodySizeLimit —
so any upload over 10MB (routinely hit by video, well within this app's
20MB app-side cap) got silently truncated there, never rejected with a
clear error, and the Server Action then failed parsing the now-broken
multipart body. Fixed via next.config.ts's experimental.proxyClientMaxBodySize.

Video uploads aren't content-sniffed server-side (unlike audio's
sniffAudioFormat) — only MIME type, extension, and size are checked — so a
fake file of null bytes with a real .mp4 extension exercises the same code
path as a real video file without needing ffmpeg or a committed binary
fixture. Written to a real temp file rather than passed as an in-memory
buffer — an in-memory buffer has to be base64-encoded and transferred over
CDP before the upload itself even starts, which timed out in CI's more
constrained runner even at a 90s allowance; a real file path lets the
browser read it directly with no such overhead.

File size deliberately kept close to (rather than far above) the 10MB
threshold the actual bug lived at — 11MB is the smallest size that still
meaningfully exercises the >10MB regression. Cleans up after itself since
this suite runs against a shared Preview database.

This test previously appeared to hang for minutes in CI regardless of how
high timeout_s was raised. That was never a real speed/infra problem: the
Preview-environment SUPABASE_SECRET_KEY had been stored as a Vercel
"sensitive" variable, which `vercel env pull` (this repo's CI setup step)
can never read back — it silently wrote the literal string "[SENSITIVE]"
into .env.local, so every upload failed auth instantly with "Invalid
Compact JWS." The failure was invisible because _wait_for_outcome() below
didn't recognise that error text as a terminal state, so it just polled
for the full timeout every time. Once the key was fixed (re-added as
non-sensitive) and _wait_for_outcome() was taught to recognise any inline
form error, a real successful upload measured ~3s in CI end to end.
"""

import os
import tempfile
import time

from _common import admin_page, SEED_SONG_TITLE

# Just over proxy.ts's 10MB default buffering cap (the actual bug), and
# still under this app's 20MB app-side upload cap — see the module
# docstring for why this is kept close to 10MB rather than larger.
LARGE_FILE_BYTES = 11 * 1024 * 1024

LABEL = "E2E Large Video Upload Test"


def _wait_for_outcome(page, timeout_s=30, interval_s=1):
    """Polls for either an inline form error or the new label, whichever
    comes first — more reliable here than a single fixed wait (upload time
    varies with system load) or a JS-side wait_for_function poll (both
    proved flaky in practice: the fixed wait was sometimes too short, and
    wait_for_function's document.body.innerText poll didn't reliably fire
    even once the content had visibly rendered). 30s is ~10x the ~3s a real
    upload measured in CI — enough headroom for normal variance without
    masking a real failure behind a multi-minute wait.

    Raises immediately on any inline error rather than just returning and
    letting the caller assert afterward: a real failure once surfaced here
    as "Upload failed — please try again." (AddMediaForm's own returned-
    error path), which is a different string from "Something went wrong"
    (only used for a thrown/network-level error) — silently returning on
    neither string ever matching left this polling the full timeout with
    no useful signal. Anchored on the error paragraph's own CSS class
    instead of a hardcoded message so this can't recur if the message
    changes again.
    """
    deadline = time.monotonic() + timeout_s
    error_locator = page.locator("p.text-red-600")
    while time.monotonic() < deadline:
        if error_locator.count() > 0:
            raise AssertionError(f"Upload failed: {error_locator.first.inner_text()}")
        if page.get_by_text(LABEL).count() > 0:
            return
        page.wait_for_timeout(interval_s * 1000)
    raise TimeoutError(f"Neither an error nor '{LABEL}' appeared within {timeout_s}s")


def test_large_video_upload_succeeds():
    with tempfile.NamedTemporaryFile(suffix=".mp4", delete=False) as f:
        f.write(b"\x00" * LARGE_FILE_BYTES)
        video_path = f.name

    try:
        with admin_page() as page:
            page.goto("/songs")
            page.get_by_text(SEED_SONG_TITLE).first.click()
            page.wait_for_url("**/songs/**", timeout=10_000)
            page.get_by_role("link", name="Media").click()
            page.wait_for_url("**/media", timeout=10_000)

            page.get_by_role("button", name="Upload file").click()
            page.set_input_files('input[type="file"]', video_path)
            page.get_by_label("Label").fill(LABEL)
            page.get_by_role("button", name="Add Media").click()
            _wait_for_outcome(page)

            try:
                assert page.get_by_text(LABEL).count() > 0
            finally:
                # A broad `div[has_text=...]` locator matches every ancestor
                # div containing the label, not just the media item's own
                # card — anchor on the label <p> itself instead and walk up
                # to its card (media/page.tsx's `rounded-lg` wrapper).
                page.on("dialog", lambda d: d.accept())
                card = page.locator("p", has_text=LABEL).locator(
                    "xpath=ancestor::div[contains(@class,'rounded-lg')][1]"
                )
                card.get_by_text("Remove").click()
                page.wait_for_timeout(1000)
    finally:
        os.unlink(video_path)


TESTS = [
    test_large_video_upload_succeeds,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
