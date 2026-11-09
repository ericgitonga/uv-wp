"""Regression test for unauthenticated API / data-access boundaries in uv-wp:
1. Unauthenticated requests to /songs do not leak song titles (redirected to /login).
2. Unauthenticated requests to /wp-json/wp/v2/users are denied (401/403/404).
3. Unauthenticated POST mutations (uv_action=save_song) are rejected with 403.
"""

import json
import requests
from _common import BASE_URL, SEED_SONG_TITLE


def test_anon_key_cannot_read_songs_via_rest_api():
    resp = requests.get(f"{BASE_URL}/songs", allow_redirects=False, timeout=15)
    assert resp.status_code in (301, 302, 401, 403), (
        f"expected unauthenticated /songs request to redirect or deny, got {resp.status_code}"
    )
    assert SEED_SONG_TITLE not in resp.text


def test_anon_key_cannot_read_users_via_rest_api():
    resp = requests.get(f"{BASE_URL}/wp-json/wp/v2/users", timeout=15)
    assert resp.status_code in (401, 403, 404), (
        f"expected unauthenticated /wp-json/wp/v2/users to be blocked, got {resp.status_code}"
    )


def test_anon_key_cannot_insert_a_song_via_rest_api():
    resp = requests.post(
        f"{BASE_URL}/admin/songs/new",
        headers={"Accept": "application/json"},
        data={
            "uv_action": "save_song",
            "uv_json": "1",
            "payload": json.dumps({"title": "Unauthorized song — should never be written"}),
        },
        timeout=15,
    )
    assert resp.status_code in (401, 403), (
        f"expected unauthenticated song insert to be rejected (401/403), got {resp.status_code}"
    )


TESTS = [
    test_anon_key_cannot_read_songs_via_rest_api,
    test_anon_key_cannot_read_users_via_rest_api,
    test_anon_key_cannot_insert_a_song_via_rest_api,
]

if __name__ == "__main__":
    for t in TESTS:
        t()
        print(f"PASS {t.__name__}")
