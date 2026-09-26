package com.lemon.personnel;

import android.app.Activity;
import android.nfc.NfcAdapter;
import android.nfc.Tag;
import android.nfc.tech.MifareClassic;
import android.nfc.tech.NfcA;
import android.nfc.tech.NdefFormatable;
import android.nfc.tech.Ndef;
import android.os.Bundle;
import android.text.InputType;
import android.view.View;
import android.view.WindowManager;
import android.widget.*;
import java.io.InputStream;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.UUID;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import javax.net.ssl.HttpsURLConnection;
import org.json.JSONObject;

/** Bağımsız NFC adaptörü: yalnız kimlik okur, hesap ve puantaj sunucuda kalır. */
public final class MainActivity extends Activity implements NfcAdapter.ReaderCallback {
    private final ExecutorService worker = Executors.newSingleThreadExecutor();
    private NfcAdapter nfc;
    private CredentialStore credentialStore;
    private TextView status;
    private EditText server, credential;
    private Spinner direction;
    private Button retry;
    private CheckBox identifyOnly;
    private String secret = "", endpoint = "", pendingBody, pendingEndpoint, pendingSecret;
    private boolean busy, destroyed;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_SECURE);
        credentialStore = new CredentialStore(this);
        nfc = NfcAdapter.getDefaultAdapter(this);
        ScrollView scroll = new ScrollView(this);
        scroll.setOnApplyWindowInsetsListener((view, insets) -> {
            view.setPadding(insets.getSystemWindowInsetLeft(), insets.getSystemWindowInsetTop(),
                insets.getSystemWindowInsetRight(), insets.getSystemWindowInsetBottom());
            return insets;
        });
        LinearLayout content = new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        int pad = (int) (20 * getResources().getDisplayMetrics().density);
        content.setPadding(pad, pad, pad, pad);
        scroll.addView(content);
        setContentView(scroll);
        label(content, "Lemon • Personel kart okutma", 24);
        label(content, "Cihaz kimliği (terminal kaydında kullanın):", 14);
        String deviceId = getPreferences(MODE_PRIVATE).getString("device_uuid", "");
        if (deviceId.isEmpty()) {
            deviceId = UUID.randomUUID().toString();
            getPreferences(MODE_PRIVATE).edit().putString("device_uuid", deviceId).apply();
        }
        TextView device = label(content, deviceId, 16);
        device.setTextIsSelectable(true);
        server = field(content, "HTTPS sunucu adresi", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        server.setText(getPreferences(MODE_PRIVATE).getString("server", "https://lemon.derspros.com.tr"));
        credential = field(content, "Terminal anahtarı", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD);
        credential.setSaveEnabled(false);
        credential.setImportantForAutofill(View.IMPORTANT_FOR_AUTOFILL_NO);
        Button save = button(content, "Terminali bağla");
        save.setOnClickListener(v -> configure());
        Button forget = button(content, "Bu cihazdaki anahtarı kaldır");
        forget.setOnClickListener(v -> {
            if (busy || pendingBody != null) { show("Önce bekleyen isteğin sonucunu doğrulayın."); return; }
            secret = ""; credentialStore.clear(); credential.setText("");
            show("Anahtar kaldırıldı. Sunucudaki terminali de iptal edebilirsiniz.");
        });
        label(content, "Okutma işlemi", 16);
        direction = new Spinner(this);
        direction.setMinimumHeight(48);
        direction.setAdapter(new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item,
            new String[]{"Otomatik giriş / çıkış", "Giriş", "Çıkış"}));
        content.addView(direction);
        identifyOnly = new CheckBox(this);
        identifyOnly.setText("Kart atama için yalnız kimliği göster (puantaj yazmaz)");
        content.addView(identifyOnly);
        status = label(content, "Kart bekleniyor.", 20);
        status.setAccessibilityLiveRegion(View.ACCESSIBILITY_LIVE_REGION_POLITE);
        retry = button(content, "Sonucu belirsiz aynı isteği yeniden dene");
        retry.setEnabled(false);
        retry.setOnClickListener(v -> sendPending());
        label(content, "Kartı telefonun NFC alanına yaklaştırın. Sektör, anahtar ve kart içeriği okunmaz. Ağ hatasında aynı isteği tekrar deneyin; çevrimdışı puantaj tutulmaz.", 14);
        try { secret = credentialStore.read(); endpoint = validateServer(server.getText().toString()); }
        catch (Exception e) { show("Terminal kurulumu gerekli. Yeni anahtar girin."); }
        try {
            String saved = credentialStore.readPending();
            if (!saved.isEmpty()) {
                JSONObject pending = new JSONObject(saved);
                pendingBody = pending.getString("body"); pendingEndpoint = pending.getString("endpoint");
                pendingSecret = pending.getString("credential");
                retry.setEnabled(true); show("Önceki okutmanın sonucu belirsiz. Aynı isteği yeniden doğrulayın.");
            }
        } catch (Exception e) {
            busy = true; show("Bekleyen kayıt kurtarılamadı. Yeni okutma yapmadan yöneticiden son puantajı doğrulamasını isteyin.");
        }
    }

    private TextView label(LinearLayout parent, String text, int size) {
        TextView view = new TextView(this); view.setText(text); view.setTextSize(size);
        view.setPadding(0, 12, 0, 12); parent.addView(view); return view;
    }
    private EditText field(LinearLayout parent, String name, int type) {
        TextView label = label(parent, name, 16);
        EditText input = new EditText(this); input.setId(View.generateViewId()); label.setLabelFor(input.getId());
        input.setSingleLine(true); input.setInputType(type); parent.addView(input); return input;
    }
    private Button button(LinearLayout parent, String name) {
        Button button = new Button(this); button.setText(name); parent.addView(button); return button;
    }
    private void show(String message) { if (status != null) status.setText(message); }

    private String validateServer(String input) throws Exception {
        URL url = new URL(input.trim());
        if (!url.getProtocol().equals("https") || url.getHost().isEmpty() || url.getUserInfo() != null
            || url.getQuery() != null || url.getRef() != null || !(url.getPath().isEmpty() || url.getPath().equals("/"))) {
            throw new IllegalArgumentException("Yalnız HTTPS sunucu kök adresi kabul edilir.");
        }
        return url.toString().replaceAll("/+$", "") + "/api/personnel/scans";
    }
    private void configure() {
        if (busy || pendingBody != null) { show("Önce bekleyen isteği sonuçlandırın."); return; }
        try {
            String next = validateServer(server.getText().toString());
            String token = credential.getText().toString().trim();
            if (token.isEmpty() && !next.equals(endpoint)) throw new IllegalArgumentException();
            if (!token.isEmpty()) {
                if (!token.matches("[0-9]+\\.[A-Za-z0-9_-]{32,}")) throw new IllegalArgumentException();
                credentialStore.save(token); secret = token;
            }
            if (secret.isEmpty()) throw new IllegalArgumentException();
            endpoint = next;
            getPreferences(MODE_PRIVATE).edit().putString("server", server.getText().toString().trim()).apply();
            credential.setText(""); show("Terminal hazır. Kartınızı okutun.");
        } catch (Exception e) { show("HTTPS adresini ve terminal anahtarını kontrol edin."); }
    }

    @Override protected void onResume() {
        super.onResume();
        if (nfc == null) { show("Bu cihazda NFC donanımı yok."); return; }
        if (!nfc.isEnabled()) { show("Telefon ayarlarından NFC'yi açın."); return; }
        // SKIP_NDEF_CHECK kullanılmaz: Ndef/NdefFormatable teknoloji tanısı korunur.
        nfc.enableReaderMode(this, this, NfcAdapter.FLAG_READER_NFC_A | NfcAdapter.FLAG_READER_NFC_B
            | NfcAdapter.FLAG_READER_NFC_F | NfcAdapter.FLAG_READER_NFC_V, null);
    }
    @Override protected void onPause() { if (nfc != null) nfc.disableReaderMode(this); super.onPause(); }
    @Override protected void onDestroy() { destroyed = true; worker.shutdown(); super.onDestroy(); }

    @Override public void onTagDiscovered(Tag tag) {
        try {
            String uid = CardIdentifier.fromBytes(tag.getId());
            String technology = MifareClassic.get(tag) != null ? "MifareClassic"
                : NdefFormatable.get(tag) != null ? "NdefFormatable"
                : NfcA.get(tag) != null ? "NfcA" : Ndef.get(tag) != null ? "Ndef" : null;
            if (technology == null) { runOnUiThread(() -> show("Bu kart teknolojisi desteklenmiyor.")); return; }
            runOnUiThread(() -> scan(uid, technology));
        } catch (Exception e) { runOnUiThread(() -> show("Kart kimliği okunamadı. Kartı tekrar yaklaştırın.")); }
    }
    private void scan(String uid, String technology) {
        if (busy || pendingBody != null) { show("Önceki okutmanın sonucu bekleniyor."); return; }
        if (identifyOnly.isChecked()) { show("Kart kimliği: " + uid + "\nTeknoloji: " + technology + "\nPersonel kart atama formuna bu kimliği girin."); return; }
        if (secret.isEmpty() || endpoint.isEmpty()) { show("Önce terminali bağlayın."); return; }
        try {
            JSONObject body = new JSONObject();
            body.put("request_id", UUID.randomUUID().toString()); body.put("card_uid", uid);
            body.put("technology", technology);
            body.put("action", new String[]{"auto", "in", "out"}[direction.getSelectedItemPosition()]);
            JSONObject saved = new JSONObject();
            saved.put("body", body.toString()); saved.put("endpoint", endpoint); saved.put("credential", secret);
            credentialStore.savePending(saved.toString());
            pendingBody = body.toString(); pendingEndpoint = endpoint; pendingSecret = secret;
            sendPending();
        } catch (Exception e) { show("Okutma hazırlanamadı."); }
    }
    private void sendPending() {
        if (busy || pendingBody == null) return;
        busy = true; retry.setEnabled(false); show("Okutma doğrulanıyor…");
        final String body = pendingBody, target = pendingEndpoint, token = pendingSecret;
        worker.execute(() -> {
            HttpsURLConnection connection = null;
            String message; boolean resolved = false;
            try {
                connection = (HttpsURLConnection) new URL(target).openConnection();
                connection.setInstanceFollowRedirects(false);
                connection.setConnectTimeout(10000); connection.setReadTimeout(15000);
                connection.setRequestMethod("POST"); connection.setDoOutput(true);
                connection.setRequestProperty("Authorization", "Bearer " + token);
                connection.setRequestProperty("Accept", "application/json");
                connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
                byte[] bytes = body.getBytes(StandardCharsets.UTF_8);
                connection.setFixedLengthStreamingMode(bytes.length);
                try (java.io.OutputStream out = connection.getOutputStream()) { out.write(bytes); }
                int code = connection.getResponseCode();
                if (code == 200) {
                    try (InputStream in = connection.getInputStream()) {
                        java.io.ByteArrayOutputStream responseBuffer = new java.io.ByteArrayOutputStream();
                        byte[] chunk = new byte[2048];
                        int count;
                        while ((count = in.read(chunk)) != -1) {
                            if (responseBuffer.size() + count > 16384) throw new IllegalStateException();
                            responseBuffer.write(chunk, 0, count);
                        }
                        byte[] response = responseBuffer.toByteArray();
                        JSONObject result = new JSONObject(new String(response, StandardCharsets.UTF_8));
                        if (!result.optString("request_id").equals(new JSONObject(body).getString("request_id"))) throw new IllegalStateException();
                        String outcome = result.optString("result");
                        if (!java.util.Arrays.asList("accepted", "duplicate", "unknown_card", "inactive_employee", "inactive_card", "invalid_transition").contains(outcome)) throw new IllegalStateException();
                        String name = result.isNull("employee_name") ? "" : result.optString("employee_name");
                        String time = java.time.Instant.parse(result.getString("occurred_at"))
                            .atZone(java.time.ZoneId.of("Europe/Istanbul"))
                            .format(java.time.format.DateTimeFormatter.ofPattern("dd.MM.yyyy HH:mm:ss"));
                        String shift = result.isNull("shift_label") ? "" : result.optString("shift_label");
                        message = result.getString("message") + "\n" + name + "\n" + time + (shift.isEmpty() ? "" : "\n" + shift);
                        resolved = true;
                    }
                } else if (code == 401 || code == 403) { message = "Yetkisiz veya iptal edilmiş terminal. Yönetici yeni anahtar oluşturmalı."; resolved = true; }
                else if (code == 422 || code == 409) { message = "Okutma reddedildi (" + code + "). Kartı ve terminal ayarlarını kontrol edin."; resolved = true; }
                else if (code == 429) { message = "Çok sık okutma. Bir süre sonra aynı isteği yeniden deneyin."; }
                else { message = "Sunucu yanıtı doğrulanamadı. Aynı isteği yeniden deneyin."; }
            } catch (Exception e) { message = "Ağ bağlantısı kesildi veya yanıt doğrulanamadı. Aynı isteği yeniden deneyin; yeni okutma yapmayın."; }
            finally { if (connection != null) connection.disconnect(); }
            final String resultMessage = message; final boolean complete = resolved;
            runOnUiThread(() -> {
                if (destroyed) return;
                busy = false;
                if (complete) {
                    try { credentialStore.clearPending(); }
                    catch (Exception e) { retry.setEnabled(true); show("Sonuç alındı, yerel kayıt temizlenemedi. Aynı isteği doğrulayın."); return; }
                    pendingBody = null; pendingSecret = null; pendingEndpoint = null;
                }
                retry.setEnabled(!complete); show(resultMessage);
            });
        });
    }
}
