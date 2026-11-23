<?php
require_once 'koneksi.php';
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$pesan = '';
$jenis_pesan = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nama = trim($_POST['nama'] ?? '');
    $harga = filter_var($_POST['harga'] ?? null, FILTER_VALIDATE_INT);
    $kategori = trim($_POST['kategori'] ?? '');
    $stok = filter_var($_POST['stok'] ?? 0, FILTER_VALIDATE_INT);

    if ($nama === '' || $harga === false || $harga < 1 || $stok === false || $stok < 0) {
        $pesan = 'Periksa kembali nama, harga, dan stok menu.';
        $jenis_pesan = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO menu (nama, harga, kategori, stok, tersedia) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param('sisi', $nama, $harga, $kategori, $stok);
        if ($stmt->execute()) $pesan = 'Menu berhasil ditambahkan.';
        else { $pesan = 'Menu gagal ditambahkan: ' . $stmt->error; $jenis_pesan = 'error'; }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id = (int)($_POST['id'] ?? 0); $nama = trim($_POST['nama'] ?? '');
    $harga = filter_var($_POST['harga'] ?? null, FILTER_VALIDATE_INT);
    $kategori = trim($_POST['kategori'] ?? ''); $stok = filter_var($_POST['stok'] ?? null, FILTER_VALIDATE_INT);
    if ($id < 1 || $nama === '' || $harga === false || $harga < 1 || $stok === false || $stok < 0) {
        $pesan = 'Data edit menu tidak valid.'; $jenis_pesan = 'error';
    } else {
        $stmt = $conn->prepare('UPDATE menu SET nama=?, harga=?, kategori=?, stok=? WHERE id=?');
        $stmt->bind_param('sisii', $nama, $harga, $kategori, $stok, $id);
        if ($stmt->execute()) $pesan = 'Menu berhasil diperbarui.'; else { $pesan='Gagal memperbarui menu.'; $jenis_pesan='error'; }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id=(int)($_POST['id']??0); $nama=trim($_POST['nama']??''); $harga=filter_var($_POST['harga']??null,FILTER_VALIDATE_INT); $kategori=trim($_POST['kategori']??''); $stok=filter_var($_POST['stok']??null,FILTER_VALIDATE_INT);
    if($id<1 || $nama==='' || $harga===false || $harga<1 || $stok===false || $stok<0){$pesan='Data edit menu tidak valid.';$jenis_pesan='error';}
    else{$stmt=$conn->prepare('UPDATE menu SET nama=?,harga=?,kategori=?,stok=? WHERE id=?');$stmt->bind_param('sisii',$nama,$harga,$kategori,$stok,$id);if($stmt->execute())$pesan='Menu berhasil diperbarui.';else{$pesan='Gagal memperbarui menu.';$jenis_pesan='error';}$stmt->close();}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) $pesan = 'Menu berhasil dihapus.';
    else { $pesan = 'Menu tidak ditemukan atau gagal dihapus.'; $jenis_pesan = 'error'; }
    $stmt->close();
}

$menu = $conn->query("SELECT id, nama, harga, kategori, stok, tersedia FROM menu ORDER BY id DESC");
$totalMenu = $menu ? $menu->num_rows : 0;
$totalStok = 0;
$rows = [];
if ($menu) {
    while ($row = $menu->fetch_assoc()) {
        $rows[] = $row;
        $totalStok += (int)$row['stok'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Kelola Menu - Kantin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php"><span class="brand-mark">K</span><span>Kantin<span class="brand-light">Sekolah</span></span></a>
        <nav class="nav">
            <a href="index.php">Kasir</a>
            <a href="display.php">Display Antrean</a>
            <a href="dapur.php">Dapur</a>
            <a class="active" href="admin.php">Kelola Menu</a>
            <a href="laporan.php">Laporan</a>
        </nav>
    </div>
</header>
<main class="page">
    <section class="page-heading">
        <div><p class="eyebrow">PENGELOLAAN KANTIN</p><h1>Kelola Menu</h1><p class="muted">Tambah dan pantau daftar makanan serta minuman yang dijual.</p></div>
        <a class="button button-secondary" href="index.php">← Kembali ke Kasir</a>
    </section>

    <?php if ($pesan): ?><div class="alert alert-<?= e($jenis_pesan) ?>"><?= e($pesan) ?></div><?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card"><span class="stat-icon">▤</span><div><small>Total menu</small><strong><?= $totalMenu ?></strong></div></div>
        <div class="stat-card"><span class="stat-icon">▦</span><div><small>Total stok</small><strong><?= $totalStok ?></strong></div></div>
    </section>

    <div class="admin-layout">
        <section class="panel add-menu-panel">
            <div class="panel-title"><span class="panel-icon">＋</span><div><h2>Tambah Menu Baru</h2><p class="muted small">Lengkapi informasi menu di bawah ini.</p></div></div>
            <form method="post" class="admin-form">
                <label>Nama menu<input type="text" name="nama" placeholder="Contoh: Nasi goreng" maxlength="100" required></label>
                <label>Harga jual (Rp)<input type="number" name="harga" placeholder="Contoh: 15000" min="1" required></label>
                <label>Kategori<input type="text" name="kategori" placeholder="Makanan / Minuman" maxlength="60"></label>
                <label>Stok awal<input type="number" name="stok" value="0" min="0" required></label>
                <button class="button button-primary" type="submit" name="tambah">Simpan Menu <span>→</span></button>
            </form>
        </section>

        <section class="panel inventory-panel">
            <div class="section-heading"><div><h2>Daftar Menu</h2><p class="muted small">Menu yang tercatat di database.</p></div><span class="count-pill"><?= $totalMenu ?> menu</span></div>
            <?php if (!$rows): ?>
                <div class="empty-state compact"><div class="empty-icon">☕</div><h3>Belum ada data menu</h3><p>Gunakan formulir untuk menambahkan menu pertama.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Menu</th><th>Harga</th><th>Stok</th><th>Edit / Hapus</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><strong><?= e($row['nama']) ?></strong><small class="table-sub"><?= e($row['kategori'] ?: 'Lainnya') ?></small></td>
                                <td>Rp <?= number_format((int)$row['harga'], 0, ',', '.') ?></td>
                                <td><span class="stock <?= (int)$row['stok'] <= 0 ? 'stock-empty' : '' ?>"><?= (int)$row['stok'] ?></span></td>
                                <td>
                                    <details><summary class="text-button">Edit</summary>
                                      <form method="post" class="admin-form">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <label>Nama<input name="nama" value="<?= e($row['nama']) ?>" required></label>
                                        <label>Harga<input type="number" name="harga" min="1" value="<?= (int)$row['harga'] ?>" required></label>
                                        <label>Kategori<input name="kategori" value="<?= e($row['kategori']) ?>"></label>
                                        <label>Stok<input type="number" name="stok" min="0" value="<?= (int)$row['stok'] ?>" required></label>
                                        <button class="button button-primary" name="edit">Simpan Perubahan</button>
                                      </form>
                                    </details>
                                    <form method="post" onsubmit="return confirm('Yakin ingin menghapus menu <?= e($row['nama']) ?>?')">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button class="text-button danger" type="submit" name="hapus">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
