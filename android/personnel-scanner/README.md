# Lemon Android kart okuyucu

Native Android 8.0+ yardımcı uygulama. Web oturumundan bağımsız, yalnız kayıtlı
terminal anahtarıyla `/api/personnel/scans` adresine HTTPS POST gönderir.

## Kurulum ve derleme

JDK 17, Android SDK 35 ve Gradle 8.9 gerekir. Bu klasörde
`gradle assembleDebug lintDebug` çalıştırın. GitHub Actions **Personnel Android**
işi aynı derlemeyi yapar; APK `lemon-personnel-scanner-debug` artifact'ındadır.
Debug APK pilot donanım kabulü içindir. Kalıcı cihaz dağıtımı için işletmenin
koruduğu imzalama anahtarıyla release APK üretin; anahtarı depoya koymayın.

1. APK'yı güvenilir kanaldan Android cihaza kurun, NFC'yi açın.
2. Uygulamanın gösterdiği cihaz UUID'siyle Lemon → Personel → Terminaller
   sayfasında Android terminali oluşturun.
3. Bir kez gösterilen anahtarı uygulamaya girin. Varsayılan adres
   `https://lemon.derspros.com.tr`; kök HTTPS adresi kabul edilir.
4. Kartı önce personel kaydına atayın. USB okuyucu veya uygulamanın
   **Yalnız kimliği göster** modu kullanılabilir. Bu mod puantaj yazmaz.
5. Normal kullanımda Otomatik mod giriş/çıkışı sunucuda belirler. Sonuç
   gelmeden ikinci okutma gönderilmez. Ağ kesilirse aynı UUID ile tekrar
   deneyin; çevrimdışı kayıt kuyruğu yoktur.
6. Cihaz kaybolursa terminali web ekranından iptal edin. Cihazdaki anahtarı
   silmek sunucudaki terminali iptal etmez.

Anahtar Android Keystore AES-GCM ile şifreli saklanır, backup kapalıdır.
Ekran görüntüsü/son uygulamalar görüntüsü kapalıdır; token loglanmaz.
HTTPS sertifika ve host denetimi devrededir, yönlendirme takip edilmez.

## Donanım sınırı ve kabul

Android `Tag.getId()` ve `MifareClassic`, `NfcA`, `NdefFormatable`, `Ndef`
teknoloji sınıfları kullanılır. Kartın sektör/anahtar/NDEF içeriği okunmaz veya
yazılmaz. MIFARE Classic donanım desteği Android cihazına bağlıdır; sınıfın
bulunması her telefonun kartı okuyabileceği anlamına gelmez. Rastgele veya
kararsız UID kullanan kartlar kalıcı personel kartı olarak uygun değildir.

Web NFC NDEF odaklıdır; ham MIFARE/UID okuyucu yerine kullanılmaz. iOS için
bu Android APK çalışmaz; raw MIFARE Classic desteği iddia edilmez. PC/SC USB
okuyucular doğrudan tarayıcıdan çalışmaz; bu sürüm PC'de yalnız klavye gibi
UID yazan HID modu destekler. WebHID/WebUSB/WebSerial desteği iddia edilmez.

Aynı kartın Android ve USB çıktısını karşılaştırın: uppercase hex, baştaki
sıfırlar ve bayt sırası aynı olmalı. Decimal/ters bayt çıktısı otomatik tahmin
edilmez; okuyucu ayarı düzeltilir. Fiziksel kabulde giriş/çıkış, hızlı tekrar,
tanımsız kart, pasif çalışan, iptal edilmiş terminal, ağ kesintisi/tekrar ve
gece vardiyasını deneyin. Derleme ve birim testler fiziksel kabul yerine geçmez.

Kaynaklar: [Android NFC teknolojileri](https://developer.android.com/develop/connectivity/nfc/advanced-nfc),
[MifareClassic donanım desteği](https://developer.android.com/reference/android/nfc/tech/MifareClassic),
[Chrome Web NFC sınırları](https://developer.chrome.com/docs/capabilities/nfc).
