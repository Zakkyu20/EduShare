"""Pure Python stdlib HTTP client, multipart encoder, and HTML form/CSRF parser."""

from __future__ import annotations

import html
import http.cookiejar
import mimetypes
import re
import urllib.error
import urllib.parse
import urllib.request
import uuid
from dataclasses import dataclass, field
from email.message import Message
from html.parser import HTMLParser
from typing import Any, Mapping, Sequence

from .config import BASE_URL, HTTP_TIMEOUT


@dataclass
class HttpResponse:
    """Represents a completed HTTP response including redirect history."""

    status: int
    url: str
    headers: Message
    body: bytes
    history: list[tuple[int, str]] = field(default_factory=list)
    history_headers: list[Message] = field(default_factory=list)

    @property
    def text(self) -> str:
        content_type = self.headers.get("Content-Type", "")
        charset = "utf-8"
        match = re.search(r"charset=([^\s;]+)", content_type, re.IGNORECASE)
        if match:
            charset = match.group(1).strip("\"'")
        try:
            return self.body.decode(charset, errors="replace")
        except LookupError:
            return self.body.decode("utf-8", errors="replace")

    def get_header(self, name: str, default: str | None = None) -> str | None:
        return self.headers.get(name, default)

    def get_headers(self, name: str) -> list[str]:
        return list(self.headers.get_all(name, []))


def _sanitize_header_param(value: str) -> str:
    return value.replace("\r", "").replace("\n", "").replace('"', '\\"')


def encode_multipart_formdata(
    data: Mapping[str, Any] | Sequence[tuple[str, Any]] | None = None,
    files: Mapping[str, Any] | Sequence[tuple[str, Any]] | None = None,
) -> tuple[bytes, str]:
    """Encode form fields and uploaded files into a multipart/form-data payload."""
    boundary = f"----EduShareE2E{uuid.uuid4().hex}"
    chunks: list[bytes] = []

    if data is not None:
        items = list(data.items()) if isinstance(data, Mapping) else list(data)
        for key, raw_val in items:
            if raw_val is None:
                continue
            values = raw_val if isinstance(raw_val, (list, tuple)) else [raw_val]
            safe_name = _sanitize_header_param(str(key))
            for val in values:
                if val is None:
                    continue
                val_bytes = val if isinstance(val, (bytes, bytearray)) else str(val).encode("utf-8")
                chunks.append(
                    (
                        f"--{boundary}\r\n"
                        f'Content-Disposition: form-data; name="{safe_name}"\r\n\r\n'
                    ).encode("utf-8")
                )
                chunks.append(bytes(val_bytes))
                chunks.append(b"\r\n")

    if files is not None:
        file_items = list(files.items()) if isinstance(files, Mapping) else list(files)
        for field_name, spec in file_items:
            if spec is None:
                continue
            safe_field = _sanitize_header_param(str(field_name))
            if isinstance(spec, (bytes, bytearray)):
                filename = f"{safe_field}.bin"
                content_bytes = bytes(spec)
                content_type = "application/octet-stream"
            elif isinstance(spec, (tuple, list)):
                if len(spec) == 3:
                    filename, raw_content, content_type = spec
                elif len(spec) == 2:
                    filename, raw_content = spec
                    content_type = mimetypes.guess_type(str(filename))[0] or "application/octet-stream"
                else:
                    raise ValueError(f"Unsupported file tuple length {len(spec)} for field {field_name!r}")
                content_bytes = (
                    bytes(raw_content)
                    if isinstance(raw_content, (bytes, bytearray))
                    else str(raw_content).encode("utf-8")
                )
            else:
                raise TypeError(f"Unsupported file spec type {type(spec).__name__} for field {field_name!r}")

            safe_filename = _sanitize_header_param(str(filename))
            safe_ctype = str(content_type).replace("\r", "").replace("\n", "")
            chunks.append(
                (
                    f"--{boundary}\r\n"
                    f'Content-Disposition: form-data; name="{safe_field}"; filename="{safe_filename}"\r\n'
                    f"Content-Type: {safe_ctype}\r\n\r\n"
                ).encode("utf-8")
            )
            chunks.append(content_bytes)
            chunks.append(b"\r\n")

    chunks.append(f"--{boundary}--\r\n".encode("utf-8"))
    return b"".join(chunks), f"multipart/form-data; boundary={boundary}"


