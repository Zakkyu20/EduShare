# 🎬 NASKAH LENGKAP VIDEO PRESENTASI PROYEK EDUSHARE
**Judul Proyek:** EduShare — Platform Repositori & Kolaborasi Materi Edukasi Berbasis Multi-Role Access Control (RBAC)  
**Estimasi Durasi Total:** 8 – 10 Menit  
**Target Audiens:** Dosen Penguji, Rekan Akademik, Reviewer Portofolio / Klien

---

## 📋 DAFTAR PERSIAPAN SEBELUM RECORDING (CHECKLIST)

Sebelum menekan tombol **Record** di OBS Studio / Screen Recorder, siapkan hal-hal berikut:

1. **Web Server & Database:**
   - Apache & MySQL di XAMPP Control Panel sudah berstatus **Running** (Hijau).
   - URL utama: `http://localhost/edushare/`

2. **Setup Tab Browser (Disarankan menggunakan 1 jendela browser dengan 4 tab siap pakai):**
   - **Tab 1 (Incognito / Mode Penyamaran):** `http://localhost/edushare/` (untuk demo Guest).
   - **Tab 2:** Halaman Login `http://localhost/edushare/login.php` (siap login User: `user@edushare.com`).
   - **Tab 3:** Halaman Login / Dashboard Admin `http://localhost/edushare/admin/dashboard.php` (`admin@edushare.com`).
   - **Tab 4:** Halaman Login / Dashboard Super Admin `http://localhost/edushare/superadmin/dashboard.php` (`superadmin@edushare.com`).
   *(Semua password akun default: `admin123`)*

3. **File Dummy untuk Uji Upload:**
   - Siapkan 1 file PDF materi (misal: `Modul_Algoritma_Dasar.pdf`) di Desktop agar siap di-drag/pilih saat demo upload materi.

---

## ⏱️ RUNDOWN & STRUKTUR SCENE

| Scene | Waktu | Topik Demo | Peran yang Didemokan |
|---|---|---|---|
| **Scene 1** | 00:00 - 01:00 | Pembuka & Latar Belakang EduShare | Presenter |
| **Scene 2** | 01:00 - 02:15 | Pengalaman Pengunjung (Katalog Publik & Guarding) | Guest / Publik |
| **Scene 3** | 02:15 - 04:15 | Alur Siswa (Dashboard, Upload & Manajemen Materi) | Pengguna (User) |
| **Scene 4** | 04:15 - 06:15 | Alur Kurasi & Moderasi Konten | Administrator |
| **Scene 5** | 06:15 - 08:00 | Kontrol Penuh Arsitektur & Keamanan RBAC | Super Administrator |
| **Scene 6** | 08:00 - 09:00 | Keunggulan Arsitektur Teknis & Keamanan | Presenter / Code Insight |
| **Scene 7** | 09:00 - 09:30 | Kesimpulan & Penutup | Presenter |

---

## 📜 SKRIP DETAIL KATA-DEMI-KATA (SCENE BY SCENE)

### 📌 SCENE 1: PEMBUKA & LATAR BELAKANG (00:00 - 01:00)

**[Visual / Aksi Layar]:**
- Kamera aktif menampilkan wajah presenter atau langsung menampilkan Landing Page EduShare (`http://localhost/edushare/`).
- Kursor berada di area hero banner dengan judul *“Platform Berbagi Materi Edukasi”*.

**[Audio / Narasi Pembicara]:**
> *"Halo semuanya, perkenalkan nama saya **[Nama Anda]**. Pada kesempatan kali ini, saya akan mendemonstrasikan hasil pengembangan proyek aplikasi web yang saya bangun, yaitu **EduShare**.*
>
> *Latar belakang dikembangkannya EduShare berawal dari permasalahan distribusi materi akademik yang sering kali tersebar tidak terstruktur di berbagai grup percakapan atau penyimpanan awan tanpa adanya standardisasi, kurasi kelayakan isi, maupun pembagian hak akses yang jelas.*
>
> *EduShare hadir sebagai platform repositori dan kurasi materi edukasi terpadu. Sistem ini menerapkan arsitektur **Multi-Tier Role-Based Access Control (RBAC)** dengan 4 tingkatan entitas: mulai dari Pengunjung Publik (Guest), Pengguna Terdaftar (Siswa/Mahasiswa), Administrator Konten, hingga Super Administrator sebagai pemegang kendali master sistem. Mari kita mulai demonstrasi alurnya secara langsung."*

