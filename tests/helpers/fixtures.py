"""Test fixture factory and baseline-preserving cleanup for EduShare E2E tests."""

from __future__ import annotations

import io
import os
import subprocess
import uuid
import zipfile
from dataclasses import dataclass
from typing import Any

from .config import (
    PHP_BIN,
    PROTECTED_CATEGORY_IDS,
    PROTECTED_USER_IDS,
    SUPERADMIN_EMAIL,
    SUPERADMIN_PASSWORD,
    TEST_PREFIX,
    UPLOADS_DIR,
)
from .db_helper import DbHelper
from .http_client import parse_forms


@dataclass
class CreatedUser:
    """Represents a test user created by FixtureManager."""

    id_user: int
    nama: str
    email: str
    password: str
    role: str
    status: str = "active"


_MINIMAL_PDF_BYTES = (
    b"%PDF-1.4\n"
    b"1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
    b"2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n"
    b"3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\n"
    b"xref\n"
    b"0 4\n"
    b"0000000000 65535 f \n"
    b"0000000009 00000 n \n"
    b"0000000054 00000 n \n"
    b"0000000105 00000 n \n"
    b"trailer<</Size 4/Root 1 0 R>>\n"
    b"startxref\n"
    b"172\n"
    b"%%EOF\n"
)


