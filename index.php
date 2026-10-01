<?php
// index.php - Dashboard Utama Absensi Digital RFID
require_once "koneksi.php";

// Tanggal dalam format Bahasa Indonesia
$hari_list = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulan_list = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$hari_ini = $hari_list[date('l')];
$tgl_ini = date('d');
$bln_ini = $bulan_list[(int)date('m')];
$thn_ini = date('Y');
$tanggal_lengkap = "$hari_ini, $tgl_ini $bln_ini $thn_ini";

// Ambil data pengaturan
$q_set = mysqli_query($conn, "SELECT nama_sekolah, jam_masuk FROM pengaturan WHERE id = 1 LIMIT 1");
$setting = mysqli_fetch_assoc($q_set);
$nama_sekolah = $setting['nama_sekolah'] ?? 'MTs Matholiul Huda Tlogowungu';
$jam_masuk = $setting['jam_masuk'] ?? '07:00:00';

// Ambil IP lokal server
$server_ip = gethostbyname(gethostname());
if ($server_ip === '127.0.0.1' && isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '::1') {
    $server_ip = $_SERVER['SERVER_ADDR'];
}

// Ambil daftar siswa aktif untuk modal simulasi
$q_siswa_simulasi = mysqli_query($conn, "SELECT id, nis, nama, kelas, uid_rfid FROM siswa WHERE status = 'aktif' ORDER BY kelas ASC, nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Absensi Digital RFID - <?= htmlspecialchars($nama_sekolah) ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #0ea5e9;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-page: #f8fafc;
            --card-border: #e2e8f0;
            --font-family: 'Plus Jakarta Sans', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        body {
            font-family: var(--font-family);
            background: 
                radial-gradient(circle at 10% 10%, #dbeafe 0%, transparent 35%),
                radial-gradient(circle at 90% 90%, #e0e7ff 0%, transparent 35%),
                var(--bg-page);
            color: #1e293b;
            min-height: 100vh;
        }

        .card-custom {
            border: 1px solid var(--card-border);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-stat {
            position: relative;
            overflow: hidden;
            border-radius: 18px;
            border: 1px solid var(--card-border);
            background: white;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            transition: all 0.25s ease;
        }
        .card-stat:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.09);
        }
        .card-stat .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        /* LIVE SCANNER STYLING */
        .scanner-card {
            background: linear-gradient(145deg, #1e293b, #0f172a);
            border: 1px solid #334155;
            color: white;
            border-radius: 24px;
            position: relative;
            overflow: hidden;
        }
        .scanner-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.15) 0%, transparent 65%);
            pointer-events: none;
        }

        .radar-pulse {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.2);
            border: 2px solid #38bdf8;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin: 0 auto;
        }
        .radar-pulse::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid #38bdf8;
            animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.4); opacity: 0.4; }
            100% { transform: scale(1.8); opacity: 0; }
        }

        /* SCAN STATUS THEMES */
        .status-box {
            border-radius: 18px;
            padding: 20px;
            transition: all 0.4s ease;
        }
        .status-standby {
            background: rgba(255, 255, 255, 0.05);
            border: 1px dashed rgba(255, 255, 255, 0.2);
        }
        .status-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.3));
            border: 1px solid #10b981;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.3);
            animation: flashGreen 0.6s ease;
        }
        .status-already {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.3));
            border: 1px solid #f59e0b;
            box-shadow: 0 0 25px rgba(245, 158, 11, 0.3);
            animation: flashYellow 0.6s ease;
        }
        .status-unregistered {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.3));
            border: 1px solid #ef4444;
            box-shadow: 0 0 25px rgba(239, 68, 68, 0.3);
            animation: flashRed 0.6s ease;
        }

        @keyframes flashGreen {
            0% { transform: scale(0.97); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        @keyframes flashYellow {
            0% { transform: scale(0.97); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        @keyframes flashRed {
            0% { transform: scale(0.97); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        /* STUDENT AVATAR */
        .avatar-circle {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }

        .avatar-lg {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            font-size: 1.6rem;
        }

        /* BADGES */
        .badge-soft-success {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: 600;
        }
        .badge-soft-warning {
            background-color: #fef3c7;
            color: #b45309;
            font-weight: 600;
        }
        .badge-soft-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            font-weight: 600;
        }

        /* KIOSK / FULLSCREEN MODE */
        .kiosk-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: #090d16;
            z-index: 9999;
            color: white;
            overflow-y: auto;
            padding: 40px 5%;
        }
        .kiosk-overlay.active {
            display: block;
        }

        /* FEED ITEM ANIMATION */
        .feed-item {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        .feed-item:last-child {
            border-bottom: none;
        }
        .feed-item:hover {
            background-color: #f8fafc;
        }
        .feed-item.new-entry {
            animation: slideIn 0.5s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .font-mono {
            font-family: var(--font-mono);
        }
    </style>
</head>
<body>

<!-- INCLUDE NAVBAR TERPADU -->
<?php include "navbar.php"; ?>

<div class="container-fluid px-3 px-lg-5 py-4">

    <!-- SUBHEADER / ACTION BAR -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 d-none d-sm-block">
                    <i class="bi bi-person-badge fs-2"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 text-primary-dark d-flex align-items-center gap-2">
                        Dashboard Absensi Digital RFID
                        <span class="badge bg-success-subtle text-success fs-6 fw-semibold rounded-pill px-3 py-1 border border-success-subtle d-inline-flex align-items-center gap-1">
                            <span class="spinner-grow spinner-grow-sm text-success" role="status" style="width: 0.5rem; height: 0.5rem;"></span>
                            Live Reader
                        </span>
                    </h2>
                    <p class="text-muted mb-0 small">
                        <i class="bi bi-calendar-event me-1"></i> <?= $tanggal_lengkap ?> &bull; 
                        Batas Jam Masuk: <strong class="text-dark font-mono"><?= htmlspecialchars($jam_masuk) ?> WIB</strong> &bull; 
                        Lembaga: <strong><?= htmlspecialchars($nama_sekolah) ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                <!-- Tombol Simulasi Tap -->
                <button type="button" class="btn btn-primary rounded-pill px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalSimulasi">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <span>Simulasi Tap Kartu</span>
                </button>

                <!-- Tombol Audio Toggle -->
                <button type="button" id="btnAudioToggle" class="btn btn-outline-secondary rounded-pill px-3 d-flex align-items-center gap-2" title="Toggle Suara Suara Notifikasi">
                    <i class="bi bi-volume-up-fill text-primary" id="audioIcon"></i>
                    <span id="audioText">Suara: On</span>
                </button>

                <!-- Tombol Layar Kiosk / Fullscreen -->
                <button type="button" id="btnKioskMode" class="btn btn-dark rounded-pill px-3 d-flex align-items-center gap-2">
                    <i class="bi bi-fullscreen"></i>
                    <span>Mode Kiosk</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 4 STATISTIK UTAMA -->
    <div class="row g-3 mb-4">
        <!-- TOTAL SISWA -->
        <div class="col-6 col-lg-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold text-uppercase">Total Siswa</span>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="h2 fw-bold mb-1" id="statTotalSiswa">0</div>
                <div class="small text-muted">Siswa terdaftar aktif</div>
            </div>
        </div>

        <!-- HADIR TEPAT WAKTU -->
        <div class="col-6 col-lg-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold text-uppercase">Tepat Waktu</span>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <div class="h2 fw-bold mb-1 text-success" id="statTotalHadir">0</div>
                    <span class="badge bg-success-subtle text-success rounded-pill small" id="badgePersenHadir">0%</span>
                </div>
                <div class="small text-muted">&le; <?= substr($jam_masuk, 0, 5) ?> WIB</div>
            </div>
        </div>

        <!-- TERLAMBAT -->
        <div class="col-6 col-lg-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold text-uppercase">Terlambat</span>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="h2 fw-bold mb-1 text-warning" id="statTotalTerlambat">0</div>
                <div class="small text-muted">&gt; <?= substr($jam_masuk, 0, 5) ?> WIB</div>
            </div>
        </div>

        <!-- BELUM HADIR -->
        <div class="col-6 col-lg-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold text-uppercase">Belum Absen</span>
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                </div>
                <div class="h2 fw-bold mb-1 text-danger" id="statBelumHadir">0</div>
                <div class="small text-muted">Belum tap kartu hari ini</div>
            </div>
        </div>
    </div>

    <!-- MAIN SECTION: LIVE SCANNER & REALTIME ACTIVITY FEED -->
    <div class="row g-4 mb-4">
        <!-- MONITOR SCANNER RFID LIVE -->
        <div class="col-lg-5">
            <div class="card scanner-card h-100 p-4 shadow-lg">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary bg-opacity-25 p-2 rounded-circle text-info">
                            <i class="bi bi-broadcast fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-white">Live Scanner RFID</h5>
                            <small class="text-white-50">Sensor RC522 & ESP32</small>
                        </div>
                    </div>
                    <span class="badge bg-success rounded-pill px-3 py-2 font-mono small d-flex align-items-center gap-1">
                        <i class="bi bi-wifi"></i> WiFi: IMS_SAKATECH
                    </span>
                </div>

                <!-- BOX HASIL SCANNING (DYNAMIC) -->
                <div id="scannerBox" class="status-box status-standby text-center my-auto py-4">
                    <div id="scannerStandby">
                        <div class="radar-pulse mb-3">
                            <i class="bi bi-credit-card-2-front fs-2 text-info"></i>
                        </div>
                        <h5 class="fw-bold text-white mb-1">Siap Menerima Tap Kartu</h5>
                        <p class="text-white-50 small mb-0">Tempelkan kartu RFID siswa pada alat pembaca...</p>
                    </div>

                    <div id="scannerResult" style="display: none;">
                        <!-- Konten diisi otomatis oleh JavaScript saat kartu di-tap -->
                    </div>
                </div>

                <!-- HARDWARE INFO BAR -->
                <div class="bg-dark bg-opacity-50 p-3 rounded-4 mt-3 border border-secondary border-opacity-25">
                    <div class="d-flex justify-content-between align-items-center text-white-50 small mb-2">
                        <span><i class="bi bi-hdd-network me-1"></i> Server Endpoint:</span>
                        <button class="btn btn-link btn-sm text-info text-decoration-none p-0" onclick="copyApiUrl()">
                            <i class="bi bi-clipboard me-1"></i> Salin URL
                        </button>
                    </div>
                    <code id="apiCodeDisplay" class="d-block font-mono bg-black bg-opacity-60 text-info p-2 rounded small text-break user-select-all">
                        http://<?= $server_ip ?>/absensi/api_absensi.php?uid=KODE_RFID
                    </code>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-secondary border-opacity-25 text-white-50" style="font-size: 0.75rem;">
                        <span><i class="bi bi-router me-1"></i> SSID: <strong class="text-light">IMS_SAKATECH</strong></span>
                        <span><i class="bi bi-clock me-1"></i> Polling: <span class="text-success font-monospace" id="pollStatus">Aktif (1.5s)</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT ATTENDANCE FEED -->
        <div class="col-lg-7">
            <div class="card card-custom h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-1 text-primary-dark">
                            <i class="bi bi-activity text-primary me-2"></i>Aktivitas Absensi Terkini
                        </h5>
                        <small class="text-muted">Daftar siswa yang baru saja melakukan tap kartu hari ini</small>
                    </div>
                    <a href="absensi.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-uppercase text-muted">
                                <th style="width: 50px;">Siswa</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Jam Tap</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="recentFeedBody">
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Memuat data kehadiran...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS SECTION: DONUT PROPORSI & BAR PER KELAS -->
    <div class="row g-4">
        <!-- CHART DONUT: STATUS KEHADIRAN -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-primary-dark">
                        <i class="bi bi-pie-chart-fill text-primary me-2"></i>Proporsi Kehadiran
                    </h6>
                    <span class="badge bg-light text-muted border">Hari Ini</span>
                </div>
                <div style="position: relative; height: 260px;">
                    <canvas id="chartDonutKehadiran"></canvas>
                </div>
                <div class="d-flex justify-content-around text-center mt-3 pt-3 border-top small">
                    <div>
                        <div class="fw-bold text-success" id="legendHadir">0</div>
                        <span class="text-muted">Hadir</span>
                    </div>
                    <div>
                        <div class="fw-bold text-warning" id="legendTerlambat">0</div>
                        <span class="text-muted">Terlambat</span>
                    </div>
                    <div>
                        <div class="fw-bold text-danger" id="legendBelum">0</div>
                        <span class="text-muted">Belum</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHART BAR: KEHADIRAN PER KELAS -->
        <div class="col-lg-8">
            <div class="card card-custom p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-primary-dark">
                            <i class="bi bi-bar-chart-fill text-primary me-2"></i>Statistik Kehadiran per Kelas
                        </h6>
                        <small class="text-muted">Perbandingan siswa hadir dan terlambat di tiap kelas</small>
                    </div>
                    <span class="badge bg-light text-muted border">Semua Kelas</span>
                </div>
                <div style="position: relative; height: 280px;">
                    <canvas id="chartBarKelas"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- MODAL SIMULASI TAP KARTU (UNTUK UJI COBA TANPA HARWARE / DENGAN HARDWARE) -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalSimulasi" tabindex="-1" aria-labelledby="modalSimulasiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-gradient text-white rounded-top-4" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalSimulasiLabel">
                    <i class="bi bi-lightning-charge-fill text-warning"></i>
                    Simulasi Tap Kartu RFID
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Gunakan alat simulasi ini untuk menguji respon API, animasi scanner, audio bel, serta penambahan data absensi secara langsung seolah-olah kartu ditempelkan ke sensor ESP32.
                </p>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Pilih Siswa Terdaftar:</label>
                    <select id="simSiswaSelect" class="form-select rounded-3 py-2" onchange="pilihSiswaSimulasi(this)">
                        <option value="">-- Pilih Siswa Untuk Uji Coba --</option>
                        <?php while ($s = mysqli_fetch_assoc($q_siswa_simulasi)): ?>
                            <option value="<?= htmlspecialchars($s['uid_rfid']) ?>" data-nama="<?= htmlspecialchars($s['nama']) ?>" data-kelas="<?= htmlspecialchars($s['kelas']) ?>">
                                [<?= htmlspecialchars($s['kelas']) ?>] <?= htmlspecialchars($s['nama']) ?> (UID: <?= htmlspecialchars($s['uid_rfid']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Atau Masukkan / Scan UID RFID Manual:</label>
                    <div class="input-group">
                        <span class="input-group-text font-mono bg-light"><i class="bi bi-upc-scan"></i></span>
                        <input type="text" id="simUidInput" class="form-control font-mono text-uppercase" placeholder="Contoh: A3 7B 91 22" value="A3 7B 91 22">
                        <button class="btn btn-outline-secondary" type="button" onclick="randomCardSimulasi()" title="Gunakan UID Kartu Acak Baru">
                            <i class="bi bi-shuffle"></i> Kartu Acak
                        </button>
                    </div>
                    <div class="form-text text-muted">
                        Format bisa dengan spasi atau tanpa spasi (misal: <code>A37B9122</code>).
                    </div>
                </div>

                <div id="simulasiAlert" class="alert alert-secondary py-2 small mb-0 d-none">
                    <!-- Pesan respon simulasi -->
                </div>
            </div>
            <div class="modal-footer bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 d-flex align-items-center gap-2" id="btnKirimSimulasi" onclick="eksekusiSimulasiTap()">
                    <i class="bi bi-broadcast"></i>
                    <span>Kirim Tap ke API</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODE KIOSK / DISPLAY FULLSCREEN MONITOR PINTU GERBANG SEKOLAH             -->
<!-- ========================================================================= -->
<div id="kioskOverlay" class="kiosk-overlay">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary border-opacity-25 pb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary text-white p-3 rounded-4">
                <i class="bi bi-broadcast fs-1"></i>
            </div>
            <div>
                <h2 class="fw-bold text-white mb-0"><?= htmlspecialchars($nama_sekolah) ?></h2>
                <div class="text-info fs-5">Sistem Absensi Digital RFID Mandiri</div>
            </div>
        </div>
        <div class="text-end">
            <div id="kioskClock" class="display-5 fw-bold font-mono text-warning">--:--:-- WIB</div>
            <div class="text-white-50 fs-6"><?= $tanggal_lengkap ?></div>
        </div>
        <button class="btn btn-outline-light rounded-pill px-4 ms-3" onclick="toggleKioskMode(false)">
            <i class="bi bi-fullscreen-exit me-1"></i> Keluar Kiosk
        </button>
    </div>

    <div class="row g-4 align-items-center justify-content-center" style="min-height: 65vh;">
        <div class="col-lg-8">
            <div id="kioskScannerCard" class="card bg-dark bg-opacity-75 border border-secondary border-opacity-50 p-5 rounded-5 shadow-lg text-center">
                <div id="kioskStandby">
                    <div class="radar-pulse mb-4" style="width: 140px; height: 140px;">
                        <i class="bi bi-credit-card-2-front text-info" style="font-size: 3.5rem;"></i>
                    </div>
                    <h2 class="fw-bold text-white mb-2">SILAKAN TEMPELKAN KARTU ANDA</h2>
                    <p class="text-white-50 fs-5 mb-0">Arahkan kartu RFID siswa ke sensor ESP32 untuk melakukan absensi otomatis</p>
                </div>
                <div id="kioskResult" style="display: none;">
                    <!-- Konten terisi saat scan -->
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-4 text-center">
        <div class="col-md-3">
            <div class="bg-dark bg-opacity-50 p-3 rounded-4 border border-secondary border-opacity-25">
                <span class="text-white-50 d-block small">TOTAL SISWA</span>
                <span class="h2 fw-bold text-white" id="kioskTotalSiswa">0</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bg-dark bg-opacity-50 p-3 rounded-4 border border-secondary border-opacity-25">
                <span class="text-white-50 d-block small">HADIR TEPAT WAKTU</span>
                <span class="h2 fw-bold text-success" id="kioskTotalHadir">0</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bg-dark bg-opacity-50 p-3 rounded-4 border border-secondary border-opacity-25">
                <span class="text-white-50 d-block small">TERLAMBAT</span>
                <span class="h2 fw-bold text-warning" id="kioskTotalTerlambat">0</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bg-dark bg-opacity-50 p-3 rounded-4 border border-secondary border-opacity-25">
                <span class="text-white-50 d-block small">BELUM HADIR</span>
                <span class="h2 fw-bold text-danger" id="kioskTotalBelum">0</span>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS: BOOTSTRAP, AUDIO SYNTH, REALTIME POLLING & CHARTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// =============================================================================
// STATE & VARIABEL GLOBAL
// =============================================================================
let audioEnabled = true;
let lastScanId = 0;
let chartDonut = null;
let chartBar = null;
let kioskActive = false;

// =============================================================================
// WEB AUDIO SYNTHESIZER & SPEECH FEEDBACK
// =============================================================================
const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

function playTone(freq, type, duration, delay = 0) {
    if (!audioEnabled) return;
    setTimeout(() => {
        try {
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + duration);
        } catch (e) {
            console.error("Audio error:", e);
        }
    }, delay);
}

function playSuccessChime() {
    // 2-tone melodic chime: C5 (523Hz) -> G5 (784Hz)
    playTone(523.25, 'sine', 0.15, 0);
    playTone(783.99, 'sine', 0.25, 120);
}

function playWarningChime() {
    // Alert chime
    playTone(440, 'triangle', 0.15, 0);
    playTone(330, 'triangle', 0.25, 100);
}

function playErrorChime() {
    // Low buzz
    playTone(220, 'sawtooth', 0.25, 0);
    playTone(180, 'sawtooth', 0.35, 120);
}

function speakGreeting(text) {
    if (!audioEnabled || !('speechSynthesis' in window)) return;
    try {
        window.speechSynthesis.cancel(); // batalkan ucapan sebelumnya jika ada
        const utter = new SpeechSynthesisUtterance(text);
        utter.lang = 'id-ID';
        utter.rate = 1.05;
        utter.pitch = 1.0;
        window.speechSynthesis.speak(utter);
    } catch (e) {
        console.warn("TTS Error:", e);
    }
}

// Toggle Audio Button
document.getElementById('btnAudioToggle').addEventListener('click', function() {
    audioEnabled = !audioEnabled;
    const icon = document.getElementById('audioIcon');
    const text = document.getElementById('audioText');
    if (audioEnabled) {
        icon.className = 'bi bi-volume-up-fill text-primary';
        text.textContent = 'Suara: On';
        playSuccessChime();
    } else {
        icon.className = 'bi bi-volume-mute-fill text-danger';
        text.textContent = 'Suara: Mute';
    }
});

// =============================================================================
// INISIALISASI GRAFIK CHART.JS
// =============================================================================
function initCharts() {
    // 1. Chart Donut: Proporsi Kehadiran
    const ctxDonut = document.getElementById('chartDonutKehadiran').getContext('2d');
    chartDonut = new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: ['Hadir Tepat Waktu', 'Terlambat', 'Belum Hadir'],
            datasets: [{
                data: [0, 0, 0],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: { family: 'Plus Jakarta Sans', size: 12 }
                    }
                }
            }
        }
    });

    // 2. Chart Bar: Kehadiran per Kelas
    const ctxBar = document.getElementById('chartBarKelas').getContext('2d');
    chartBar = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Hadir',
                    data: [],
                    backgroundColor: '#10b981',
                    borderRadius: 6
                },
                {
                    label: 'Terlambat',
                    data: [],
                    backgroundColor: '#f59e0b',
                    borderRadius: 6
                },
                {
                    label: 'Belum Hadir',
                    data: [],
                    backgroundColor: '#e2e8f0',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Plus Jakarta Sans', weight: '600' } }
                },
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans' } }
                }
            }
        }
    });
}