---

### 📌 SCENE 2: PENGALAMAN PENGUNJUNG (GUEST EXPERIENCE) (01:00 - 02:15)

**[Visual / Aksi Layar]:**
- Buka jendela browser dalam mode Guest/Incognito pada `http://localhost/edushare/`.
- Gulir perlahan melewati hero section, statistik materi, kategori pilihan, dan daftar materi terbaru.
- Klik menu navbar **"Jelajahi Materi"** (`materi.php`).
- Ketik kata kunci pencarian pada search bar (misal: *"PHP"* atau *"Basis Data"*), lalu coba klik salah satu filter kategori.
- Klik salah satu kartu materi untuk masuk ke halaman detail. Tunjukkan bahwa informasi materi dan preview dapat dilihat, namun saat mengklik tombol **"Download Materi"**, sistem secara otomatis menahan dan menampilkan notifikasi atau mengarahkan ke halaman login.

**[Audio / Narasi Pembicara]:**
> *"Sekarang kita berada di sudut pandang pertama, yaitu **Guest atau Pengunjung Publik**.*
>
> *Di halaman utama ini, pengunjung dapat melihat antarmuka landing page modern yang bersih, informatif, dan responsif. Pengunjung dapat mengeksplorasi katalog materi edukasi terbuka melalui halaman 'Jelajahi Materi'. Di sini tersedia fitur pencarian instan dan filter berdasarkan kategori keilmuan.*
>
> *Namun, untuk menjaga integritas data dan keamanan distribusi file, sistem menerapkan aturan autentikasi: pengunjung dapat melihat detail dan ringkasan materi, tetapi jika ingin mengunduh file materi secara penuh, sistem akan mengarahkan pengguna untuk mendaftar atau masuk terlebih dahulu. Sekarang, mari kita beralih ke peran Pengguna Terdaftar."*

---

### 📌 SCENE 3: PENGALAMAN SISWA / USER (USER EXPERIENCE) (02:15 - 04:15)

**[Visual / Aksi Layar]:**
- Masuk ke halaman `login.php`.
- Masukkan kredensial: `user@edushare.com` / `admin123`.
- Klik tombol **"Masuk"**. Sistem mengarahkan langsung ke dashboard siswa (`user/dashboard.php`).
- Sorot kartu ringkasan: Total Materi Saya, Materi Disetujui, Menunggu Review, dan Ditolak.
- Klik menu sidebar **"Upload Materi"** (`user/upload.php`).
- Isi form:
  - Judul: *"Rangkuman Praktikum Basis Data Relasional"*
  - Kategori: Pilih kategori (misal: *Teknologi Informasi*)
  - Deskripsi: Ketik deskripsi singkat materi.
  - Upload File: Pilih file PDF contoh.
- Klik **"Simpan & Ajukan"**. Tunjukkan notifikasi sukses dan materi langsung muncul di tabel *Materi Saya* dengan status badge kuning bertuliskan **"Menunggu Review"**.
- Buka halaman **"Profil Saya"** (`user/profil.php`). Tunjukkan layout kartu data diri, ringkasan keaktifan, dan form perubahan kata sandi yang rapi dan terisolasi.

**[Audio / Narasi Pembicara]:**
> *"Sekarang saya masuk sebagai **Pengguna Terdaftar** atau Siswa.*
>
> *Setelah berhasil melakukan autentikasi, pengguna langsung disambut di Dashboard Pribadi. Dashboard ini menyajikan rekap analitik personal: berapa banyak materi yang telah diunggah, berapa yang sudah disetujui, sedang ditinjau, maupun yang ditolak.*
>
> *Salah satu modul utama bagi pengguna adalah **Upload Materi**. Mari kita coba mengunggah materi baru. Di formulir ini, sistem tidak hanya menerima input judul dan deskripsi, tetapi juga melakukan validasi ketat terhadap berkas yang diunggah—seperti pemeriksaan ekstensi, MIME type dokumen, dan batasan ukuran berkas untuk mencegah injeksi file berbahaya.*
>
> *Setelah berkas dikirimkan, materi tidak langsung tayang ke publik, melainkan berstatus **'Menunggu Review'**. Ini adalah bagian dari alur kurasi kualitas konten yang dimiliki EduShare. Selain itu, pengguna juga memiliki halaman **Profil Saya** yang memungkinkan pembaruan data identitas dan pergantian password secara aman dengan enkripsi hash bcrypt."*

