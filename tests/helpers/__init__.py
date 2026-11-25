"""Shared helpers package for the EduShare E2E test harness."""

from __future__ import annotations

import sys
from pathlib import Path

_TESTS_DIR = str(Path(__file__).resolve().parent.parent)
if _TESTS_DIR not in sys.path:
    sys.path.insert(0, _TESTS_DIR)

from . import config
from .http_client import (
    HttpClient,
    HttpResponse,
    encode_multipart_formdata,
    extract_csrf,
    extract_nav_links,
    extract_status_badges,
    parse_forms,
)

__all__ = [
    "config",
    "HttpClient",
    "HttpResponse",
    "encode_multipart_formdata",
    "parse_forms",
    "extract_csrf",
    "extract_nav_links",
    "extract_status_badges",
]

try:
    from .db_helper import CliResult, DbError, DbHelper

    __all__.extend(["DbHelper", "DbError", "CliResult"])
except ImportError:
    pass

try:
    from .fixtures import CreatedUser, FixtureManager

    __all__.extend(["FixtureManager", "CreatedUser"])
except ImportError:
    pass

try:
    from .browser_helper import ConsoleError, HeadlessBrowser

    __all__.extend(["HeadlessBrowser", "ConsoleError"])
except ImportError:
    pass
