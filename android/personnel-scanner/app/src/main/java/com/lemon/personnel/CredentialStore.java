package com.lemon.personnel;

import android.content.Context;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.util.Base64;
import java.security.KeyStore;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;
import java.nio.charset.StandardCharsets;

/** Terminal sırrı Android Keystore anahtarı ile uygulamaya özel alanda şifrelenir. */
final class CredentialStore {
    private static final String ALIAS = "lemon_personnel_terminal";
    private final Context context;
    CredentialStore(Context context) { this.context = context; }

    private SecretKey key() throws Exception {
        KeyStore store = KeyStore.getInstance("AndroidKeyStore");
        store.load(null);
        if (!store.containsAlias(ALIAS)) {
            KeyGenerator generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
            generator.init(new KeyGenParameterSpec.Builder(ALIAS,
                KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).build());
            generator.generateKey();
        }
        return (SecretKey) store.getKey(ALIAS, null);
    }

    void save(String secret) throws Exception { write("value", secret); }
    void savePending(String value) throws Exception { write("pending", value); }
    String readPending() throws Exception { return readValue("pending"); }
    void clearPending() throws Exception {
        if (!context.getSharedPreferences("credential", Context.MODE_PRIVATE).edit().remove("pending").commit()) {
            throw new IllegalStateException("Bekleyen istek temizlenemedi.");
        }
    }
    private void write(String name, String secret) throws Exception {
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, key());
        String value = Base64.encodeToString(cipher.getIV(), Base64.NO_WRAP) + ":"
            + Base64.encodeToString(cipher.doFinal(secret.getBytes(StandardCharsets.UTF_8)), Base64.NO_WRAP);
        if (!context.getSharedPreferences("credential", Context.MODE_PRIVATE).edit().putString(name, value).commit()) {
            throw new IllegalStateException("Terminal anahtarı saklanamadı.");
        }
    }

    String read() throws Exception { return readValue("value"); }
    private String readValue(String name) throws Exception {
        String value = context.getSharedPreferences("credential", Context.MODE_PRIVATE).getString(name, null);
        if (value == null) return "";
        String[] parts = value.split(":", 2);
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.DECRYPT_MODE, key(), new GCMParameterSpec(128, Base64.decode(parts[0], Base64.NO_WRAP)));
        return new String(cipher.doFinal(Base64.decode(parts[1], Base64.NO_WRAP)), StandardCharsets.UTF_8);
    }

    void clear() { context.getSharedPreferences("credential", Context.MODE_PRIVATE).edit().clear().apply(); }
}