---

### 📌 SCENE 4: ALUR ADMINISTRATOR (MODERASI & KURASI KONTEN) (04:15 - 06:15)

**[Visual / Aksi Layar]:**
- Logout dari akun user, lalu login menggunakan akun Administrator (`admin@edushare.com` / `admin123`).
- Masuk ke dashboard admin (`admin/dashboard.php`).
- Tunjukkan metrik admin: Total Materi Masuk, Menunggu Verifikasi, Total Kategori, dan Total Pengguna.
- Klik menu sidebar **"Verifikasi Materi"** (`admin/verifikasi.php`).
- Cari materi yang baru saja diunggah oleh user pada demo sebelumnya.
- Tunjukkan tombol aksi: **Setujui (Approve)** dan **Tolak (Reject)**.
- Klik tombol **"Tolak"** untuk memperlihatkan adanya modal dialog alasan penolakan, lalu coba klik **"Setujui"** pada materi yang valid.
- Buka menu **"Kelola Kategori"** (`admin/kategori.php`). Tunjukkan form penambahan kategori baru serta tabel daftar kategori materi yang terstruktur.

**[Audio / Narasi Pembicara]:**
> *"Sekarang, kita beralih ke tingkatan peran ketiga, yaitu **Administrator Konten**.*
>
> *Peran Admin bertugas sebagai kurator mutu materi edukasi sebelum dapat diakses oleh publik luas. Di dashboard admin, disajikan statistik operasional konten harian.*
>
> *Masuk ke modul **Verifikasi Materi**. Di sini terlihat antrean berkas yang diajukan oleh para pengguna. Admin dapat memeriksa pratinjau dokumen, membaca deskripsi, dan memutuskan apakah materi tersebut layak terbit.*
>
> *Jika materi tidak sesuai standar, Admin dapat memilih opsi 'Tolak' lengkap dengan catatan alasan agar pengunggah dapat melakukan revisi. Apabila materi valid dan bermutu baik, Admin cukup menekan tombol 'Setujui'. Seketika itu juga status materi berubah menjadi **Disetujui** dan langsung terbit di katalog publik.*
>
> *Admin juga memiliki hak untuk mengelola taksonomi materi pada modul **Kelola Kategori**, memastikan repositori tetap tertata rapi sesuai bidang keilmuan."*

---

### 📌 SCENE 5: SUPER ADMINISTRATOR (MASTER CONTROL & RBAC GOVERNANCE) (06:15 - 08:00)

**[Visual / Aksi Layar]:**
- Logout dari Admin, lalu login sebagai Super Administrator (`superadmin@edushare.com` / `admin123`).
- Masuk ke `superadmin/dashboard.php`. Tunjukkan tampilan statistik tingkat enterprise (Total User, Total Admin, Total Materi, Log Keamanan).
- Buka menu **"Manajemen Pengguna"** (`superadmin/users.php`). Tunjukkan bahwa Super Admin dapat menambahkan akun Administrator baru, mengaktifkan/menonaktifkan akun pengguna, maupun me-reset password.
- Buka menu **"Hak Akses & Role (RBAC)"** (`superadmin/roles.php`). Sorot matriks permissions di mana setiap privilege (seperti `manage_users`, `verify_content`, `manage_roles`) dapat dipetakan secara dinamis ke tiap role.
- Buka menu **"Audit Log / Log Aktivitas"** (`superadmin/audit_log.php`). Tunjukkan catatan forensik sistem yang merekam IP address, timestamp, aksi login, pembuatan materi, dan perubahan konfigurasi secara real-time.

**[Audio / Narasi Pembicara]:**
> *"Kini kita berada pada puncak hierarki sistem, yaitu **Super Administrator**.*
>
> *Super Admin memiliki hak kendali penuh atas tata kelola sistem, otorisasi, dan pengawasan keamanan. Di halaman Dashboard Super Admin, disajikan ringkasan eksekutif seluruh aktivitas platform.*
>
> *Pada modul **Manajemen Pengguna**, Super Admin memiliki wewenang eksklusif untuk membuat akun staf Administrator baru, mengubah status akun pengguna, maupun melakukan penangguhan akses apabila ditemukan pelanggaran.*
>
> *Keunggulan utama arsitektur EduShare terletak pada modul **Hak Akses & Role**. Di sini, sistem menggunakan database-driven permission mapping. Setiap peran dipetakan ke izin spesifik, sehingga batasan kewenangan setiap tingkatan akun bersifat dinamis dan terlindungi secara ketat di sisi backend.*
>
> *Terakhir, untuk memenuhi standar keamanan dan akuntabilitas, EduShare dilengkapi modul **Audit Log**. Setiap interaksi penting—mulai dari autentikasi, persetujuan materi, hingga perubahan data—tercatat rapi dengan rincian waktu, identitas pengguna, dan alamat IP untuk kebutuhan audit forensik digital."*

