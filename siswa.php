<?php
session_start();
require_once "koneksi.php";

// Ambil pesan notifikasi jika ada
$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : '';

// Filter pencarian dan kelas
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$filter_kelas = isset($_GET['kelas']) ? mysqli_real_escape_string($conn, trim($_GET['kelas'])) : '';

$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND (nama LIKE '%$search%' OR nis LIKE '%$search%' OR uid_rfid LIKE '%$search%')";
}
if (!empty($filter_kelas)) {
    $where .= " AND kelas = '$filter_kelas'";
}

$query = mysqli_query($conn, "
    SELECT *
    FROM siswa
    $where
    ORDER BY kelas ASC, nama ASC
");

// Ambil daftar kelas unik untuk filter
$q_kelas_list = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa WHERE kelas != '' ORDER BY kelas ASC");

// Ambil nama sekolah
$q_set = mysqli_query($conn, "SELECT nama_sekolah FROM pengaturan WHERE id = 1 LIMIT 1");
$setting = mysqli_fetch_assoc($q_set);
$nama_sekolah = $setting['nama_sekolah'] ?? 'MTs Matholiul Huda Tlogowungu';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Siswa & Kartu RFID - <?= htmlspecialchars($nama_sekolah) ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --font-family: 'Plus Jakarta Sans', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        body {
            font-family: var(--font-family);
            background: 
                radial-gradient(circle at 10% 10%, #dbeafe 0%, transparent 35%),
                radial-gradient(circle at 90% 90%, #e0e7ff 0%, transparent 35%),
                #f8fafc;
            color: #1e293b;
            min-height: 100vh;
        }

        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .font-mono {
            font-family: var(--font-mono);
        }

        .badge-uid {
            background-color: #f1f5f9;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            padding: 5px 9px;
            border-radius: 8px;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="container-fluid px-3 px-lg-5 py-4">

    <!-- HEADER TITLE & ACTIONS -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-primary-dark">
                <i class="bi bi-person-lines-fill text-primary me-2"></i>Data Siswa & Registrasi Kartu RFID
            </h3>
            <p class="text-muted mb-0 small">
                Kelola data identitas siswa serta tautan kode UID fisik kartu RFID untuk absensi otomatis.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
                <i class="bi bi-person-plus-fill"></i>
                <span>Tambah Siswa Baru</span>
            </button>
        </div>
    </div>

    <!-- NOTIFIKASI ALERT -->
    <?php if ($pesan === 'tambah_sukses'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <strong>Berhasil!</strong> Data siswa baru dan kartu RFID berhasil didaftarkan.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($pesan === 'edit_sukses'): ?>
        <div class="alert alert-info alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> Perubahan data siswa berhasil disimpan.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($pesan === 'hapus_sukses'): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-trash-fill me-2"></i> Data siswa berhasil dihapus dari sistem.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($pesan === 'nis_terdaftar'): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Gagal!</strong> Nomor Induk Siswa (NIS) sudah terdaftar pada siswa lain.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($pesan === 'uid_terdaftar'): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Gagal!</strong> UID Kartu RFID tersebut sudah digunakan oleh siswa lain.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FILTER & PENCARIAN -->
    <div class="card card-custom p-3 mb-4">
        <form method="GET" action="siswa.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama, NIS, atau kode UID kartu..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="kelas" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Kelas --</option>
                    <?php while ($kr = mysqli_fetch_assoc($q_kelas_list)): ?>
                        <option value="<?= htmlspecialchars($kr['kelas']) ?>" <?= ($filter_kelas === $kr['kelas']) ? 'selected' : '' ?>>
                            Kelas <?= htmlspecialchars($kr['kelas']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-3 w-100">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($filter_kelas)): ?>
                    <a href="siswa.php" class="btn btn-outline-secondary rounded-3" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- TABEL DATA SISWA -->
    <div class="card card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-dark">
                Daftar Siswa Terdaftar (<?= mysqli_num_rows($query) ?> Data)
            </h5>
            <div class="text-muted small">
                Klik tombol scan pada modal tambah untuk mengambil UID kartu otomatis
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-muted">
                        <th style="width: 50px;">No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>UID RFID</th>
                        <th>Status</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if (mysqli_num_rows($query) == 0):
                    ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-search me-1"></i> Tidak ada data siswa yang cocok dengan filter.
                            </td>
                        </tr>
                    <?php 
                    else:
                        while ($row = mysqli_fetch_assoc($query)): 
                    ?>
                        <tr>
                            <td class="text-muted"><?= $no++ ?></td>
                            <td class="font-mono fw-semibold text-primary"><?= htmlspecialchars($row['nis']) ?></td>
                            <td>
                                <strong class="text-dark"><?= htmlspecialchars($row['nama']) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 font-mono">Kelas <?= htmlspecialchars($row['kelas']) ?></span>
                            </td>
                            <td>
                                <span class="badge-uid font-mono">
                                    <i class="bi bi-broadcast me-1 text-primary"></i><?= htmlspecialchars($row['uid_rfid']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'aktif'): ?>
                                    <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1" onclick='editSiswa(<?= json_encode($row) ?>)' title="Edit Siswa">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <a href="proses_siswa.php?aksi=hapus&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1" onclick="return confirm('Apakah Anda yakin ingin menghapus data siswa <?= htmlspecialchars(addslashes($row['nama'])) ?>? Seluruh riwayat absensinya juga akan dihapus.')" title="Hapus Siswa">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- MODAL TAMBAH SISWA DENGAN SCAN KARTU OTOMATIS                            -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalTambahSiswa" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="proses_siswa.php" method="POST">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header bg-primary text-white rounded-top-4">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="modalTambahLabel">
                        <i class="bi bi-person-plus-fill"></i> Tambah Siswa & Hubungkan Kartu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nomor Induk Siswa (NIS) <span class="text-danger">*</span></label>
                        <input type="text" name="nis" class="form-control rounded-3 font-mono" placeholder="Contoh: 10293" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap Siswa <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control rounded-3" placeholder="Contoh: Muhammad Ali" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kelas <span class="text-danger">*</span></label>
                            <input type="text" name="kelas" class="form-control rounded-3 font-mono" placeholder="Contoh: 7A, 8B" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status Siswa</label>
                            <select name="status" class="form-select rounded-3">
                                <option value="aktif" selected>Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode UID RFID Kartu <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text font-mono bg-light"><i class="bi bi-broadcast"></i></span>
                            <input type="text" name="uid_rfid" id="tambahUidInput" class="form-control font-mono text-uppercase" placeholder="Contoh: A3 7B 91 22" required>
                            <button type="button" class="btn btn-outline-primary" id="btnAmbilKartu" onclick="ambilUidTerakhir('tambahUidInput')">
                                <i class="bi bi-magic me-1"></i> Ambil dari Alat
                            </button>
                        </div>
                        <div class="form-text text-muted">
                            💡 <em>Tempelkan kartu baru Anda ke modul sensor ESP32, lalu klik tombol <strong>"Ambil dari Alat"</strong> di atas.</em>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL EDIT SISWA                                                          -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalEditSiswa" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="proses_siswa.php" method="POST">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header bg-dark text-white rounded-top-4">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="modalEditLabel">
                        <i class="bi bi-pencil-square"></i> Edit Data Siswa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nomor Induk Siswa (NIS)</label>
                        <input type="text" name="nis" id="editNis" class="form-control rounded-3 font-mono" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap Siswa</label>
                        <input type="text" name="nama" id="editNama" class="form-control rounded-3" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kelas</label>
                            <input type="text" name="kelas" id="editKelas" class="form-control rounded-3 font-mono" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="editStatus" class="form-select rounded-3">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode UID RFID</label>
                        <div class="input-group">
                            <span class="input-group-text font-mono bg-light"><i class="bi bi-broadcast"></i></span>
                            <input type="text" name="uid_rfid" id="editUidInput" class="form-control font-mono text-uppercase" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="ambilUidTerakhir('editUidInput')">
                                <i class="bi bi-magic"></i> Ambil dari Alat
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark rounded-pill px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Fungsi Ambil UID Kartu Belum Terdaftar dari Alat ESP32
async function ambilUidTerakhir(targetInputId) {
    const input = document.getElementById(targetInputId);
    try {
        const res = await fetch('api_realtime.php?action=get_last_unregistered&t=' + Date.now());
        const data = await res.json();
        if (data.status === 'success' && data.uid) {
            input.value = data.uid;
            alert(`Kartu terdeteksi: ${data.uid}\n(Waktu tap: ${data.waktu})`);
        } else {
            alert(data.message || "Belum ada kartu baru yang di-tap ke alat RFID. Silakan tempelkan kartu baru Anda ke modul sensor ESP32 terlebih dahulu!");
        }
    } catch (e) {
        alert("Gagal membaca dari alat. Pastikan server web berjalan.");
        console.error(e);
    }
}

// Buka Modal Edit Siswa
function editSiswa(siswa) {
    document.getElementById('editId').value = siswa.id;
    document.getElementById('editNis').value = siswa.nis;
    document.getElementById('editNama').value = siswa.nama;
    document.getElementById('editKelas').value = siswa.kelas;
    document.getElementById('editUidInput').value = siswa.uid_rfid;
    document.getElementById('editStatus').value = siswa.status;

    const modal = new bootstrap.Modal(document.getElementById('modalEditSiswa'));
    modal.show();
}
</script>

</body>
</html>