<?php
require_once __DIR__ . '/config/app.php';
$q = trim($_GET['q'] ?? '');
$sql = "SELECT m.*, c.nama_kategori, u.nama AS pengunggah
        FROM materials m JOIN categories c ON c.id_category=m.id_category
        JOIN users u ON u.id_user=m.id_user WHERE m.status='approved'";
$params = [];
if ($q !== '') {
    $sql .= " AND (m.judul LIKE ? OR m.deskripsi LIKE ? OR c.nama_kategori LIKE ?)";
    $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= " ORDER BY m.tanggal_upload DESC LIMIT 8";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();
$categories = $pdo->query("SELECT c.*, COUNT(m.id_material) AS jumlah FROM categories c LEFT JOIN materials m ON m.id_category=c.id_category AND m.status='approved' GROUP BY c.id_category ORDER BY c.nama_kategori")->fetchAll();
$layoutTitle = 'Beranda';
include __DIR__ . '/partials/header.php';
?>
<section class="hero">
  <div class="hero-copy">
    <span class="eyebrow">RUANG BELAJAR TERBUKA</span>
    <h1>Belajar lebih mudah.<br><em>Berbagi ilmu lebih luas.</em></h1>
    <p>Temukan materi pembelajaran, bagikan catatan, dan tumbuh bersama komunitas belajar di EduShare.</p>
    <form class="search-form" action="materi.php" method="get">
      <input name="q" placeholder="Mau belajar apa hari ini?" value="<?= e($q) ?>">
      <button class="btn btn-primary" type="submit">Cari materi</button>
    </form>
    <div class="hero-note">Belajar bersama, saling berbagi pengetahuan.</div>
  </div>
  <div class="hero-art">
    <div class="art-orbit"></div>
    <div class="art-card art-main">
      <span class="eyebrow" style="margin-bottom:8px;">EduShare</span>
      <b>Materi untuk semua</b>
      <span>Belajar tanpa batas</span>
    </div>
    <div class="art-card art-float">
      <div>
        <b>Berbagi pengetahuan</b>
        <small>Mulai dari hal sederhana</small>
      </div>
    </div>
    <div class="art-dot"></div>
  </div>
</section>

<section class="benefits">
  <div><b>Akses mudah</b><small>Temukan materi dalam satu tempat</small></div>
  <div><b>Berbagi ilmu</b><small>Bagikan catatan dan referensi</small></div>
  <div><b>Materi terkurasi</b><small>Materi diperiksa sebelum tayang</small></div>
  <div><b>Belajar bersama</b><small>Dukung kesempatan belajar</small></div>
</section>

<section class="section">
  <div class="section-heading">
    <div><span class="eyebrow">MULAI MENJELAJAH</span><h2>Kategori pembelajaran</h2></div>
    <a href="materi.php">Lihat semua materi</a>
  </div>
  <div class="category-grid">
    <?php foreach($categories as $c): ?>
      <a class="category-card" href="materi.php?kategori=<?= $c['id_category'] ?>">
        <b><?= e($c['nama_kategori']) ?></b>
        <small><?= (int)$c['jumlah'] ?> materi</small>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <div class="section-heading">
    <div><span class="eyebrow">DIPERBARUI UNTUKMU</span><h2>Materi terbaru</h2></div>
    <a href="materi.php">Jelajahi materi</a>
  </div>
  <?php if($materials): ?>
    <div class="material-grid">
      <?php foreach($materials as $m): ?>
        <article class="material-card">
          <div class="material-cover">
            <span><?= e(strtoupper($m['format_file'])) ?></span>
          </div>
          <div class="material-body">
            <small class="muted"><?= e($m['nama_kategori']) ?></small>
            <h3><?= e($m['judul']) ?></h3>
            <p><?= e(mb_strimwidth($m['deskripsi'], 0, 95, '…')) ?></p>
            <div class="card-meta">
              <span><?= e($m['pengunggah']) ?></span>
              <a href="detail.php?id=<?= $m['id_material'] ?>">Lihat detail</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <h3>Belum ada materi yang dipublikasikan</h3>
      <p>Materi yang sudah disetujui admin akan tampil di sini.</p>
    </div>
  <?php endif; ?>
</section>

<section class="cta">
  <div>
    <span class="eyebrow">PUNYA MATERI BERMANFAAT?</span>
    <h2>Ilmu akan lebih berarti<br>ketika dibagikan.</h2>
    <p>Bagikan rangkuman atau bahan belajarmu untuk membantu pelajar lain.</p>
  </div>
  <a class="btn btn-light" href="<?= is_logged_in() ? 'user/upload.php' : 'register.php' ?>">Mulai berbagi</a>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>
