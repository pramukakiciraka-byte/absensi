<?php
// api_absensi.php - API Endpoint untuk Menerima Tap Kartu RFID dari ESP32 / ESP8266
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "koneksi.php";

// Ambil input UID baik dari GET, POST form-urlencoded, maupun raw JSON body
$raw_uid = '';
if (isset($_REQUEST['uid'])) {
    $raw_uid = trim($_REQUEST['uid']);
} else {
    $json_input = json_decode(file_get_contents('php://input'), true);
    if (isset($json_input['uid'])) {
        $raw_uid = trim($json_input['uid']);
    }
}

$format = isset($_REQUEST['format']) ? strtolower(trim($_REQUEST['format'])) : 'json';

if (empty($raw_uid)) {
    http_response_code(400);
    $response = [
        "status" => "error",
        "message" => "Parameter UID tidak ditemukan. Gunakan: ?uid=KODE_RFID",
        "sound" => "error"
    ];
    if ($format === 'text') {
        echo "ERROR:\nUID KOSONG";
        exit;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Normalisasi UID (hapus spasi, strip, titik dua, ubah ke uppercase)
$clean_uid = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $raw_uid));
$formatted_uid = trim(chunk_split($clean_uid, 2, ' '));

// Ambil batas jam masuk dan jam pulang dari database pengaturan
$query_setting = mysqli_query($conn, "SELECT jam_masuk, jam_pulang, nama_sekolah FROM pengaturan WHERE id = 1 LIMIT 1");
$jam_masuk = "07:00:00";
$jam_pulang = "13:00:00";
$nama_sekolah = "MTs Matholiul Huda Tlogowungu";
if ($query_setting && $setting = mysqli_fetch_assoc($query_setting)) {
    if (!empty($setting['jam_masuk'])) {
        $jam_masuk = $setting['jam_masuk'];
    }
    if (!empty($setting['jam_pulang'])) {
        $jam_pulang = $setting['jam_pulang'];
    }
    if (!empty($setting['nama_sekolah'])) {
        $nama_sekolah = $setting['nama_sekolah'];
    }
}

// Mode tap (auto, masuk, pulang) - berguna untuk simulasi pengujian
$mode_tap = isset($_REQUEST['mode']) ? strtolower(trim($_REQUEST['mode'])) : 'auto';

