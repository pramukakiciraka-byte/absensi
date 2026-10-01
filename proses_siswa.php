<?php
require_once "koneksi.php";

$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : (isset($_GET['aksi']) ? $_GET['aksi'] : '');

if ($aksi === 'tambah') {
    $nis = mysqli_real_escape_string($conn, trim($_POST['nis']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $kelas = mysqli_real_escape_string($conn, trim($_POST['kelas']));
    $uid_rfid = mysqli_real_escape_string($conn, strtoupper(trim($_POST['uid_rfid'])));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));

    // Format UID agar konsisten ada spasi per 2 hex (misal A3 7B 91 22) jika dimasukkan tanpa spasi
    $clean_uid = preg_replace('/[^a-zA-Z0-9]/', '', $uid_rfid);
    $formatted_uid = trim(chunk_split($clean_uid, 2, ' '));

    // Cek duplikasi NIS
    $cek_nis = mysqli_query($conn, "SELECT id FROM siswa WHERE nis = '$nis' LIMIT 1");
    if (mysqli_num_rows($cek_nis) > 0) {
        header("Location: siswa.php?pesan=nis_terdaftar");
        exit;
    }

    // Cek duplikasi UID RFID
    $cek_uid = mysqli_query($conn, "
        SELECT id FROM siswa 
        WHERE UPPER(REPLACE(uid_rfid, ' ', '')) = '$clean_uid'
        LIMIT 1
    ");
    if (mysqli_num_rows($cek_uid) > 0) {
        header("Location: siswa.php?pesan=uid_terdaftar");
        exit;
    }

    $insert = mysqli_query($conn, "
        INSERT INTO siswa (nis, nama, kelas, uid_rfid, status)
        VALUES ('$nis', '$nama', '$kelas', '$formatted_uid', '$status')
    ");

    if ($insert) {
        // Hapus dari rfid_temp jika ada
        mysqli_query($conn, "DELETE FROM rfid_temp WHERE UPPER(REPLACE(uid_rfid, ' ', '')) = '$clean_uid'");
        header("Location: siswa.php?pesan=tambah_sukses");
    } else {
        header("Location: siswa.php?pesan=gagal");
    }
    exit;
}

if ($aksi === 'edit') {
    $id = (int)$_POST['id'];
    $nis = mysqli_real_escape_string($conn, trim($_POST['nis']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $kelas = mysqli_real_escape_string($conn, trim($_POST['kelas']));
    $uid_rfid = mysqli_real_escape_string($conn, strtoupper(trim($_POST['uid_rfid'])));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));

    $clean_uid = preg_replace('/[^a-zA-Z0-9]/', '', $uid_rfid);
    $formatted_uid = trim(chunk_split($clean_uid, 2, ' '));

    // Cek duplikasi NIS pada siswa lain
    $cek_nis = mysqli_query($conn, "SELECT id FROM siswa WHERE nis = '$nis' AND id != $id LIMIT 1");
    if (mysqli_num_rows($cek_nis) > 0) {
        header("Location: siswa.php?pesan=nis_terdaftar");
        exit;
    }

    // Cek duplikasi UID pada siswa lain
    $cek_uid = mysqli_query($conn, "
        SELECT id FROM siswa 
        WHERE UPPER(REPLACE(uid_rfid, ' ', '')) = '$clean_uid' AND id != $id
        LIMIT 1
    ");
    if (mysqli_num_rows($cek_uid) > 0) {
        header("Location: siswa.php?pesan=uid_terdaftar");
        exit;
    }

    $update = mysqli_query($conn, "
        UPDATE siswa 
        SET nis = '$nis', nama = '$nama', kelas = '$kelas', uid_rfid = '$formatted_uid', status = '$status'
        WHERE id = $id
    ");

    if ($update) {
        header("Location: siswa.php?pesan=edit_sukses");
    } else {
        header("Location: siswa.php?pesan=gagal");
    }
    exit;
}

if ($aksi === 'hapus') {
    $id = (int)$_GET['id'];
    
    // Hapus juga riwayat absensinya agar data konsisten
    mysqli_query($conn, "DELETE FROM absensi WHERE siswa_id = $id");
    $del = mysqli_query($conn, "DELETE FROM siswa WHERE id = $id");

    if ($del) {
        header("Location: siswa.php?pesan=hapus_sukses");
    } else {
        header("Location: siswa.php?pesan=gagal");
    }
    exit;
}

header("Location: siswa.php");
exit;
?>