class _NoRedirectHandler(urllib.request.HTTPRedirectHandler):
    """Prevents urllib from auto-following redirects so every hop can be inspected."""

    def http_error_302(self, req, fp, code, msg, headers):
        return fp

    http_error_301 = http_error_303 = http_error_307 = http_error_308 = http_error_302


class _FormHTMLParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.forms: list[dict[str, Any]] = []
        self._form_stack: list[dict[str, Any]] = []
        self._textarea_buf: list[str] | None = None
        self._textarea_attrs: dict[str, str] | None = None
        self._select_stack: list[dict[str, Any]] = []
        self._option_buf: list[str] | None = None
        self._option_attrs: dict[str, str] | None = None
        self._button_buf: list[str] | None = None
        self._button_attrs: dict[str, str] | None = None
        self.standalone_inputs: list[dict[str, str]] = []
        self.meta_tags: list[dict[str, str]] = []

    @staticmethod
    def _attrs_dict(attrs: list[tuple[str, str | None]]) -> dict[str, str]:
        out: dict[str, str] = {}
        for k, v in attrs:
            key = k.lower()
            out[key] = "" if v is None else v
        return out

    def _current_form(self) -> dict[str, Any] | None:
        return self._form_stack[-1] if self._form_stack else None

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag_l = tag.lower()
        attr_map = self._attrs_dict(attrs)

        if tag_l == "meta":
            self.meta_tags.append(attr_map)
            return

        if tag_l == "form":
            form_obj: dict[str, Any] = {
                "index": len(self.forms),
                "action": attr_map.get("action", ""),
                "method": attr_map.get("method", "get").strip().lower() or "get",
                "enctype": attr_map.get("enctype", "application/x-www-form-urlencoded").strip().lower()
                or "application/x-www-form-urlencoded",
                "attrs": attr_map,
                "inputs": [],
                "buttons": [],
                "selects": [],
                "textareas": [],
                "defaults": {},
            }
            self.forms.append(form_obj)
            self._form_stack.append(form_obj)
            return

        if tag_l == "input":
            if self._current_form() is None:
                self.standalone_inputs.append(attr_map)
                return
            form = self._current_form()
            assert form is not None
            itype = attr_map.get("type", "text").strip().lower() or "text"
            name = attr_map.get("name", "")
            value = attr_map.get("value", "")
            disabled = "disabled" in attr_map
            checked = "checked" in attr_map
            info = {
                "tag": "input",
                "type": itype,
                "name": name,
                "value": value,
                "disabled": disabled,
                "checked": checked,
                "attrs": attr_map,
            }
            form["inputs"].append(info)
            if itype in ("submit", "button", "reset", "image"):
                if not disabled and name and itype in ("submit", "image"):
                    form["buttons"].append(
                        {"tag": "input", "type": itype, "name": name, "value": value, "text": value, "attrs": attr_map}
                    )
                return
            if not disabled and name and itype != "file":
                if itype == "checkbox":
                    if checked:
                        val = attr_map["value"] if "value" in attr_map else "on"
                        existing = form["defaults"].get(name)
                        if existing is None:
                            form["defaults"][name] = val
                        elif isinstance(existing, list):
                            existing.append(val)
                        else:
                            form["defaults"][name] = [existing, val]
                elif itype == "radio":
                    if checked:
                        form["defaults"][name] = attr_map["value"] if "value" in attr_map else "on"
                else:
                    form["defaults"][name] = value
            return

        if tag_l == "textarea" and self._current_form() is not None:
            self._textarea_attrs = attr_map
            self._textarea_buf = []
            return

        if tag_l == "select" and self._current_form() is not None:
            self._select_stack.append(
                {
                    "name": attr_map.get("name", ""),
                    "disabled": "disabled" in attr_map,
                    "multiple": "multiple" in attr_map,
                    "attrs": attr_map,
                    "options": [],
                }
            )
            return

        if tag_l == "option" and self._select_stack:
            self._option_attrs = attr_map
            self._option_buf = []
            return

        if tag_l == "button" and self._current_form() is not None:
            self._button_attrs = attr_map
            self._button_buf = []
            return

    def handle_data(self, data: str) -> None:
        if self._textarea_buf is not None:
            self._textarea_buf.append(data)
        if self._option_buf is not None:
            self._option_buf.append(data)
        if self._button_buf is not None:
            self._button_buf.append(data)

    def handle_endtag(self, tag: str) -> None:
        tag_l = tag.lower()

        if tag_l == "textarea" and self._textarea_buf is not None and self._textarea_attrs is not None:
            form = self._current_form()
            text_val = "".join(self._textarea_buf)
            if text_val.startswith("\r\n"):
                text_val = text_val[2:]
            elif text_val.startswith("\n"):
                text_val = text_val[1:]
            name = self._textarea_attrs.get("name", "")
            disabled = "disabled" in self._textarea_attrs
            if form is not None:
                form["textareas"].append(
                    {"name": name, "value": text_val, "disabled": disabled, "attrs": self._textarea_attrs}
                )
                if not disabled and name:
                    form["defaults"][name] = text_val
            self._textarea_buf = None
            self._textarea_attrs = None
            return

        if tag_l == "option" and self._option_buf is not None and self._option_attrs is not None:
            text_val = re.sub(r"\s+", " ", "".join(self._option_buf)).strip()
            opt_val = self._option_attrs.get("value", text_val)
            if self._select_stack:
                self._select_stack[-1]["options"].append(
                    {
                        "value": opt_val,
                        "text": text_val,
                        "selected": "selected" in self._option_attrs,
                        "disabled": "disabled" in self._option_attrs,
                        "attrs": self._option_attrs,
                    }
                )
            self._option_buf = None
            self._option_attrs = None
            return

        if tag_l == "select" and self._select_stack:
            sel = self._select_stack.pop()
            form = self._current_form()
            if form is not None:
                form["selects"].append(sel)
                name = sel["name"]
                if not sel["disabled"] and name:
                    opts = sel["options"]
                    if sel["multiple"]:
                        selected_vals = [o["value"] for o in opts if o["selected"] and not o["disabled"]]
                        if selected_vals:
                            form["defaults"][name] = selected_vals
                    else:
                        selected_opts = [o["value"] for o in opts if o["selected"] and not o["disabled"]]
                        if selected_opts:
                            form["defaults"][name] = selected_opts[-1]
                        else:
                            first_enabled = next((o["value"] for o in opts if not o["disabled"]), None)
                            if first_enabled is not None:
                                form["defaults"][name] = first_enabled
            return

        if tag_l == "button" and self._button_buf is not None and self._button_attrs is not None:
            form = self._current_form()
            btn_text = re.sub(r"\s+", " ", "".join(self._button_buf)).strip()
            btype = self._button_attrs.get("type", "submit").strip().lower() or "submit"
            name = self._button_attrs.get("name", "")
            value = self._button_attrs.get("value", "")
            disabled = "disabled" in self._button_attrs
            if form is not None and not disabled and btype == "submit":
                form["buttons"].append(
                    {
                        "tag": "button",
                        "type": btype,
                        "name": name,
                        "value": value,
                        "text": btn_text,
                        "attrs": self._button_attrs,
                    }
                )
            self._button_buf = None
            self._button_attrs = None
            return

        if tag_l == "form" and self._form_stack:
            self._form_stack.pop()


