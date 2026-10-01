<?php

include "koneksi.php";

$uid = $_POST['uid_rfid'] ?? '';

if (empty($uid)) {
    die("UID RFID belum dikirim.");
}

// Cari siswa berdasarkan UID RFID
$uid_esc = mysqli_real_escape_string($conn, $uid);

$query_siswa = mysqli_query($conn, "
    SELECT id, nama, kelas
    FROM siswa
    WHERE uid_rfid = '$uid_esc'
    AND status = 'aktif'
    LIMIT 1
");

$siswa = mysqli_fetch_assoc($query_siswa);

if (!$siswa) {
    die("Kartu RFID tidak terdaftar.");
}

// Cek apakah siswa sudah absen hari ini
$siswa_id = $siswa['id'];

$query_cek = mysqli_query($conn, "
    SELECT id
    FROM absensi
    WHERE siswa_id = '$siswa_id'
    AND tanggal = CURDATE()
    LIMIT 1
");

if (mysqli_num_rows($query_cek) > 0) {
    echo "Siswa sudah absen hari ini: " . $siswa['nama'];
    exit;
}

// Ambil waktu sekarang
$jam = date('H:i:s');

// Tentukan status
if ($jam <= '07:00:00') {
    $status = 'Hadir';
} else {
    $status = 'Terlambat';
}

// Simpan absensi
$query_simpan = mysqli_query($conn, "
    INSERT INTO absensi
    (siswa_id, tanggal, jam, status)
    VALUES
    ('$siswa_id', CURDATE(), '$jam', '$status')
");

if ($query_simpan) {
    echo "Absensi berhasil: " . $siswa['nama'] . " - " . $status;
} else {
    echo "Gagal menyimpan absensi.";
}

?>