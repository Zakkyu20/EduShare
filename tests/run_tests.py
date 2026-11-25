"""CLI test runner and smoke verifier for the EduShare E2E test suite."""

from __future__ import annotations

import argparse
import fnmatch
import json
import os
import subprocess
import sys
import unittest
import urllib.request
import uuid
from pathlib import Path

TESTS_DIR = Path(__file__).resolve().parent
if str(TESTS_DIR) not in sys.path:
    sys.path.insert(0, str(TESTS_DIR))

from helpers.browser_helper import HeadlessBrowser
from helpers.config import (
    BASE_URL,
    PHP_BIN,
    PROTECTED_CATEGORY_IDS,
    PROTECTED_USER_IDS,
    UPLOADS_DIR,
    VIEWPORTS,
)
from helpers.db_helper import DbHelper
from helpers.fixtures import FixtureManager
from helpers.http_client import (
    HttpClient,
    encode_multipart_formdata,
    extract_csrf,
    extract_nav_links,
    parse_forms,
)


_TIER_GLOBS = {
    "1": "test_tier1*.py",
    "2": "test_tier2*.py",
    "3": "test_tier3*.py",
    "4": "test_tier4*.py",
    "all": "test_tier*.py",
}


def _iter_tests(suite: unittest.TestSuite):
    for item in suite:
        if isinstance(item, unittest.TestSuite):
            yield from _iter_tests(item)
        else:
            yield item


def discover_suite(tier: str = "all", pattern: str | None = None) -> unittest.TestSuite:
    loader = unittest.TestLoader()
    file_glob = _TIER_GLOBS.get(tier, "test_tier*.py")
    matched_files = sorted(TESTS_DIR.glob(file_glob))
    if not matched_files and tier == "all":
        matched_files = sorted(TESTS_DIR.glob("test_*.py"))

    if pattern:
        pat_l = pattern.lower()
        file_filtered = [p for p in matched_files if fnmatch.fnmatch(p.name.lower(), pat_l)]
        if file_filtered:
            matched_files = file_filtered

    suite = unittest.TestSuite()
    for mod_path in matched_files:
        mod_suite = loader.discover(start_dir=str(TESTS_DIR), pattern=mod_path.name)
        for test_case in _iter_tests(mod_suite):
            if pattern:
                pat_l = pattern.lower()
                test_id = test_case.id().lower()
                method_name = getattr(test_case, "_testMethodName", "").lower()
                mod_match = fnmatch.fnmatch(mod_path.name.lower(), pat_l)
                if not (
                    mod_match
                    or fnmatch.fnmatch(test_id, pat_l)
                    or fnmatch.fnmatch(method_name, pat_l)
                    or pat_l.strip("*") in test_id
                ):
                    continue
            suite.addTest(test_case)
    return suite


def cmd_list(tier: str, pattern: str | None) -> int:
    suite = discover_suite(tier=tier, pattern=pattern)
    cases = list(_iter_tests(suite))
    grouped: dict[str, list[str]] = {}
    for tc in cases:
        tid = tc.id()
        mod_name = tid.split(".", 1)[0]
        grouped.setdefault(mod_name, []).append(tid)

    print(f"EduShare E2E Test Discovery (tier={tier}, pattern={pattern or '*'}):")
    if not cases:
        print("  (0 test modules/cases discovered yet)")
        return 0

    for mod_name, tids in sorted(grouped.items()):
        print(f"  [{mod_name}] ({len(tids)} tests)")
        for tid in tids:
            print(f"    - {tid}")
    print(f"Total discovered: {len(cases)} tests across {len(grouped)} modules.")
    return 0


def _php_finfo_mime(payload: bytes) -> str:
    proc = subprocess.run(
        [
            PHP_BIN,
            "-d",
            "display_errors=0",
            "-d",
            "html_errors=0",
            "-r",
            "$f = finfo_open(FILEINFO_MIME_TYPE); echo finfo_buffer($f, stream_get_contents(STDIN));",
        ],
        input=payload,
        capture_output=True,
        timeout=10,
        shell=False,
    )
    if proc.returncode != 0:
        err = proc.stderr.decode("utf-8", errors="replace").strip()
        raise RuntimeError(f"PHP finfo check failed (rc={proc.returncode}): {err}")
    return proc.stdout.decode("utf-8", errors="replace").strip()