class _NavHTMLParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.links: list[dict[str, Any]] = []
        self._container_stack: list[tuple[str, str]] = []
        self._current_link: dict[str, Any] | None = None
        self._link_buf: list[str] = []

    @staticmethod
    def _attrs_dict(attrs: list[tuple[str, str | None]]) -> dict[str, str]:
        return {k.lower(): ("" if v is None else v) for k, v in attrs}

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag_l = tag.lower()
        attr_map = self._attrs_dict(attrs)
        if tag_l in ("nav", "aside"):
            self._container_stack.append((tag_l, attr_map.get("class", "")))
        elif tag_l == "a" and self._container_stack and self._current_link is None:
            container, container_cls = self._container_stack[-1]
            self._current_link = {
                "href": attr_map.get("href", ""),
                "text": "",
                "container": container,
                "container_class": container_cls,
                "attrs": attr_map,
            }
            self._link_buf = []

    def handle_data(self, data: str) -> None:
        if self._current_link is not None:
            self._link_buf.append(data)

    def handle_endtag(self, tag: str) -> None:
        tag_l = tag.lower()
        if tag_l == "a" and self._current_link is not None:
            self._current_link["text"] = re.sub(r"\s+", " ", "".join(self._link_buf)).strip()
            self.links.append(self._current_link)
            self._current_link = None
            self._link_buf = []
        elif tag_l in ("nav", "aside") and self._container_stack:
            for idx in range(len(self._container_stack) - 1, -1, -1):
                if self._container_stack[idx][0] == tag_l:
                    del self._container_stack[idx:]
                    break


