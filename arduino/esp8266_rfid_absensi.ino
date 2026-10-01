/*
  ===================================================================
  SISTEM ABSENSI SISWA RFID DENGAN NODEMCU ESP8266 & RC522
  Tujuan: Membaca UID kartu RFID dan mengirimkan ke Web Dashboard XAMPP
  Endpoint: http://192.168.1.239/absensi/kirim_kartu.php?uid=KODE_RFID
  ===================================================================

  SKEMA PIN NODEMCU ESP8266 KE RFID RC522:
  -----------------------------------------
  RFID RC522       NodeMCU ESP8266
  SDA (SS)    -->  D8 (GPIO 15)
  SCK         -->  D5 (GPIO 14)
  MOSI        -->  D7 (GPIO 13)
  MISO        -->  D6 (GPIO 12)
  IRQ         -->  (Tidak dihubungkan)
  GND         -->  GND
  RST         -->  D3 (GPIO 0)
  3.3V        -->  3.3V  (PERHATIAN: JANGAN sambungkan ke 5V!)

  BUZZER & LED (Opsional):
  Buzzer (+)  -->  D1 (GPIO 5)
  Buzzer (-)  -->  GND
  LED Hijau   -->  D2 (GPIO 4)
  LED Merah   -->  D4 (GPIO 2)
*/

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <SPI.h>
#include <MFRC522.h>

// ===== 1. PENGATURAN WIFI =====
const char* ssid = "NAMA_WIFI_ANDA";         // Ganti dengan SSID WiFi Anda
const char* password = "PASSWORD_WIFI_ANDA"; // Ganti dengan Password WiFi Anda

// ===== 2. PENGATURAN SERVER WEB =====
// IP Komputer / Laptop Anda yang menjalankan XAMPP
const char* serverHost = "http://192.168.1.239/absensi/kirim_kartu.php";

// ===== 3. DEFINISI PIN =====
#define SS_PIN  D8  // SDA
#define RST_PIN D3  // RST
#define BUZZER_PIN D1 // Buzzer (Opsional)
#define LED_HIJAU  D2 // LED Sukses (Opsional)

MFRC522 mfrc522(SS_PIN, RST_PIN);

void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(LED_HIJAU, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(LED_HIJAU, LOW);

  // Inisialisasi SPI dan RFID RC522
  SPI.begin();
  mfrc522.PCD_Init();

  Serial.println();
  Serial.println("=========================================");
  Serial.println("   SISTEM ABSENSI SISWA RFID (ESP8266)  ");
  Serial.println("=========================================");

  // Menghubungkan ke WiFi
  Serial.print("Menghubungkan ke WiFi: ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);

  int wifi_timeout = 0;
  while (WiFi.status() != WL_CONNECTED && wifi_timeout < 40) {
    delay(500);
    Serial.print(".");
    wifi_timeout++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] Terhubung!");
    Serial.print("[WiFi] IP NodeMCU: ");
    Serial.println(WiFi.localIP());

    // Beep 2x pendek tanda alat siap
    toneBuzzer(100, 1);
    delay(100);
    toneBuzzer(100, 1);
  } else {
    Serial.println("\n[WiFi] Gagal terhubung! Periksa SSID & Password.");
  }

  Serial.println("[RFID] Silakan tap kartu siswa pada modul RC522...");
}

void loop() {
  // Cek apakah ada kartu baru yang didekatkan
  if (!mfrc522.PICC_IsNewCardPresent()) {
    return;
  }

  // Baca serial kartu
  if (!mfrc522.PICC_ReadCardSerial()) {
    return;
  }

  // Baca UID kartu dan susun ke format HEX string (tanpa spasi)
  String uidString = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) {
      uidString += "0";
    }
    uidString += String(mfrc522.uid.uidByte[i], HEX);
  }
  uidString.toUpperCase();

  Serial.println("\n-----------------------------------------");
  Serial.print("[RFID] Kartu terdeteksi! UID: ");
  Serial.println(uidString);

  // Beep pendek 1x saat kartu disentuh
  toneBuzzer(80, 1);

  // Kirim data ke server web
  kirimKeServer(uidString);

  // Hentikan enkripsi kartu dan pembacaan berulang
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();

  // Delay agar satu tap tidak terbaca berkali-kali
  delay(1500);
}

void kirimKeServer(String uid) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[ERROR] WiFi terputus! Mencoba menyambung kembali...");
    WiFi.reconnect();
    return;
  }

  WiFiClient client;
  HTTPClient http;

  // Bangun URL lengkap: http://192.168.1.239/absensi/kirim_kartu.php?uid=A37B9122&format=text
  String url = String(serverHost) + "?uid=" + uid + "&format=text";

  Serial.print("[HTTP] Mengirim request: ");
  Serial.println(url);

  http.begin(client, url);
  int httpCode = http.GET();

  if (httpCode > 0) {
    String payload = http.getString();
    Serial.println("[HTTP] Respon dari Server:");
    Serial.println(payload);

    // Feedback berdasarkan respon
    if (payload.indexOf("Hadir") >= 0 || payload.indexOf("Terlambat") >= 0) {
      // Absensi Berhasil
      digitalWrite(LED_HIJAU, HIGH);
      toneBuzzer(250, 1); // 1x beep panjang
      digitalWrite(LED_HIJAU, LOW);
    } else if (payload.indexOf("SUDAH ABSEN") >= 0) {
      // Sudah absen sebelumnya
      toneBuzzer(100, 2); // 2x beep sedang
    } else {
      // Kartu tidak dikenal atau error
      toneBuzzer(80, 3);  // 3x beep cepat
    }
  } else {
    Serial.print("[HTTP] Gagal terhubung ke server. Kode Error: ");
    Serial.println(http.errorToString(httpCode).c_str());
    toneBuzzer(500, 1); // Beep panjang tanda error koneksi
  }

  http.end();
}

void toneBuzzer(int durationMs, int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(durationMs);
    digitalWrite(BUZZER_PIN, LOW);
    if (times > 1 && i < times - 1) {
      delay(80);
    }
  }
}
