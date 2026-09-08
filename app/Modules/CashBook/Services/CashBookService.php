<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\CashBook\Enums\CashMovementDirection;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Enums\CashSessionStatus;
use App\Modules\CashBook\Enums\CashTransferStatus;
use App\Modules\CashBook\Exceptions\CannotReverseMovement;
use App\Modules\CashBook\Exceptions\CashSessionAlreadyOpen;
use App\Modules\CashBook\Exceptions\CashSessionIsClosed;
use App\Modules\CashBook\Exceptions\CashTransferNotPending;
use App\Modules\CashBook\Exceptions\InvalidCashMovement;
use App\Modules\CashBook\Models\CashCount;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Models\CashTransfer;
use App\Modules\CashBook\Models\CashTransferAmount;
use App\Modules\CashBook\Support\CashSessionSummary;
use App\Modules\CashBook\Support\CashSessionTotals;
use App\Modules\CashBook\Support\CashSnapshot;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Kasa defterinin dışa açık TEK kapısı (CLAUDE.md §4.3).
 *
 * Hiçbir controller, görünüm veya başka modül CashMovement::create() çağırmaz.
 *
 * Bu servis Companies/Stays/Payments/Ledger modellerine HİÇ dokunmaz: kasa
 * defteri fiziksel parayı izler, cari hesap firma borcunu izler. İkisi ayrı
 * defterlerdir ve birbirine yazmazlar (kullanıcı talimatı §22).
 *
 * DEĞİŞMEZLER
 *  - İşletmede aynı anda en fazla BİR açık vardiya vardır (DB: UNIQUE(is_open)).
 *  - Kapalı vardiyaya hareket yazılamaz (DB: BEFORE INSERT tetikleyici).
 *  - Hareket güncellenemez/silinemez; düzeltme ters harekettir (DB: tetikleyici).
 *  - Bir hareket yalnızca bir kez ters kaydedilebilir (DB: UNIQUE).
 *  - Devir kabul bekliyorken vardiyaya yeni hareket girilmez (servis kuralı).
 */
