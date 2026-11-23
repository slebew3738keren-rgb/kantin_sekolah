<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'koneksi.php';

$menuQuery = $conn->query("SELECT id, nama, harga, kategori, stok FROM menu WHERE tersedia = 1 ORDER BY kategori, nama");
if (!$menuQuery) {
    die('Gagal mengambil menu: ' . htmlspecialchars($conn->error));
}
$daftar_menu = [];
while ($row = $menuQuery->fetch_assoc()) {
    $daftar_menu[] = $row;
}

$q = $conn->query("SELECT COALESCE(MAX(nomor_antrian), 0) AS terakhir FROM pesanan WHERE DATE(created_at) = CURDATE()");
if (!$q) {
    die('Gagal mengambil nomor antrean: ' . htmlspecialchars($conn->error));
}
$nomor_berikutnya = (int) $q->fetch_assoc()['terakhir'] + 1;

$kategori = [];
foreach ($daftar_menu as $item) {
    $namaKategori = trim($item['kategori'] ?? '');
    $kategori[$namaKategori === '' ? 'Lainnya' : $namaKategori] = true;
}
$kategori = array_keys($kategori);
sort($kategori);
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Kasir Kantin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php"><span class="brand-mark">K</span><span>Kantin<span class="brand-light">Sekolah</span></span></a>
        <nav class="nav">
            <a class="active" href="index.php">Kasir</a>
            <a href="display.php">Display Antrean</a>
            <a href="dapur.php">Dapur</a>
            <a href="admin.php">Kelola Menu</a>
            <a href="laporan.php">Laporan</a>
        </nav>
    </div>
</header>

<main class="page">
    <section class="page-heading">
        <div>
            <p class="eyebrow">SISTEM KASIR KANTIN</p>
            <h1>Kasir Kantin</h1>
            <p class="muted">Pilih menu, masukkan ke keranjang, lalu buat nomor antrean.</p>
        </div>
        <div class="queue-preview">
            <span class="queue-label">ANTREAN BERIKUTNYA</span>
            <strong><?= $nomor_berikutnya ?></strong>
        </div>
    </section>

    <div class="cashier-layout">
        <section class="panel menu-panel">
            <div class="section-heading">
                <div><h2>Pilih Menu</h2><p class="muted small"><?= count($daftar_menu) ?> menu tersedia</p></div>
                <label class="search-box"><span>⌕</span><input type="search" id="cari-menu" placeholder="Cari menu..."></label>
            </div>
            <div class="category-list" id="kategori-list">
                <button class="category-chip selected" type="button" data-category="all">Semua</button>
                <?php foreach ($kategori as $kat): ?>
                    <button class="category-chip" type="button" data-category="<?= e($kat) ?>"><?= e($kat) ?></button>
                <?php endforeach; ?>
            </div>

            <?php if (!$daftar_menu): ?>
                <div class="empty-state"><div class="empty-icon">☕</div><h3>Belum ada menu</h3><p>Tambahkan daftar menu melalui halaman Kelola Menu.</p><a class="button button-primary" href="admin.php">Kelola Menu</a></div>
            <?php else: ?>
                <div class="menu-grid" id="menu-grid">
                    <?php foreach ($daftar_menu as $m): ?>
                        <?php $kat = trim($m['kategori'] ?? '') ?: 'Lainnya'; $habis = (int)$m['stok'] <= 0; ?>
                        <article class="menu-card" data-name="<?= e(mb_strtolower($m['nama'], 'UTF-8')) ?>" data-category="<?= e($kat) ?>">
                            <div class="menu-card-top"><span class="menu-category"><?= e($kat) ?></span><span class="stock <?= $habis ? 'stock-empty' : '' ?>"><?= $habis ? 'Habis' : 'Stok ' . (int)$m['stok'] ?></span></div>
                            <h3><?= e($m['nama']) ?></h3>
                            <div class="menu-card-bottom">
                                <strong class="price">Rp <?= number_format((int)$m['harga'], 0, ',', '.') ?></strong>
                                <button class="add-button" type="button"
                                    data-id="<?= (int)$m['id'] ?>"
                                    data-name="<?= e($m['nama']) ?>"
                                    data-price="<?= (int)$m['harga'] ?>"
                                    data-stock="<?= (int)$m['stok'] ?>"
                                    <?= $habis ? 'disabled' : '' ?>>
                                    <?= $habis ? 'Stok habis' : '+ Tambah' ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="empty-search hidden" id="empty-search">Menu tidak ditemukan.</div>
            <?php endif; ?>
        </section>

        <aside class="panel cart-panel">
            <div class="cart-heading"><div><h2>Keranjang</h2><p class="muted small"><span id="cart-count">0</span> item dipilih</p></div><span class="cart-icon">🛒</span></div>
            <div id="cart-items" class="cart-items">
                <div class="cart-empty"><span>＋</span><p>Keranjang masih kosong</p><small>Pilih menu untuk memulai pesanan.</small></div>
            </div>
            <div class="cart-summary">
                <div class="summary-row"><span>Subtotal</span><strong id="subtotal">Rp 0</strong></div>
                <div class="summary-total"><span>Total pembayaran</span><strong id="total">Rp 0</strong></div>
                <label class="payment-label">Metode pembayaran
                    <select id="metode-bayar"><option value="tunai">Tunai</option><option value="qris">QRIS</option></select>
                </label>
                <label class="payment-label" id="bayar-tunai-wrap">Uang diterima (Rp)
                    <input type="number" id="uang-diterima" min="0" step="500" placeholder="Masukkan nominal tunai">
                </label>
                <button class="button button-primary checkout-button" id="checkout" type="button" disabled>Proses Pembayaran <span>→</span></button>
                <button class="button button-ghost clear-button" id="clear-cart" type="button" disabled>Kosongkan keranjang</button>
                <p class="cart-note">Pastikan pesanan sudah sesuai sebelum diproses.</p>
            </div>
        </aside>
    </div>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>