// =============================================================================
// POLLING REALTIME KE API_REALTIME.PHP
// =============================================================================
async function fetchRealtimeData() {
    try {
        const response = await fetch('api_realtime.php?action=dashboard&t=' + Date.now());
        if (!response.ok) return;
        const data = await response.json();

        if (data.status === 'success') {
            updateDashboardStats(data.stats);
            updateCharts(data.stats, data.kelas_stats);
            updateRecentTable(data.recent_absensi);

            // Periksa scan kartu baru
            if (data.last_scan && data.last_scan.id != lastScanId) {
                // Jika ini bukan inisialisasi awal, tampilkan event tap kartu
                if (lastScanId !== 0) {
                    handleNewScanEvent(data.last_scan);
                } else {
                    // Update state silent untuk pertama kali buka
                    renderScannerState(data.last_scan, false);
                }
                lastScanId = data.last_scan.id;
            }
        }
    } catch (e) {
        console.error("Gagal polling realtime data:", e);
    }
}

// Update Statistik Card
function updateDashboardStats(stats) {
    document.getElementById('statTotalSiswa').textContent = stats.total_siswa;
    document.getElementById('statTotalHadir').textContent = stats.total_hadir;
    document.getElementById('statTotalTerlambat').textContent = stats.total_terlambat;
    document.getElementById('statBelumHadir').textContent = stats.belum_hadir;
    document.getElementById('badgePersenHadir').textContent = stats.persen_hadir + '%';

    // Update Kiosk Stats jika sedang aktif
    document.getElementById('kioskTotalSiswa').textContent = stats.total_siswa;
    document.getElementById('kioskTotalHadir').textContent = stats.total_hadir;
    document.getElementById('kioskTotalTerlambat').textContent = stats.total_terlambat;
    document.getElementById('kioskTotalBelum').textContent = stats.belum_hadir;

    // Update Legend Nilai
    document.getElementById('legendHadir').textContent = stats.total_hadir;
    document.getElementById('legendTerlambat').textContent = stats.total_terlambat;
    document.getElementById('legendBelum').textContent = stats.belum_hadir;
}