class CashBookService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    // ------------------------------------------------------------ okuma

    /** İşletmedeki tek açık vardiya; yoksa null. */
    public function activeSession(): ?CashSession
    {
        return CashSession::query()
            ->with('openedBy')
            ->where('status', CashSessionStatus::Open->value)
            ->first();
    }

    /**
     * Vardiyanın para birimi bazında toplamları — TEK sorgu.
     *
     * Hesap SQL'de değil burada yapılır ve tamsayı kuruş üzerinden ilerler;
     * kayan nokta kullanılmaz (CLAUDE.md §13).
     *
     * @return array<string, CashSessionTotals>
     */
    public function totalsFor(CashSession $session): array
    {
        $rows = DB::table('cash_movements')
            ->select('currency', 'type')
            ->selectRaw(
                "CAST(COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) AS CHAR) AS net"
            )
            ->where('cash_session_id', $session->getKey())
            ->groupBy('currency', 'type')
            ->get();

        /** @var array<string, array<string, Money>> $byCurrency */
        $byCurrency = [];

        foreach ($rows as $row) {
            $byCurrency[(string) $row->currency][(string) $row->type] = Money::fromDecimal((string) $row->net);
        }

        return $this->buildTotals($byCurrency);
    }

    /**
     * Para birimi => tip => net eşlemesinden toplam nesnelerini kurar.
     *
     * @param  array<string, array<string, Money>>  $byCurrency
     * @return array<string, CashSessionTotals>
     */
    private function buildTotals(array $byCurrency): array
    {
        $totals = [];

        foreach ($this->orderCurrencies(array_keys($byCurrency)) as $code) {
            $nets = $byCurrency[$code];

            $totals[$code] = new CashSessionTotals(
                currency: Currency::from($code),
                opening: $nets[CashMovementType::Opening->value] ?? Money::zero(),
                roomIncome: $nets[CashMovementType::RoomIncome->value] ?? Money::zero(),
                extraIncome: $nets[CashMovementType::ExtraIncome->value] ?? Money::zero(),
                expense: $nets[CashMovementType::Expense->value] ?? Money::zero(),
                cashOut: $nets[CashMovementType::CashOut->value] ?? Money::zero(),
                cashIn: $nets[CashMovementType::CashIn->value] ?? Money::zero(),
            );
        }

        return $totals;
    }

    /**
     * Beklenen fiziksel kasa, para birimi bazında.
     *
     * @return array<string, Money>
     */
    public function expectedFor(CashSession $session): array
    {
        return array_map(
            static fn (CashSessionTotals $totals): Money => $totals->expected(),
            $this->totalsFor($session),
        );
    }

    /** Ekranların ve raporun tek veri kaynağı. */
    public function summaryFor(CashSession $session): CashSessionSummary
    {
        return new CashSessionSummary(
            session: $session,
            totals: $this->totalsFor($session),
            movementCount: CashMovement::query()->where('cash_session_id', $session->getKey())->count(),
            counts: $session->counts()->orderBy('currency')->get(),
            outgoingTransfer: $this->latestTransferFrom($session),
            incomingTransfer: CashTransfer::query()
                ->with(['fromUser', 'amounts'])
                ->where('to_session_id', $session->getKey())
                ->first(),
        );
    }

    /**
     * Birden çok vardiyanın özeti — SABİT sayıda sorgu (N+1 yok).
     *
     * Günlük kasa defteri ekranı bir günde 3-4 vardiya gösterebilir; her
     * vardiya için ayrı ayrı `summaryFor()` çağırmak sorgu sayısını vardiya
     * sayısıyla büyütürdü. Burada tüm vardiyalar için toplamlar, sayımlar ve
     * devirler toplu çekilir: vardiya sayısı ne olursa olsun 4 sorgu.
     *
     * @param  EloquentCollection<int, CashSession>  $sessions
     * @return array<int, CashSessionSummary>
     */
    public function summariesFor(EloquentCollection $sessions): array
    {
        $ids = $sessions->modelKeys();

        if ($ids === []) {
            return [];
        }

        $netRows = DB::table('cash_movements')
            ->select('cash_session_id', 'currency', 'type')
            ->selectRaw(
                "CAST(COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) AS CHAR) AS net"
            )
            ->selectRaw('COUNT(*) AS movement_count')
            ->whereIn('cash_session_id', $ids)
            ->groupBy('cash_session_id', 'currency', 'type')
            ->get();

        /** @var array<int, array<string, array<string, Money>>> $nets */
        $nets = [];
        /** @var array<int, int> $movementCounts */
        $movementCounts = [];

        foreach ($netRows as $row) {
            $sessionId = (int) $row->cash_session_id;
            $nets[$sessionId][(string) $row->currency][(string) $row->type] = Money::fromDecimal((string) $row->net);
            $movementCounts[$sessionId] = ($movementCounts[$sessionId] ?? 0) + (int) $row->movement_count;
        }

        $countsBySession = CashCount::query()
            ->whereIn('cash_session_id', $ids)
            ->orderBy('currency')
            ->get()
            ->groupBy('cash_session_id');

        $transfers = CashTransfer::query()
            ->with(['toUser', 'fromUser', 'amounts'])
            ->where(function ($query) use ($ids): void {
                $query->whereIn('from_session_id', $ids)->orWhereIn('to_session_id', $ids);
            })
            ->orderByDesc('id')
            ->get();

        $summaries = [];

        foreach ($sessions as $session) {
            $id = (int) $session->getKey();

            $summaries[$id] = new CashSessionSummary(
                session: $session,
                totals: $this->buildTotals($nets[$id] ?? []),
                movementCount: $movementCounts[$id] ?? 0,
                counts: $countsBySession->get($id) ?? new Collection,
                outgoingTransfer: $transfers->firstWhere('from_session_id', $id),
                incomingTransfer: $transfers->firstWhere('to_session_id', $id),
            );
        }

        return $summaries;
    }

    /**
     * Vardiyanın hareketleri — en yeni üstte.
     *
     * @return Collection<int, CashMovement>
     */
    public function movementsFor(CashSession $session, ?int $limit = null): Collection
    {
        $query = CashMovement::query()
            // `session` de yüklenir: CashMovementPolicy::reverse() vardiyanın
            // açık olup olmadığına bakar ve liste ekranı her satır için bu
            // yeteneği sorar — eager load olmadan satır başına bir sorgu doğar.
            ->with(['creator', 'session'])
            ->where('cash_session_id', $session->getKey())
            ->orderByDesc('entry_at')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /** Kullanıcının kabul etmesi beklenen devir. */
    public function pendingTransferFor(User $user): ?CashTransfer
    {
        return CashTransfer::query()
            ->with(['fromUser', 'amounts', 'fromSession'])
            ->where('to_user_id', $user->getKey())
            ->where('status', CashTransferStatus::Pending->value)
            ->orderByDesc('id')
            ->first();
    }

    /** Vardiyadan çıkan en son devir (bekleyen ya da tamamlanmış). */
    public function latestTransferFrom(CashSession $session): ?CashTransfer
    {
        return CashTransfer::query()
            ->with(['toUser', 'amounts'])
            ->where('from_session_id', $session->getKey())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Bir güne ait vardiyalar — günlük kasa defteri ekranı için.
     *
     * Gün içinde AÇILMIŞ ya da gün içinde hâlâ açık olan vardiyalar döner.
     *
     * @return Collection<int, CashSession>
     */
    public function sessionsForDate(DateTimeInterface|string $date): Collection
    {
        $day = Carbon::parse($date);

        return CashSession::query()
            ->with(['openedBy', 'closedBy'])
            ->where('opened_at', '<', $day->copy()->endOfDay())
            ->where(function ($query) use ($day): void {
                $query->whereNull('closed_at')
                    ->orWhere('closed_at', '>=', $day->copy()->startOfDay());
            })
            ->orderBy('opened_at')
            ->get();
    }

    /**
     * Pano kartı için açık vardiyanın özeti — SABİT 4 sorgu, açık vardiya
     * yoksa 1 sorgu.
     *
     * Pano sorgu bütçesi kayıt sayısıyla büyümez (CLAUDE.md §8/Dashboard); bu
     * yüzden burada tam `summaryFor()` kullanılmaz: sayım satırları ve giden
     * devirler pano kartında gösterilmediği için sorgulanmaz da.
     */
    public function dashboardSnapshot(): ?CashSnapshot
    {
        $session = $this->activeSession();

        if ($session === null) {
            return null;
        }

        $row = DB::table('cash_movements')
            ->selectRaw('COUNT(*) AS movement_count')
            ->where('cash_session_id', $session->getKey())
            ->first();

        return new CashSnapshot(
            session: $session,
            expected: $this->expectedFor($session),
            movementCount: (int) ($row->movement_count ?? 0),
            lastTransfer: CashTransfer::query()
                ->with(['fromUser', 'toUser'])
                ->where('status', CashTransferStatus::Accepted->value)
                ->orderByDesc('responded_at')
                ->orderByDesc('id')
                ->first(),
        );
    }

    /**
     * Kullanıcı yeni bir vardiya açabilir mi (yetki bazlı).
     *
     * Panonun "Kasayı Aç" CTA'sını göstermeden önce sorduğu tek soru budur.
     * Modüller arası kural gereği (CLAUDE.md §4.3/1) `CashSession::class`
     * yalnızca burada, kendi modülünde referans alınır; Dashboard bu servisi çağırır.
     */
    public function canOpenSession(User $user): bool
    {
        return $user->can('create', CashSession::class);
    }

    /** Son tamamlanmış devirler — patron ekranı için. */
    public function recentTransfers(int $limit = 5): Collection
    {
        return CashTransfer::query()
            ->with(['fromUser', 'toUser', 'amounts'])
            ->where('status', CashTransferStatus::Accepted->value)
            ->orderByDesc('responded_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    // ------------------------------------------------------------ yazma

    /**
     * Kasayı açar. Açılış bakiyeleri `opening` tipli hareketler olarak yazılır.
     *
     * @param  array<string, Money>  $openingAmounts  para birimi kodu => tutar
     *
     * @throws CashSessionAlreadyOpen
     */
    public function openSession(User $actor, array $openingAmounts = [], ?string $note = null): CashSession
    {
        if ($this->activeSession() !== null) {
            throw CashSessionAlreadyOpen::make();
        }

        try {
            return DB::transaction(function () use ($actor, $openingAmounts, $note): CashSession {
                $session = $this->createSession($actor, $openingAmounts, $note);

                $this->auditLogger->log(
                    event: AuditEvent::Created,
                    subject: $session,
                    newValues: [
                        'cash_session_id' => $session->getKey(),
                        'opened_by' => $actor->getKey(),
                        'opened_at' => $session->opened_at->toDateTimeString(),
                        'opening_balances' => $this->describeAmounts($openingAmounts),
                    ],
                    user: $actor,
                );

                return $session;
            });
        } catch (UniqueConstraintViolationException) {
            // Eşzamanlı ikinci açılış: veritabanı reddetti.
            throw CashSessionAlreadyOpen::make();
        }
    }

    /**
     * Kasa hareketi yazar.
     *
     * @throws CashSessionIsClosed
     * @throws InvalidCashMovement
     */
    public function recordMovement(
        CashSession $session,
        CashMovementType $type,
        Currency $currency,
        Money $amount,
        User $actor,
        ?string $description = null,
        ?string $counterparty = null,
        DateTimeInterface|string|null $entryAt = null,
    ): CashMovement {
        if (! $amount->isPositive()) {
            throw InvalidCashMovement::amountNotPositive();
        }

        return DB::transaction(function () use (
            $session, $type, $currency, $amount, $actor, $description, $counterparty, $entryAt
        ): CashMovement {
            // Kontrol ve yazma ATOMİK olmalıdır: satır kilitlenmeden önce
            // yapılan bir kontrol, tam bu anda başlayan bir devirle (startHandover)
            // yarışa girer — devir "kabul bekliyor" olmadan hemen önce sızan bir
            // hareket, devrin zaten bildirdiği tutarı sessizce stale bırakırdı.
            $locked = $this->lockOpenSession($session);

            $movement = $this->insertMovement(
                session: $locked,
                type: $type,
                direction: $type->direction(),
                currency: $currency,
                amount: $amount,
                actor: $actor,
                description: $description,
                counterparty: $counterparty,
                entryAt: $entryAt,
            );

            $this->auditLogger->log(
                event: AuditEvent::Created,
                subject: $movement,
                newValues: $this->snapshot($movement),
                user: $actor,
            );

            return $movement;
        });
    }

    /**
     * Bir hareketin tam tersini yazar — düzeltmenin TEK yolu.
     *
     * Ters hareket aynı tipi ve tutarı taşır, yalnızca yönü döner; böylece hem
     * beklenen kasa hem gelir-gider raporu kendiliğinden düzelir.
     *
     * @throws CannotReverseMovement
     */
    public function reverseMovement(CashMovement $movement, User $actor, ?string $reason = null): CashMovement
    {
        if ($movement->is_reversal) {
            throw CannotReverseMovement::isReversal($movement->getKey());
        }

        // Açılış bakiyesi ters kaydedilemez: devredilen tutarla kasa arasındaki
        // bağ kopardı. Policy de aynı kuralı uygular; bu servis katmanındaki
        // son savunmadır (CLAUDE.md §7.1).
        if ($movement->type === CashMovementType::Opening) {
            throw CannotReverseMovement::openingBalance($movement->getKey());
        }

        $session = $movement->session;

        if ($session === null) {
            throw CannotReverseMovement::sessionClosed($movement->getKey());
        }

        return DB::transaction(function () use ($movement, $session, $actor, $reason): CashMovement {
            // Aynı kilit/guard atomikliği recordMovement()'taki gibi: bekleyen
            // bir devir varken ters kayıt da YASAKTIR — aksi hâlde devrin zaten
            // bildirdiği tutar, kabul edilmeden hemen önce sızan bir ters
            // kayıtla sessizce stale kalırdı. `sessionClosed` istisnası burada
            // hem "kapalı" hem "devir bekliyor" durumunu kapsar; ikisi de aynı
            // sonucu doğurur: bu an itibarıyla vardiyaya yazılamaz.
            try {
                $locked = $this->lockOpenSession($session);
            } catch (CashSessionIsClosed|InvalidCashMovement) {
                throw CannotReverseMovement::sessionClosed($movement->getKey());
            }

            // Yarışta ikinci ters kaydı veritabanındaki tekil kısıt engeller;
            // buradaki kontrol kullanıcıya anlaşılır hata vermek içindir.
            if ($this->isReversed($movement)) {
                throw CannotReverseMovement::alreadyReversed($movement->getKey());
            }

            try {
                $reversal = $this->insertMovement(
                    session: $locked,
                    type: $movement->type,
                    direction: $movement->direction->opposite(),
                    currency: $movement->currency,
                    amount: $movement->amountMoney(),
                    actor: $actor,
                    description: $reason ?? '[İPTAL] '.($movement->description ?? $movement->type->label()),
                    counterparty: $movement->counterparty,
                    entryAt: null,
                    isReversal: true,
                    reversesMovementId: $movement->getKey(),
                );
            } catch (UniqueConstraintViolationException) {
                throw CannotReverseMovement::alreadyReversed($movement->getKey());
            }

            $this->auditLogger->log(
                event: AuditEvent::Reversed,
                subject: $movement,
                oldValues: $this->snapshot($movement),
                newValues: $this->snapshot($reversal) + ['reversal_movement_id' => $reversal->getKey()],
                user: $actor,
            );

            return $reversal;
        });
    }

    public function isReversed(CashMovement $movement): bool
    {
        return CashMovement::query()->where('reverses_movement_id', $movement->getKey())->exists();
    }

    /**
     * Devri başlatır — teslim alan kabul edene kadar TAMAMLANMAZ.
     *
     * Devir beklerken vardiyaya yeni hareket girilemez: para fiilen elden
     * çıkmıştır, sonradan eklenen hareket devredilen tutarla çelişirdi.
     *
     * @param  array<string, Money>  $amounts  teslim edilen tutarlar
     */
    public function startHandover(
        CashSession $session,
        User $toUser,
        array $amounts,
        User $actor,
        ?string $note = null,
    ): CashTransfer {
        if ($toUser->getKey() === $actor->getKey()) {
            throw new InvalidCashMovement('Kasa kendi kendine devredilemez.');
        }

        try {
            return DB::transaction(function () use ($session, $toUser, $amounts, $actor, $note): CashTransfer {
                // Aynı kilit: tam bu anda bir hareket/ters kayıt yazılıyorsa
                // (recordMovement/reverseMovement) o işlem önce biter, devir
                // ondan SONRAKİ gerçek durumu görür. Sessiz stale tutar oluşmaz.
                $locked = $this->lockOpenSession($session);

                $transfer = new CashTransfer;
                $transfer->from_session_id = $locked->getKey();
                $transfer->from_user_id = $actor->getKey();
                $transfer->to_user_id = $toUser->getKey();
                $transfer->status = CashTransferStatus::Pending;
                $transfer->initiated_at = Carbon::now();
                $transfer->note = $note;
                $transfer->save();

                foreach ($this->positiveAmounts($amounts) as $code => $money) {
                    CashTransferAmount::query()->create([
                        'cash_transfer_id' => $transfer->getKey(),
                        'currency' => $code,
                        'amount' => $money->toDecimal(),
                    ]);
                }

                $this->auditLogger->log(
                    event: AuditEvent::Created,
                    subject: $transfer,
                    newValues: [
                        'cash_transfer_id' => $transfer->getKey(),
                        'from_session_id' => $session->getKey(),
                        'from_user_id' => $actor->getKey(),
                        'to_user_id' => $toUser->getKey(),
                        'status' => CashTransferStatus::Pending->value,
                        'amounts' => $this->describeAmounts($amounts),
                    ],
                    user: $actor,
                );

                return $transfer;
            });
        } catch (UniqueConstraintViolationException) {
            // Aynı vardiyadan ikinci bekleyen devir: veritabanı reddetti.
            throw new InvalidCashMovement('Bu vardiya için zaten kabul bekleyen bir devir var.');
        }
    }

    /**
     * Devri kabul eder: teslim eden vardiya KAPANIR, teslim alan için YENİ
     * vardiya açılır ve teslim edilen tutarlar yeni vardiyanın açılış
     * hareketleri olur. Hepsi tek transaction içindedir.
     *
     * Kapanış sayımı burada yazılır: beklenen tutar o anda hesaplanır, sayılan
     * tutar devredilen tutardır — personel elindekini devreder, farkı sistem
     * hesaplar.
     *
     * @throws CashTransferNotPending
     */
    public function acceptHandover(CashTransfer $transfer, User $actor): CashSession
    {
        return DB::transaction(function () use ($transfer, $actor): CashSession {
            // Kilitli okuma: iki kişi aynı anda kabul edemez.
            $locked = CashTransfer::query()
                ->whereKey($transfer->getKey())
                ->where('status', CashTransferStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw CashTransferNotPending::make($transfer->getKey());
            }

            $fromSession = CashSession::query()
                ->whereKey($locked->from_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amounts = $this->transferAmounts($locked);

            // 1) Teslim eden vardiyanın sayımı ve kapanışı.
            $this->writeCounts($fromSession, $this->expectedFor($fromSession), $amounts, $actor);
            $this->closeSessionRow($fromSession, $locked->from_user_id, null);

            // 2) Teslim alan için yeni vardiya (UNIQUE(is_open) sırayı garanti eder).
            // Devri kabul eden, devrin hedefidir; policy bunu ayrıca doğrular.
            $toSession = $this->createSession($actor, $amounts, null);

            // 3) Devir kaydının sonuçlanması.
            $locked->status = CashTransferStatus::Accepted;
            $locked->to_session_id = $toSession->getKey();
            $locked->responded_at = Carbon::now();
            $locked->save();

            $this->auditLogger->log(
                event: AuditEvent::Updated,
                subject: $locked,
                oldValues: ['status' => CashTransferStatus::Pending->value],
                newValues: [
                    'status' => CashTransferStatus::Accepted->value,
                    'accepted_by' => $actor->getKey(),
                    'from_session_id' => $fromSession->getKey(),
                    'to_session_id' => $toSession->getKey(),
                    'amounts' => $this->describeAmounts($amounts),
                ],
                user: $actor,
            );

            return $toSession;
        });
    }

    /** Devri iptal eder; vardiya açık kalır ve yeni devir başlatılabilir. */
    public function cancelHandover(CashTransfer $transfer, User $actor): CashTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): CashTransfer {
            $locked = CashTransfer::query()
                ->whereKey($transfer->getKey())
                ->where('status', CashTransferStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw CashTransferNotPending::make($transfer->getKey());
            }

            $locked->status = CashTransferStatus::Cancelled;
            $locked->responded_at = Carbon::now();
            $locked->save();

            $this->auditLogger->log(
                event: AuditEvent::Cancelled,
                subject: $locked,
                oldValues: ['status' => CashTransferStatus::Pending->value],
                newValues: ['status' => CashTransferStatus::Cancelled->value, 'cancelled_by' => $actor->getKey()],
                user: $actor,
            );

            return $locked;
        });
    }

    /**
     * Vardiyayı devir olmadan kapatır (gün sonu / kasa boşaltıldı).
     *
     * @param  array<string, Money>  $countedAmounts  fiilen sayılan tutarlar
     */
    public function closeSession(
        CashSession $session,
        array $countedAmounts,
        User $actor,
        ?string $note = null,
    ): CashSession {
        return DB::transaction(function () use ($session, $countedAmounts, $actor, $note): CashSession {
            /*
             * Bekleyen bir devir varken KAPATMA da yasaktır: aksi hâlde
             * writeCounts() burada bir kez, acceptHandover() içinde bir daha
             * çalışır — cash_counts'taki UNIQUE(cash_session_id, currency)
             * kısıtına çarpar ve devir kabulü sessizce 500'e düşerdi. Devri
             * önce kabul ettirin ya da iptal edin.
             */
            $locked = $this->lockOpenSession($session);

            $expected = $this->expectedFor($locked);

            $this->writeCounts($locked, $expected, $countedAmounts, $actor);
            $this->closeSessionRow($locked, $actor->getKey(), $note);

            $this->auditLogger->log(
                event: AuditEvent::Updated,
                subject: $locked,
                oldValues: ['status' => CashSessionStatus::Open->value],
                newValues: [
                    'status' => CashSessionStatus::Closed->value,
                    'closed_by' => $actor->getKey(),
                    'expected' => $this->describeAmounts($expected),
                    'counted' => $this->describeAmounts($countedAmounts),
                ],
                user: $actor,
            );

            return $locked;
        });
    }

    // ------------------------------------------------------------------ iç

    /**
     * @param  array<string, Money>  $openingAmounts
     */
    private function createSession(User $owner, array $openingAmounts, ?string $note): CashSession
    {
        $session = new CashSession;
        $session->opened_by = $owner->getKey();
        $session->opened_at = Carbon::now();
        $session->status = CashSessionStatus::Open;
        $session->is_open = 1;
        $session->opening_note = $note;
        $session->save();

        foreach ($this->positiveAmounts($openingAmounts) as $code => $money) {
            $this->insertMovement(
                session: $session,
                type: CashMovementType::Opening,
                direction: CashMovementDirection::In,
                currency: Currency::from($code),
                amount: $money,
                actor: $owner,
                description: 'Vardiya açılış bakiyesi',
                counterparty: null,
                entryAt: $session->opened_at,
            );
        }

        return $session;
    }

    private function closeSessionRow(CashSession $session, int $closedBy, ?string $note): void
    {
        $session->status = CashSessionStatus::Closed;
        $session->closed_at = Carbon::now();
        $session->closed_by = $closedBy;
        // NULL yapılmadan UNIQUE(is_open) yeni vardiyanın açılmasını engellerdi.
        $session->is_open = null;
        $session->closing_note = $note;
        $session->save();
    }

    /**
     * Kapanış sayımını yazar: beklenen ile sayılanın kesişimi değil BİRLEŞİMİ.
     *
     * Beklenen 300 EUR varken hiç EUR devredilmediyse satır yine yazılır ve
     * fark -300 EUR olarak görünür; eksik para sessizce kaybolmaz.
     *
     * @param  array<string, Money>  $expected
     * @param  array<string, Money>  $counted
     */
    private function writeCounts(CashSession $session, array $expected, array $counted, User $actor): void
    {
        $codes = $this->orderCurrencies(array_unique([...array_keys($expected), ...array_keys($counted)]));

        foreach ($codes as $code) {
            $expectedMoney = $expected[$code] ?? Money::zero();
            $countedMoney = $counted[$code] ?? Money::zero();

            // Hiç görülmemiş para birimi için satır açmaya gerek yok.
            if ($expectedMoney->isZero() && $countedMoney->isZero()) {
                continue;
            }

            CashCount::query()->create([
                'cash_session_id' => $session->getKey(),
                'currency' => $code,
                'expected_amount' => $expectedMoney->toDecimal(),
                'counted_amount' => $countedMoney->toDecimal(),
                'created_by' => $actor->getKey(),
            ]);
        }
    }

    private function insertMovement(
        CashSession $session,
        CashMovementType $type,
        CashMovementDirection $direction,
        Currency $currency,
        Money $amount,
        User $actor,
        ?string $description,
        ?string $counterparty,
        DateTimeInterface|string|null $entryAt,
        bool $isReversal = false,
        ?int $reversesMovementId = null,
    ): CashMovement {
        return CashMovement::query()->create([
            'cash_session_id' => $session->getKey(),
            'type' => $type->value,
            'direction' => $direction->value,
            'amount' => $amount->toDecimal(),
            'currency' => $currency->value,
            'entry_at' => $entryAt !== null ? Carbon::parse($entryAt) : Carbon::now(),
            'description' => $description !== null ? mb_substr($description, 0, 255) : null,
            'counterparty' => $counterparty !== null ? mb_substr($counterparty, 0, 150) : null,
            'is_reversal' => $isReversal,
            'reverses_movement_id' => $reversesMovementId,
            'created_by' => $actor->getKey(),
        ]);
    }

    /**
     * @return array<string, Money>
     */
    private function transferAmounts(CashTransfer $transfer): array
    {
        $amounts = [];

        foreach ($transfer->amounts()->orderBy('currency')->get() as $row) {
            $amounts[$row->currency->value] = $row->amountMoney();
        }

        return $amounts;
    }

    /**
     * Vardiyayı SATIR KİLİDİYLE okur ve "yazılabilir" durumda olduğunu
     * doğrular — kontrol ile yazma arasında ATOMİK bir bütün oluşturur.
     *
     * ÇAĞIRAN ZATEN BİR `DB::transaction()` İÇİNDE OLMALIDIR. Kilit
     * olmadan yapılan bir kontrol (`if (! $session->isClosed()) { ... }`)
     * ile asıl yazma arasındaki pencerede, tam o anda başlayan bir devir
     * (`startHandover`) ya da tam o anda kapatılan vardiya araya girebilir:
     * bu durumda devrin zaten bildirdiği tutar sessizce stale kalır —
     * kullanıcı talimatındaki tam olarak tarif edilen yarış budur. Bu yüzden
     * `recordMovement`, `reverseMovement`, `startHandover` ve `closeSession`
     * hepsi BU metottan geçer: aynı anda ikisi çalışırsa biri satır kilidinde
     * bekler, ilki bitince İKİNCİSİ EN GÜNCEL durumu görür ve doğru kararı verir.
     *
     * @throws CashSessionIsClosed
     * @throws InvalidCashMovement
     */
    private function lockOpenSession(CashSession $session): CashSession
    {
        $locked = CashSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

        $this->guardOpenSession($locked);

        return $locked;
    }

    /**
     * @throws CashSessionIsClosed
     * @throws InvalidCashMovement
     */
    private function guardOpenSession(CashSession $session): void
    {
        if ($session->isClosed()) {
            throw CashSessionIsClosed::make($session->getKey());
        }

        $pending = CashTransfer::query()
            ->where('from_session_id', $session->getKey())
            ->where('status', CashTransferStatus::Pending->value)
            ->exists();

        if ($pending) {
            throw new InvalidCashMovement(
                'Bu vardiya için kabul bekleyen bir devir var; devir sonuçlanmadan işlem yapılamaz.'
            );
        }
    }

    /**
     * Sıfır ve negatif tutarları eler.
     *
     * @param  array<string, Money>  $amounts
     * @return array<string, Money>
     */
    private function positiveAmounts(array $amounts): array
    {
        return array_filter($amounts, static fn (Money $money): bool => $money->isPositive());
    }

    /**
     * Para birimlerini enum sırasına göre dizer — ekranlarda sıra veri
     * girişine göre değil, sabit kalsın diye.
     *
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function orderCurrencies(array $codes): array
    {
        return array_values(array_filter(
            Currency::values(),
            static fn (string $code): bool => in_array($code, $codes, true),
        ));
    }

    /**
     * Denetim kaydı için tutar dökümü — para birimleri ayrı ayrı.
     *
     * @param  array<string, Money>  $amounts
     * @return array<string, string>
     */
    private function describeAmounts(array $amounts): array
    {
        return array_map(static fn (Money $money): string => $money->toDecimal(), $amounts);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CashMovement $movement): array
    {
        return [
            'cash_movement_id' => $movement->getKey(),
            'cash_session_id' => $movement->cash_session_id,
            'type' => $movement->type->value,
            'direction' => $movement->direction->value,
            'amount' => $movement->amountMoney()->toDecimal(),
            'currency' => $movement->currency->value,
            'entry_at' => $movement->entry_at->toDateTimeString(),
            'description' => $movement->description,
            'counterparty' => $movement->counterparty,
            'is_reversal' => $movement->is_reversal,
        ];
    }

    /** Servis dışından aktör verilmediyse oturumdaki kullanıcıyı çözer. */
    public function resolveActor(?User $actor = null): User
    {
        $user = $actor ?? (Auth::check() ? Auth::user() : null);

        if ($user === null) {
            throw InvalidCashMovement::missingActor();
        }

        return $user;
    }
}
