-- Database: absensi_sekolah
CREATE DATABASE IF NOT EXISTS absensi_sekolah;
USE absensi_sekolah;

-- Tabel Pengaturan Sistem
CREATE TABLE IF NOT EXISTS pengaturan (
    id INT PRIMARY KEY,
    nama_sekolah VARCHAR(150) NOT NULL,
    jam_masuk TIME NOT NULL DEFAULT '07:00:00',
    jam_pulang TIME NOT NULL DEFAULT '13:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pengaturan (id, nama_sekolah, jam_masuk, jam_pulang) 
VALUES (1, 'MTs Matholiul Huda Tlogowungu', '07:00:00', '13:00:00')
ON DUPLICATE KEY UPDATE jam_masuk='07:00:00', jam_pulang='13:00:00';

-- Tabel Siswa
CREATE TABLE IF NOT EXISTS siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(30) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(20) NOT NULL,
    uid_rfid VARCHAR(50) NOT NULL,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Absensi
CREATE TABLE IF NOT EXISTS absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    jam_masuk TIME NULL DEFAULT NULL,
    jam_pulang TIME NULL DEFAULT NULL,
    status ENUM('Hadir', 'Terlambat') DEFAULT 'Hadir',
    status_pulang VARCHAR(30) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (siswa_id),
    INDEX (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel RFID Temp (Untuk Live Scanner & Registrasi Kartu Baru)
CREATE TABLE IF NOT EXISTS rfid_temp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid_rfid VARCHAR(50) NOT NULL,
    waktu DATETIME NOT NULL,
    status_scan VARCHAR(30) NOT NULL,
    keterangan TEXT NULL,
    INDEX (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Pengguna (Admin & Guru)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('admin', 'guru') DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, nama, role) 
VALUES ('admin', 'admin123', 'Administrator', 'admin')
ON DUPLICATE KEY UPDATE username=username;

-- Contoh Data Siswa untuk Pengujian
INSERT INTO siswa (nis, nama, kelas, uid_rfid, status) VALUES
('1001', 'Ahmad Rizky Pratama', '7A', 'A3 7B 91 22', 'aktif'),
('1002', 'Siti Nur Aisyah', '7A', '5C 42 1B 89', 'aktif'),
('1003', 'Muhammad Budi Santoso', '7B', 'E4 1A 88 3C', 'aktif'),
('1004', 'Dewi Lestari', '8A', '7F 2B C9 41', 'aktif'),
('1005', 'Fajar Ramadhan', '8B', '3D 9E 45 12', 'aktif')
ON DUPLICATE KEY UPDATE nis=nis;
