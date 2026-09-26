import com.lemon.personnel.CardIdentifier;

public final class CardIdentifierTest {
    public static void main(String[] args) {
        if (!"00017FFF".equals(CardIdentifier.fromBytes(new byte[]{0, 1, 127, -1}))) throw new AssertionError("UID byte order/zero padding");
        for (byte[] value : new byte[][]{null, new byte[0], new byte[3], new byte[21]}) {
            try { CardIdentifier.fromBytes(value); throw new AssertionError("Invalid UID accepted"); }
            catch (IllegalArgumentException expected) { }
        }
        System.out.println("CardIdentifier: all tests passed");
    }
}