def cmd_smoke() -> int:
    print("=== EduShare E2E-M1 Harness Smoke Check ===")
    failures: list[str] = []
    http = HttpClient(base_url=BASE_URL)
    db = DbHelper()

    # 1. HttpClient GET / -> 200 + "EduShare" + parse_forms + extract_nav_links
    try:
        home = http.get("/")
        assert home.status == 200, f"Expected GET / -> 200, got {home.status}"
        assert "EduShare" in home.text, "Expected 'EduShare' in GET / response body"
        forms = parse_forms(home.text)
        nav_links = extract_nav_links(home.text)
        assert len(forms) >= 1, "Expected at least 1 <form> (search form) on /"
        assert len(nav_links) >= 4, f"Expected >= 4 nav links on /, got {len(nav_links)}"
        print(f"[PASS] 1. HTTP GET / -> 200 ('EduShare', {len(forms)} form(s), {len(nav_links)} nav links)")
    except Exception as exc:
        failures.append(f"Check 1 (GET /): {exc}")
        print(f"[FAIL] 1. HTTP GET / -> {exc}")

    # 2. Apache protection on tests/ -> 403
    try:
        for protected_path in (
            "/edushare/tests/",
            "/edushare/tests/run_tests.py",
            "/edushare/tests/helpers/config.py",
        ):
            r = http.get(protected_path, follow_redirects=False)
            assert r.status == 403, f"Expected {protected_path} -> 403, got {r.status}"
        print("[PASS] 2. Apache .htaccess protection -> 403 on /tests/, run_tests.py, helpers/config.py")
    except Exception as exc:
        failures.append(f"Check 2 (Apache 403): {exc}")
        print(f"[FAIL] 2. Apache .htaccess protection -> {exc}")

    # 3. HttpClient HTML form / CSRF / multipart checks
    try:
        login_resp = http.get("login.php")
        assert login_resp.status == 200, f"Expected login.php -> 200, got {login_resp.status}"
        login_forms = parse_forms(login_resp.text)
        assert len(login_forms) >= 1, "Expected >= 1 form on login.php"
        login_inputs = {inp["name"] for inp in login_forms[0]["inputs"] if inp.get("name")}
        assert {"email", "password"}.issubset(login_inputs), f"Missing login inputs: {login_inputs}"

        synthetic_html = (
            '<form method="post" action="submit.php">'
            '<input type="hidden" name="csrf_token" value="tok_smoke_123">'
            '<input name="title" value="abc">'
            '<button name="action" value="save">Save</button>'
            "</form>"
        )
        syn_forms = parse_forms(synthetic_html)
        assert len(syn_forms) == 1
        assert extract_csrf(synthetic_html) == "tok_smoke_123"

        mp_body, mp_ctype = encode_multipart_formdata(
            data={"csrf_token": "tok_smoke_123", "judul": "Smoke Test"},
            files={"file": ("smoke.pdf", FixtureManager.minimal_pdf_bytes(), "application/pdf")},
        )
        assert mp_ctype.startswith("multipart/form-data; boundary=")
        assert b'name="csrf_token"' in mp_body and b'filename="smoke.pdf"' in mp_body and b"%PDF-1.4" in mp_body

        cookie_http = HttpClient(base_url="http://localhost/edushare")
        cookie_http.set_cookie("smoke_ck", "smoke_val", domain="localhost", http_only=True)
        assert cookie_http.get_cookie("smoke_ck") == "smoke_val"
        exported_cookies = cookie_http.export_cookies()
        assert len(exported_cookies) == 1
        assert exported_cookies[0]["domain"] == "localhost"
        assert exported_cookies[0]["httpOnly"] is True
        probe_req = urllib.request.Request("http://localhost/edushare/")
        cookie_http.cookie_jar.add_cookie_header(probe_req)
        assert probe_req.get_header("Cookie") == "smoke_ck=smoke_val", (
            f"Expected Cookie: smoke_ck=smoke_val, got {probe_req.get_header('Cookie')!r}"
        )
        print("[PASS] 3. HttpClient form/CSRF/multipart/cookie parser & encoder verified")
    except Exception as exc:
        failures.append(f"Check 3 (Form/CSRF/Multipart): {exc}")
        print(f"[FAIL] 3. Form/CSRF/Multipart -> {exc}")

    # 4. DbHelper + FixtureManager non-destructive check + MIME + temp create/cleanup
    smoke_run_id = f"smoke_{uuid.uuid4().hex[:8]}"
    fixtures = FixtureManager(db=db, http=http, uploads_dir=UPLOADS_DIR, run_id=smoke_run_id)
    try:
        for tbl in ("users", "categories", "materials"):
            assert db.table_exists(tbl), f"Required baseline table missing: {tbl}"
        fixtures.assert_baseline_intact()

        m1_tables = {
            t: db.table_exists(t)
            for t in ("role_permissions", "system_settings", "audit_logs", "activity_logs")
        }
        print(f"[INFO]    M1 table status: {json.dumps(m1_tables)}")

        pdf_mime = _php_finfo_mime(FixtureManager.minimal_pdf_bytes())
        docx_mime = _php_finfo_mime(FixtureManager.minimal_docx_bytes())
        spoof_mime = _php_finfo_mime(FixtureManager.spoofed_pdf_bytes())
        assert pdf_mime == "application/pdf", f"Unexpected minimal PDF MIME: {pdf_mime}"
        assert (
            docx_mime == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        ), f"Unexpected minimal DOCX MIME: {docx_mime}"
        assert spoof_mime != "application/pdf", f"Spoofed PDF MIME must not be application/pdf: {spoof_mime}"

        tmp_user = fixtures.create_user(role="user")
        tmp_cat_id = fixtures.create_category()
        tmp_mat_id = fixtures.create_material(id_user=tmp_user.id_user, id_category=tmp_cat_id)
        mat_row = db.query("SELECT nama_file FROM materials WHERE id_material = ?", [tmp_mat_id])
        assert len(mat_row) == 1, "Temporary material row not found in DB"
        tmp_file_path = os.path.join(os.fspath(UPLOADS_DIR), str(mat_row[0]["nama_file"]))
        assert os.path.isfile(tmp_file_path), f"Temporary upload file not created at {tmp_file_path}"

        fixtures.cleanup()

        assert db.scalar("SELECT COUNT(*) FROM users WHERE id_user = ?", [tmp_user.id_user]) == 0
        assert db.scalar("SELECT COUNT(*) FROM categories WHERE id_category = ?", [tmp_cat_id]) == 0
        assert db.scalar("SELECT COUNT(*) FROM materials WHERE id_material = ?", [tmp_mat_id]) == 0
        assert not os.path.exists(tmp_file_path), f"Temporary upload file left behind: {tmp_file_path}"
        for protected_uid in sorted(PROTECTED_USER_IDS):
            try:
                fixtures.track_user(protected_uid)
                raise AssertionError(f"track_user({protected_uid}) should have raised ValueError")
            except ValueError:
                pass
        fixtures.assert_baseline_intact()
        u_ids = sorted(PROTECTED_USER_IDS)
        u_ph = ",".join(["?"] * len(u_ids))
        user_count = db.scalar(f"SELECT COUNT(*) FROM users WHERE id_user IN ({u_ph})", u_ids)
        assert int(user_count) == len(PROTECTED_USER_IDS)
        c_ids = sorted(PROTECTED_CATEGORY_IDS)
        c_ph = ",".join(["?"] * len(c_ids))
        cat_count = db.scalar(f"SELECT COUNT(*) FROM categories WHERE id_category IN ({c_ph})", c_ids)
        assert int(cat_count) == len(PROTECTED_CATEGORY_IDS)
        print(
            f"[PASS] 4. DbHelper + FixtureManager verified (MIME: {pdf_mime}, docx ok, spoof={spoof_mime}; "
            "temp records/files cleaned; baseline intact)"
        )
    except Exception as exc:
        failures.append(f"Check 4 (DB/Fixtures): {exc}")
        print(f"[FAIL] 4. DbHelper + FixtureManager -> {exc}")
        try:
            fixtures.cleanup()
        except Exception:
            pass

    # 5. HeadlessBrowser CDP check across all 3 viewports
    profile_dir_seen: str | None = None
    try:
        with HeadlessBrowser() as browser:
            profile_dir_seen = browser.profile_dir
            target_url = BASE_URL + "/"
            for width, height in VIEWPORTS:
                browser.clear_console_errors()
                browser.set_viewport(width, height)
                browser.navigate(target_url)
                metrics = browser.eval_js(
                    "({title: document.title, "
                    "scrollWidth: document.documentElement.scrollWidth, "
                    "innerWidth: window.innerWidth})"
                )
                assert metrics and metrics["title"].strip(), f"Empty title at {width}x{height}"
                assert (
                    metrics["scrollWidth"] <= metrics["innerWidth"]
                ), f"Horizontal overflow at {width}x{height}: {metrics}"

                local_errors = browser.get_console_errors(include_external_fonts=False)
                assert not local_errors, f"Local console errors at {width}x{height}: {local_errors}"

                if (width, height) == (375, 812):
                    toggle_state = browser.eval_js(
                        "(() => {"
                        "const btn = document.querySelector('.mobile-menu');"
                        "const nav = document.querySelector('.main-nav');"
                        "if (!btn || !nav) return {found: false};"
                        "const btnStyle = getComputedStyle(btn);"
                        "const beforeDisplay = getComputedStyle(nav).display;"
                        "btn.click();"
                        "const afterOpenDisplay = getComputedStyle(nav).display;"
                        "const hasOpenClass = nav.classList.contains('open');"
                        "btn.click();"
                        "const afterCloseDisplay = getComputedStyle(nav).display;"
                        "return {found: true, btnVisible: btnStyle.display !== 'none', "
                        "beforeDisplay, afterOpenDisplay, hasOpenClass, afterCloseDisplay};"
                        "})()"
                    )
                    assert toggle_state and toggle_state.get("found"), "Mobile menu toggle or nav missing at 375x812"
                    assert toggle_state.get("btnVisible"), f"Mobile menu button not visible at 375x812: {toggle_state}"
                    assert toggle_state.get("hasOpenClass") and toggle_state.get("afterOpenDisplay") != "none", (
                        f"Mobile nav failed to open on click at 375x812: {toggle_state}"
                    )
                    assert toggle_state.get("afterCloseDisplay") == "none", (
                        f"Mobile nav failed to close on second click at 375x812: {toggle_state}"
                    )

        assert profile_dir_seen and not os.path.exists(
            profile_dir_seen
        ), f"Browser temp profile dir was not removed: {profile_dir_seen}"
        print(
            f"[PASS] 5. HeadlessBrowser CDP verified across viewports {VIEWPORTS}, "
            "responsive nav toggle at 375x812, zero local console errors, temp profile cleaned"
        )
    except Exception as exc:
        failures.append(f"Check 5 (HeadlessBrowser): {exc}")
        print(f"[FAIL] 5. HeadlessBrowser CDP -> {exc}")

    if failures:
        print(f"\nSmoke check FAILED ({len(failures)} failure(s)).")
        return 1
    print("\nAll 5 smoke checks PASSED.")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="EduShare E2E Test Runner")
    parser.add_argument("--list", action="store_true", help="List discovered tests without executing")
    parser.add_argument(
        "--tier",
        choices=["1", "2", "3", "4", "all"],
        default="all",
        help="Run or list a specific test tier (default: all)",
    )
    parser.add_argument("--smoke", action="store_true", help="Run non-destructive harness & environment smoke checks")
    parser.add_argument("--pattern", default=None, help="Glob pattern to filter test files or test IDs")
    parser.add_argument("-v", "--verbose", type=int, default=2, help="unittest verbosity (default: 2)")

    args = parser.parse_args(argv)

    if args.smoke:
        return cmd_smoke()
    if args.list:
        return cmd_list(tier=args.tier, pattern=args.pattern)

    suite = discover_suite(tier=args.tier, pattern=args.pattern)
    if suite.countTestCases() == 0:
        print(f"No tests matched (tier={args.tier}, pattern={args.pattern or '*'}).")
        return 1

    runner = unittest.TextTestRunner(verbosity=args.verbose)
    result = runner.run(suite)
    return 0 if (result.wasSuccessful() and result.testsRun > 0) else 1


if __name__ == "__main__":
    raise SystemExit(main())
