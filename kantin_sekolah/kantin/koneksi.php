<?php
$host          = 'localhost';
$user          = 'root';
$password      = '';
$nama_database = 'kantin_sekolah';

$conn = new mysqli($host, $user, $password, $nama_database);

if ($conn->connect_error) {
    die('Koneksi database gagal: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
date_default_timezone_set('Asia/Jakarta');
$conn->query("SET time_zone = '+07:00'");
?>