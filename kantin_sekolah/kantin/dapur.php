<?php
require_once 'koneksi.php';

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$pesan = '';
$jenis_pesan = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'pesanan_id', FILTER_VALIDATE_INT);
    $aksi = $_POST['aksi'] ?? '';
    $transisi = [
        'proses' => ['menunggu', 'diproses'],
        'siap' => ['diproses', 'siap'],
        'selesai' => ['siap', 'diambil'],
    ];

    if (!$id || !isset($transisi[$aksi])) {
        $pesan = 'Permintaan tidak valid.';
        $jenis_pesan = 'error';
    } else {
        [$statusLama, $statusBaru] = $transisi[$aksi];
        $stmt = $conn->prepare('UPDATE pesanan SET status = ? WHERE id = ? AND status = ?');
        $stmt->bind_param('sis', $statusBaru, $id, $statusLama);
        if ($stmt->execute() && $stmt->affected_rows === 1) {
            $pesan = 'Status pesanan berhasil diubah menjadi ' . ucfirst($statusBaru) . '.';
        } else {
            $pesan = 'Status pesanan tidak berubah. Muat ulang halaman dan periksa pesanan.';
            $jenis_pesan = 'error';
        }
        $stmt->close();
    }
}

$sql = "SELECT p.id, p.nomor_antrian, p.total, p.status, p.created_at,
               pd.qty, pd.subtotal, m.nama AS nama_menu
        FROM pesanan p
        LEFT JOIN pesanan_detail pd ON pd.pesanan_id = p.id
        LEFT JOIN menu m ON m.id = pd.menu_id
        WHERE DATE(p.created_at) = CURDATE()
          AND p.status IN ('menunggu', 'diproses', 'siap')
        ORDER BY FIELD(p.status, 'menunggu', 'diproses', 'siap'), p.nomor_antrian ASC, m.nama ASC";
$result = $conn->query($sql);
if (!$result) die('Gagal mengambil pesanan dapur: ' . e($conn->error));

$pesanan = [];
while ($row = $result->fetch_assoc()) {
    $id = (int)$row['id'];
    if (!isset($pesanan[$id])) {
        $pesanan[$id] = [
            'id' => $id,
            'nomor' => (int)$row['nomor_antrian'],
            'total' => (int)$row['total'],
            'status' => $row['status'],
            'waktu' => $row['created_at'],
            'items' => [],
        ];
    }
    if ($row['nama_menu'] !== null) {
        $pesanan[$id]['items'][] = [
            'nama' => $row['nama_menu'],
            'qty' => (int)$row['qty'],
            'subtotal' => (int)$row['subtotal'],
        ];
    }
}

$jumlahMenunggu = 0;
$jumlahDiproses = 0;
$jumlahSiap = 0;
foreach ($pesanan as $item) {
    if ($item['status'] === 'menunggu') $jumlahMenunggu++;
    if ($item['status'] === 'diproses') $jumlahDiproses++;
    if ($item['status'] === 'siap') $jumlahSiap++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Halaman Dapur - Kantin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php"><span class="brand-mark">K</span><span>Kantin<span class="brand-light">Sekolah</span></span></a>
        <nav class="nav">
            <a href="index.php">Kasir</a>
            <a href="display.php">Display Antrean</a>
            <a class="active" href="dapur.php">Dapur</a>
            <a href="admin.php">Kelola Menu</a>
            <a href="laporan.php">Laporan</a>
        </nav>
    </div>
</header>
<main class="page">
    <section class="page-heading">
        <div>
            <p class="eyebrow">PENGELOLAAN PESANAN</p>
            <h1>Halaman Dapur</h1>
            <p class="muted">Terima pesanan dari kasir, proses, lalu tandai jika sudah siap disajikan.</p>
        </div>
        <a class="button button-secondary" href="dapur.php">↻ Muat ulang</a>
    </section>

    <?php if ($pesan): ?><div class="alert alert-<?= e($jenis_pesan) ?>"><?= e($pesan) ?></div><?php endif; ?>

    <section class="stats-grid kitchen-stats">
        <div class="stat-card"><span class="stat-icon">◷</span><div><small>Menunggu</small><strong><?= $jumlahMenunggu ?></strong></div></div>
        <div class="stat-card"><span class="stat-icon">⚙</span><div><small>Diproses</small><strong><?= $jumlahDiproses ?></strong></div></div>
        <div class="stat-card"><span class="stat-icon">✓</span><div><small>Siap disajikan</small><strong><?= $jumlahSiap ?></strong></div></div>
    </section>

    <?php if (!$pesanan): ?>
        <section class="panel empty-state kitchen-empty">
            <div class="empty-icon">☕</div>
            <h2>Belum ada pesanan aktif</h2>
            <p>Pesanan baru dari kasir akan muncul di halaman ini.</p>
            <a class="button button-primary" href="index.php">Buka Kasir</a>
        </section>
    <?php else: ?>
        <section class="kitchen-grid">
            <?php foreach ($pesanan as $item): ?>
                <article class="panel order-card">
                    <div class="order-card-heading">
                        <div><span class="queue-label">NOMOR ANTREAN</span><h2>#<?= $item['nomor'] ?></h2></div>
                        <span class="status status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span>
                    </div>
                    <p class="order-time">Masuk pukul <?= date('H:i', strtotime($item['waktu'])) ?></p>
                    <div class="order-items">
                        <?php foreach ($item['items'] as $detail): ?>
                            <div class="order-item"><span><?= e($detail['nama']) ?></span><strong>× <?= $detail['qty'] ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="order-total"><span>Total pesanan</span><strong>Rp <?= number_format($item['total'], 0, ',', '.') ?></strong></div>
                    <form method="post" class="order-action">
                        <input type="hidden" name="pesanan_id" value="<?= $item['id'] ?>">
                        <?php if ($item['status'] === 'menunggu'): ?>
                            <button class="button button-primary" type="submit" name="aksi" value="proses">Mulai Proses Pesanan →</button>
                        <?php elseif ($item['status'] === 'diproses'): ?>
                            <button class="button button-primary" type="submit" name="aksi" value="siap">Tandai Pesanan Siap ✓</button>
                        <?php else: ?>
                            <button class="button button-secondary" type="submit" name="aksi" value="selesai">Tandai Sudah Diambil</button>
                        <?php endif; ?>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <p class="refresh-note">Halaman ini tidak memperbarui status otomatis. Tekan “Muat ulang” untuk memeriksa pesanan baru.</p>
</main>
</body>
</html>