_BADGE_TAGS = {"span", "div", "small", "strong", "a", "em", "b"}
_BADGE_CLASSES = {"status", "badge", "status-badge", "role-badge"}


class _BadgeHTMLParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.badges: list[dict[str, Any]] = []
        self._stack: list[tuple[str, dict[str, Any] | None, list[str]]] = []

    @staticmethod
    def _attrs_dict(attrs: list[tuple[str, str | None]]) -> dict[str, str]:
        return {k.lower(): ("" if v is None else v) for k, v in attrs}

    @staticmethod
    def _is_badge(tag: str, attr_map: dict[str, str]) -> bool:
        if tag not in _BADGE_TAGS:
            return False
        if "data-status" in attr_map or "data-badge" in attr_map:
            return True
        classes = attr_map.get("class", "").split()
        for cls in classes:
            if cls in _BADGE_CLASSES or cls.startswith("badge-") or cls.startswith("status-"):
                return True
        return False

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag_l = tag.lower()
        attr_map = self._attrs_dict(attrs)
        if self._is_badge(tag_l, attr_map):
            classes = attr_map.get("class", "").split()
            status_val = attr_map.get("data-status") or attr_map.get("data-badge") or ""
            if not status_val:
                for cls in classes:
                    if cls not in _BADGE_CLASSES:
                        if cls.startswith("status-"):
                            status_val = cls[len("status-") :]
                            break
                        if cls.startswith("badge-"):
                            status_val = cls[len("badge-") :]
                            break
                        status_val = cls
                        break
            badge = {
                "tag": tag_l,
                "classes": classes,
                "text": "",
                "status": status_val,
                "attrs": attr_map,
            }
            self._stack.append((tag_l, badge, []))
        else:
            self._stack.append((tag_l, None, []))

    def handle_data(self, data: str) -> None:
        for _, badge, buf in self._stack:
            if badge is not None:
                buf.append(data)

    def handle_endtag(self, tag: str) -> None:
        tag_l = tag.lower()
        for idx in range(len(self._stack) - 1, -1, -1):
            if self._stack[idx][0] == tag_l:
                _, badge, buf = self._stack.pop(idx)
                del self._stack[idx:]
                if badge is not None:
                    text = re.sub(r"\s+", " ", "".join(buf)).strip()
                    badge["text"] = text
                    if not badge["status"]:
                        badge["status"] = text.lower()
                    self.badges.append(badge)
                break


def parse_forms(html_text: str) -> list[dict[str, Any]]:
    """Parse HTML <form> elements, inputs, selects, textareas, buttons, and defaults."""
    parser = _FormHTMLParser()
    parser.feed(html_text)
    parser.close()
    return parser.forms


_CSRF_NAMES = ("csrf_token", "_csrf_token", "_token", "csrf", "token", "authenticity_token")


def extract_csrf(html_text: str, form_index: int | None = None) -> str | None:
    """Extract a CSRF token from parsed forms, standalone inputs, or meta tags."""
    parser = _FormHTMLParser()
    parser.feed(html_text)
    parser.close()

    candidate_inputs: list[dict[str, Any]] = []
    if form_index is not None:
        if 0 <= form_index < len(parser.forms):
            candidate_inputs.extend(parser.forms[form_index]["inputs"])
    else:
        for form in parser.forms:
            candidate_inputs.extend(form["inputs"])
        for inp in parser.standalone_inputs:
            candidate_inputs.append({"name": inp.get("name", ""), "value": inp.get("value", "")})

    by_name: dict[str, str] = {}
    for inp in candidate_inputs:
        name = str(inp.get("name", "")).strip().lower()
        if name in _CSRF_NAMES and name not in by_name:
            by_name[name] = str(inp.get("value", ""))

    for target in _CSRF_NAMES:
        if target in by_name and by_name[target] != "":
            return by_name[target]
    for target in _CSRF_NAMES:
        if target in by_name:
            return by_name[target]

    for meta in parser.meta_tags:
        mname = meta.get("name", "").strip().lower()
        if mname in ("csrf-token", "csrf_token"):
            return html.unescape(meta.get("content", ""))

    return None


