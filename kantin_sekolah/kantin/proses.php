<?php
require_once 'koneksi.php';
header('Content-Type: application/json; charset=utf-8');

function responseJson($success, $message = '', $number = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['sukses' => $success, 'pesan' => $message, 'nomor' => $number]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJson(false, 'Metode permintaan tidak valid.', null, 405);
}

$keranjang = json_decode($_POST['keranjang'] ?? '', true);
if (!is_array($keranjang) || count($keranjang) === 0) {
    responseJson(false, 'Keranjang masih kosong atau datanya tidak valid.', null, 422);
}

$metode = $_POST['metode_bayar'] ?? 'tunai';
if (!in_array($metode, ['tunai', 'qris'], true)) responseJson(false, 'Metode pembayaran tidak valid.', null, 422);
$uangDiterima = filter_var($_POST['uang_diterima'] ?? 0, FILTER_VALIDATE_INT);
if ($uangDiterima === false || $uangDiterima < 0) responseJson(false, 'Nominal pembayaran tidak valid.', null, 422);
$conn->begin_transaction();

try {
    $items = [];
    $total = 0;

    // Harga dan stok selalu dibaca ulang dari database, bukan dipercaya dari browser.
    $stmtMenu = $conn->prepare("SELECT id, nama, harga, stok, tersedia FROM menu WHERE id = ? FOR UPDATE");
    foreach ($keranjang as $item) {
        $menuId = (int)($item['id'] ?? 0);
        $qty = (int)($item['qty'] ?? 0);

        if ($menuId < 1 || $qty < 1) {
            throw new Exception('Data salah satu menu tidak valid.');
        }

        $stmtMenu->bind_param('i', $menuId);
        $stmtMenu->execute();
        $result = $stmtMenu->get_result();
        $menu = $result->fetch_assoc();

        if (!$menu || (int)$menu['tersedia'] !== 1) {
            throw new Exception('Salah satu menu sudah tidak tersedia.');
        }
        if ((int)$menu['stok'] < $qty) {
            throw new Exception('Stok ' . $menu['nama'] . ' tidak mencukupi. Sisa stok: ' . (int)$menu['stok']);
        }

        $harga = (int)$menu['harga'];
        $subtotal = $harga * $qty;
        $total += $subtotal;
        $items[] = ['id' => $menuId, 'qty' => $qty, 'subtotal' => $subtotal];
    }
    $stmtMenu->close();

    if ($total <= 0) throw new Exception('Total pesanan tidak valid.');
    if ($metode === 'tunai' && $uangDiterima < $total) throw new Exception('Uang tunai yang diterima kurang dari total pembayaran.');
    if ($metode === 'qris') $uangDiterima = $total;
    $kembalian = $uangDiterima - $total;

    // Nomor antrean berdasarkan pesanan terakhir hari ini.
    $q = $conn->query("SELECT COALESCE(MAX(nomor_antrian), 0) AS terakhir FROM pesanan WHERE DATE(created_at) = CURDATE() FOR UPDATE");
    if (!$q) throw new Exception('Gagal menentukan nomor antrean.');
    $nomor = (int)$q->fetch_assoc()['terakhir'] + 1;

    $stmtPesanan = $conn->prepare("INSERT INTO pesanan (nomor_antrian, total, status, metode_bayar, uang_diterima, kembalian) VALUES (?, ?, 'menunggu', ?, ?, ?)");
    $stmtPesanan->bind_param('iisii', $nomor, $total, $metode, $uangDiterima, $kembalian);
    $stmtPesanan->execute();
    $pesananId = $stmtPesanan->insert_id;
    $stmtPesanan->close();

    $stmtDetail = $conn->prepare("INSERT INTO pesanan_detail (pesanan_id, menu_id, qty, subtotal) VALUES (?, ?, ?, ?)");
    $stmtStok = $conn->prepare("UPDATE menu SET stok = stok - ? WHERE id = ? AND stok >= ?");
    foreach ($items as $item) {
        $stmtDetail->bind_param('iiii', $pesananId, $item['id'], $item['qty'], $item['subtotal']);
        $stmtDetail->execute();

        $stmtStok->bind_param('iii', $item['qty'], $item['id'], $item['qty']);
        $stmtStok->execute();
        if ($stmtStok->affected_rows !== 1) throw new Exception('Stok berubah. Silakan ulangi pesanan.');
    }
    $stmtDetail->close();
    $stmtStok->close();

    $conn->commit();
    http_response_code(200); echo json_encode(['sukses'=>true,'pesan'=>'Pembayaran berhasil.','nomor'=>$nomor,'pesanan_id'=>$pesananId]); exit;
} catch (Throwable $e) {
    $conn->rollback();
    responseJson(false, $e->getMessage(), null, 400);
}
