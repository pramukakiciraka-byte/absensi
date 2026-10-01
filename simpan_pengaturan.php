<?php
require_once "koneksi.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_sekolah = mysqli_real_escape_string($conn, trim($_POST['nama_sekolah']));
    $jam_masuk = mysqli_real_escape_string($conn, trim($_POST['jam_masuk']));
    $jam_pulang = mysqli_real_escape_string($conn, trim($_POST['jam_pulang'] ?? '13:00:00'));
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'index.php';

    // Format jam masuk jika belum ada detik
    if (strlen($jam_masuk) == 5) {
        $jam_masuk .= ":00";
    }
    // Format jam pulang jika belum ada detik
    if (strlen($jam_pulang) == 5) {
        $jam_pulang .= ":00";
    }

    $update = mysqli_query($conn, "
        UPDATE pengaturan 
        SET nama_sekolah = '$nama_sekolah', jam_masuk = '$jam_masuk', jam_pulang = '$jam_pulang' 
        WHERE id = 1
    ");

    header("Location: $redirect_to?pesan=setting_sukses");
    exit;
}

header("Location: index.php");
exit;
?>
