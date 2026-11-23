-- Jalankan SATU KALI melalui phpMyAdmin pada database kantin_sekolah.
-- Perluas status agar tahap 'diambil' dapat disimpan.
ALTER TABLE pesanan MODIFY status VARCHAR(20) NOT NULL DEFAULT 'menunggu';
-- Tambahkan kolom pembayaran untuk tunai dan QRIS.
ALTER TABLE pesanan
  ADD COLUMN metode_bayar ENUM('tunai','qris') NOT NULL DEFAULT 'tunai' AFTER status,
  ADD COLUMN uang_diterima INT NOT NULL DEFAULT 0 AFTER metode_bayar,
  ADD COLUMN kembalian INT NOT NULL DEFAULT 0 AFTER uang_diterima;