class FixtureManager:
    """Creates uniquely prefixed test records/files and safely cleans them up."""

    def __init__(
        self,
        db: DbHelper,
        http: Any | None = None,
        uploads_dir: str | os.PathLike[str] = UPLOADS_DIR,
        run_id: str | None = None,
    ) -> None:
        self.db: DbHelper = db
        self.http: Any | None = http
        self.uploads_dir: str = os.fspath(uploads_dir)
        self.run_id: str = run_id or uuid.uuid4().hex[:8]
        self._seq: int = 0
        self.tracked_users: set[int] = set()
        self.tracked_categories: set[int] = set()
        self.tracked_materials: set[int] = set()
        self.tracked_files: set[str] = set()

    @property
    def prefix(self) -> str:
        if self.run_id.startswith(TEST_PREFIX):
            return self.run_id
        return f"{TEST_PREFIX}{self.run_id}"

    def _next_seq(self) -> int:
        self._seq += 1
        return self._seq

    def unique_email(self, label: str = "user") -> str:
        return f"{self.prefix}_{label}_{self._next_seq()}@e2e.edushare.local"

    def unique_name(self, label: str = "item") -> str:
        return f"{self.prefix}_{label}_{self._next_seq()}"

    def hash_password(self, plain: str) -> str:
        proc = subprocess.run(
            [
                self.db.php_bin if self.db else PHP_BIN,
                "-d",
                "display_errors=0",
                "-d",
                "html_errors=0",
                "-r",
                "echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);",
            ],
            input=plain.encode("utf-8"),
            capture_output=True,
            timeout=15,
            shell=False,
        )
        out = proc.stdout.decode("utf-8", errors="replace").strip()
        if proc.returncode != 0 or not out:
            err = proc.stderr.decode("utf-8", errors="replace").strip()
            raise RuntimeError(f"PHP password_hash failed (rc={proc.returncode}): {err}")
        return out

    def track_user(self, id_user: int) -> None:
        uid = int(id_user)
        if uid in PROTECTED_USER_IDS:
            raise ValueError(f"Refusing to track protected baseline user id_user={uid}")
        self.tracked_users.add(uid)

    def track_category(self, id_category: int) -> None:
        cid = int(id_category)
        if cid in PROTECTED_CATEGORY_IDS:
            raise ValueError(f"Refusing to track protected baseline category id_category={cid}")
        self.tracked_categories.add(cid)

    def track_material(self, id_material: int) -> None:
        self.tracked_materials.add(int(id_material))

    def track_file(self, filename: str) -> None:
        base = os.path.basename(filename)
        if not base or base == ".htaccess":
            raise ValueError(f"Refusing to track protected filename {filename!r}")
        self.tracked_files.add(base)

    def create_user(
        self,
        role: str = "user",
        status: str = "active",
        password: str = "TestPass123!",
        nama: str | None = None,
        email: str | None = None,
        via_http: bool = False,
    ) -> CreatedUser:
        final_nama = nama or self.unique_name(role)
        final_email = email or self.unique_email(role)

        if via_http:
            if self.http is None:
                raise ValueError("via_http=True requires FixtureManager(http=...) to be set")
            if role != "user":
                raise ValueError("Public registration via_http only supports role='user'")
            reg_page = self.http.get("register.php", follow_redirects=True)
            forms = parse_forms(reg_page.text)
            if not forms:
                raise RuntimeError("No form found on register.php")
            overrides: dict[str, Any] = {
                "nama": final_nama,
                "email": final_email,
                "password": password,
            }
            input_names = {inp["name"] for inp in forms[0]["inputs"] if inp.get("name")}
            for confirm_name in ("confirm", "confirm_password", "password_confirm", "password_confirmation"):
                if confirm_name in input_names:
                    overrides[confirm_name] = password
            self.http.submit_form(reg_page, overrides=overrides, follow_redirects=True)
            row = self.db.query(
                "SELECT id_user, nama, email, role FROM users WHERE email = ? LIMIT 1",
                [final_email],
            )
            if not row:
                raise RuntimeError(f"HTTP registration failed to create user {final_email!r}")
            uid = int(row[0]["id_user"])
            self.track_user(uid)
            return CreatedUser(
                id_user=uid,
                nama=str(row[0]["nama"]),
                email=str(row[0]["email"]),
                password=password,
                role=str(row[0]["role"]),
                status=status,
            )

        role_col_type = self.db.column_type("users", "role") or ""
        if f"'{role}'" not in role_col_type:
            raise RuntimeError(
                f"Role {role!r} is not supported by users.role schema ({role_col_type})"
            )

        hashed = self.hash_password(password)
        has_status = self.db.column_exists("users", "status")
        if has_status:
            uid = self.db.insert(
                "INSERT INTO users (nama, email, password, role, status) VALUES (?, ?, ?, ?, ?)",
                [final_nama, final_email, hashed, role, status],
            )
        else:
            uid = self.db.insert(
                "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)",
                [final_nama, final_email, hashed, role],
            )

        self.track_user(uid)
        return CreatedUser(
            id_user=uid,
            nama=final_nama,
            email=final_email,
            password=password,
            role=role,
            status=status,
        )

    def get_superadmin_credentials(self) -> tuple[str, str, CreatedUser | None]:
        """Return (email, password, created_user) from env or an isolated e2e_ super_admin."""
        if SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD:
            return SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD, None
        created = self.create_user(role="super_admin")
        return created.email, created.password, created

    def create_category(
        self,
        nama_kategori: str | None = None,
        deskripsi: str = "E2E test category",
        is_active: int = 1,
    ) -> int:
        final_name = nama_kategori or self.unique_name("cat")
        if self.db.column_exists("categories", "is_active"):
            cid = self.db.insert(
                "INSERT INTO categories (nama_kategori, deskripsi, is_active) VALUES (?, ?, ?)",
                [final_name, deskripsi, int(is_active)],
            )
        else:
            cid = self.db.insert(
                "INSERT INTO categories (nama_kategori, deskripsi) VALUES (?, ?)",
                [final_name, deskripsi],
            )
        self.track_category(cid)
        return cid

    def create_material(
        self,
        id_user: int,
        id_category: int,
        judul: str | None = None,
        deskripsi: str = "E2E test material",
        status: str = "pending",
        format_file: str = "pdf",
        content: bytes | None = None,
        rejection_reason: str | None = None,
        reviewed_by: int | None = None,
    ) -> int:
        final_judul = judul or self.unique_name("mat")
        ext = format_file.lower().lstrip(".") or "pdf"
        if content is None:
            payload = self.minimal_docx_bytes(final_judul) if ext == "docx" else self.minimal_pdf_bytes()
        else:
            payload = content

        os.makedirs(self.uploads_dir, exist_ok=True)
        filename = f"{self.prefix}_{uuid.uuid4().hex[:16]}.{ext}"
        uploads_real = os.path.realpath(self.uploads_dir)
        file_path = os.path.realpath(os.path.join(uploads_real, filename))
        if not file_path.startswith(uploads_real + os.sep):
            raise ValueError(f"Refusing to write file outside uploads_dir: {file_path}")

        with open(file_path, "wb") as fh:
            fh.write(payload)
        self.track_file(filename)

        cols = ["id_user", "id_category", "judul", "deskripsi", "nama_file", "format_file", "status"]
        vals: list[Any] = [int(id_user), int(id_category), final_judul, deskripsi, filename, ext, status]

        if self.db.column_exists("materials", "file_size"):
            cols.append("file_size")
            vals.append(len(payload))
        if rejection_reason is not None and self.db.column_exists("materials", "rejection_reason"):
            cols.append("rejection_reason")
            vals.append(rejection_reason)
        if reviewed_by is not None and self.db.column_exists("materials", "reviewed_by"):
            cols.append("reviewed_by")
            vals.append(int(reviewed_by))
            if self.db.column_exists("materials", "reviewed_at"):
                cols.append("reviewed_at")
                vals.append(self.db.scalar("SELECT NOW()"))

        placeholders = ", ".join(["?"] * len(cols))
        col_list = ", ".join(f"`{c}`" for c in cols)
        mid = self.db.insert(f"INSERT INTO materials ({col_list}) VALUES ({placeholders})", vals)
        self.track_material(mid)
        return mid

    @staticmethod
    def minimal_pdf_bytes() -> bytes:
        return _MINIMAL_PDF_BYTES

    @staticmethod
    def minimal_docx_bytes(text: str = "EduShare E2E DOCX") -> bytes:
        buf = io.BytesIO()
        safe_text = (
            str(text)
            .replace("&", "&amp;")
            .replace("<", "&lt;")
            .replace(">", "&gt;")
        )
        content_types = (
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            '<Default Extension="xml" ContentType="application/xml"/>'
            '<Override PartName="/word/document.xml" '
            'ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            "</Types>"
        )
        rels = (
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            '<Relationship Id="rId1" '
            'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
            'Target="word/document.xml"/>'
            "</Relationships>"
        )
        document_xml = (
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            f"<w:body><w:p><w:r><w:t>{safe_text}</w:t></w:r></w:p></w:body>"
            "</w:document>"
        )
        with zipfile.ZipFile(buf, "w", compression=zipfile.ZIP_DEFLATED) as zf:
            zf.writestr("[Content_Types].xml", content_types.encode("utf-8"))
            zf.writestr("_rels/.rels", rels.encode("utf-8"))
            zf.writestr("word/document.xml", document_xml.encode("utf-8"))
        return buf.getvalue()

    @staticmethod
    def spoofed_pdf_bytes() -> bytes:
        return b"<?php echo 'pwn'; ?>"

    @staticmethod
    def oversized_pdf_bytes(size_bytes: int = 10 * 1024 * 1024 + 1024) -> bytes:
        base = _MINIMAL_PDF_BYTES
        if size_bytes <= len(base):
            return base
        return base + (b"%" + b"0" * (size_bytes - len(base) - 1))

    def assert_baseline_intact(self) -> None:
        if PROTECTED_USER_IDS:
            u_ids = sorted(PROTECTED_USER_IDS)
            u_ph = ",".join(["?"] * len(u_ids))
            user_rows = self.db.query(
                f"SELECT id_user, email, role, status FROM users WHERE id_user IN ({u_ph})",
                u_ids,
            )
            users_by_id = {int(r["id_user"]): r for r in user_rows}
            missing_users = sorted(PROTECTED_USER_IDS - set(users_by_id))
            if missing_users:
                raise AssertionError(
                    f"Baseline integrity failure: missing protected users {missing_users}!"
                )
            if 2 in users_by_id:
                u2 = users_by_id[2]
                if str(u2.get("role", "")) != "super_admin":
                    raise AssertionError(
                        "Baseline integrity failure: users.id_user = 2 must have "
                        f"role='super_admin', got {u2.get('role')!r}!"
                    )
                if u2.get("status") is not None and str(u2["status"]) != "active":
                    raise AssertionError(
                        "Baseline integrity failure: users.id_user = 2 must have "
                        f"status='active', got {u2.get('status')!r}!"
                    )

        if PROTECTED_CATEGORY_IDS:
            c_ids = sorted(PROTECTED_CATEGORY_IDS)
            c_ph = ",".join(["?"] * len(c_ids))
            cats = self.db.query(
                f"SELECT id_category FROM categories WHERE id_category IN ({c_ph})",
                c_ids,
            )
            found_cids = {int(r["id_category"]) for r in cats}
            missing = sorted(PROTECTED_CATEGORY_IDS - found_cids)
            if missing:
                raise AssertionError(
                    f"Baseline integrity failure: missing seeded categories {missing}!"
                )

    def _discover_prefixed_and_child_records(self) -> None:
        self.tracked_users.difference_update(PROTECTED_USER_IDS)
        self.tracked_categories.difference_update(PROTECTED_CATEGORY_IDS)
        pref_like = f"{self.prefix}%"

        if PROTECTED_USER_IDS:
            u_ids = sorted(PROTECTED_USER_IDS)
            u_ph = ",".join(["?"] * len(u_ids))
            user_rows = self.db.query(
                f"SELECT id_user FROM users WHERE (email LIKE ? OR nama LIKE ?) AND id_user NOT IN ({u_ph})",
                [pref_like, pref_like, *u_ids],
            )
        else:
            user_rows = self.db.query(
                "SELECT id_user FROM users WHERE (email LIKE ? OR nama LIKE ?)",
                [pref_like, pref_like],
            )
        for row in user_rows:
            uid = int(row["id_user"])
            if uid not in PROTECTED_USER_IDS:
                self.tracked_users.add(uid)

        if PROTECTED_CATEGORY_IDS:
            c_ids = sorted(PROTECTED_CATEGORY_IDS)
            c_ph = ",".join(["?"] * len(c_ids))
            cat_rows = self.db.query(
                f"SELECT id_category FROM categories WHERE nama_kategori LIKE ? AND id_category NOT IN ({c_ph})",
                [pref_like, *c_ids],
            )
        else:
            cat_rows = self.db.query(
                "SELECT id_category FROM categories WHERE nama_kategori LIKE ?",
                [pref_like],
            )
        for row in cat_rows:
            cid = int(row["id_category"])
            if cid not in PROTECTED_CATEGORY_IDS:
                self.tracked_categories.add(cid)

        clauses = ["judul LIKE ?"]
        params: list[Any] = [pref_like]
        if self.tracked_users:
            u_ph = ",".join(["?"] * len(self.tracked_users))
            clauses.append(f"id_user IN ({u_ph})")
            params.extend(sorted(self.tracked_users))
        if self.tracked_categories:
            c_ph = ",".join(["?"] * len(self.tracked_categories))
            clauses.append(f"id_category IN ({c_ph})")
            params.extend(sorted(self.tracked_categories))

        mat_rows = self.db.query(
            f"SELECT id_material, nama_file FROM materials WHERE {' OR '.join(clauses)}",
            params,
        )
        for row in mat_rows:
            self.tracked_materials.add(int(row["id_material"]))
            fname = row.get("nama_file")
            if fname and os.path.basename(str(fname)) != ".htaccess":
                self.tracked_files.add(os.path.basename(str(fname)))

        if self.tracked_materials:
            m_ph = ",".join(["?"] * len(self.tracked_materials))
            extra_files = self.db.query(
                f"SELECT nama_file FROM materials WHERE id_material IN ({m_ph})",
                sorted(self.tracked_materials),
            )
            for row in extra_files:
                fname = row.get("nama_file")
                if fname and os.path.basename(str(fname)) != ".htaccess":
                    self.tracked_files.add(os.path.basename(str(fname)))

    def cleanup(self) -> None:
        self._discover_prefixed_and_child_records()
        self.tracked_users.difference_update(PROTECTED_USER_IDS)
        self.tracked_categories.difference_update(PROTECTED_CATEGORY_IDS)
        like_pat = f"%{self.prefix}%"

        # 1. audit_logs
        if self.db.table_exists("audit_logs"):
            conds = ["description LIKE ?", "metadata LIKE ?"]
            params: list[Any] = [like_pat, like_pat]
            if self.tracked_users:
                ph = ",".join(["?"] * len(self.tracked_users))
                conds.append(f"actor_id IN ({ph})")
                params.extend(sorted(self.tracked_users))
                conds.append(f"(target_type = 'user' AND target_id IN ({ph}))")
                params.extend(sorted(self.tracked_users))
            if self.tracked_materials:
                ph = ",".join(["?"] * len(self.tracked_materials))
                conds.append(f"(target_type = 'material' AND target_id IN ({ph}))")
                params.extend(sorted(self.tracked_materials))
            if self.tracked_categories:
                ph = ",".join(["?"] * len(self.tracked_categories))
                conds.append(f"(target_type = 'category' AND target_id IN ({ph}))")
                params.extend(sorted(self.tracked_categories))
            self.db.execute(f"DELETE FROM audit_logs WHERE {' OR '.join(conds)}", params)

        # 2. activity_logs
        if self.db.table_exists("activity_logs"):
            conds = ["summary LIKE ?"]
            params = [like_pat]
            if self.tracked_users:
                ph = ",".join(["?"] * len(self.tracked_users))
                conds.append(f"id_user IN ({ph})")
                params.extend(sorted(self.tracked_users))
            if self.tracked_materials:
                ph = ",".join(["?"] * len(self.tracked_materials))
                conds.append(f"(entity_type = 'material' AND entity_id IN ({ph}))")
                params.extend(sorted(self.tracked_materials))
            if self.tracked_categories:
                ph = ",".join(["?"] * len(self.tracked_categories))
                conds.append(f"(entity_type = 'category' AND entity_id IN ({ph}))")
                params.extend(sorted(self.tracked_categories))
            self.db.execute(f"DELETE FROM activity_logs WHERE {' OR '.join(conds)}", params)

        # 3. materials (must precede categories because ON DELETE RESTRICT)
        if self.tracked_materials:
            ph = ",".join(["?"] * len(self.tracked_materials))
            self.db.execute(
                f"DELETE FROM materials WHERE id_material IN ({ph})",
                sorted(self.tracked_materials),
            )

        assert not (self.tracked_categories & PROTECTED_CATEGORY_IDS), (
            f"Refusing to delete protected categories: {sorted(self.tracked_categories & PROTECTED_CATEGORY_IDS)}"
        )
        assert not (self.tracked_users & PROTECTED_USER_IDS), (
            f"Refusing to delete protected users: {sorted(self.tracked_users & PROTECTED_USER_IDS)}"
        )

        # 4. categories (never touch PROTECTED_CATEGORY_IDS)
        safe_cats = sorted(cid for cid in self.tracked_categories if cid not in PROTECTED_CATEGORY_IDS)
        if safe_cats:
            ph = ",".join(["?"] * len(safe_cats))
            self.db.execute(f"DELETE FROM categories WHERE id_category IN ({ph})", safe_cats)

        # 5. users (never touch PROTECTED_USER_IDS)
        safe_users = sorted(uid for uid in self.tracked_users if uid not in PROTECTED_USER_IDS)
        if safe_users:
            ph = ",".join(["?"] * len(safe_users))
            self.db.execute(f"DELETE FROM users WHERE id_user IN ({ph})", safe_users)

        # 6. uploaded test files
        uploads_real = os.path.realpath(self.uploads_dir)
        for fname in list(self.tracked_files):
            base = os.path.basename(fname)
            if not base or base == ".htaccess":
                continue
            target = os.path.realpath(os.path.join(uploads_real, base))
            if target.startswith(uploads_real + os.sep) and os.path.isfile(target):
                try:
                    os.unlink(target)
                except OSError:
                    pass

        self.tracked_materials.clear()
        self.tracked_categories.clear()
        self.tracked_users.clear()
        self.tracked_files.clear()

        self.assert_baseline_intact()
