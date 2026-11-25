"""Automated verification suite for the 14 required EduShare Multi-Role (RBAC) scenarios."""

from __future__ import annotations

import sys
from pathlib import Path

_TESTS_DIR = Path(__file__).resolve().parent
if str(_TESTS_DIR) not in sys.path:
    sys.path.insert(0, str(_TESTS_DIR))

import unittest
from helpers.config import BASE_URL
from helpers.db_helper import DbHelper
from helpers.fixtures import FixtureManager
from helpers.http_client import HttpClient


class TestEduShare14Scenarios(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.db = DbHelper()
        cls.fixtures = FixtureManager(cls.db)

        # Ensure seed passwords for test users
        cls.user_email = cls.fixtures.unique_email("scenario_user")
        cls.user_pass = "UserPass123!"
        cls.admin_email = "admin@edushare.com"
        cls.admin_pass = "admin123"
        cls.superadmin_email = "superadmin@edushare.com"
        cls.superadmin_pass = "admin123"

        # Create test user
        user_obj = cls.fixtures.create_user(
            nama="Test User Scenario",
            email=cls.user_email,
            password=cls.user_pass,
            role="user",
            status="active",
        )
        cls.test_user_id = user_obj.id_user

        # Set known passwords for admin and super admin in testing
        cls.db.execute(
            "UPDATE users SET password = ? WHERE email = ?",
            (cls.fixtures.hash_password(cls.admin_pass), cls.admin_email),
        )
        cls.db.execute(
            "UPDATE users SET password = ? WHERE email = ?",
            (cls.fixtures.hash_password(cls.superadmin_pass), cls.superadmin_email),
        )
        cls.db.execute(
            "UPDATE users SET password = ? WHERE email = ?",
            (cls.fixtures.hash_password("admin123"), "user@edushare.com"),
        )

    @classmethod
    def tearDownClass(cls):
        cls.fixtures.cleanup()

    def _login(self, client: HttpClient, email: str, password: str):
        """Helper to log in using HttpClient.submit_form."""
        return client.submit_form(
            "/login.php",
            overrides={"email": email, "password": password},
        )

    def _post_with_csrf(self, client: HttpClient, path: str, data: dict):
        """Helper to post to a page with CSRF token."""
        page = client.get(path)
        token = client.extract_csrf(page.text)
        payload = dict(data)
        if token:
            payload["csrf_token"] = token
            payload["_csrf_token"] = token
        return client.post(path, data=payload)

    def test_01_pengunjung_buka_beranda_tanpa_login(self):
        """Scenario 1: Pengunjung membuka beranda tanpa login."""
        client = HttpClient()
        res = client.get("/index.php")
        self.assertEqual(res.status, 200)
        self.assertIn("EduShare", res.text)
        self.assertIn("Beranda", res.text)
        self.assertIn("Materi", res.text)
        self.assertIn("Kategori", res.text)
        self.assertIn("Tentang", res.text)
        self.assertIn("Login", res.text)
        self.assertIn("Daftar", res.text)

    def test_02_pengunjung_buka_dashboard_pengguna_ditolak(self):
        """Scenario 2: Pengunjung mencoba membuka dashboard pengguna (ditolak/redirect)."""
        client = HttpClient()
        res = client.get("/user/dashboard.php", follow_redirects=False)
        self.assertTrue(
            res.status in (302, 303, 403) or "login.php" in res.url or "403" in res.text,
            f"Expected redirect or 403, got status={res.status}",
        )

    def test_03_dan_04_pengguna_login_dan_redirect_ke_dashboard_user(self):
        """Scenario 3 & 4: Pengguna login dan diarahkan ke dashboard pengguna."""
        client = HttpClient()
        login_res = self._login(client, self.user_email, self.user_pass)
        self.assertEqual(login_res.status, 200)
        self.assertTrue(
            "user/dashboard.php" in login_res.url or "user/dashboard" in login_res.url,
            f"Expected redirect to user dashboard, got url={login_res.url}",
        )
        self.assertIn("Halo,", login_res.text)
        self.assertIn("Total Materi", login_res.text)

    def test_05_pengguna_buka_dashboard_admin_ditolak(self):
        """Scenario 5: Pengguna mencoba membuka dashboard admin (403 Forbidden atau penolakan)."""
        client = HttpClient()
        self._login(client, self.user_email, self.user_pass)

        res = client.get("/admin/dashboard.php", follow_redirects=False)
        self.assertTrue(
            res.status in (403, 302, 303) or "403" in res.text,
            f"Expected 403 or redirect away, got status={res.status}",
        )

    def test_06_dan_07_admin_login_dan_redirect_ke_dashboard_admin(self):
        """Scenario 6 & 7: Admin login dan diarahkan ke dashboard administrator."""
        client = HttpClient()
        login_res = self._login(client, self.admin_email, self.admin_pass)
        self.assertEqual(login_res.status, 200)
        self.assertTrue(
            "admin/dashboard.php" in login_res.url or "admin/dashboard" in login_res.url,
            f"Expected redirect to admin dashboard, got url={login_res.url}",
        )
        self.assertIn("Dashboard Admin", login_res.text)

    def test_08_admin_verifikasi_materi(self):
        """Scenario 8: Admin memverifikasi materi (menyetujui atau menolak dengan alasan)."""
        cat_id = int(self.db.scalar("SELECT id_category FROM categories WHERE is_active = 1 LIMIT 1"))
        mat = self.fixtures.create_material(
            id_user=self.test_user_id,
            id_category=cat_id,
            judul="Materi Uji Verifikasi Admin",
            deskripsi="Deskripsi pengujian verifikasi",
            status="pending",
        )
        mat_id = int(mat)

        client = HttpClient()
        self._login(client, self.admin_email, self.admin_pass)

        # 1. Admin rejects with reason
        reason = "Format penulisan belum memenuhi standar kurikulum."
        reject_res = self._post_with_csrf(
            client,
            "/admin/verifikasi.php",
            {"id": str(mat_id), "action": "reject", "rejection_reason": reason},
        )
        self.assertIn(reject_res.status, (200, 302))

        # Check DB for rejection
        mat_status = self.db.scalar("SELECT status FROM materials WHERE id_material = ?", (mat_id,))
        mat_reason = self.db.scalar("SELECT rejection_reason FROM materials WHERE id_material = ?", (mat_id,))
        self.assertEqual(mat_status, "rejected")
        self.assertEqual(mat_reason, reason)

        # 2. Admin approves material
        approve_res = self._post_with_csrf(
            client,
            "/admin/verifikasi.php",
            {"id": str(mat_id), "action": "approve"},
        )
        self.assertIn(approve_res.status, (200, 302))

        # Check DB for approval
        mat_status_after = self.db.scalar("SELECT status FROM materials WHERE id_material = ?", (mat_id,))
        self.assertEqual(mat_status_after, "approved")

    def test_09_admin_buka_halaman_super_admin_ditolak(self):
        """Scenario 9: Admin mencoba membuka halaman super admin (harus ditolak/403)."""
        client = HttpClient()
        self._login(client, self.admin_email, self.admin_pass)

        res = client.get("/superadmin/dashboard.php", follow_redirects=False)
        self.assertTrue(
            res.status in (403, 302, 303) or "403" in res.text,
            f"Admin access to superadmin dashboard must be forbidden, got status={res.status}",
        )

    def test_10_dan_11_super_admin_login_dan_redirect_ke_dashboard_superadmin(self):
        """Scenario 10 & 11: Super admin login dan diarahkan ke dashboard super admin."""
        client = HttpClient()
        login_res = self._login(client, self.superadmin_email, self.superadmin_pass)
        self.assertEqual(login_res.status, 200)
        self.assertTrue(
            "superadmin/dashboard.php" in login_res.url or "superadmin/dashboard" in login_res.url,
            f"Expected redirect to superadmin dashboard, got url={login_res.url}",
        )
        self.assertIn("Dashboard Super Admin", login_res.text)

    def test_12_super_admin_buat_akun_administrator(self):
        """Scenario 12: Super admin membuat akun administrator baru dan akun dapat login."""
        client = HttpClient()
        self._login(client, self.superadmin_email, self.superadmin_pass)

        new_adm_email = self.fixtures.unique_email("new_admin")
        new_adm_pass = "AdminBaru123!"

        create_res = self._post_with_csrf(
            client,
            "/superadmin/admins.php",
            {
                "form_action": "create_admin",
                "nama": "Admin Baru Dibuat",
                "email": new_adm_email,
                "password": new_adm_pass,
            },
        )
        self.assertIn(create_res.status, (200, 302))

        # Check DB
        role = self.db.scalar("SELECT role FROM users WHERE email = ?", (new_adm_email,))
        self.assertEqual(role, "admin")

        # Now test that the new admin can log in
        adm_client = HttpClient()
        adm_login_res = self._login(adm_client, new_adm_email, new_adm_pass)
        self.assertEqual(adm_login_res.status, 200)
        self.assertTrue(
            "admin/dashboard.php" in adm_login_res.url or "admin/dashboard" in adm_login_res.url,
            f"New admin failed to log in: {adm_login_res.url}",
        )

    def test_13_super_admin_ubah_hak_akses(self):
        """Scenario 13: Super admin mengubah hak akses di tabel role_permissions."""
        client = HttpClient()
        self._login(client, self.superadmin_email, self.superadmin_pass)

        perm_key = "manage_categories"
        # Set to 0
        res0 = self._post_with_csrf(
            client,
            "/superadmin/roles.php",
            {"role": "admin", "permission_key": perm_key, "is_allowed": "0"},
        )
        self.assertIn(res0.status, (200, 302))
        val0 = self.db.scalar(
            "SELECT is_allowed FROM role_permissions WHERE role = 'admin' AND permission_key = ?",
            (perm_key,),
        )
        self.assertEqual(int(val0), 0)

        # Set back to 1
        res1 = self._post_with_csrf(
            client,
            "/superadmin/roles.php",
            {"role": "admin", "permission_key": perm_key, "is_allowed": "1"},
        )
        self.assertIn(res1.status, (200, 302))
        val1 = self.db.scalar(
            "SELECT is_allowed FROM role_permissions WHERE role = 'admin' AND permission_key = ?",
            (perm_key,),
        )
        self.assertEqual(int(val1), 1)

    def test_14_seluruh_role_logout_dan_sesi_terhapus(self):
        """Scenario 14: Seluruh role logout dan sesi terhapus sehingga akses dashboard kembali ditolak."""
        roles_to_test = [
            (self.user_email, self.user_pass, "/user/dashboard.php"),
            (self.admin_email, self.admin_pass, "/admin/dashboard.php"),
            (self.superadmin_email, self.superadmin_pass, "/superadmin/dashboard.php"),
        ]

        for email, password, target_dash in roles_to_test:
            client = HttpClient()
            self._login(client, email, password)

            # Verify logged in
            dash_res = client.get(target_dash)
            self.assertEqual(dash_res.status, 200)

            # Perform logout
            logout_res = client.get("/logout.php", follow_redirects=True)
            self.assertEqual(logout_res.status, 200)

            # Attempt accessing dashboard again without session
            recheck = client.get(target_dash, follow_redirects=False)
            self.assertTrue(
                recheck.status in (302, 303, 403) or "login.php" in recheck.url or "403" in recheck.text,
                f"Session was not destroyed for {email} on {target_dash}",
            )


if __name__ == "__main__":
    unittest.main()
