<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

// Fetch stats from DB safely
try {
    $totalMaterials  = (int) db()->query("SELECT COUNT(*) FROM materials WHERE status='approved'")->fetchColumn();
    $totalUsers      = (int) db()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    $totalCategories = (int) db()->query("SELECT COUNT(*) FROM categories")->fetchColumn();
} catch (Throwable $e) {
    $totalMaterials  = 0;
    $totalUsers      = 0;
    $totalCategories = 0;
}

$layoutTitle = 'Tentang EduShare';
include __DIR__ . '/partials/header.php';
?>

<section class="page-intro">
    <span class="eyebrow">TENTANG KAMI</span>
    <h1>Tentang EduShare</h1>
    <p>Platform berbagi materi pembelajaran terbuka untuk semua kalangan pelajar Indonesia.</p>
</section>

<!-- Mission -->
<section class="section" style="padding-top:0">
    <div class="about-mission" style="
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:40px;
        align-items:center;
        margin-bottom:60px;">
        <div>
            <span class="eyebrow">MISI KAMI</span>
            <h2 style="font-size:clamp(22px,3vw,32px);margin:12px 0 18px">
                Ilmu adalah hak semua orang.<br>
                <em>Ayo wujudkan bersama.</em>
            </h2>
            <p style="color:#77758b;line-height:1.75;margin:0 0 16px">
                EduShare lahir dari keyakinan bahwa setiap pelajar berhak mendapatkan akses ke materi berkualitas,
                tanpa batasan biaya maupun jarak. Kami membangun ruang digital di mana siapa pun bisa berbagi ilmu
                dan saling mendukung pertumbuhan bersama.
            </p>
            <p style="color:#77758b;line-height:1.75;margin:0">
                Dari catatan kuliah, modul belajar, hingga referensi riset—semua tersedia dalam satu tempat yang
                terkurasi, mudah dicari, dan bebas diakses.
            </p>
        </div>
        <div style="
            background:linear-gradient(135deg,#5142d8 0%,#7c6ef5 100%);
            border-radius:24px;
            padding:40px;
            color:#fff;
            text-align:center;">
            <span class="eyebrow" style="color:#fff;background:rgba(255,255,255,0.2);padding:6px 14px;border-radius:20px;display:inline-block;margin-bottom:16px;">EDUSHARE</span>
            <h3 style="margin:0 0 8px;color:#fff;font-size:20px">Ruang Belajar Terbuka</h3>
            <p style="margin:0;opacity:0.85;font-size:14px;line-height:1.6">
                Diunggah oleh komunitas, dikurasi oleh admin, dimanfaatkan oleh semua.
            </p>
        </div>
    </div>

    <!-- Stats -->
    <div style="
        display:grid;
        grid-template-columns:repeat(3,1fr);
        gap:24px;
        margin-bottom:60px;">
        <div class="stat-card" style="
            background:#fff;
            border:1px solid #eceaf2;
            border-radius:18px;
            padding:28px 24px;
            text-align:center;
            box-shadow:0 4px 20px rgba(81,66,216,0.06);">
            <div style="font-size:40px;font-weight:800;color:#5142d8;line-height:1">
                <?= number_format($totalMaterials) ?>
            </div>
            <div style="margin-top:8px;color:#77758b;font-size:14px;font-weight:500">Materi Tersedia</div>
        </div>
        <div class="stat-card" style="
            background:#fff;
            border:1px solid #eceaf2;
            border-radius:18px;
            padding:28px 24px;
            text-align:center;
            box-shadow:0 4px 20px rgba(81,66,216,0.06);">
            <div style="font-size:40px;font-weight:800;color:#5142d8;line-height:1">
                <?= number_format($totalUsers) ?>
            </div>
            <div style="margin-top:8px;color:#77758b;font-size:14px;font-weight:500">Pengguna Aktif</div>
        </div>
        <div class="stat-card" style="
            background:#fff;
            border:1px solid #eceaf2;
            border-radius:18px;
            padding:28px 24px;
            text-align:center;
            box-shadow:0 4px 20px rgba(81,66,216,0.06);">
            <div style="font-size:40px;font-weight:800;color:#5142d8;line-height:1">
                <?= number_format($totalCategories) ?>
            </div>
            <div style="margin-top:8px;color:#77758b;font-size:14px;font-weight:500">Kategori Pelajaran</div>
        </div>
    </div>

    <!-- Features -->
    <div style="margin-bottom:60px">
        <div class="section-heading">
            <div>
                <span class="eyebrow">APA YANG KAMI TAWARKAN</span>
                <h2>Fitur unggulan EduShare</h2>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px;margin-top:32px">
            <?php
            $features = [
                ['01', 'Akses Gratis',         'Semua materi dapat diakses tanpa biaya berlangganan apapun.'],
                ['02', 'Upload Mudah',          'Bagikan materi kamu dalam hitungan menit melalui formulir sederhana.'],
                ['03', 'Konten Terkurasi',      'Setiap materi diperiksa admin sebelum ditampilkan ke publik.'],
                ['04', 'Pencarian Cerdas',      'Temukan materi yang kamu butuhkan dengan cepat lewat fitur pencarian.'],
                ['05', 'Komunitas Belajar',     'Bergabung dengan ribuan pelajar yang saling mendukung.'],
                ['06', 'Multi-format',          'Mendukung PDF, DOCX, PPTX, dan berbagai format dokumen lainnya.'],
            ];
            foreach ($features as [$num, $title, $desc]): ?>
                <div style="
                    background:#fff;
                    border:1px solid #eceaf2;
                    border-radius:16px;
                    padding:24px 20px;
                    box-shadow:0 2px 12px rgba(81,66,216,0.05);
                    transition:box-shadow 0.2s ease,transform 0.2s ease;"
                    onmouseenter="this.style.boxShadow='0 8px 30px rgba(81,66,216,0.12)';this.style.transform='translateY(-2px)'"
                    onmouseleave="this.style.boxShadow='0 2px 12px rgba(81,66,216,0.05)';this.style.transform=''">
                    <div style="
                        width:38px;height:38px;
                        background:#ede9fe;
                        border-radius:10px;
                        display:flex;align-items:center;justify-content:center;
                        font-size:13px;
                        font-weight:700;
                        margin-bottom:14px;
                        color:#5142d8">
                        <?= $num ?>
                    </div>
                    <h3 style="margin:0 0 8px;font-size:16px;color:#242238"><?= e($title) ?></h3>
                    <p style="margin:0;color:#77758b;font-size:13px;line-height:1.65"><?= e($desc) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Team / Contact -->
    <div style="
        background:linear-gradient(135deg,#f8f7ff 0%,#ede9fe 100%);
        border:1px solid #d9d4f5;
        border-radius:24px;
        padding:48px 40px;
        text-align:center;
        margin-bottom:40px">
        <span class="eyebrow">HUBUNGI KAMI</span>
        <h2 style="margin:12px 0 16px">Ada pertanyaan atau masukan?</h2>
        <p style="color:#77758b;max-width:520px;margin:0 auto 28px;line-height:1.75">
            Kami selalu terbuka untuk masukan, laporan masalah, atau kolaborasi.
            Kirimkan pesan melalui saluran di bawah ini.
        </p>
        <div style="display:flex;justify-content:center;gap:16px;flex-wrap:wrap">
            <a class="btn btn-primary" href="mailto:hello@edushare.id">Email Kami</a>
            <a class="btn btn-outline" href="<?= e(url('materi.php')) ?>">Jelajahi Materi</a>
        </div>
        <div style="margin-top:36px;padding-top:28px;border-top:1px solid #d9d4f5">
            <p style="color:#77758b;font-size:13px;margin:0 0 16px;font-weight:600;letter-spacing:.05em;text-transform:uppercase">
                Diciptakan dengan bangga oleh Tim EduShare
            </p>
            <div style="display:flex;justify-content:center;gap:32px;flex-wrap:wrap">
                <?php
                $team = [
                    ['Dev Team',      'Platform & Backend'],
                    ['Design Team',   'UI/UX & Branding'],
                    ['Content Team',  'Kurasi & Moderasi'],
                ];
                foreach ($team as [$name, $role]): ?>
                    <div style="text-align:center">
                        <div style="
                            width:48px;height:48px;
                            background:linear-gradient(135deg,#5142d8,#7c6ef5);
                            border-radius:50%;
                            margin:0 auto 8px;
                            display:flex;align-items:center;justify-content:center;
                            color:#fff;font-weight:700;font-size:16px">
                            <?= strtoupper(substr($name, 0, 1)) ?>
                        </div>
                        <div style="font-weight:600;font-size:14px;color:#242238"><?= e($name) ?></div>
                        <div style="font-size:12px;color:#77758b"><?= e($role) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <?php if (!is_logged_in()): ?>
    <div style="text-align:center;padding:16px 0">
        <p style="color:#77758b;margin:0 0 20px">Siap bergabung dan mulai berbagi ilmu?</p>
        <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap">
            <a class="btn btn-primary" href="<?= e(url('register.php')) ?>">Daftar Sekarang</a>
            <a class="btn btn-outline" href="<?= e(url('login.php')) ?>">Sudah punya akun? Masuk</a>
        </div>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:16px 0">
        <p style="color:#77758b;margin:0 0 20px">Sudah bergabung? Ayo mulai berbagi materi bermanfaat.</p>
        <a class="btn btn-primary" href="<?= e(url('user/upload.php')) ?>">Upload Materi</a>
    </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
