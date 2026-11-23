<?php
require_once 'koneksi.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$q = $conn->query("SELECT nomor_antrian FROM pesanan WHERE DATE(created_at) = CURDATE() AND status IN ('diproses', 'siap') ORDER BY nomor_antrian DESC LIMIT 1");
if (!$q) {
    http_response_code(500);
    echo json_encode(['sukses' => false, 'pesan' => 'Gagal membaca antrean']);
    exit;
}
$row = $q->fetch_assoc();
$dipanggil = $row['nomor_antrian'] ?? '-';

$q2 = $conn->query("SELECT MIN(nomor_antrian) AS berikut FROM pesanan WHERE DATE(created_at) = CURDATE() AND status = 'menunggu'");
if (!$q2) {
    http_response_code(500);
    echo json_encode(['sukses' => false, 'pesan' => 'Gagal membaca antrean berikutnya']);
    exit;
}
$row2 = $q2->fetch_assoc();
$berikut = $row2['berikut'] ?? '-';

echo json_encode(['sukses' => true, 'dipanggil' => $dipanggil, 'berikutnya' => $berikut]);
