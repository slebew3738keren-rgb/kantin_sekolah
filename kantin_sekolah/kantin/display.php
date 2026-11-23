<?php
require_once 'koneksi.php';
$list = $conn->query("SELECT nomor_antrian, status, total, created_at FROM pesanan WHERE DATE(created_at) = CURDATE() ORDER BY nomor_antrian ASC");
if (!$list) die('Gagal mengambil daftar antrean: ' . htmlspecialchars($conn->error));
$antrian = [];
while ($row = $list->fetch_assoc()) $antrian[] = $row;
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Display Antrean - Kantin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="display-page">
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php"><span class="brand-mark">K</span><span>Kantin<span class="brand-light">Sekolah</span></span></a>
        <nav class="nav">
            <a href="index.php">Kasir</a>
            <a class="active" href="display.php">Display Antrean</a>
            <a href="dapur.php">Dapur</a>
            <a href="admin.php">Kelola Menu</a>
            <a href="laporan.php">Laporan</a>
        </nav>
    </div>
</header>
<main class="page">
    <section class="page-heading">
        <div><p class="eyebrow">INFORMASI PESANAN</p><h1>Display Antrean</h1><p class="muted">Nomor antrean diperbarui secara otomatis.</p></div>
        <span class="live-indicator"><i></i> Pembaruan langsung</span>
    </section>

    <section class="queue-board">
        <div class="now-serving">
            <span class="queue-label">SEDANG DIPROSES / SIAP</span>
            <strong id="nomor-dipanggil">-</strong>
            <p>Nomor antrean yang sedang dilayani</p>
        </div>
        <div class="next-serving">
            <span class="queue-label">ANTREAN BERIKUTNYA</span>
            <strong id="nomor-berikut">-</strong>
            <p>Mohon bersiap saat nomor dipanggil</p>
        </div>
    </section>

    <section class="panel" style="padding:18px;margin-bottom:20px"><h2>Cek Status Pesanan</h2><p class="muted small">Masukkan nomor antrean pada struk untuk melihat status pesanan.</p><form action="cek_pesanan.php" method="get" style="display:flex;gap:10px;flex-wrap:wrap"><input type="number" name="nomor" min="1" placeholder="Nomor antrean" required><button class="button button-primary">Cek Pesanan</button></form></section>
    <section class="panel queue-list-panel">
        <div class="section-heading"><div><h2>Daftar Antrean Hari Ini</h2><p class="muted small">Riwayat pesanan pada hari ini.</p></div><span class="count-pill"><?= count($antrian) ?> pesanan</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Nomor</th><th>Status</th><th>Estimasi</th><th>Total</th><th>Waktu</th></tr></thead>
                <tbody id="queue-table-body">
                <?php if (!$antrian): ?>
                    <tr><td colspan="5" class="table-empty">Belum ada antrean hari ini.</td></tr>
                <?php else: foreach ($antrian as $row): ?>
                    <tr>
                        <td><strong class="queue-number">#<?= (int)$row['nomor_antrian'] ?></strong></td>
                        <td><span class="status status-<?= e(strtolower($row['status'])) ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                        <td><?= $row['status']==='menunggu' ? (max(1, (int)$row['nomor_antrian'] - (int)($antrian[0]['nomor_antrian'] ?? 1)) * 5) . ' menit (perkiraan)' : ($row['status']==='diproses' ? 'Sedang dibuat' : ($row['status']==='siap' ? 'Silakan ambil' : 'Selesai')) ?></td>
                        <td>Rp <?= number_format((int)$row['total'], 0, ',', '.') ?></td>
                        <td><?= date('H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <p class="refresh-note">Data nomor antrean diperiksa setiap 3 detik.</p>
</main>
<script>
async function updateAntrian() {
    try {
        const response = await fetch('get_antrian.php', {cache: 'no-store'});
        if (!response.ok) throw new Error('Gagal mengambil antrean');
        const data = await response.json();
        document.getElementById('nomor-dipanggil').textContent = data.dipanggil ?? '-';
        document.getElementById('nomor-berikut').textContent = data.berikutnya ?? '-';
    } catch (error) {
        console.error(error);
    }
}
updateAntrian();
setInterval(updateAntrian, 3000);
</script>
</body>
</html>