def extract_nav_links(html_text: str) -> list[dict[str, Any]]:
    """Extract navigation links inside <nav> and <aside> containers."""
    parser = _NavHTMLParser()
    parser.feed(html_text)
    parser.close()
    return parser.links


def extract_status_badges(html_text: str) -> list[dict[str, Any]]:
    """Extract status/role badge elements from HTML markup."""
    parser = _BadgeHTMLParser()
    parser.feed(html_text)
    parser.close()
    return parser.badges


class HttpClient:
    """Session-scoped HTTP client backed by urllib.request and http.cookiejar."""

    def __init__(self, base_url: str = BASE_URL, timeout: float = HTTP_TIMEOUT) -> None:
        self.base_url: str = base_url.rstrip("/")
        self.timeout: float = float(timeout)
        self.cookie_jar: http.cookiejar.CookieJar = http.cookiejar.CookieJar()
        self._opener = urllib.request.build_opener(
            urllib.request.ProxyHandler({}),
            urllib.request.HTTPCookieProcessor(self.cookie_jar),
            _NoRedirectHandler(),
        )

    def resolve_url(self, path: str) -> str:
        if path.startswith("http://") or path.startswith("https://"):
            return path
        parsed_base = urllib.parse.urlsplit(self.base_url)
        base_path = parsed_base.path.rstrip("/")
        if path.startswith("/"):
            if base_path and (path == base_path or path.startswith(base_path + "/")):
                return f"{parsed_base.scheme}://{parsed_base.netloc}{path}"
            if path == "/":
                return f"{self.base_url}/"
            return f"{self.base_url}/{path.lstrip('/')}"
        if path == "":
            return f"{self.base_url}/"
        return f"{self.base_url}/{path.lstrip('/')}"

    def get(
        self,
        path: str,
        follow_redirects: bool = True,
        headers: Mapping[str, str] | None = None,
        params: Mapping[str, Any] | Sequence[tuple[str, Any]] | None = None,
    ) -> HttpResponse:
        url = self.resolve_url(path)
        if params is not None:
            parts = urllib.parse.urlsplit(url)
            existing = urllib.parse.parse_qsl(parts.query, keep_blank_values=True)
            extra = list(params.items()) if isinstance(params, Mapping) else list(params)
            combined = existing + [(k, v) for k, v in extra if v is not None]
            query = urllib.parse.urlencode(combined, doseq=True)
            url = urllib.parse.urlunsplit((parts.scheme, parts.netloc, parts.path, query, parts.fragment))
        return self._request("GET", url, body_bytes=None, headers=headers, follow_redirects=follow_redirects)

    def post(
        self,
        path: str,
        data: Mapping[str, Any] | Sequence[tuple[str, Any]] | bytes | bytearray | str | None = None,
        files: Mapping[str, Any] | Sequence[tuple[str, Any]] | None = None,
        follow_redirects: bool = True,
        headers: Mapping[str, str] | None = None,
    ) -> HttpResponse:
        url = self.resolve_url(path)
        req_headers = dict(headers) if headers else {}
        has_ctype = any(k.lower() == "content-type" for k in req_headers)

        if files is not None:
            body_bytes, content_type = encode_multipart_formdata(data=data, files=files)
            req_headers["Content-Type"] = content_type
        elif isinstance(data, (bytes, bytearray)):
            body_bytes = bytes(data)
            if not has_ctype:
                req_headers["Content-Type"] = "application/x-www-form-urlencoded; charset=utf-8"
        elif isinstance(data, str):
            body_bytes = data.encode("utf-8")
            if not has_ctype:
                req_headers["Content-Type"] = "application/x-www-form-urlencoded; charset=utf-8"
        elif data is not None:
            items = list(data.items()) if isinstance(data, Mapping) else list(data)
            filtered = [(k, v) for k, v in items if v is not None]
            body_bytes = urllib.parse.urlencode(filtered, doseq=True).encode("utf-8")
            if not has_ctype:
                req_headers["Content-Type"] = "application/x-www-form-urlencoded; charset=utf-8"
        else:
            body_bytes = b""

        return self._request("POST", url, body_bytes=body_bytes, headers=req_headers, follow_redirects=follow_redirects)

    def _request(
        self,
        method: str,
        url: str,
        body_bytes: bytes | None = None,
        headers: Mapping[str, str] | None = None,
        follow_redirects: bool = True,
        max_redirects: int = 10,
    ) -> HttpResponse:
        current_method = method.upper()
        current_url = url
        current_body = body_bytes
        current_headers = dict(headers) if headers else {}
        history: list[tuple[int, str]] = []
        history_headers: list[Message] = []

        while True:
            req = urllib.request.Request(
                current_url,
                data=current_body if current_method == "POST" else None,
                headers=current_headers,
                method=current_method,
            )
            try:
                resp = self._opener.open(req, timeout=self.timeout)
                status = int(getattr(resp, "status", resp.getcode()))
                resp_headers = resp.headers
                resp_body = resp.read()
                resp.close()
            except urllib.error.HTTPError as exc:
                status = int(exc.code)
                resp_headers = exc.headers
                resp_body = exc.read()
                exc.close()

            location = resp_headers.get("Location") if resp_headers is not None else None
            if follow_redirects and status in (301, 302, 303, 307, 308) and location:
                if len(history) >= max_redirects:
                    raise RuntimeError(f"Exceeded max redirects ({max_redirects}) starting from {url}")
                next_url = urllib.parse.urljoin(current_url, location.strip().replace(" ", "%20"))
                history.append((status, next_url))
                history_headers.append(resp_headers)
                current_url = next_url
                if status in (301, 302, 303) and current_method == "POST":
                    current_method = "GET"
                    current_body = None
                    current_headers = {
                        k: v
                        for k, v in current_headers.items()
                        if k.lower() not in ("content-type", "content-length")
                    }
                continue

            return HttpResponse(
                status=status,
                url=current_url,
                headers=resp_headers,
                body=resp_body,
                history=history,
                history_headers=history_headers,
            )

    def get_cookie_obj(self, name: str) -> http.cookiejar.Cookie | None:
        self.cookie_jar.clear_expired_cookies()
        for cookie in self.cookie_jar:
            if cookie.name == name:
                return cookie
        return None

    def get_cookie(self, name: str) -> str | None:
        cookie = self.get_cookie_obj(name)
        return None if cookie is None else cookie.value

    def set_cookie(
        self,
        name: str,
        value: str,
        domain: str | None = None,
        path: str = "/",
        secure: bool = False,
        expires: int | None = None,
        http_only: bool = False,
    ) -> None:
        if not domain:
            parsed = urllib.parse.urlsplit(self.base_url)
            host = parsed.hostname or "localhost"
            stripped = host.lstrip(".")
            if "." not in stripped:
                eff_domain = f"{stripped}.local"
                domain_specified = False
                domain_initial_dot = False
            else:
                eff_domain = host
                domain_specified = False
                domain_initial_dot = False
        else:
            stripped = domain.lstrip(".")
            if "." not in stripped:
                eff_domain = f"{stripped}.local"
                domain_specified = False
                domain_initial_dot = False
            else:
                eff_domain = domain
                domain_specified = True
                domain_initial_dot = domain.startswith(".")

        rest = {"HttpOnly": ""} if http_only else {}
        cookie = http.cookiejar.Cookie(
            version=0,
            name=name,
            value=value,
            port=None,
            port_specified=False,
            domain=eff_domain,
            domain_specified=domain_specified,
            domain_initial_dot=domain_initial_dot,
            path=path,
            path_specified=True,
            secure=secure,
            expires=expires,
            discard=expires is None,
            comment=None,
            comment_url=None,
            rest=rest,
            rfc2109=False,
        )
        self.cookie_jar.set_cookie(cookie)

    def clear_cookies(self) -> None:
        self.cookie_jar.clear()

    def export_cookies(self) -> list[dict[str, Any]]:
        self.cookie_jar.clear_expired_cookies()
        exported: list[dict[str, Any]] = []
        for c in self.cookie_jar:
            dom = c.domain or "localhost"
            if dom == "localhost.local":
                dom = "localhost"
            elif dom.endswith(".local") and "." not in dom[:-6]:
                dom = dom[:-6]
            rest = getattr(c, "_rest", {}) or {}
            exported.append(
                {
                    "name": c.name,
                    "value": c.value,
                    "domain": dom,
                    "path": c.path or "/",
                    "secure": bool(c.secure),
                    "httpOnly": any(str(k).lower() == "httponly" for k in rest),
                    "expires": c.expires,
                }
            )
        return exported

    def parse_forms(self, html_text: str) -> list[dict[str, Any]]:
        return parse_forms(html_text)

    def extract_csrf(self, html_text: str, form_index: int | None = None) -> str | None:
        return extract_csrf(html_text, form_index=form_index)

    def extract_nav_links(self, html_text: str) -> list[dict[str, Any]]:
        return extract_nav_links(html_text)

    def extract_status_badges(self, html_text: str) -> list[dict[str, Any]]:
        return extract_status_badges(html_text)

    def submit_form(
        self,
        path_or_html: str | HttpResponse,
        overrides: Mapping[str, Any] | None = None,
        *,
        form_index: int = 0,
        form_action_contains: str | None = None,
        form_id: str | None = None,
        files: Mapping[str, Any] | Sequence[tuple[str, Any]] | None = None,
        follow_redirects: bool = True,
        base_url: str | None = None,
        headers: Mapping[str, str] | None = None,
    ) -> HttpResponse:
        if isinstance(path_or_html, HttpResponse):
            html_text = path_or_html.text
            page_url = base_url or path_or_html.url
        elif isinstance(path_or_html, str) and "<form" in path_or_html.lower():
            html_text = path_or_html
            page_url = self.resolve_url(base_url) if base_url else f"{self.base_url}/"
        else:
            resp = self.get(str(path_or_html), follow_redirects=True)
            html_text = resp.text
            page_url = base_url or resp.url

        forms = parse_forms(html_text)
        if not forms:
            raise ValueError(f"No <form> found in {page_url}")

        target_form: dict[str, Any] | None = None
        if form_id is not None:
            for f in forms:
                if f["attrs"].get("id") == form_id:
                    target_form = f
                    break
            if target_form is None:
                raise ValueError(f"No <form id={form_id!r}> found in {page_url}")
        elif form_action_contains is not None:
            for f in forms:
                if form_action_contains in f["action"]:
                    target_form = f
                    break
            if target_form is None:
                raise ValueError(f"No <form action*={form_action_contains!r}> found in {page_url}")
        else:
            if not (0 <= form_index < len(forms)):
                raise IndexError(f"form_index {form_index} out of range for {len(forms)} forms in {page_url}")
            target_form = forms[form_index]

        payload: dict[str, Any] = {}
        for k, v in target_form["defaults"].items():
            payload[k] = list(v) if isinstance(v, list) else v

        override_keys = set(overrides.keys()) if overrides else set()
        named_buttons = [b for b in target_form["buttons"] if b.get("name")]
        if len(named_buttons) == 1:
            btn = named_buttons[0]
            if btn["name"] not in override_keys:
                payload[btn["name"]] = btn["value"]

        if overrides:
            for k, v in overrides.items():
                if v is None:
                    payload.pop(k, None)
                else:
                    payload[k] = v

        action = target_form["action"].strip()
        target_url = urllib.parse.urljoin(page_url, action) if action else page_url

        method = target_form["method"].lower()
        if method == "get":
            parts = urllib.parse.urlsplit(target_url)
            filtered = [(k, v) for k, v in payload.items() if v is not None]
            query = urllib.parse.urlencode(filtered, doseq=True)
            get_url = urllib.parse.urlunsplit((parts.scheme, parts.netloc, parts.path, query, parts.fragment))
            return self._request("GET", get_url, headers=headers, follow_redirects=follow_redirects)

        use_multipart = files is not None or target_form["enctype"] == "multipart/form-data"
        if use_multipart:
            return self.post(
                target_url,
                data=payload,
                files=files if files is not None else {},
                follow_redirects=follow_redirects,
                headers=headers,
            )
        return self.post(
            target_url,
            data=payload,
            files=None,
            follow_redirects=follow_redirects,
            headers=headers,
        )
