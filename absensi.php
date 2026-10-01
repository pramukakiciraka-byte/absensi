<?php
// session_start();

require_once "koneksi.php";

// Pengecekan login dimatikan agar bisa diakses oleh publik:
/*
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}
*/

$filter_tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : date('Y-m-d');
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where = "WHERE 1=1";

if ($filter_tanggal !== 'semua' && !empty($filter_tanggal)) {
    $tgl_esc = mysqli_real_escape_string($conn, $filter_tanggal);
    $where .= " AND absensi.tanggal = '$tgl_esc'";
}

if (!empty($filter_status)) {
    $status_esc = mysqli_real_escape_string($conn, $filter_status);
    $where .= " AND absensi.status = '$status_esc'";
}

if (!empty($filter_kelas)) {
    $kelas_esc = mysqli_real_escape_string($conn, $filter_kelas);
    $where .= " AND siswa.kelas = '$kelas_esc'";
}

if (!empty($search)) {
    $where .= " AND (siswa.nama LIKE '%$search%' OR siswa.nis LIKE '%$search%')";
}

/*
|--------------------------------------------------------------------------
| QUERY ABSENSI
|--------------------------------------------------------------------------
| Data lama tetap dipakai:
| - id
| - nis
| - nama
| - kelas
| - tanggal
| - jam
| - status
|
| Ditambahkan:
| - jam_masuk
| - jam_pulang
| - status_pulang
*/
$query = mysqli_query($conn, "
    SELECT
        absensi.id,
        siswa.nis,
        siswa.nama,
        siswa.kelas,
        absensi.tanggal,
        absensi.jam,
        absensi.jam_masuk,
        absensi.jam_pulang,
        absensi.status,
        absensi.status_pulang
    FROM absensi
    INNER JOIN siswa ON absensi.siswa_id = siswa.id
    $where
    ORDER BY absensi.tanggal DESC, absensi.jam_masuk DESC, absensi.jam DESC
");

// Hitung total hasil filter
$total_baris = mysqli_num_rows($query);
$count_hadir = 0;
$count_terlambat = 0;
$count_pulang = 0;

// Ambil daftar kelas untuk dropdown filter
$q_kelas = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa WHERE kelas != '' ORDER BY kelas ASC");

// Ambil nama sekolah
$query_setting = mysqli_query($conn, "SELECT nama_sekolah FROM pengaturan WHERE id = 1 LIMIT 1");
$setting = mysqli_fetch_assoc($query_setting);
$nama_sekolah = $setting['nama_sekolah'] ?? 'MTs Matholiul Huda Tlogowungu';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Rekap Absensi Siswa - <?= htmlspecialchars($nama_sekolah) ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --blue-soft: #eff6ff;
        --text: #1e293b;
        --muted: #64748b;
        --border: #e2e8f0;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background:
            radial-gradient(
                circle at 10% 10%,
                #dbeafe 0,
                transparent 30%
            ),
            #f8fafc;
        color: var(--text);
        min-height: 100vh;
    }

    /* CARD UTAMA */
    .card-custom {
        border: 1px solid var(--border);
        border-radius: 22px;
        background: rgba(255, 255, 255, 0.97);
        box-shadow: 0 10px 35px rgba(15, 23, 42, 0.07);
    }

    /* JUDUL */
    h2 {
        color: #1e3a8a;
        letter-spacing: -0.5px;
    }

    h2 .bi {
        color: var(--primary);
    }

    /* FILTER */
    .form-label {
        color: #475569 !important;
    }

    .form-control,
    .form-select {
        border: 1px solid #dbeafe;
        border-radius: 11px;
        padding: 10px 13px;
        min-height: 43px;
        background: #f8fbff;
        color: #334155;
        transition: 0.2s;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #60a5fa;
        background: white;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10);
    }

    /* BUTTON */
    .btn {
        transition: all 0.2s ease;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        border: none;
        box-shadow: 0 6px 15px rgba(37, 99, 235, 0.20);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
        transform: translateY(-1px);
    }

    .btn-outline-success,
    .btn-outline-secondary {
        border-width: 1px;
        font-weight: 600;
    }

    .btn-outline-success:hover,
    .btn-outline-secondary:hover {
        transform: translateY(-1px);
    }

    /* TABEL */
    .table-responsive {
        border-radius: 15px;
        overflow-x: auto;
        border: 1px solid var(--border);
    }

    .table {
        margin-bottom: 0;
        vertical-align: middle;
        min-width: 1100px;
    }

    .table thead th {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        color: #1e40af;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 15px;
        border-bottom: 2px solid #bfdbfe;
        white-space: nowrap;
    }

    .table tbody td {
        padding: 15px;
        border-color: #eef2f7;
        color: #475569;
        font-size: 13px;
        white-space: nowrap;
    }

    .table tbody tr {
        transition: 0.15s ease;
    }

    .table tbody tr:hover {
        background: #f8fbff;
        transform: scale(1.001);
    }

    /* NAMA SISWA */
    .table tbody td:nth-child(3) {
        color: #1e293b;
        font-weight: 700;
    }

    /* BADGE KELAS */
    .table .badge.bg-light {
        background: #f1f5f9 !important;
        color: #334155 !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px;
        padding: 7px 10px;
    }

    /* STATUS HADIR */
    .badge.bg-success-subtle {
        background: #dcfce7 !important;
        color: #15803d !important;
        border-color: #bbf7d0 !important;
        font-weight: 600;
    }

    /* STATUS TERLAMBAT */
    .badge.bg-warning-subtle {
        background: #fef3c7 !important;
        color: #b45309 !important;
        border-color: #fde68a !important;
        font-weight: 600;
    }

    /* STATUS BELUM PULANG */
    .badge.bg-light {
        background: #f8fafc !important;
        color: #64748b !important;
        border-color: #e2e8f0 !important;
        font-weight: 600;
    }

    /* RINGKASAN */
    .border-top {
        border-color: #e2e8f0 !important;
    }

    /* PRINT */
    @media print {
        .no-print,
        nav,
        .navbar,
        form,
        .btn {
            display: none !important;
        }

        body {
            background: white !important;
            color: black !important;
        }

        .card-custom {
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
        }

        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 20px;
        }

        .table {
            min-width: 0 !important;
        }

        .table thead th,
        .table tbody td {
            font-size: 10px !important;
            padding: 7px !important;
        }
    }

    .print-header {
        display: none;
    }

    /* MOBILE */
    @media (max-width: 768px) {
        .container-fluid {
            padding-left: 15px !important;
            padding-right: 15px !important;
        }

        h2 {
            font-size: 22px;
        }

        .card-custom {
            padding: 18px !important;
            border-radius: 16px;
        }

        .table thead th,
        .table tbody td {
            padding: 12px 10px;
        }
    }
    </style>
