# PANDUAN LENGKAP SISTEM ABSENSI SISWA DIGITAL RFID & WEB DASHBOARD

Panduan ini menjelaskan cara merakit hardware ESP32, menghubungkan ke WiFi **IMS_SAKATECH**, mengunggah kode mikrokontroler, serta menghubungkannya ke Web Dashboard dan API XAMPP.

---

## 1. Komponen yang Digunakan

1. **Mikrokontroler**: ESP32 DEVKIT V1 (30 / 38 Pin)
2. **Sensor RFID**: Modul RC522 (13.56 MHz) + Kartu / Gantungan Kunci RFID
3. **Kabel Jumper**: Female-to-Female
4. **Buzzer 5V / LED 5mm** (Opsional, indikator bunyi tap)
5. **Kabel Micro USB** untuk upload program dan daya alat

---

## 2. Skema Rangkaian Pin ESP32 ke Modul RFID RC522

Sesuai dengan konfigurasi pin program:

| Pin Modul RC522 | Pin ESP32 (GPIO) | Keterangan |
|-----------------|------------------|------------|
| **SDA (SS)**    | **GPIO 5**       | SPI Slave Select (SS) |
| **SCK**         | **GPIO 18**      | SPI Clock |
| **MOSI**        | **GPIO 23**      | Master Out Slave In |
| **MISO**        | **GPIO 19**      | Master In Slave Out |
| **IRQ**         | *(Biarkan Kosong)* | Tidak Digunakan |
| **GND**         | **GND**          | Ground |
| **RST**         | **GPIO 4**       | Reset RC522 |
| **3.3V**        | **3V3**          | **PENTING: JANGAN ke VIN / 5V!** |

### Skema Pin Layar LCD I2C (16x2 / 20x4 PCF8574):

| Pin Modul LCD I2C | Pin ESP32 (GPIO) | Keterangan |
|-------------------|------------------|------------|
| **GND**           | **GND**          | Ground |
| **VCC**           | **VIN (5V)**     | Daya 5V untuk LCD |
| **SDA**           | **GPIO 21**      | I2C Data ESP32 |
| **SCL**           | **GPIO 22**      | I2C Clock ESP32 |

**Indikator Tambahan (Opsional):**
- Buzzer (+) $\rightarrow$ Pin **GPIO 2**
- Buzzer (-) $\rightarrow$ Pin **GND**
- LED Hijau (+) $\rightarrow$ Pin **GPIO 15** dengan resistor 220Ω
- LED (-) $\rightarrow$ Pin **GND**

---

## 3. Instalasi Library di Arduino IDE

1. Buka software **Arduino IDE**.
2. Masuk ke menu: **Sketch $\rightarrow$ Include Library $\rightarrow$ Manage Libraries...**
3. Cari dan pasang library:
   - **MFRC522** (oleh *GithubCommunity* atau *Miguel Balboa*)
   - **LiquidCrystal I2C** (oleh *Frank de Brabander* atau *Marco Schwartz*)
4. Pastikan Board ESP32 sudah terpasang di Boards Manager:
   - Pilih board: **DOIT ESP32 DEVKIT V1** atau **ESP32 Dev Module**
   - Port: Sesuaikan port COM ESP32 Anda.

---

## 4. Konfigurasi Program Arduino (ESP32)

Buka file sketsa yang telah dipersiapkan:
- Lokasi file: `c:\xampp\htdocs\absensi\arduino\esp32_rfid_absensi.ino`

Dalam program tersebut, WiFi dan IP server sudah dikonfigurasi:
```cpp
const char* ssid      = "IMS_SAKATECH";
const char* password  = "14492563";
const char* serverUrl = "http://192.168.110.240/absensi/api_absensi.php";
```

> **Tips Melihat IP Komputer Anda:**
> Buka browser di laptop Anda: `http://localhost/absensi/`.
> Di halaman Dashboard utama, pada kotak monitor live scanner sudah tertera URL API lengkap dengan IP komputer Anda yang dapat langsung Anda salin ke Arduino IDE.

---

## 5. API Endpoint Web Server

Sistem menyediakan 2 API endpoint yang dapat menerima data tap kartu dari mikrokontroler:

- **Endpoint Utama**: `http://<IP_KOMPUTER>/absensi/api_absensi.php?uid=KODE_RFID`
- **Metode**: HTTP GET atau POST
- **Parameter**:
  - `uid` : Kode heksadesimal kartu RFID (misal: `A37B9122` atau `A3 7B 91 22`)
  - `format` (opsional): `json` (default) atau `text`

### Contoh Format Respon API (JSON):

**1. Jika Absensi Berhasil (Tepat Waktu / Terlambat):**
```json
{
  "status": "success",
  "message": "Absensi Berhasil!",
  "nama": "Ahmad Rizky Pratama",
  "nis": "1001",
  "kelas": "7A",
  "jam": "06:48:20",
  "keterangan": "Hadir",
  "sound": "success"
}
```

**2. Jika Sudah Absen Sebelumnya Hari Ini:**
```json
{
  "status": "already",
  "message": "Sudah Absen Hari Ini!",
  "nama": "Ahmad Rizky Pratama",
  "nis": "1001",
  "kelas": "7A",
  "jam": "06:48:20",
  "keterangan": "Hadir",
  "sound": "warning"
}
```

**3. Jika Kartu Belum Terdaftar:**
```json
{
  "status": "unregistered",
  "message": "Kartu Belum Terdaftar!",
  "uid": "A3 7B 91 22",
  "jam": "07:12:05",
  "sound": "error"
}
```

---

## 6. Fitur-Fitur Dashboard Absensi Digital

1. **Live Scanner RFID Real-time**:
   - Memantau tap kartu secara langsung tanpa reload halaman.
   - Efek visual responsif (Hijau untuk sukses, Kuning untuk sudah absen, Merah untuk kartu tidak terdaftar).
   - Dilengkapi synthesizer audio bel & ucapan suara (Text-to-Speech bahasa Indonesia).
2. **Simulasi Tap Kartu Tanpa Hardware**:
   - Tombol **"Simulasi Tap Kartu"** di pojok kanan atas memungkinkan Anda menguji coba sistem langsung dari browser.
3. **Statistik & Grafik Interaktif**:
   - Total Siswa Aktif, Hadir Tepat Waktu, Terlambat, dan Belum Hadir.
   - Grafik Donat persentase kehadiran hari ini.
   - Grafik Batang kehadiran per kelas.
4. **Mode Kiosk / Monitor Gerbang**:
   - Tampilan layar penuh interaktif dengan jam digital besar untuk dipasang di layar TV / monitor gerbang masuk sekolah.
5. **Pendaftaran Kartu RFID Otomatis**:
   - Di menu **Data Siswa**, saat mendaftarkan siswa baru, cukup tap kartu ke alat dan klik tombol **"Ambil dari Alat"** untuk mengisi UID secara otomatis.
