<?php
date_default_timezone_set('Asia/Jakarta');

// Mengambil konfigurasi dari Environment Variable Railway (atau fallback ke Localhost)
$host     = getenv('MYSQLHOST') ?: 'localhost';
$user     = getenv('MYSQLUSER') ?: 'root';$password = getenv('MYSQLPASSWORD') ?: '';
$database = getenv('MYSQLDATABASE') ?: 'absensi_sekolah';$port     = getenv('MYSQLPORT') ?: '3306';

// Koneksi langsung ke host dan port
$conn = @mysqli_connect($host,$user, $password, '', (int)$port);

if (!$conn) {
    die("Koneksi database server gagal: " . mysqli_connect_error());
}

// 1. Abaikan pembuatan database jika berjalan di Railway (karena nama DB sudah ditentukan sistem Railway)
if (!getenv('MYSQLHOST')) {
    mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$database` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

mysqli_select_db($conn,$database);
mysqli_set_charset($conn, "utf8mb4");

// 2. Buat tabel jika belum tersedia
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pengaturan` (
    `id` INT PRIMARY KEY,
    `nama_sekolah` VARCHAR(150) NOT NULL,
    `jam_masuk` TIME NOT NULL DEFAULT '07:00:00',
    `jam_pulang` TIME NOT NULL DEFAULT '13:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-migrate jam_pulang ke tabel pengaturan jika belum ada
$cek_kol_pulang = mysqli_query($conn, "SHOW COLUMNS FROM `pengaturan` LIKE 'jam_pulang'");
if ($cek_kol_pulang && mysqli_num_rows($cek_kol_pulang) == 0) {
    mysqli_query($conn, "ALTER TABLE `pengaturan` ADD `jam_pulang` TIME NOT NULL DEFAULT '13:00:00' AFTER `jam_masuk`");
}

mysqli_query($conn, "INSERT INTO `pengaturan` (`id`, `nama_sekolah`, `jam_masuk`, `jam_pulang`) 
    VALUES (1, 'MTs Matholiul Huda Tlogowungu', '07:00:00', '13:00:00')
    ON DUPLICATE KEY UPDATE `jam_pulang` = IF(`jam_pulang` IS NULL OR `jam_pulang` = '00:00:00', '13:00:00', `jam_pulang`)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `siswa` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nis` VARCHAR(30) NOT NULL UNIQUE,
    `nama` VARCHAR(100) NOT NULL,
    `kelas` VARCHAR(20) NOT NULL,
    `uid_rfid` VARCHAR(50) NOT NULL,
    `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `absensi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `siswa_id` INT NOT NULL,
    `tanggal` DATE NOT NULL,
    `jam` TIME NOT NULL,
    `jam_masuk` TIME NULL DEFAULT NULL,
    `jam_pulang` TIME NULL DEFAULT NULL,
    `status` ENUM('Hadir', 'Terlambat') DEFAULT 'Hadir',
    `status_pulang` VARCHAR(30) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`siswa_id`),
    INDEX (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-migrate kolom jam_masuk, jam_pulang, status_pulang ke tabel absensi
$cek_jam_masuk = mysqli_query($conn, "SHOW COLUMNS FROM `absensi` LIKE 'jam_masuk'");
if ($cek_jam_masuk && mysqli_num_rows($cek_jam_masuk) == 0) {
    mysqli_query($conn, "ALTER TABLE `absensi` ADD `jam_masuk` TIME NULL DEFAULT NULL AFTER `jam`");
    mysqli_query($conn, "UPDATE `absensi` SET `jam_masuk` = `jam` WHERE `jam_masuk` IS NULL");
}

$cek_jam_pulang = mysqli_query($conn, "SHOW COLUMNS FROM `absensi` LIKE 'jam_pulang'");
if ($cek_jam_pulang && mysqli_num_rows($cek_jam_pulang) == 0) {
    mysqli_query($conn, "ALTER TABLE `absensi` ADD `jam_pulang` TIME NULL DEFAULT NULL AFTER `jam_masuk`");
}

$cek_status_pulang = mysqli_query($conn, "SHOW COLUMNS FROM `absensi` LIKE 'status_pulang'");
if ($cek_status_pulang && mysqli_num_rows($cek_status_pulang) == 0) {
    mysqli_query($conn, "ALTER TABLE `absensi` ADD `status_pulang` VARCHAR(30) NULL DEFAULT NULL AFTER `jam_pulang`");
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `rfid_temp` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `uid_rfid` VARCHAR(50) NOT NULL,
    `waktu` DATETIME NOT NULL,
    `status_scan` VARCHAR(30) NOT NULL,
    `keterangan` TEXT NULL,
    INDEX (`waktu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'guru') DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "INSERT INTO `users` (`username`, `password`, `nama`, `role`) 
    VALUES ('admin', 'admin123', 'Administrator', 'admin')
    ON DUPLICATE KEY UPDATE `username` = `username`");
?>