</head>

<body>

<!-- NAVBAR NAVIGASI PUBLIK -->
<div class="no-print">
    <?php 
    if (file_exists("navbar.php")) {
        include "navbar.php"; 
    } else {
    ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="absensi.php">
                    <i class="bi bi-qr-code-scan"></i> Portal Absensi
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navMenu">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link active fw-semibold" href="absensi.php">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50 fw-semibold" href="scan.php">
                                <i class="bi bi-camera me-1"></i> Scan Kartu
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50 fw-semibold" href="siswa.php">
                                <i class="bi bi-people me-1"></i> Data Siswa
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php } ?>
</div>

<div class="container-fluid px-4 py-3">

    <!-- Kop Cetak saat Print -->
    <div class="print-header">
        <h3 class="fw-bold mb-0">LAPORAN REKAPITULASI ABSENSI SISWA</h3>
        <h5 class="mb-1"><?= htmlspecialchars($nama_sekolah) ?></h5>
        <p class="small text-muted mb-3">
            Dicetak pada: <?= date('d/m/Y H:i:s') ?> WIB
        </p>
        <hr>
    </div>

    <!-- Header & Action -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 no-print">
        <div>
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-calendar-check text-primary"></i>
                Rekapitulasi Data Absensi
            </h2>
            <p class="text-muted mb-0">
                Riwayat kehadiran harian siswa dari hasil scan kartu RFID (Akses Publik)
            </p>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-outline-success rounded-pill px-3 shadow-sm" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </button>

            <button class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak Laporan
            </button>
        </div>
    </div>

    <!-- Card Filter & Tabel -->
    <div class="card card-custom p-4">

        <!-- Form Filter -->
        <form method="GET" class="row g-3 align-items-end mb-4 no-print">

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">
                    Filter Tanggal
                </label>

                <div class="input-group">
                    <input
                        type="date"
                        name="tanggal"
                        class="form-control"
                        value="<?= ($filter_tanggal !== 'semua') ? htmlspecialchars($filter_tanggal) : '' ?>"
                    >

                    <a
                        href="absensi.php?tanggal=semua"
                        class="btn btn-outline-secondary"
                        title="Tampilkan Semua Tanggal"
                    >
                        Semua
                    </a>
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">
                    Kelas
                </label>

                <select name="kelas" class="form-select">
                    <option value="">Semua Kelas</option>

                    <?php while ($k = mysqli_fetch_assoc($q_kelas)): ?>

                        <option
                            value="<?= htmlspecialchars($k['kelas']) ?>"
                            <?= ($filter_kelas == $k['kelas']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($k['kelas']) ?>
                        </option>

                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">
                    Status
                </label>

                <select name="status" class="form-select">
                    <option value="">Semua Status</option>

                    <option
                        value="Hadir"
                        <?= ($filter_status == 'Hadir') ? 'selected' : '' ?>
                    >
                        Hadir Tepat Waktu
                    </option>

                    <option
                        value="Terlambat"
                        <?= ($filter_status == 'Terlambat') ? 'selected' : '' ?>
                    >
                        Terlambat
                    </option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">
                    Cari Nama / NIS
                </label>

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Nama atau NIS..."
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill w-100">
                    <i class="bi bi-funnel"></i> Terapkan
                </button>

                <a
                    href="absensi.php"
                    class="btn btn-light border rounded-pill"
                    title="Reset Filter"
                >
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>

        </form>

        <!-- Tabel Data Absensi -->
        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0" id="tableAbsensi">

                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Tanggal</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status</th>
                        <th>Status Pulang</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($total_baris > 0): ?>

                        <?php
                        $no = 1;

                        while ($data = mysqli_fetch_assoc($query)):

                            if ($data['status'] === 'Hadir') {
                                $count_hadir++;
                            }

                            if ($data['status'] === 'Terlambat') {
                                $count_terlambat++;
                            }

                            if (!empty($data['jam_pulang'])) {
                                $count_pulang++;
                            }
                        ?>

                            <tr>

                                <!-- NO -->
                                <td>
                                    <?= $no++ ?>
                                </td>

                                <!-- NIS -->
                                <td class="font-monospace fw-semibold text-muted">
                                    <?= htmlspecialchars($data['nis']) ?>
                                </td>

                                <!-- NAMA -->
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($data['nama']) ?>
                                </td>

                                <!-- KELAS -->
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($data['kelas']) ?>
                                    </span>
                                </td>

                                <!-- TANGGAL -->
                                <td>
                                    <?= date('d/m/Y', strtotime($data['tanggal'])) ?>
                                </td>

                                <!-- JAM MASUK -->
                                <td class="font-monospace fw-semibold">

                                    <?php
                                    $jam_masuk_tampil = !empty($data['jam_masuk'])
                                        ? $data['jam_masuk']
                                        : $data['jam'];
                                    ?>

                                    <?= !empty($jam_masuk_tampil)
                                        ? substr($jam_masuk_tampil, 0, 5) . ' WIB'
                                        : '-'
                                    ?>

                                </td>

                                <!-- JAM PULANG -->
                                <td class="font-monospace fw-semibold">

                                    <?php if (!empty($data['jam_pulang'])): ?>

                                        <?= substr($data['jam_pulang'], 0, 5) ?> WIB

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Belum Pulang
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- STATUS MASUK -->
                                <td>

                                    <?php if ($data['status'] == 'Hadir'): ?>

                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Hadir
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1">
                                            <i class="bi bi-clock-history me-1"></i>
                                            Terlambat
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- STATUS PULANG -->
                                <td>

                                    <?php if (!empty($data['jam_pulang'])): ?>

                                        <?php if ($data['status_pulang'] === 'Tepat Waktu'): ?>

                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                                                <i class="bi bi-check-circle me-1"></i>
                                                Tepat Waktu
                                            </span>

                                        <?php elseif ($data['status_pulang'] === 'Pulang Cepat'): ?>

                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1">
                                                <i class="bi bi-clock-history me-1"></i>
                                                Pulang Cepat
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-light text-muted border rounded-pill px-3 py-1">
                                                <?= htmlspecialchars($data['status_pulang']) ?>
                                            </span>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        <span class="badge bg-light text-muted border rounded-pill px-3 py-1">
                                            Belum Pulang
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">

                                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>

                                Tidak ada data absensi untuk kriteria filter ini.

                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- Footer Ringkasan Hasil Filter -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top small text-muted">

            <div>
                Menampilkan
                <strong><?= $total_baris ?></strong>
                rekaman absensi

                (Tanggal:
                <strong>
                    <?= ($filter_tanggal === 'semua')
                        ? 'Semua Tanggal'
                        : date('d/m/Y', strtotime($filter_tanggal))
                    ?>
                </strong>)
            </div>

            <div class="d-flex gap-3 flex-wrap">

                <span>
                    Hadir:
                    <strong class="text-success">
                        <?= $count_hadir ?>
                    </strong>
                </span>

                <span>
                    Terlambat:
                    <strong class="text-warning">
                        <?= $count_terlambat ?>
                    </strong>
                </span>

                <span>
                    Sudah Pulang:
                    <strong class="text-primary">
                        <?= $count_pulang ?>
                    </strong>
                </span>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Fungsi Export Table ke file XLS (Excel)
function exportToExcel() {

    let table = document.getElementById("tableAbsensi");
    let html = table.outerHTML;

    let url = 'data:application/vnd.ms-excel;charset=utf-8,' + encodeURIComponent(`
        <html xmlns:o="urn:schemas-microsoft-com:office:office"
              xmlns:x="urn:schemas-microsoft-com:office:excel"
              xmlns="http://www.w3.org/TR/REC-html40">

        <head>
            <meta charset="utf-8"/>
        </head>

        <body>

            <h3>
                Laporan Absensi Siswa - <?= htmlspecialchars($nama_sekolah) ?>
            </h3>

            <p>
                Tanggal:
                <?= ($filter_tanggal === 'semua') ? 'Semua' : $filter_tanggal ?>
            </p>

            ${html}

        </body>

        </html>
    `);

    let downloadLink = document.createElement("a");

    downloadLink.href = url;

    downloadLink.download =
        `rekap_absensi_${new Date().toISOString().slice(0,10)}.xls`;

    document.body.appendChild(downloadLink);

    downloadLink.click();

    document.body.removeChild(downloadLink);
}
</script>

</body>
</html>