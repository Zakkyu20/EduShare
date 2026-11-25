"""Pure Python stdlib Chrome/Edge CDP WebSocket browser helper."""

from __future__ import annotations

import base64
import hashlib
import http.cookiejar
import json
import os
import shutil
import socket
import struct
import subprocess
import tempfile
import time
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any, Mapping, Sequence

from .config import BASE_URL, BROWSER_PATHS

_WS_MAGIC_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11"
_EXTERNAL_FONT_HOSTS = ("fonts.googleapis.com", "fonts.gstatic.com")


@dataclass
class ConsoleError:
    """Captured browser console error, runtime exception, or log error."""

    source: str
    text: str
    url: str = ""
    level: str = "error"


class HeadlessBrowser:
    """Drives headless Chrome or Edge via CDP over a pure stdlib WebSocket client."""

    def __init__(
        self,
        browser_paths: Sequence[str] = BROWSER_PATHS,
        timeout: float = 10.0,
    ) -> None:
        self.browser_paths: list[str] = [str(p) for p in browser_paths if p]
        self.timeout: float = float(timeout)
        self.profile_dir: str | None = None
        self._proc: subprocess.Popen[bytes] | None = None
        self._sock: socket.socket | None = None
        self._rx_buf: bytearray = bytearray()
        self._cmd_id: int = 0
        self._console_errors: list[ConsoleError] = []

    def __enter__(self) -> "HeadlessBrowser":
        self.start()
        return self

    def __exit__(self, exc_type, exc, tb) -> None:
        self.close()

    def _find_browser(self) -> str:
        for path in self.browser_paths:
            if os.path.isfile(path):
                return path
        raise FileNotFoundError(
            f"No Chrome or Edge executable found in BROWSER_PATHS={self.browser_paths!r}"
        )

    def start(self) -> None:
        if self._sock is not None:
            return
        exe = self._find_browser()
        self.profile_dir = tempfile.mkdtemp(prefix="edushare_cdp_")
        cmd = [
            exe,
            "--headless=new",
            "--disable-gpu",
            "--no-first-run",
            "--no-default-browser-check",
            "--disable-extensions",
            "--remote-debugging-port=0",
            f"--user-data-dir={self.profile_dir}",
            "about:blank",
        ]
        try:
            self._proc = subprocess.Popen(
                cmd,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                shell=False,
            )
            port = self._wait_for_devtools_port()
            ws_url = self._get_page_ws_url(port)
            self._ws_connect(ws_url)
            self._cdp("Page.enable")
            self._cdp("Runtime.enable")
            self._cdp("Log.enable")
            self._cdp("Network.enable")
        except Exception:
            self.close()
            raise

    def _wait_for_devtools_port(self) -> int:
        assert self.profile_dir is not None
        port_file = os.path.join(self.profile_dir, "DevToolsActivePort")
        deadline = time.monotonic() + self.timeout
        while time.monotonic() < deadline:
            if self._proc is not None and self._proc.poll() is not None:
                raise RuntimeError(f"Browser exited prematurely with code {self._proc.returncode}")
            if os.path.isfile(port_file):
                try:
                    with open(port_file, "r", encoding="utf-8") as fh:
                        lines = [ln.strip() for ln in fh.read().splitlines() if ln.strip()]
                    if lines:
                        return int(lines[0])
                except (OSError, ValueError):
                    pass
            time.sleep(0.05)
        raise TimeoutError(f"Timed out waiting for DevToolsActivePort in {self.profile_dir}")

    def _get_page_ws_url(self, port: int) -> str:
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        list_url = f"http://127.0.0.1:{port}/json/list"
        deadline = time.monotonic() + self.timeout
        last_err: Exception | None = None
        while time.monotonic() < deadline:
            try:
                with opener.open(list_url, timeout=2.0) as resp:
                    targets = json.loads(resp.read().decode("utf-8"))
                for tgt in targets:
                    if tgt.get("type") == "page" and tgt.get("webSocketDebuggerUrl"):
                        return str(tgt["webSocketDebuggerUrl"])
            except Exception as exc:
                last_err = exc
            time.sleep(0.05)
        raise RuntimeError(f"Failed to acquire CDP page target from {list_url}: {last_err}")

    def _ws_connect(self, ws_url: str) -> None:
        parsed = urllib.parse.urlsplit(ws_url)
        host = parsed.hostname or "127.0.0.1"
        port = parsed.port or 80
        resource = parsed.path or "/"
        if parsed.query:
            resource += "?" + parsed.query

        sock = socket.create_connection((host, port), timeout=self.timeout)
        sock.settimeout(self.timeout)
        key_bytes = os.urandom(16)
        sec_key = base64.b64encode(key_bytes).decode("ascii")
        expected_accept = base64.b64encode(
            hashlib.sha1((sec_key + _WS_MAGIC_GUID).encode("ascii")).digest()
        ).decode("ascii")

        req = (
            f"GET {resource} HTTP/1.1\r\n"
            f"Host: {host}:{port}\r\n"
            "Upgrade: websocket\r\n"
            "Connection: Upgrade\r\n"
            f"Sec-WebSocket-Key: {sec_key}\r\n"
            "Sec-WebSocket-Version: 13\r\n\r\n"
        ).encode("ascii")
        sock.sendall(req)

        buf = bytearray()
        while b"\r\n\r\n" not in buf:
            chunk = sock.recv(4096)
            if not chunk:
                sock.close()
                raise ConnectionError("EOF during WebSocket handshake")
            buf.extend(chunk)

        sep_idx = buf.index(b"\r\n\r\n")
        header_part = bytes(buf[:sep_idx])
        leftover = bytes(buf[sep_idx + 4 :])
        if not header_part.startswith(b"HTTP/1.1 101 "):
            sock.close()
            raise ConnectionError(f"Unexpected WebSocket handshake response: {header_part!r}")

        accept_val = None
        for line in header_part.split(b"\r\n")[1:]:
            if b":" in line:
                k, v = line.split(b":", 1)
                if k.strip().lower() == b"sec-websocket-accept":
                    accept_val = v.strip().decode("ascii", errors="replace")
                    break
        if accept_val != expected_accept:
            sock.close()
            raise ConnectionError(f"WebSocket Sec-WebSocket-Accept mismatch: {accept_val!r} != {expected_accept!r}")

        self._sock = sock
        self._rx_buf = bytearray(leftover)

    def _recv_exact(self, n: int) -> bytes:
        assert self._sock is not None
        while len(self._rx_buf) < n:
            chunk = self._sock.recv(max(4096, n - len(self._rx_buf)))
            if not chunk:
                raise ConnectionError("WebSocket connection closed unexpectedly")
            self._rx_buf.extend(chunk)
        out = bytes(self._rx_buf[:n])
        del self._rx_buf[:n]
        return out

    def _ws_send_frame(self, opcode: int, payload: bytes) -> None:
        assert self._sock is not None
        mask_key = os.urandom(4)
        length = len(payload)
        header = bytearray([0x80 | (opcode & 0x0F)])
        if length < 126:
            header.append(0x80 | length)
        elif length < 65536:
            header.append(0x80 | 126)
            header.extend(struct.pack("!H", length))
        else:
            header.append(0x80 | 127)
            header.extend(struct.pack("!Q", length))
        header.extend(mask_key)

        masked = bytearray(payload)
        for i in range(length):
            masked[i] ^= mask_key[i & 3]
        self._sock.sendall(bytes(header) + bytes(masked))

    def _ws_recv_message(self) -> str:
        fragments: list[bytes] = []
        message_opcode: int | None = None
        while True:
            b1, b2 = self._recv_exact(2)
            fin = bool(b1 & 0x80)
            opcode = b1 & 0x0F
            masked = bool(b2 & 0x80)
            length = b2 & 0x7F
            if length == 126:
                length = struct.unpack("!H", self._recv_exact(2))[0]
            elif length == 127:
                length = struct.unpack("!Q", self._recv_exact(8))[0]
            mask_key = self._recv_exact(4) if masked else b""
            payload = self._recv_exact(length) if length else b""
            if masked:
                unmasked = bytearray(payload)
                for i in range(len(unmasked)):
                    unmasked[i] ^= mask_key[i & 3]
                payload = bytes(unmasked)

            if opcode == 0x8:  # Close
                try:
                    self._ws_send_frame(0x8, payload[:2])
                except OSError:
                    pass
                raise ConnectionError("WebSocket closed by browser")
            if opcode == 0x9:  # Ping
                self._ws_send_frame(0xA, payload)
                continue
            if opcode == 0xA:  # Pong
                continue

            if opcode != 0x0:
                message_opcode = opcode
                fragments = [payload]
            else:
                fragments.append(payload)

            if fin:
                full_payload = b"".join(fragments)
                if message_opcode in (0x1, 0x2, None):
                    return full_payload.decode("utf-8", errors="replace")

    def _handle_event(self, msg: dict[str, Any]) -> None:
        method = msg.get("method")
        params = msg.get("params") or {}

        if method == "Runtime.exceptionThrown":
            details = params.get("exceptionDetails") or {}
            exc = details.get("exception") or {}
            desc = exc.get("description") or details.get("text") or "Uncaught exception"
            url = str(details.get("url") or "")
            self._console_errors.append(
                ConsoleError(source="exception", text=str(desc), url=url, level="error")
            )
        elif method == "Runtime.consoleAPICalled":
            ctype = str(params.get("type", "")).lower()
            if ctype in ("error", "assert"):
                args = params.get("args") or []
                parts = []
                for arg in args:
                    if "value" in arg:
                        parts.append(str(arg["value"]))
                    elif "description" in arg:
                        parts.append(str(arg["description"]))
                text = " ".join(parts) or f"console.{ctype}"
                stack = params.get("stackTrace") or {}
                frames = stack.get("callFrames") or []
                url = str(frames[0].get("url", "")) if frames else ""
                self._console_errors.append(
                    ConsoleError(source="console", text=text, url=url, level="error")
                )
        elif method == "Log.entryAdded":
            entry = params.get("entry") or {}
            level = str(entry.get("level", "")).lower()
            if level == "error":
                self._console_errors.append(
                    ConsoleError(
                        source="log",
                        text=str(entry.get("text") or "Log error"),
                        url=str(entry.get("url") or ""),
                        level=level,
                    )
                )

    def _cdp(self, method: str, params: Mapping[str, Any] | None = None) -> dict[str, Any]:
        if self._sock is None:
            raise RuntimeError("HeadlessBrowser is not started")
        self._cmd_id += 1
        cid = self._cmd_id
        payload = json.dumps(
            {"id": cid, "method": method, "params": dict(params) if params else {}},
            ensure_ascii=False,
        ).encode("utf-8")
        self._ws_send_frame(0x1, payload)

        while True:
            raw = self._ws_recv_message()
            msg = json.loads(raw)
            if "id" in msg and msg["id"] == cid:
                if "error" in msg and msg["error"]:
                    raise RuntimeError(f"CDP {method} error: {msg['error']}")
                return msg.get("result") or {}
            if "method" in msg:
                self._handle_event(msg)

    def set_viewport(
        self,
        width: int,
        height: int,
        device_scale_factor: float = 1.0,
        mobile: bool = False,
    ) -> None:
        self._cdp(
            "Emulation.setDeviceMetricsOverride",
            {
                "width": int(width),
                "height": int(height),
                "deviceScaleFactor": float(device_scale_factor),
                "mobile": bool(mobile),
            },
        )

    @staticmethod
    def _normalize_cookie_domain(domain: str | None) -> str | None:
        if not domain:
            return domain
        if domain == "localhost.local":
            return "localhost"
        if domain.endswith(".local") and "." not in domain[:-6]:
            return domain[:-6]
        return domain

    @staticmethod
    def _is_http_only(cookie: http.cookiejar.Cookie) -> bool:
        rest = getattr(cookie, "_rest", {}) or {}
        return any(str(k).lower() == "httponly" for k in rest)

    def set_cookies(self, cookies: Any, url: str = BASE_URL + "/") -> None:
        items: list[dict[str, Any]] = []
        if isinstance(cookies, http.cookiejar.CookieJar):
            for c in cookies:
                dom = self._normalize_cookie_domain(c.domain)
                entry: dict[str, Any] = {
                    "name": c.name,
                    "value": c.value,
                    "path": c.path or "/",
                    "secure": bool(c.secure),
                    "httpOnly": self._is_http_only(c),
                }
                if dom:
                    entry["domain"] = dom
                else:
                    entry["url"] = url
                if c.expires is not None:
                    entry["expires"] = int(c.expires)
                items.append(entry)
        elif isinstance(cookies, Mapping):
            for name, val in cookies.items():
                items.append({"name": str(name), "value": str(val), "url": url})
        elif isinstance(cookies, Sequence):
            for c in cookies:
                if isinstance(c, http.cookiejar.Cookie):
                    dom = self._normalize_cookie_domain(c.domain)
                    entry = {
                        "name": c.name,
                        "value": c.value,
                        "path": c.path or "/",
                        "secure": bool(c.secure),
                        "httpOnly": self._is_http_only(c),
                    }
                    if dom:
                        entry["domain"] = dom
                    else:
                        entry["url"] = url
                    if getattr(c, "expires", None) is not None:
                        entry["expires"] = int(c.expires)
                    items.append(entry)
                elif isinstance(c, Mapping):
                    entry = dict(c)
                    if "domain" in entry:
                        entry["domain"] = self._normalize_cookie_domain(entry["domain"])
                    if "domain" not in entry and "url" not in entry:
                        entry["url"] = url
                    if entry.get("expires") is None:
                        entry.pop("expires", None)
                    items.append(entry)
        else:
            raise TypeError(f"Unsupported cookies type: {type(cookies).__name__}")

        for item in items:
            if "domain" in item and "url" not in item:
                parsed = urllib.parse.urlsplit(url)
                item["url"] = f"{parsed.scheme}://{item['domain']}{item.get('path', '/')}"
            res = self._cdp("Network.setCookie", item)
            if not res.get("success", False):
                raise RuntimeError(f"Network.setCookie failed for {item.get('name')!r}: {res}")

    def navigate(
        self,
        url: str,
        timeout: float | None = None,
        load_grace: float = 1.5,
    ) -> None:
        wait_timeout = self.timeout if timeout is None else float(timeout)
        res = self._cdp("Page.navigate", {"url": url})
        if res.get("errorText"):
            raise RuntimeError(f"Page.navigate failed for {url}: {res['errorText']}")

        deadline = time.monotonic() + wait_timeout
        interactive_since: float | None = None
        while time.monotonic() < deadline:
            state = self.eval_js("document.readyState")
            if state == "complete":
                return
            if state == "interactive":
                if interactive_since is None:
                    interactive_since = time.monotonic()
                elif (time.monotonic() - interactive_since) >= load_grace:
                    return
            time.sleep(0.05)

    def eval_js(self, expr: str) -> Any:
        res = self._cdp(
            "Runtime.evaluate",
            {
                "expression": expr,
                "returnByValue": True,
                "awaitPromise": True,
            },
        )
        if "exceptionDetails" in res and res["exceptionDetails"]:
            details = res["exceptionDetails"]
            exc = details.get("exception") or {}
            msg = exc.get("description") or details.get("text") or "JS evaluation error"
            raise RuntimeError(f"JS exception in eval_js: {msg}")
        result_obj = res.get("result") or {}
        return result_obj.get("value")

    @staticmethod
    def _is_external_font_error(err: ConsoleError) -> bool:
        haystack = f"{err.url} {err.text}".lower()
        return any(host in haystack for host in _EXTERNAL_FONT_HOSTS)

    def get_console_errors(self, include_external_fonts: bool = False) -> list[ConsoleError]:
        if self._sock is not None:
            try:
                self.eval_js("1")
            except Exception:
                pass
        if include_external_fonts:
            return list(self._console_errors)
        return [e for e in self._console_errors if not self._is_external_font_error(e)]

    def clear_console_errors(self) -> None:
        self._console_errors.clear()

    def close(self) -> None:
        if self._sock is not None:
            try:
                self._ws_send_frame(0x8, b"")
            except OSError:
                pass
            try:
                self._sock.close()
            except OSError:
                pass
            self._sock = None
        self._rx_buf.clear()

        if self._proc is not None:
            if self._proc.poll() is None:
                try:
                    self._proc.terminate()
                    self._proc.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    subprocess.run(
                        ["taskkill", "/F", "/T", "/PID", str(self._proc.pid)],
                        stdout=subprocess.DEVNULL,
                        stderr=subprocess.DEVNULL,
                        shell=False,
                    )
                    try:
                        self._proc.wait(timeout=3)
                    except subprocess.TimeoutExpired:
                        pass
            self._proc = None

        if self.profile_dir and os.path.exists(self.profile_dir):
            for _ in range(25):
                try:
                    shutil.rmtree(self.profile_dir, ignore_errors=False)
                    break
                except OSError:
                    time.sleep(0.2)
            if os.path.exists(self.profile_dir):
                shutil.rmtree(self.profile_dir, ignore_errors=True)
