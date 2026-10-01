<?php
// navbar.php - Komponen navigasi atas
$current_page = basename($_SERVER['PHP_SELF']);

// Ambil IP lokal server
$server_ip = gethostbyname(gethostname());
if ($server_ip === '127.0.0.1' && isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '::1') {
    $server_ip = $_SERVER['SERVER_ADDR'];
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="index.php">
      <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
        <i class="bi bi-broadcast fs-5 text-primary"></i>
      </div>
      <div>
        <span class="d-block lh-1 fs-5">SI-ABSENSI RFID</span>
        <small class="text-white-50 fw-normal" style="font-size: 0.75rem;">MTs Matholiul Huda Tlogowungu</small>
      </div>
    </a>
    
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3 gap-1">
        <li class="nav-item">
          <a class="nav-link px-3 <?= ($current_page == 'index.php') ? 'active fw-semibold bg-white bg-opacity-25 rounded-pill' : '' ?>" href="index.php">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link px-3 <?= ($current_page == 'siswa.php') ? 'active fw-semibold bg-white bg-opacity-25 rounded-pill' : '' ?>" href="siswa.php">
            <i class="bi bi-people me-1"></i> Data Siswa
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link px-3 <?= ($current_page == 'absensi.php') ? 'active fw-semibold bg-white bg-opacity-25 rounded-pill' : '' ?>" href="absensi.php">
            <i class="bi bi-calendar-check me-1"></i> Rekap Absensi
          </a>
        </li>
      </ul>
      
      <div class="d-flex align-items-center gap-2 text-white flex-wrap">
        <div class="d-none d-md-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-1 rounded-pill border border-white border-opacity-10">
          <i class="bi bi-clock text-warning"></i>
          <span id="liveClock" class="fw-semibold font-monospace">--:--:--</span>
        </div>
        <button class="btn btn-sm btn-outline-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#settingModal" title="Pengaturan Jam Masuk">
          <i class="bi bi-gear me-1"></i> Pengaturan
        </button>
      </div>
    </div>
  </div>
</nav>

<!-- Modal Pengaturan -->
<div class="modal fade" id="settingModal" tabindex="-1" aria-labelledby="settingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form action="simpan_pengaturan.php" method="POST">
        <div class="modal-header bg-primary text-white rounded-top-4">
          <h5 class="modal-title d-flex align-items-center gap-2" id="settingModalLabel">
            <i class="bi bi-sliders"></i> Pengaturan Sistem Absensi
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <?php
          $q_set = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1 LIMIT 1");
          $curr_setting = mysqli_fetch_assoc($q_set);
          $jam_def = $curr_setting ? $curr_setting['jam_masuk'] : '07:00:00';
          $jam_pulang_def = $curr_setting && !empty($curr_setting['jam_pulang']) ? $curr_setting['jam_pulang'] : '13:00:00';
          $sekolah_def = $curr_setting ? $curr_setting['nama_sekolah'] : 'MTs Matholiul Huda Tlogowungu';
          ?>
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($current_page) ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Lembaga / Sekolah</label>
            <input type="text" name="nama_sekolah" class="form-control rounded-3" value="<?= htmlspecialchars($sekolah_def) ?>" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold"><i class="bi bi-box-arrow-in-right text-success me-1"></i> Jam Masuk</label>
              <input type="time" step="1" name="jam_masuk" class="form-control rounded-3" value="<?= htmlspecialchars($jam_def) ?>" required>
              <div class="form-text text-muted small">
                &le; jam ini = <strong>Hadir</strong>, &gt; jam ini = <strong>Terlambat</strong>.
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold"><i class="bi bi-box-arrow-right text-primary me-1"></i> Jam Pulang</label>
              <input type="time" step="1" name="jam_pulang" class="form-control rounded-3" value="<?= htmlspecialchars($jam_pulang_def) ?>" required>
              <div class="form-text text-muted small">
                &ge; jam ini = <strong>Pulang Tepat Waktu</strong>.
              </div>
            </div>
          </div>
          <div class="alert alert-info py-2 px-3 small mb-0 rounded-3">
            <div class="fw-semibold mb-1"><i class="bi bi-broadcast-pin me-1"></i> URL API untuk ESP32:</div>
            <code class="user-select-all text-break d-block bg-white p-2 rounded border">http://<?= $server_ip ?>/absensi/api_absensi.php?uid=KODE_RFID</code>
          </div>
        </div>
        <div class="modal-footer bg-light rounded-bottom-4">
          <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Jam Digital Realtime di Navbar
function updateClock() {
  const clockEl = document.getElementById('liveClock');
  if (clockEl) {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    clockEl.textContent = `${h}:${m}:${s} WIB`;
  }
}
setInterval(updateClock, 1000);
updateClock();
</script>
