/*
  =============================================================================
  SISTEM ABSENSI DIGITAL RFID BERBASIS ESP32 + RC522 + LCD I2C (16x2 / 20x4)
  -----------------------------------------------------------------------------
  WiFi SSID     : IMS_SAKATECH
  WiFi Password : 14492563
  IP Server     : 192.168.110.240
  API Endpoint  : http://192.168.110.240/absensi/api_absensi.php?uid=KODE_RFID
  =============================================================================

  SKEMA PIN KABEL ESP32 (30/38 Pin):
  -----------------------------------------------------------------------------
  1. Modul RFID RC522:
     - SDA (SS)  --> GPIO 5
     - SCK       --> GPIO 18
     - MOSI      --> GPIO 23
     - MISO      --> GPIO 19
     - IRQ       --> (Tidak Terhubung / Kosong)
     - GND       --> GND
     - RST       --> GPIO 4
     - 3.3V      --> Pin 3V3 ESP32 (PERHATIAN: JANGAN sambung ke VIN / 5V!)

  2. Modul LCD I2C (16x2 / 20x4 PCF8574):
     - GND       --> GND
     - VCC       --> VIN / 5V ESP32
     - SDA       --> GPIO 21 (I2C SDA ESP32)
     - SCL       --> GPIO 22 (I2C SCL ESP32)

  3. Indikator Tambahan (Opsional):
     - Buzzer (+)--> GPIO 2
     - Buzzer (-)--> GND
     - LED Hijau --> GPIO 15
  =============================================================================
*/

#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// =============================================================================
// 1. PENGATURAN WIFI & SERVER API
// =============================================================================
const char* ssid      = "IMS_SAKATECH";
const char* password  = "14492563";

// IP Komputer/Laptop XAMPP
const char* serverUrl = "http://192.168.110.240/absensi/api_absensi.php";

// =============================================================================
// 2. DEFINISI PIN HARDWARE
// =============================================================================
#define SS_PIN      5
#define RST_PIN     4
#define I2C_SDA_PIN 21
#define I2C_SCL_PIN 22
#define BUZZER_PIN  2   // Opsional
#define LED_PIN     15  // Opsional

// =============================================================================
// 3. OBJEK RFID & LCD
// =============================================================================
MFRC522 rfid(SS_PIN, RST_PIN);
LiquidCrystal_I2C lcd(0x27, 16, 2); // Alamat I2C umum: 0x27 atau 0x3F

void beep(int durasiMs, int jumlah = 1) {
  for (int i = 0; i < jumlah; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(durasiMs);
    digitalWrite(BUZZER_PIN, LOW);
    if (i < jumlah - 1) delay(70);
  }
}

void tampilkanStandbyLcd() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("ABSENSI DIGITAL ");
  lcd.setCursor(0, 1);
  lcd.print("TEMPEL KARTU...");
}

// Parser JSON ringan tanpa butuh library eksternal tambahan
String getJsonValue(String json, String key) {
  String searchKey = "\"" + key + "\":\"";
  int startIdx = json.indexOf(searchKey);
  if (startIdx == -1) {
    searchKey = "\"" + key + "\":";
    startIdx = json.indexOf(searchKey);
    if (startIdx == -1) return "";
    startIdx += searchKey.length();
    int endIdx = json.indexOf(",", startIdx);
    if (endIdx == -1) endIdx = json.indexOf("}", startIdx);
    return json.substring(startIdx, endIdx);
  }
  startIdx += searchKey.length();
  int endIdx = json.indexOf("\"", startIdx);
  if (endIdx == -1) return "";
  return json.substring(startIdx, endIdx);
}

