<?php
// api_realtime.php - API Polling untuk Dashboard Real-time
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once "koneksi.php";

$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

if ($action === 'get_last_unregistered') {
    // Digunakan oleh form tambah siswa di siswa.php
    $q = mysqli_query($conn, "SELECT uid_rfid, waktu FROM rfid_temp WHERE status_scan = 'unregistered' ORDER BY id DESC LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        echo json_encode([
            "status" => "success",
            "uid" => $row['uid_rfid'],
            "waktu" => $row['waktu']
        ]);
    } else {
        echo json_encode([
            "status" => "empty",
            "message" => "Belum ada kartu baru yang di-tap ke alat RFID."
        ]);
    }
    exit;
}

// Default action: dashboard realtime data
$today = date('Y-m-d');

// 1. Total Siswa Aktif
$q_siswa = mysqli_query($conn, "SELECT COUNT(*) AS total FROM siswa WHERE status = 'aktif'");
$total_siswa = (int)mysqli_fetch_assoc($q_siswa)['total'];

// 2. Hadir Tepat Waktu
$q_hadir = mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = '$today' AND status = 'Hadir'");
$total_hadir = (int)mysqli_fetch_assoc($q_hadir)['total'];

// 3. Terlambat
$q_terlambat = mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = '$today' AND status = 'Terlambat'");
$total_terlambat = (int)mysqli_fetch_assoc($q_terlambat)['total'];

$total_masuk = $total_hadir + $total_terlambat;
$belum_hadir = max(0, $total_siswa - $total_masuk);
$persen_hadir = ($total_siswa > 0) ? round(($total_masuk / $total_siswa) * 100, 1) : 0;

// 4. Scan Terakhir dari rfid_temp
$q_last = mysqli_query($conn, "SELECT * FROM rfid_temp ORDER BY id DESC LIMIT 1");
$last_scan = null;
if ($q_last && mysqli_num_rows($q_last) > 0) {
    $last_scan = mysqli_fetch_assoc($q_last);

    // Cari info siswa jika ada
    $clean_uid = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $last_scan['uid_rfid']));
    $q_stu = mysqli_query($conn, "SELECT id, nis, nama, kelas FROM siswa WHERE UPPER(REPLACE(REPLACE(REPLACE(uid_rfid, ' ', ''), ':', ''), '-', '')) = '$clean_uid' LIMIT 1");
    if ($q_stu && mysqli_num_rows($q_stu) > 0) {
        $stu_row = mysqli_fetch_assoc($q_stu);
        $last_scan['nama'] = $stu_row['nama'];
        $last_scan['nis'] = $stu_row['nis'];
        $last_scan['kelas'] = $stu_row['kelas'];
    }
}

// 5. 10 Absensi Terkini Hari Ini
$q_recent = mysqli_query($conn, "
    SELECT 
        absensi.id,
        siswa.nis,
        siswa.nama,
        siswa.kelas,
        absensi.jam,
        absensi.status
    FROM absensi
    INNER JOIN siswa ON absensi.siswa_id = siswa.id
    WHERE absensi.tanggal = '$today'
    ORDER BY absensi.id DESC
    LIMIT 10
");

$recent_list = [];
while ($row = mysqli_fetch_assoc($q_recent)) {
    $recent_list[] = $row;
}

// 6. Statistik Kehadiran per Kelas
$q_kelas = mysqli_query($conn, "
    SELECT 
        s.kelas,
        COUNT(DISTINCT s.id) as total_siswa_kelas,
        COUNT(DISTINCT CASE WHEN a.status = 'Hadir' THEN a.siswa_id END) as hadir_kelas,
        COUNT(DISTINCT CASE WHEN a.status = 'Terlambat' THEN a.siswa_id END) as terlambat_kelas
    FROM siswa s
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = '$today'
    WHERE s.status = 'aktif' AND s.kelas != ''
    GROUP BY s.kelas
    ORDER BY s.kelas ASC
");

$kelas_labels = [];
$kelas_hadir = [];
$kelas_terlambat = [];
$kelas_belum = [];

if ($q_kelas) {
    while ($krow = mysqli_fetch_assoc($q_kelas)) {
        $kelas_labels[] = $krow['kelas'];
        $h = (int)$krow['hadir_kelas'];
        $t = (int)$krow['terlambat_kelas'];
        $tot = (int)$krow['total_siswa_kelas'];
        $b = max(0, $tot - ($h + $t));

        $kelas_hadir[] = $h;
        $kelas_terlambat[] = $t;
        $kelas_belum[] = $b;
    }
}

// Ambil IP Lokal Server
$local_ip = gethostbyname(gethostname());
if ($local_ip === '127.0.0.1' && isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '::1') {
    $local_ip = $_SERVER['SERVER_ADDR'];
}

// Ambil pengaturan
$q_set = mysqli_query($conn, "SELECT nama_sekolah, jam_masuk FROM pengaturan WHERE id = 1 LIMIT 1");
$setting = mysqli_fetch_assoc($q_set);

echo json_encode([
    "status" => "success",
    "stats" => [
        "total_siswa" => $total_siswa,
        "total_hadir" => $total_hadir,
        "total_terlambat" => $total_terlambat,
        "total_masuk" => $total_masuk,
        "belum_hadir" => $belum_hadir,
        "persen_hadir" => $persen_hadir
    ],
    "kelas_stats" => [
        "labels" => $kelas_labels,
        "hadir" => $kelas_hadir,
        "terlambat" => $kelas_terlambat,
        "belum" => $kelas_belum
    ],
    "last_scan" => $last_scan,
    "recent_absensi" => $recent_list,
    "server_time" => date('H:i:s'),
    "server_date" => date('d M Y'),
    "local_ip" => $local_ip,
    "jam_masuk" => $setting['jam_masuk'] ?? '07:00:00',
    "nama_sekolah" => $setting['nama_sekolah'] ?? 'MTs Matholiul Huda Tlogowungu'
], JSON_UNESCAPED_UNICODE);
?>
