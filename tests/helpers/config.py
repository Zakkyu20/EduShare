"""Configuration constants and environment overrides for EduShare E2E tests."""

from __future__ import annotations

import os
import shutil
import sys
from pathlib import Path


def _env(name: str, default: str = "") -> str:
    val = os.environ.get(name)
    return default if val is None else val


TESTS_DIR: Path = Path(__file__).resolve().parent.parent
PROJECT_ROOT: Path = TESTS_DIR.parent
UPLOADS_DIR: Path = PROJECT_ROOT / "uploads"

BASE_URL: str = _env("EDUSHARE_BASE_URL", "http://localhost/edushare").rstrip("/")

PYTHON_BIN: str = (
    os.environ.get("EDUSHARE_PYTHON_BIN")
    or sys.executable
    or r"C:\Python314\python.exe"
)
PHP_BIN: str = (
    os.environ.get("EDUSHARE_PHP_BIN")
    or shutil.which("php")
    or r"C:\xampp\php\php.exe"
)
MYSQL_BIN: str = (
    os.environ.get("EDUSHARE_MYSQL_BIN")
    or shutil.which("mysql")
    or r"C:\xampp\mysql\bin\mysql.exe"
)

DB_CONFIG: dict[str, str | int] = {
    "host": _env("EDUSHARE_DB_HOST", "localhost"),
    "port": int(_env("EDUSHARE_DB_PORT", "3306")),
    "database": _env("EDUSHARE_DB_NAME", "edushare"),
    "user": _env("EDUSHARE_DB_USER", "root"),
    "password": _env("EDUSHARE_DB_PASS", ""),
    "charset": "utf8mb4",
}


def _build_browser_paths() -> list[str]:
    candidates = [
        os.environ.get("EDUSHARE_BROWSER_BIN"),
        r"C:\Program Files\Google\Chrome\Application\chrome.exe",
        r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
        shutil.which("chrome"),
        shutil.which("msedge"),
    ]
    seen: set[str] = set()
    result: list[str] = []
    for item in candidates:
        if not item:
            continue
        norm = os.path.normcase(os.path.normpath(item))
        if norm not in seen:
            seen.add(norm)
            result.append(item)
    return result


BROWSER_PATHS: list[str] = _build_browser_paths()

VIEWPORTS: list[tuple[int, int]] = [(1280, 800), (768, 1024), (375, 812)]

PROTECTED_USER_IDS: set[int] = {1, 2}
PROTECTED_CATEGORY_IDS: set[int] = {1, 2, 3, 4, 5, 6}

SUPERADMIN_EMAIL: str = _env("EDUSHARE_SUPERADMIN_EMAIL", "superadmin@edushare.com").strip() or "superadmin@edushare.com"
SUPERADMIN_PASSWORD: str = _env("EDUSHARE_SUPERADMIN_PASSWORD", "")

SUPERADMIN_ENV_CREDENTIALS: dict[str, str] = {
    "email": SUPERADMIN_EMAIL,
    "password": SUPERADMIN_PASSWORD,
}

HTTP_TIMEOUT: float = float(_env("EDUSHARE_HTTP_TIMEOUT", "15"))
TEST_PREFIX: str = "e2e_"
