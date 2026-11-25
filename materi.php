<?php
require_once __DIR__ . '/config/app.php';
$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['kategori'] ?? 0);
$categories = $pdo->query("SELECT * FROM categories ORDER BY nama_kategori")->fetchAll();
$sql = "SELECT m.*, c.nama_kategori, u.nama AS pengunggah FROM materials m JOIN categories c ON c.id_category=m.id_category JOIN users u ON u.id_user=m.id_user WHERE m.status='approved'";
$params = [];
if ($q !== '') {
    $sql .= " AND (m.judul LIKE ? OR m.deskripsi LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat) {
    $sql .= " AND m.id_category=?";
    $params[] = $cat;
}
$sql .= " ORDER BY m.tanggal_upload DESC";
$s = $pdo->prepare($sql);
$s->execute($params);
$materials = $s->fetchAll();
$layoutTitle = 'Jelajahi Materi';
include __DIR__ . '/partials/header.php';
?>
<section class="page-intro">
  <span class="eyebrow">PERPUSTAKAAN DIGITAL</span>
  <h1>Jelajahi materi</h1>
  <p>Temukan sumber belajar yang sesuai dengan hal yang ingin kamu pelajari.</p>
</section>
<form class="search-form wide-search" action="materi.php" method="get">
  <input name="q" placeholder="Cari judul atau topik materi..." value="<?= e($q) ?>">
  <button class="btn btn-primary" type="submit">Cari</button>
</form>
<div class="filter-row" id="kategori">
  <a class="filter-pill <?= $cat === 0 ? 'active' : '' ?>" href="materi.php">Semua</a>
  <?php foreach($categories as $c): ?>
    <a class="filter-pill <?= $cat === $c['id_category'] ? 'active' : '' ?>" href="?kategori=<?= $c['id_category'] ?>"><?= e($c['nama_kategori']) ?></a>
  <?php endforeach; ?>
</div>
<p class="result-count"><?= count($materials) ?> materi ditemukan</p>
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
          <p><?= e(mb_strimwidth($m['deskripsi'], 0, 100, '…')) ?></p>
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
    <h3>Materi belum ditemukan</h3>
    <p>Coba kata kunci atau kategori yang berbeda.</p>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