void hubungkanWiFi() {
  Serial.println();
  Serial.print("[WiFi] Menghubungkan ke: ");
  Serial.println(ssid);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Konek WiFi...");
  lcd.setCursor(0, 1);
  lcd.print(ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  int attempt = 0;
  while (WiFi.status() != WL_CONNECTED && attempt < 40) {
    delay(500);
    Serial.print(".");
    attempt++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.println("========================================");
    Serial.println("[WiFi] SUKSES TERHUBUNG!");
    Serial.print("[WiFi] IP ESP32 : ");
    Serial.println(WiFi.localIP());
    Serial.print("[WiFi] Gateway  : ");
    Serial.println(WiFi.gatewayIP());
    Serial.println("[WiFi] IP Server: 192.168.110.240");
    Serial.println("========================================");

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WIFI CONNECTED!");
    lcd.setCursor(0, 1);
    lcd.print(WiFi.localIP().toString());
    
    beep(100, 2);
    delay(1500);
  } else {
    Serial.println();
    Serial.println("[WiFi] GAGAL TERHUBUNG! Periksa SSID & Password.");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WIFI GAGAL!");
    lcd.setCursor(0, 1);
    lcd.print("Cek Sandi/SSID");
    beep(500, 1);
    delay(2000);
  }

  tampilkanStandbyLcd();
}

void kirimDataKeApi(String uidString) {
  // 1. Cek Koneksi WiFi
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WiFi] Terputus! Reconnecting...");
    hubungkanWiFi();
    if (WiFi.status() != WL_CONNECTED) {
      lcd.setCursor(0, 1);
      lcd.print("WIFI DISCONNECT!");
      beep(500, 1);
      delay(2000);
      tampilkanStandbyLcd();
      return;
    }
  }

  // 2. Siapkan WiFiClient & HTTPClient eksplisit
  WiFiClient client;
  HTTPClient http;
  String urlPenuh = String(serverUrl) + "?uid=" + uidString;

  Serial.println();
  Serial.print("[HTTP] Request URL: ");
  Serial.println(urlPenuh);

  // Inisialisasi HTTP dengan WiFiClient (Kompatibel dengan semua core ESP32)
  if (!http.begin(client, urlPenuh)) {
    Serial.println("[HTTP] Inisialisasi HTTPClient GAGAL!");
    lcd.setCursor(0, 1);
    lcd.print("HTTP INIT GAGAL!");
    beep(500, 1);
    delay(2000);
    tampilkanStandbyLcd();
    return;
  }

  http.setTimeout(7000); // Timeout 7 detik
  http.setReuse(false);
  http.addHeader("User-Agent", "ESP32-RFID-Client");
  http.addHeader("Connection", "close");

  int httpCode = http.GET();

  Serial.print("[HTTP] Kode Status: ");
  Serial.println(httpCode);

  if (httpCode > 0) {
    String respon = http.getString();
    Serial.println("[HTTP] Respon Body: ");
    Serial.println(respon);

    String statusScan = getJsonValue(respon, "status");
    String nama       = getJsonValue(respon, "nama");
    String keterangan = getJsonValue(respon, "keterangan");
    String jam        = getJsonValue(respon, "jam");

    // A. KASUS ABSENSI SUKSES (HADIR ATAU TERLAMBAT)
    if (statusScan == "success") {
      digitalWrite(LED_PIN, HIGH);
      beep(150, 1);

      lcd.setCursor(0, 1);
      lcd.print("                ");
      lcd.setCursor(0, 1);
      String barisNama = nama;
      if (barisNama.length() > 16) barisNama = barisNama.substring(0, 16);
      lcd.print(barisNama);

      delay(1200);

      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print(barisNama);
      lcd.setCursor(0, 1);
      lcd.print(keterangan + " " + jam.substring(0, 5));

      digitalWrite(LED_PIN, LOW);
      delay(2200);
    }
    // B. KASUS SUDAH ABSEN HARI INI
    else if (statusScan == "already") {
      beep(90, 2);
      lcd.setCursor(0, 1);
      lcd.print("                ");
      lcd.setCursor(0, 1);
      lcd.print("SUDAH ABSEN!    ");
      delay(1200);

      lcd.clear();
      lcd.setCursor(0, 0);
      String barisNama = nama;
      if (barisNama.length() > 16) barisNama = barisNama.substring(0, 16);
      lcd.print(barisNama);
      lcd.setCursor(0, 1);
      lcd.print("SUDAH ABSEN " + jam.substring(0, 5));
      delay(2000);
    }
    // C. KASUS KARTU BELUM TERDAFTAR
    else if (statusScan == "unregistered") {
      beep(70, 3);
      lcd.setCursor(0, 1);
      lcd.print("                ");
      lcd.setCursor(0, 1);
      lcd.print("TDK TERDAFTAR!  ");
      delay(2500);
    }
    // D. KASUS SISWA NONAKTIF
    else if (statusScan == "inactive") {
      beep(200, 2);
      lcd.setCursor(0, 1);
      lcd.print("STATUS NONAKTIF!");
      delay(2000);
    }
    else {
      lcd.setCursor(0, 1);
      lcd.print("RESPON DITERIMA ");
      delay(1500);
    }
  } else {
    // JIKA HTTP CODE <= 0 (GAGAL TERHUBUNG KE SERVER)
    String errStr = http.errorToString(httpCode);
    Serial.println();
    Serial.println("=================================================");
    Serial.print("[HTTP GAGAL] Kode Error: ");
    Serial.println(httpCode);
    Serial.print("[HTTP GAGAL] Penjelasan : ");
    Serial.println(errStr);
    Serial.println(">>> PENYEBAB UTAMA <<<");
    Serial.println("1. Windows Firewall di PC (192.168.110.240) memblokir port 80 Apache.");
    Serial.println("2. ESP32 dan PC tidak satu jaringan WiFi atau ada AP Isolation di router.");
    Serial.println("=================================================");

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("ERR: " + String(httpCode) + " (" + errStr.substring(0, 7) + ")");
    lcd.setCursor(0, 1);
    lcd.print("CEK FIREWALL PC!");

    beep(400, 2);
    delay(3000);
  }

  http.end();
  tampilkanStandbyLcd();
}

void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(LED_PIN, LOW);

  // Inisialisasi I2C & Layar LCD (SDA=21, SCL=22)
  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("INISIALISASI...");

  // Inisialisasi SPI ESP32: SCK=18, MISO=19, MOSI=23, SS=5
  SPI.begin(18, 19, 23, 5);

  // Inisialisasi RC522
  rfid.PCD_Init();
  delay(300);

  // Sambungkan ke WiFi IMS_SAKATECH
  hubungkanWiFi();
}

void loop() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    return;
  }

  // Format kode UID menjadi string HEX
  String uidStr = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) uidStr += "0";
    uidStr += String(rfid.uid.uidByte[i], HEX);
  }
  uidStr.toUpperCase();

  Serial.println();
  Serial.println("----------------------------------------");
  Serial.println("[RFID] KARTU TERDETEKSI: " + uidStr);

  // Tampilkan UID di Layar LCD
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("UID: " + uidStr);
  lcd.setCursor(0, 1);
  lcd.print("Kirim ke Server.");

  // Kirim data ke API Web Server (192.168.110.240)
  kirimDataKeApi(uidStr);

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  delay(1500);
}
