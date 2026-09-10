<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Exceptions\CashSessionIsClosed;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * §8 kullanıcı talimatı: "DB constraint + transaction + lock davranışını
 * test et. Sadece application validation'a güvenme."
 *
 * `RefreshDatabase` her testi TEK bir açık transaction içine sarar ve testin
 * sonunda geri alır — bu, iki GERÇEK veritabanı bağlantısının birbirini
 * KİLİTLEMESİNİ test etmeyi imkânsız kılar (ikinci bağlantı birincinin henüz
 * commit etmediği hiçbir şeyi göremez, çünkü hepsi aynı işlemdedir). Bu yüzden
 * bu sınıf `DatabaseTruncation` kullanır: varsayılan bağlantı GERÇEKTEN commit
 * eder ve ham bir ikinci PDO bağlantısı bu commit'leri anında görür.
 *
 * Her test, ikinci bağlantıda KISA bir `innodb_lock_wait_timeout` ayarlar ve
 * gerçek bir InnoDB kilit bekleyişinin (SQLSTATE HY000 / hata 1205) OLUŞTUĞUNU
 * ölçer — yalnızca uygulama kodunun mantığına güvenilmez, veritabanı motorunun
 * gerçekten kilitlediği ampirik olarak kanıtlanır.
 */
class CashBookConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** Bu sınıfın dokunduğu tablolarla sınırlı — diğer test sınıflarının global sayımlarını bozmasın. */
    protected array $tablesToTruncate = [
        'cash_sessions',
        'cash_movements',
        'cash_transfers',
        'cash_transfer_amounts',
        'cash_counts',
        'audit_logs',
        'model_has_roles',
        'role_has_permissions',
        'roles',
        'permissions',
        'users',
    ];

    protected CashBookService $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->cash = app(CashBookService::class);
    }

    /**
     * Testin ürettiği hiçbir satır bir sonraki (belki `RefreshDatabase`
     * kullanan, dolayısıyla önceden truncate ETMEYEN) test sınıfına sızmasın.
     */
    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    /** Her çağrı GERÇEKTEN bağımsız yeni bir PDO bağlantısı açar. */
    private function rawConnection(): PDO
    {
        $config = config('database.connections.mysql');

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        // Racer bağlantı bloklandığında testi 50 saniye değil, en fazla 2
        // saniye bekletsin — gerçek bir kilit yaşandığını KANITLAMAK için
        // yeterli, ama test paketini yavaşlatmayacak kadar kısa.
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 2');

        return $pdo;
    }

    private function assertLockWaitTimeout(callable $blockedCall): void
    {
        try {
            $blockedCall();
            $this->fail('İkinci bağlantının kilit beklemesinden bir PDOException bekleniyordu.');
        } catch (PDOException $e) {
            $this->assertStringContainsStringIgnoringCase(
                'lock wait timeout',
                $e->getMessage(),
                'Hata gerçek bir InnoDB kilit zaman aşımı olmalı, başka bir hata değil.'
            );
        }
    }

    // ============================================ İlk vardiyanın açılışı

    /**
     * İki kullanıcı TAM OLARAK aynı anda ilk vardiyayı açmaya çalışırsa:
     * biri INSERT'i commit edene kadar diğeri satırda BEKLER (uygulama kodu
     * hiç araya girmeden, salt InnoDB'nin `UNIQUE(is_open)` davranışıyla),
     * sonra ikinci bağlantı GERÇEK bir yinelenen-anahtar hatasıyla reddedilir.
     */
    #[Test]
    public function iki_kullanici_ayni_anda_ilk_vardiyayi_acmaya_calisirsa_veritabani_ikinciyi_engeller(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $y = $this->userWithRole(RoleName::Personel, ['name' => 'Y']);

        $connA = $this->rawConnection();
        $connB = $this->rawConnection();

        $insert = 'INSERT INTO cash_sessions (opened_by, opened_at, status, is_open, created_at, updated_at)
                    VALUES (:user_id, NOW(), \'open\', 1, NOW(), NOW())';

        // A: açılışı BAŞLATIR ama henüz commit ETMEZ — X'in isteği "işlemde".
        $connA->beginTransaction();
        $stmtA = $connA->prepare($insert);
        $stmtA->execute(['user_id' => $x->id]);

        // B: TAM O ANDA aynı açılışı dener — A commit etmeden karar veremez,
        // bu yüzden GERÇEKTEN bloke olur (yalnızca uygulamanın "zaten açık
        // var mı?" kontrolü değil, InnoDB'nin kendisi).
        $connB->beginTransaction();
        $stmtB = $connB->prepare($insert);
        $this->assertLockWaitTimeout(fn () => $stmtB->execute(['user_id' => $y->id]));
        $connB->rollBack();

        // A'nın vardiyası gerçekten açıldı.
        $connA->commit();

        // Şimdi B tekrar dener: bu kez blokaj yok, GERÇEK bir yinelenen
        // anahtar hatası var — ikinci açık vardiya asla var olamaz.
        $connC = $this->rawConnection();
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/duplicate|unique/i');

        $stmtC = $connC->prepare($insert);
        $stmtC->execute(['user_id' => $y->id]);
    }

    // ==================================================== Devir kabulü

    /**
     * `acceptHandover()`'ın kilitleme deseni tam olarak budur: `SELECT ...
     * WHERE status = 'pending' FOR UPDATE`. Burada bu SORGUYU İKİ AYRI
     * bağlantıda TAM OLARAK aynı anda çalıştırıp gerçek bir kilit
     * bekleyişi ölçüyoruz — servis metodunu iki kere sırayla çağırmak
     * (`kabul_edilmis_devir_tekrar_kabul_edilemez` testi bunu zaten yapıyor)
     * yalnızca UYGULAMA mantığını kanıtlar, eşzamanlılığı değil.
     */
    #[Test]
    public function ayni_transferin_kilitli_okumasi_es_zamanli_ikinci_kabulu_gercekten_bekletir(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $y = $this->userWithRole(RoleName::Personel, ['name' => 'Y']);

        $session = $this->cash->openSession($x, ['TRY' => Money::fromDecimal('1000.00')]);
        $transfer = $this->cash->startHandover($session, $y, ['TRY' => Money::fromDecimal('1000.00')], $x);

        $connA = $this->rawConnection();
        $connB = $this->rawConnection();

        $lockingSelect = "SELECT id FROM cash_transfers WHERE id = :id AND status = 'pending' FOR UPDATE";

        // A: acceptHandover()'ın YAPTIĞI TAM SATIRI kilitler, henüz commit etmez.
        $connA->beginTransaction();
        $stmtA = $connA->prepare($lockingSelect);
        $stmtA->execute(['id' => $transfer->id]);
        $this->assertNotFalse($stmtA->fetch(), 'A satırı gerçekten kilitlemiş olmalı');

        // B: TAM O ANDA aynı satırı aynı şekilde kilitlemeye çalışır — engellenir.
        $connB->beginTransaction();
        $stmtB = $connB->prepare($lockingSelect);
        $this->assertLockWaitTimeout(fn () => $stmtB->execute(['id' => $transfer->id]));
        $connB->rollBack();

        // A, gerçek kabul akışının yaptığı gibi devri sonuçlandırır.
        // `to_session_id` NOT NULL zorunludur (cash_transfers_response_consistent);
        // burada mekanik kanıt amacı dışında yeni bir vardiya açmaya gerek
        // olmadığından mevcut vardiyanın kendi id'si kullanılır (FK açısından geçerli).
        $connA->prepare(
            "UPDATE cash_transfers SET status = 'accepted', to_session_id = :to_session_id, responded_at = :responded_at WHERE id = :id"
        )->execute([
            'to_session_id' => $session->id,
            'responded_at' => now()->toDateTimeString(),
            'id' => $transfer->id,
        ]);
        $connA->commit();

        // B şimdi ARTIK BEKLEMEDEN tekrar dener: satır artık 'pending' değil,
        // bu yüzden sorgu HİÇ satır döndürmez — `acceptHandover()`'daki
        // `$locked === null` dalının tam olarak yakaladığı durum budur.
        $connC = $this->rawConnection();
        $stmtC = $connC->prepare($lockingSelect);
        $stmtC->execute(['id' => $transfer->id]);
        $this->assertFalse($stmtC->fetch(), 'İkinci kabul denemesi artık hiçbir bekleyen satır bulamamalı');
    }

    // ============================================ Kapanan vardiyayla yarış

    /**
     * `lockOpenSession()`'ın satır kilidi — `recordMovement()`/`closeSession()`
     * ORTASINDA (henüz commit edilmemişken) başka bir işlem AYNI vardiya
     * satırını yazılabilir bulmaya çalışırsa gerçekten bekler; yarış
     * çözüldüğünde (vardiya kapandıktan sonra) servis GÜNCEL durumu görür ve
     * hareketi reddeder — asla stale "açık" bilgisiyle ilerlemez.
     */
    #[Test]
    public function vardiya_kapanirken_ayni_satiri_kilitlemeye_calisan_ikinci_islem_gercekten_bekler(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $session = $this->cash->openSession($x, ['TRY' => Money::fromDecimal('1000.00')]);

        $connA = $this->rawConnection();
        $connB = $this->rawConnection();

        $lockingSelect = 'SELECT id FROM cash_sessions WHERE id = :id FOR UPDATE';

        // A: `lockOpenSession()`'ın yaptığı TAM satır kilidini simüle eder —
        // closeSession() ya da recordMovement() "işlemde", henüz commit yok.
        $connA->beginTransaction();
        $stmtA = $connA->prepare($lockingSelect);
        $stmtA->execute(['id' => $session->id]);
        $this->assertNotFalse($stmtA->fetch());

        // B: TAM O ANDA aynı vardiyayı yazmak için aynı satırı kilitlemeye
        // çalışır — bu, uygulamanın "kapalı mı?" kontrolünden ÖNCE olur ve
        // gerçekten bekletir.
        $connB->beginTransaction();
        $stmtB = $connB->prepare($lockingSelect);
        $this->assertLockWaitTimeout(fn () => $stmtB->execute(['id' => $session->id]));
        $connB->rollBack();

        // A, kapanışı GERÇEKTEN tamamlar (closeSession()'ın yaptığı gibi).
        // `closed_at` PHP tarafında `now()` ile üretilir — ham SQL'in `NOW()`'ı
        // DEĞİL: `opened_at` uygulamanın kendi saat diliminden (Carbon/Eloquent)
        // yazılmıştır, MySQL sunucusunun `NOW()`'ı ise sunucu saat dilimindedir;
        // ikisini karıştırmak `cash_sessions_closed_after_opened` kısıtını
        // gerçek bir hatayla değil, saat dilimi farkıyla tetikler.
        $connA->prepare(
            "UPDATE cash_sessions SET status = 'closed', is_open = NULL, closed_at = :closed_at, closed_by = :closed_by WHERE id = :id"
        )->execute(['closed_at' => now()->toDateTimeString(), 'closed_by' => $x->id, 'id' => $session->id]);
        $connA->commit();

        // Yarışı kaybeden taraf artık GERÇEK servisi çağırır: stale "açık"
        // bilgisiyle değil, GÜNCEL "kapalı" bilgisiyle karşılaşır ve reddedilir.
        $this->expectException(CashSessionIsClosed::class);

        $this->cash->recordMovement(
            session: $session,
            type: CashMovementType::RoomIncome,
            currency: Currency::TRY,
            amount: Money::fromDecimal('100.00'),
            actor: $x,
        );
    }

    // ========================================== Yardımcı: yalnızca sağlık

    /**
     * Ortamın gerçekten önceki commit'leri gördüğünü doğrular — bu sınıfın
     * TÜM varsayımının ön koşulu. `RefreshDatabase` altında bu test İMKÂNSIZ
     * olurdu (ikinci bağlantı ilk bağlantının açık transaction'ını hiç göremez).
     */
    #[Test]
    public function ikinci_ham_baglanti_birinci_baglantinin_commit_ettigini_gercekten_gorur(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $session = $this->cash->openSession($x, ['TRY' => Money::fromDecimal('1000.00')]);

        $raw = $this->rawConnection();
        $stmt = $raw->prepare('SELECT COUNT(*) AS c FROM cash_sessions WHERE id = :id');
        $stmt->execute(['id' => $session->id]);

        $this->assertSame(1, (int) $stmt->fetch()['c']);

        // Aynı satırı Eloquent'ten de (bambaşka bir sorgu yolundan) görebiliyoruz.
        $this->assertNotNull(CashSession::query()->find($session->id));
    }
}
