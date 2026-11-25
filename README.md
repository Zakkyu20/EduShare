# EduShare

Starter project website berbagi materi pembelajaran gratis, menggunakan PHP Native, MySQL, HTML, CSS, dan JavaScript murni.

## Fitur yang tersedia
- Beranda publik dengan pencarian dan kategori.
- Daftar materi dan detail materi.
- Registrasi dan login menggunakan `password_hash`.
- Upload materi oleh pengguna (PDF, DOC, DOCX, PPT, PPTX; maksimal 10 MB).
- Status moderasi: pending, approved, rejected.
- Download materi hanya untuk pengguna login dan materi approved.
- Dashboard pengguna dan daftar materi miliknya.
- Dashboard admin, verifikasi, kelola materi, kategori, dan pengguna.

## Instalasi di XAMPP
1. Ekstrak folder `edushare` ke `C:\xampp\htdocs\` (Windows) atau folder htdocs XAMPP.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Import file `database.sql`.
5. Periksa `config/database.php`. Default XAMPP: user `root`, password kosong.
6. Buka `http://localhost/edushare/`.

### Alternatif menjalankan dengan PHP built-in server
- Dari folder `C:\\xampp\\htdocs\\edushare`, jalankan `php -S localhost:8000`, lalu buka `http://localhost:8000/`.
- Atau dari folder `C:\\xampp\\htdocs`, jalankan `php -S localhost:8000`, lalu buka `http://localhost:8000/edushare/`.
- URL aset dan navigasi sekarang mendeteksi lokasi aplikasi secara otomatis.
7. Daftar akun melalui menu Daftar.
8. Jadikan akun admin lewat phpMyAdmin, tab SQL:
   ```sql
   USE edushare;
   UPDATE users SET role='admin' WHERE email='email-yang-kamu-daftarkan@example.com';
   ```
9. Logout lalu login kembali. Menu Dashboard akan membuka dashboard sesuai role.

## Catatan
- Pastikan folder `uploads/` dapat ditulisi oleh web server.
- Untuk mengubah batas ukuran upload, sesuaikan validasi di `user/upload.php` serta `upload_max_filesize` dan `post_max_size` pada `php.ini`.
- Proyek ini adalah starter untuk lokal/pembelajaran, belum disiapkan untuk deployment publik. Sebelum online, tambahkan CSRF protection, rate limiting, logging, kebijakan privasi, pemeriksaan MIME file yang lebih ketat, dan konfigurasi penyimpanan file di luar web root.
- URL aset, navigasi, dan redirect menggunakan helper `url()` yang mendeteksi lokasi aplikasi secara otomatis.