// Cari siswa berdasarkan UID RFID
$clean_uid_esc = mysqli_real_escape_string($conn, $clean_uid);
$query_siswa = mysqli_query($conn, "
    SELECT * FROM siswa 
    WHERE UPPER(REPLACE(REPLACE(REPLACE(uid_rfid, ' ', ''), ':', ''), '-', '')) = '$clean_uid_esc'
    LIMIT 1
");

$waktu_sekarang = date('Y-m-d H:i:s');
$tanggal_hari_ini = date('Y-m-d');
$jam_sekarang = date('H:i:s');

// 1. KASUS: Kartu belum terdaftar
if (!$query_siswa || mysqli_num_rows($query_siswa) == 0) {
    $ket = "Kartu $formatted_uid belum terdaftar di sistem";
    $ket_esc = mysqli_real_escape_string($conn, $ket);
    mysqli_query($conn, "INSERT INTO rfid_temp (uid_rfid, waktu, status_scan, keterangan) VALUES ('$formatted_uid', '$waktu_sekarang', 'unregistered', '$ket_esc')");

    $response = [
        "status" => "unregistered",
        "message" => "Kartu Belum Terdaftar!",
        "uid" => $formatted_uid,
        "jam" => $jam_sekarang,
        "sound" => "error"
    ];

    if ($format === 'text') {
        echo "TIDAK TERDAFTAR\n" . substr($formatted_uid, 0, 16);
        exit;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$siswa = mysqli_fetch_assoc($query_siswa);
$siswa_id = (int)$siswa['id'];
$nama = $siswa['nama'];
$nis = $siswa['nis'];
$kelas = $siswa['kelas'];

// 2. KASUS: Status siswa tidak aktif
if (strtolower($siswa['status']) !== 'aktif') {
    $ket = "Siswa $nama statusnya tidak aktif";
    $ket_esc = mysqli_real_escape_string($conn, $ket);
    mysqli_query($conn, "INSERT INTO rfid_temp (uid_rfid, waktu, status_scan, keterangan) VALUES ('$formatted_uid', '$waktu_sekarang', 'inactive', '$ket_esc')");

    $response = [
        "status" => "inactive",
        "message" => "Status Siswa Tidak Aktif!",
        "nama" => $nama,
        "nis" => $nis,
        "kelas" => $kelas,
        "jam" => $jam_sekarang,
        "sound" => "error"
    ];

    if ($format === 'text') {
        echo "NONAKTIF\n" . substr($nama, 0, 16);
        exit;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. KASUS: Cek apakah sudah ada rekaman absensi hari ini
$cek_absen = mysqli_query($conn, "
    SELECT * FROM absensi 
    WHERE siswa_id = $siswa_id AND tanggal = '$tanggal_hari_ini'
    LIMIT 1
");

if (mysqli_num_rows($cek_absen) > 0) {
    $data_absen = mysqli_fetch_assoc($cek_absen);
    $absen_id = (int)$data_absen['id'];
    $jam_masuk_siswa = !empty($data_absen['jam_masuk']) ? $data_absen['jam_masuk'] : $data_absen['jam'];
    $jam_pulang_siswa = $data_absen['jam_pulang'] ?? null;
    $status_masuk = $data_absen['status'];

    // Jika mode eksplisit masuk dipaksa
    if ($mode_tap === 'masuk') {
        $ket = "$nama ($kelas) sudah absen masuk pukul $jam_masuk_siswa [$status_masuk]";
        $ket_esc = mysqli_real_escape_string($conn, $ket);
        mysqli_query($conn, "INSERT INTO rfid_temp (uid_rfid, waktu, status_scan, keterangan) VALUES ('$formatted_uid', '$waktu_sekarang', 'already', '$ket_esc')");

        $response = [
            "status" => "already",
            "tipe" => "masuk",
            "message" => "Sudah Absen Masuk Hari Ini!",
            "uid" => $formatted_uid,
            "nama" => $nama,
            "nis" => $nis,
            "kelas" => $kelas,
            "jam" => $jam_masuk_siswa,
            "keterangan" => $status_masuk,
            "sound" => "warning"
        ];
        if ($format === 'text') {
            echo "SUDAH ABSEN MASUK\n" . substr($nama, 0, 16);
            exit;
        }
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Jika siswa SUDAH absen pulang
    if (!empty($jam_pulang_siswa)) {
        $ket = "$nama ($kelas) sudah absen pulang pukul $jam_pulang_siswa [{$data_absen['status_pulang']}]";
        $ket_esc = mysqli_real_escape_string($conn, $ket);
        mysqli_query($conn, "INSERT INTO rfid_temp (uid_rfid, waktu, status_scan, keterangan) VALUES ('$formatted_uid', '$waktu_sekarang', 'already', '$ket_esc')");

        $response = [
            "status" => "already",
            "tipe" => "pulang",
            "message" => "Sudah Absen Pulang Hari Ini!",
            "uid" => $formatted_uid,
            "nama" => $nama,
            "nis" => $nis,
            "kelas" => $kelas,
            "jam" => $jam_pulang_siswa,
            "keterangan" => $data_absen['status_pulang'] ?? 'Pulang',
            "sound" => "warning"
        ];

        if ($format === 'text') {
            echo "SUDAH PULANG\n" . substr($nama, 0, 16);
            exit;
        }
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Siswa BELUM absen pulang: Cek perlindungan anti double-tap dalam 30 detik dari absen masuk
    $selisih_detik = abs(strtotime($jam_sekarang) - strtotime($jam_masuk_siswa));
    if ($mode_tap === 'auto' && $selisih_detik < 30) {
        $ket = "$nama ($kelas) baru saja tap masuk ($selisih_detik detik lalu). Tunggu saat jam pulang.";
        $ket_esc = mysqli_real_escape_string($conn, $ket);
        mysqli_query($conn, "INSERT INTO rfid_temp (uid_rfid, waktu, status_scan, keterangan) VALUES ('$formatted_uid', '$waktu_sekarang', 'already', '$ket_esc')");

        $response = [
            "status" => "already",
            "tipe" => "masuk",
            "message" => "Baru Saja Absen Masuk!",
            "uid" => $formatted_uid,
            "nama" => $nama,
            "nis" => $nis,
            "kelas" => $kelas,
            "jam" => $jam_masuk_siswa,
            "keterangan" => $status_masuk,
            "sound" => "warning"
        ];
        if ($format === 'text') {
            echo "BARU SAJA MASUK\n" . substr($nama, 0, 16);
            exit;
        }
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Tentukan status kepulangan (Tepat Waktu jika >= 13:00, atau Pulang Cepat jika < 13:00)
    $is_tepat_waktu_pulang = (strtotime($jam_sekarang) >= strtotime($jam_pulang));
    $status_pulang = $is_tepat_waktu_pulang ? 'Tepat Waktu' : 'Pulang Cepat';
    $ket_lcd = $is_tepat_waktu_pulang ? 'Pulang' : 'Plg Cepat';

    $update_pulang = mysqli_query($conn, "
        UPDATE absensi 
        SET jam_pulang = '$jam_sekarang', status_pulang = '$status_pulang' 
        WHERE id = $absen_id
    ");

    if ($update_pulang) {
        $ket = "Absensi Pulang $nama ($kelas) berhasil dicatat [$status_pulang]";
        $ket_esc = mysqli_real_escape_string($conn, $ket);
        mysqli_query($conn, "INSERT INTO rfid_temp (uid_rfid, waktu, status_scan, keterangan) VALUES ('$formatted_uid', '$waktu_sekarang', 'success_pulang', '$ket_esc')");

        $response = [
            "status" => "success",
            "tipe" => "pulang",
            "message" => ($status_pulang === 'Tepat Waktu') ? "Absensi Pulang Berhasil! Hati-hati di jalan." : "Absensi Pulang Cepat Berhasil dicatat.",
            "uid" => $formatted_uid,
            "nama" => $nama,
            "nis" => $nis,
            "kelas" => $kelas,
            "jam" => $jam_sekarang,
            "jam_masuk" => $jam_masuk_siswa,
            "jam_pulang" => $jam_sekarang,
            "keterangan" => $ket_lcd,
            "status_pulang" => $status_pulang,
            "sound" => "success"
        ];

        if ($format === 'text') {
            echo "$ket_lcd\n" . substr($nama, 0, 16);
            exit;
        }
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code(500);
        $response = [
            "status" => "error",
            "message" => "Gagal menyimpan absensi pulang: " . mysqli_error($conn),
            "sound" => "error"
        ];
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 4. KASUS: Belum ada rekaman hari ini -> CATAT ABSENSI MASUK
// Tentukan status kehadiran masuk (Hadir jika <= 07:00, Terlambat jika > 07:00)
$status_kehadiran = (strtotime($jam_sekarang) <= strtotime($jam_masuk)) ? 'Hadir' : 'Terlambat';

// Jika dipaksa mode pulang padahal belum pernah absen masuk (misal datang langsung saat jam pulang)
$jam_pulang_val = "NULL";
$status_pulang_val = "NULL";
$tipe_catat = "masuk";
$status_scan_tag = "success_masuk";

if ($mode_tap === 'pulang') {
    $jam_pulang_val = "'$jam_sekarang'";
    $status_pulang_val = (strtotime($jam_sekarang) >= strtotime($jam_pulang)) ? "'Tepat Waktu'" : "'Pulang Cepat'";
    $tipe_catat = "pulang";
    $status_scan_tag = "success_pulang";
}

$simpan = mysqli_query($conn, "
    INSERT INTO absensi (siswa_id, tanggal, jam, jam_masuk, jam_pulang, status, status_pulang) 
    VALUES ($siswa_id, '$tanggal_hari_ini', '$jam_sekarang', '$jam_sekarang', $jam_pulang_val, '$status_kehadiran', $status_pulang_val)
");

if ($simpan) {
    $ket = "Absensi Masuk $nama ($kelas) berhasil dicatat sebagai $status_kehadiran";
    if ($mode_tap === 'pulang') {
        $ket = "Absensi Masuk & Pulang $nama ($kelas) berhasil dicatat";
    }
    $ket_esc = mysqli_real_escape_string($conn, $ket);
    mysqli_query($conn, "INSERT INTO rfid_temp (uid_rfid, waktu, status_scan, keterangan) VALUES ('$formatted_uid', '$waktu_sekarang', '$status_scan_tag', '$ket_esc')");

    $response = [
        "status" => "success",
        "tipe" => $tipe_catat,
        "message" => "Absensi Masuk ($status_kehadiran) Berhasil!",
        "uid" => $formatted_uid,
        "nama" => $nama,
        "nis" => $nis,
        "kelas" => $kelas,
        "jam" => $jam_sekarang,
        "jam_masuk" => $jam_sekarang,
        "jam_pulang" => ($mode_tap === 'pulang') ? $jam_sekarang : null,
        "keterangan" => $status_kehadiran,
        "sound" => "success"
    ];

    if ($format === 'text') {
        echo "$status_kehadiran\n" . substr($nama, 0, 16);
        exit;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    $response = [
        "status" => "error",
        "message" => "Gagal menyimpan absensi: " . mysqli_error($conn),
        "sound" => "error"
    ];
    if ($format === 'text') {
        echo "ERROR DB\nGAGAL SIMPAN";
        exit;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