const cart = [];
const formatter = new Intl.NumberFormat('id-ID');
const cartItems = document.getElementById('cart-items');
const checkoutButton = document.getElementById('checkout');
const clearButton = document.getElementById('clear-cart');

function rupiah(value) { return 'Rp ' + formatter.format(value); }
function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 2300);
}
function renderCart() {
    cartItems.innerHTML = '';
    let total = 0;
    let count = 0;

    if (!cart.length) {
        cartItems.innerHTML = '<div class="cart-empty"><span>＋</span><p>Keranjang masih kosong</p><small>Pilih menu untuk memulai pesanan.</small></div>';
    } else {
        cart.forEach(item => {
            const subtotal = item.price * item.qty;
            total += subtotal;
            count += item.qty;

            const row = document.createElement('div');
            row.className = 'cart-item';

            const info = document.createElement('div');
            info.className = 'cart-item-info';
            const name = document.createElement('strong');
            name.textContent = item.name;
            const price = document.createElement('small');
            price.textContent = rupiah(item.price) + ' / item';
            info.append(name, price);

            const controls = document.createElement('div');
            controls.className = 'quantity-control';
            const minus = document.createElement('button');
            minus.type = 'button'; minus.textContent = '−'; minus.setAttribute('aria-label', 'Kurangi ' + item.name);
            minus.addEventListener('click', () => changeQty(item.id, -1));
            const qty = document.createElement('span'); qty.textContent = item.qty;
            const plus = document.createElement('button');
            plus.type = 'button'; plus.textContent = '+'; plus.setAttribute('aria-label', 'Tambah ' + item.name);
            plus.disabled = item.qty >= item.stock;
            plus.addEventListener('click', () => changeQty(item.id, 1));
            controls.append(minus, qty, plus);

            const amount = document.createElement('strong');
            amount.className = 'cart-item-total';
            amount.textContent = rupiah(subtotal);

            const remove = document.createElement('button');
            remove.className = 'remove-item'; remove.type = 'button'; remove.textContent = '×';
            remove.setAttribute('aria-label', 'Hapus ' + item.name);
            remove.addEventListener('click', () => removeItem(item.id));

            const right = document.createElement('div');
            right.className = 'cart-item-right';
            right.append(controls, amount, remove);
            row.append(info, right);
            cartItems.appendChild(row);
        });
    }

    document.getElementById('cart-count').textContent = count;
    document.getElementById('subtotal').textContent = rupiah(total);
    document.getElementById('total').textContent = rupiah(total);
    checkoutButton.disabled = cart.length === 0;
    clearButton.disabled = cart.length === 0;
}
function addItem(button) {
    const id = Number(button.dataset.id);
    const item = cart.find(entry => entry.id === id);
    if (item) {
        if (item.qty >= item.stock) return showToast('Jumlah melebihi stok yang tersedia.');
        item.qty++;
    } else {
        cart.push({
            id,
            name: button.dataset.name,
            price: Number(button.dataset.price),
            stock: Number(button.dataset.stock),
            qty: 1
        });
    }
    renderCart();
    showToast(button.dataset.name + ' ditambahkan.');
}
function changeQty(id, change) {
    const item = cart.find(entry => entry.id === id);
    if (!item) return;
    item.qty += change;
    if (item.qty <= 0) removeItem(id);
    else renderCart();
}
function removeItem(id) {
    const index = cart.findIndex(entry => entry.id === id);
    if (index !== -1) cart.splice(index, 1);
    renderCart();
}
document.querySelectorAll('.add-button').forEach(button => {
    button.addEventListener('click', () => addItem(button));
});
clearButton.addEventListener('click', () => {
    cart.splice(0, cart.length);
    renderCart();
});
document.getElementById('cari-menu').addEventListener('input', filterMenu);
document.querySelectorAll('.category-chip').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.category-chip').forEach(chip => chip.classList.remove('selected'));
        button.classList.add('selected');
        filterMenu();
    });
});
function filterMenu() {
    const search = document.getElementById('cari-menu').value.trim().toLocaleLowerCase('id');
    const category = document.querySelector('.category-chip.selected').dataset.category;
    let visible = 0;
    document.querySelectorAll('.menu-card').forEach(card => {
        const matches = card.dataset.name.includes(search) && (category === 'all' || card.dataset.category === category);
        card.classList.toggle('hidden', !matches);
        if (matches) visible++;
    });
    const empty = document.getElementById('empty-search');
    if (empty) empty.classList.toggle('hidden', visible !== 0);
}
const metodeBayar = document.getElementById('metode-bayar');
const uangDiterima = document.getElementById('uang-diterima');
const bayarTunaiWrap = document.getElementById('bayar-tunai-wrap');
metodeBayar.addEventListener('change', () => { bayarTunaiWrap.style.display = metodeBayar.value === 'tunai' ? '' : 'none'; });
checkoutButton.addEventListener('click', async () => {
    if (!cart.length) return;
    checkoutButton.disabled = true;
    checkoutButton.textContent = 'Memproses...';
    const form = new FormData();
    form.append('keranjang', JSON.stringify(cart.map(({id, qty}) => ({id, qty}))));
    form.append('metode_bayar', metodeBayar.value);
    form.append('uang_diterima', uangDiterima.value || '0');
    try {
        const response = await fetch('proses.php', {method: 'POST', body: form});
        const result = await response.json();
        if (!response.ok || !result.sukses) throw new Error(result.pesan || 'Pesanan gagal diproses.');
        alert('Pembayaran berhasil. Nomor antrean: ' + result.nomor);
        if (result.pesanan_id) window.open('nota.php?id=' + result.pesanan_id, '_blank');
        window.location.reload();
    } catch (error) {
        showToast(error.message || 'Terjadi kesalahan koneksi.');
        checkoutButton.disabled = false;
        checkoutButton.innerHTML = 'Buat Pesanan <span>→</span>';
    }
});
renderCart();
</script>
</body>
</html>
