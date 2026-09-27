package com.lemon.personnel;

/** UID bayt sırası değiştirilmez, baştaki sıfırlar korunur. Sektör okunmaz. */
public final class CardIdentifier {
    private static final char[] HEX = "0123456789ABCDEF".toCharArray();
    private CardIdentifier() {}

    public static String fromBytes(byte[] bytes) {
        if (bytes == null || bytes.length < 4 || bytes.length > 20) {
            throw new IllegalArgumentException("Kartın geçerli sabit kimliği okunamadı.");
        }
        char[] result = new char[bytes.length * 2];
        for (int i = 0; i < bytes.length; i++) {
            result[i * 2] = HEX[(bytes[i] & 255) >>> 4];
            result[i * 2 + 1] = HEX[bytes[i] & 15];
        }
        return new String(result);
    }
}