---

### 📌 SCENE 6: KEUNGGULAN ARSITEKTUR & KEAMANAN SISTEM (08:00 - 09:00)

**[Visual / Aksi Layar]:**
- *(Opsional)* Tampilkan sejenak layar VS Code atau diagram alur di browser yang memperlihatkan struktur keamanan kode (misal `middleware/auth.php` atau `database/edushare.sql`), atau arahkan kursor ke browser dengan menampilkan dokumentasi teknis EduShare (`dokumentasi/dokumentasi_edushare.html`).

**[Audio / Narasi Pembicara]:**
> *"Dari sisi implementasi teknis dan rekayasa perangkat lunak, platform EduShare dibangun dengan mengedepankan standar keamanan industri:*
>
> 1. *Pertama, **Pencegahan SQL Injection**: Seluruh transaksi basis data menggunakan PDO dengan Prepared Statements dan parameterized queries.*
> 2. *Kedua, **Keamanan Kredensial**: Menggunakan algoritma hashing modern **Bcrypt** melalui fungsi native PHP `password_hash()` dan `password_verify()`.*
> 3. *Ketiga, **Session Hardening**: Dilengkapi dengan mekanisme regenerasi ID sesi saat login untuk mencegah serangan Session Fixation dan Session Hijacking.*
> 4. *Keempat, **File Security**: Validasi berlapis pada proses upload berkas, mencakup pembatasan ekstensi whitelist, validasi MIME type, dan enkripsi penamaan berkas fisik di server storage.*
> 5. *Kelima, **Antarmuka Responsif & Bebas Ketergantungan Eksternal**: Tampilan UI didesain clean menggunakan CSS modern dan ikon vektor inline SVG murni, memastikan performa loading yang sangat cepat dan tampilan yang presisi di semua resolusi layar."*

---

### 📌 SCENE 7: KESIMPULAN & PENUTUP (09:00 - 09:30)

**[Visual / Aksi Layar]:**
- Kembali ke Landing Page EduShare (`http://localhost/edushare/`) atau beralih ke kamera penuh presenter.

**[Audio / Narasi Pembicara]:**
> *"Sebagai kesimpulan, EduShare bukan sekadar platform penyimpanan berkas biasa, melainkan solusi manajemen pengetahuan yang aman, terkurasi, dan memiliki tata kelola hak akses bertingkat yang andal untuk kebutuhan institusi pendidikan maupun komunitas pembelajar.*
>
> *Demikian presentasi dan demonstrasi sistem EduShare ini saya sampaikan. Terima kasih banyak atas perhatian Bapak/Ibu dosen dan rekan-rekan sekalian. Kritik dan saran yang membangun sangat saya nantikan untuk pengembangan platform ini ke depannya.*
>
> *Wassalamu'alaikum Warahmatullahi Wabarakatuh / Salam Sejahtera bagi kita semua."*

---

## 💡 TIPS & TRIK SAAT MEREKAM VIDEO

1. **Kecepatan Bicara (Pacing):** Jangan terlalu terburu-buru. Berikan jeda 1–2 detik setelah mengklik tombol di layar sebelum mulai menjelaskan hasilnya, agar audiens dapat melihat perubahan UI dengan jelas.
2. **Kursor Mouse:** Gerakkan kursor dengan tenang dan terarah ke tombol atau elemen yang sedang dibicarakan. Hindari menggerak-gerakkan kursor berputar-putar tanpa tujuan.
3. **Resolusi Layar:** Rekam dengan resolusi standar **1080p (1920x1080)** dengan rasio 16:9. Pastikan zoom browser berada di **100%**.
4. **Volume Audio:** Gunakan microphone clip-on atau headset dengan filter noise kecil agar suara narasi terdengar renyah dan artikulatif.