// Update Grafik Chart.js
function updateCharts(stats, kelasStats) {
    if (chartDonut) {
        chartDonut.data.datasets[0].data = [
            stats.total_hadir,
            stats.total_terlambat,
            stats.belum_hadir
        ];
        chartDonut.update('none');
    }

    if (chartBar && kelasStats && kelasStats.labels) {
        chartBar.data.labels = kelasStats.labels;
        chartBar.data.datasets[0].data = kelasStats.hadir;
        chartBar.data.datasets[1].data = kelasStats.terlambat;
        chartBar.data.datasets[2].data = kelasStats.belum;
        chartBar.update('none');
    }
}

// Update Tabel Aktivitas Terkini
function updateRecentTable(recentList) {
    const tbody = document.getElementById('recentFeedBody');
    if (!recentList || recentList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-inbox me-1"></i> Belum ada siswa yang melakukan absensi hari ini.</td></tr>`;
        return;
    }

    let html = '';
    recentList.forEach(item => {
        const initials = getInitials(item.nama);
        const isHadir = (item.status === 'Hadir');
        const badgeClass = isHadir ? 'badge-soft-success' : 'badge-soft-warning';
        const iconClass = isHadir ? 'bi-check-circle-fill text-success' : 'bi-clock-history text-warning';

        html += `
            <tr class="feed-item">
                <td>
                    <div class="avatar-circle">${initials}</div>
                </td>
                <td>
                    <div class="fw-bold text-dark">${escapeHtml(item.nama)}</div>
                    <small class="text-muted font-mono">NIS: ${escapeHtml(item.nis)}</small>
                </td>
                <td>
                    <span class="badge bg-light text-dark border px-2 py-1 font-mono">${escapeHtml(item.kelas)}</span>
                </td>
                <td>
                    <div class="font-mono text-dark fw-semibold">${escapeHtml(item.jam)}</div>
                    <small class="text-muted" style="font-size:0.75rem;">WIB</small>
                </td>
                <td class="text-center">
                    <span class="badge ${badgeClass} px-3 py-2 rounded-pill d-inline-flex align-items-center gap-1">
                        <i class="bi ${iconClass}"></i> ${escapeHtml(item.status)}
                    </span>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

// Handler saat terjadi scan kartu baru
function handleNewScanEvent(scan) {
    renderScannerState(scan, true);

    if (scan.status_scan === 'success') {
        playSuccessChime();
        if (scan.nama) {
            speakGreeting(`Selamat datang, ${scan.nama}. Absensi Anda berhasil dicatat.`);
        }
    } else if (scan.status_scan === 'already') {
        playWarningChime();
        if (scan.nama) {
            speakGreeting(`${scan.nama}, Anda sudah melakukan absensi hari ini.`);
        }
    } else if (scan.status_scan === 'unregistered') {
        playErrorChime();
        speakGreeting("Kartu belum terdaftar.");
    }

    // Kembalikan ke standby setelah 6 detik
    setTimeout(() => {
        resetScannerToStandby();
    }, 6000);
}

// Render Tampilan Scanner
function renderScannerState(scan, animate = true) {
    const box = document.getElementById('scannerBox');
    const standbyEl = document.getElementById('scannerStandby');
    const resultEl = document.getElementById('scannerResult');

    // Kiosk elements
    const kioskStandby = document.getElementById('kioskStandby');
    const kioskResult = document.getElementById('kioskResult');

    standbyEl.style.display = 'none';
    resultEl.style.display = 'block';

    if (kioskStandby) kioskStandby.style.display = 'none';
    if (kioskResult) kioskResult.style.display = 'block';

    box.className = 'status-box';

    let html = '';
    let kioskHtml = '';

    if (scan.status_scan === 'success') {
        box.classList.add('status-success');
        const initials = getInitials(scan.nama || 'Siswa');
        const isHadir = (scan.keterangan && scan.keterangan.indexOf('Hadir') >= 0);
        const statusLabel = isHadir ? 'Hadir Tepat Waktu' : 'Terlambat Masuk';
        const badgeColor = isHadir ? 'bg-success' : 'bg-warning text-dark';

        html = `
            <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                <div class="avatar-circle avatar-lg shadow">${initials}</div>
                <div class="text-start">
                    <span class="badge ${badgeColor} rounded-pill px-3 py-1 mb-1 font-mono">${statusLabel}</span>
                    <h4 class="fw-bold text-white mb-0">${escapeHtml(scan.nama || 'Siswa')}</h4>
                    <span class="text-white-50 font-mono small">Kelas: ${escapeHtml(scan.kelas || '-')} | NIS: ${escapeHtml(scan.nis || '-')}</span>
                </div>
            </div>
            <div class="mt-2 text-white-50 small font-mono">
                <i class="bi bi-clock me-1 text-info"></i> Jam Tap: <strong class="text-white">${scan.waktu.split(' ')[1] || scan.waktu} WIB</strong>
            </div>
        `;

        kioskHtml = `
            <div class="d-inline-block p-4 rounded-5 mb-3" style="background: rgba(16, 185, 129, 0.2); border: 2px solid #10b981;">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            </div>
            <h1 class="display-5 fw-bold text-white mb-2">${escapeHtml(scan.nama || 'Siswa')}</h1>
            <p class="fs-4 text-info mb-3">Kelas: <strong>${escapeHtml(scan.kelas || '-')}</strong> &bull; NIS: <strong>${escapeHtml(scan.nis || '-')}</strong></p>
            <span class="badge ${badgeColor} fs-4 px-4 py-2 rounded-pill font-mono">${statusLabel}</span>
        `;
    } 
    else if (scan.status_scan === 'already') {
        box.classList.add('status-already');
        html = `
            <div class="text-center">
                <div class="mb-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                </div>
                <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 font-mono">Sudah Absen Hari Ini</span>
                <h4 class="fw-bold text-white mb-1">${escapeHtml(scan.nama || 'Siswa')}</h4>
                <p class="text-white-50 small mb-0">${escapeHtml(scan.keterangan || 'Data absensi sudah tersimpan sebelumnya.')}</p>
            </div>
        `;

        kioskHtml = `
            <div class="d-inline-block p-4 rounded-5 mb-3" style="background: rgba(245, 158, 11, 0.2); border: 2px solid #f59e0b;">
                <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
            </div>
            <h1 class="display-5 fw-bold text-white mb-2">${escapeHtml(scan.nama || 'Siswa')}</h1>
            <p class="fs-4 text-warning mb-0">Sudah Melakukan Absensi Hari Ini!</p>
        `;
    } 
    else if (scan.status_scan === 'unregistered') {
        box.classList.add('status-unregistered');
        html = `
            <div class="text-center">
                <div class="mb-2">
                    <i class="bi bi-x-circle-fill text-danger fs-1"></i>
                </div>
                <span class="badge bg-danger rounded-pill px-3 py-1 mb-2 font-mono">Kartu Belum Terdaftar</span>
                <h5 class="fw-bold text-white font-mono mb-2">UID: ${escapeHtml(scan.uid_rfid)}</h5>
                <p class="text-white-50 small mb-3">Kartu ini belum terhubung ke data siswa mana pun.</p>
                <a href="siswa.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                    <i class="bi bi-person-plus me-1"></i> Daftarkan Siswa
                </a>
            </div>
        `;

        kioskHtml = `
            <div class="d-inline-block p-4 rounded-5 mb-3" style="background: rgba(239, 68, 68, 0.2); border: 2px solid #ef4444;">
                <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
            </div>
            <h1 class="display-5 fw-bold text-danger mb-2">KARTU BELUM TERDAFTAR</h1>
            <p class="fs-4 font-mono text-white-50">UID: ${escapeHtml(scan.uid_rfid)}</p>
        `;
    }

    resultEl.innerHTML = html;
    if (kioskResult) kioskResult.innerHTML = kioskHtml;
}

// Reset Scanner Kembali ke Tampilan Siap/Standby
function resetScannerToStandby() {
    const box = document.getElementById('scannerBox');
    const standbyEl = document.getElementById('scannerStandby');
    const resultEl = document.getElementById('scannerResult');

    const kioskStandby = document.getElementById('kioskStandby');
    const kioskResult = document.getElementById('kioskResult');

    box.className = 'status-box status-standby text-center my-auto py-4';
    standbyEl.style.display = 'block';
    resultEl.style.display = 'none';

    if (kioskStandby) kioskStandby.style.display = 'block';
    if (kioskResult) kioskResult.style.display = 'none';
}

// =============================================================================
// SIMULASI TAP KARTU
// =============================================================================
function pilihSiswaSimulasi(selectEl) {
    const uid = selectEl.value;
    if (uid) {
        document.getElementById('simUidInput').value = uid;
    }
}

function randomCardSimulasi() {
    // Generate hex random 4 bytes (e.g. 5A 3F 99 1C)
    const hex = () => Math.floor(Math.random() * 256).toString(16).padStart(2, '0').toUpperCase();
    const randUid = `${hex()} ${hex()} ${hex()} ${hex()}`;
    document.getElementById('simUidInput').value = randUid;
    document.getElementById('simSiswaSelect').value = '';
}

async function eksekusiSimulasiTap() {
    const uidInput = document.getElementById('simUidInput');
    const uid = uidInput.value.trim();
    const alertEl = document.getElementById('simulasiAlert');
    const btn = document.getElementById('btnKirimSimulasi');

    if (!uid) {
        alert("Silakan masukkan atau pilih UID kartu terlebih dahulu!");
        return;
    }

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...`;

    try {
        const res = await fetch(`api_absensi.php?uid=${encodeURIComponent(uid)}&format=json`);
        const json = await res.json();

        alertEl.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger', 'alert-secondary');
        
        if (json.status === 'success') {
            alertEl.classList.add('alert-success');
            alertEl.innerHTML = `<i class="bi bi-check-circle me-1"></i> <strong>Berhasil!</strong> ${escapeHtml(json.nama)} tercatat ${escapeHtml(json.keterangan)} (${escapeHtml(json.jam)}).`;
        } else if (json.status === 'already') {
            alertEl.classList.add('alert-warning');
            alertEl.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> <strong>Peringatan!</strong> ${escapeHtml(json.nama)} sudah absen pada ${escapeHtml(json.jam)}.`;
        } else if (json.status === 'unregistered') {
            alertEl.classList.add('alert-danger');
            alertEl.innerHTML = `<i class="bi bi-x-circle me-1"></i> <strong>Tidak Terdaftar!</strong> Kartu UID ${escapeHtml(json.uid)} belum terdaftar.`;
        } else {
            alertEl.classList.add('alert-secondary');
            alertEl.innerHTML = json.message || 'Respon diterima.';
        }

        // Segera refresh data realtime di dashboard
        await fetchRealtimeData();

    } catch (err) {
        alertEl.classList.remove('d-none');
        alertEl.classList.add('alert-danger');
        alertEl.innerHTML = `<i class="bi bi-exclamation-octagon me-1"></i> Gagal menghubungi API server.`;
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-broadcast"></i> <span>Kirim Tap ke API</span>`;
    }
}

// Salin URL API
function copyApiUrl() {
    const text = document.getElementById('apiCodeDisplay').innerText.trim();
    navigator.clipboard.writeText(text).then(() => {
        alert("URL API berhasil disalin ke clipboard:\n" + text);
    }).catch(() => {
        prompt("Salin URL API ini:", text);
    });
}

// =============================================================================
// MODE KIOSK / FULLSCREEN
// =============================================================================
function toggleKioskMode(activate = true) {
    const overlay = document.getElementById('kioskOverlay');
    kioskActive = activate;

    if (activate) {
        overlay.classList.add('active');
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(() => {});
        }
    } else {
        overlay.classList.remove('active');
        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(() => {});
        }
    }
}

document.getElementById('btnKioskMode').addEventListener('click', () => toggleKioskMode(true));

// Keluar Kiosk dengan ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && kioskActive) {
        toggleKioskMode(false);
    }
});

// Update Jam di Kiosk
setInterval(() => {
    const kioskClock = document.getElementById('kioskClock');
    if (kioskClock) {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        kioskClock.textContent = `${h}:${m}:${s} WIB`;
    }
}, 1000);

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================
function getInitials(name) {
    if (!name) return 'S';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// =============================================================================
// STARTUP EXECUTION
// =============================================================================
document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    fetchRealtimeData();

    // Jalankan polling setiap 1.5 detik
    setInterval(fetchRealtimeData, 1500);
});
</script>

</body>
</html>