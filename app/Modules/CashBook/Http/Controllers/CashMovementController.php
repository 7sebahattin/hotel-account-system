<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Exceptions\CashBookException;
use App\Modules\CashBook\Filters\CashMovementFilter;
use App\Modules\CashBook\Http\Requests\StoreCashMovementRequest;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashMovementCategory;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Kasa hareketi girişi, ters kayıt ve arama/filtre (Gün 12 §8).
 *
 * DÜZENLEME UÇLARI YOKTUR (ne route, ne servis metodu): yanlış hareket ters
 * kayıtla düzeltilir (kullanıcı talimatı §3).
 */
class CashMovementController extends Controller
{
    public function __construct(private readonly CashBookService $cash) {}

    /**
     * Kasa hareketleri listesi — "Bugünkü giderleri göster", "Mehmet'in
     * hareketlerini göster" gibi soruların cevabı.
     *
     * YETKİ KAPSAMASI FİLTRELEMEDEN ÖNCE uygulanır (kullanıcı talimatı §17):
     * `cashbook.view_all` yetkisi olmayan bir kullanıcı hangi query param'ı
     * gönderirse göndersin yalnızca KENDİ hareketlerini görür — temel sorgu
     * `created_by` ile daraltıldıktan SONRA filtre bunun ÜZERİNE uygulanır.
     */
    public function index(Request $request, CashMovementFilter $filter): View
    {
        $this->authorize('viewAny', CashMovement::class);

        $user = $request->user();
        $canViewAll = $user->can(Permission::CashBookViewAll->value);

        $query = CashMovement::query()->with(['creator', 'session']);

        if (! $canViewAll) {
            $query->where('created_by', $user->getKey());
        }

        $movements = $filter->apply($query)
            ->paginate($filter->perPage())
            ->withQueryString();

        return view('cashbook::movements.index', [
            'movements' => $movements,
            'filter' => $filter,
            'canViewAll' => $canViewAll,
            'types' => CashMovementType::cases(),
            'currencies' => Currency::cases(),
            'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
            /** @var Collection<int, User> $users */
            'users' => $canViewAll
                ? User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : new Collection,
        ]);
    }

    /**
     * `$request->ajax()` iken (Hızlı İşlem'in modal/sheet'i, v2.1.1-B.1) yalnızca
     * içerik parçası döner — layout, sidebar, Lemon Bar hiç render edilmez.
     * Normal gezinmede (JS kapalı, doğrudan link) TAM sayfa döner. İkisi de
     * AYNI veriyi ve AYNI `_form` partial'ını kullanır — iki farklı form YOKTUR
     * (kullanıcı talimatı §4).
     *
     * Vardiya yoksa (redirect) veya yetki yoksa (403) burada ÖZEL bir dal
     * AÇILMAZ: `fetch()` yönlendirmeleri kendiliğinden takip eder, 403 JSON
     * döner — istemci JS ikisinde de güvenli biçimde TAM SAYFA gezinmesine
     * düşer (bkz. app.js `openCashMovementDialog`).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $session = $this->cash->activeSession();

        if ($session === null) {
            return redirect()->route('cashbook.index')
                ->with('error', 'Açık kasa vardiyası yok. Önce kasayı açın.');
        }

        $this->authorize('create', [CashMovement::class, $session]);

        $data = [
            'session' => $session,
            'categories' => CashMovementCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::active(),
            'selectedCategoryId' => $request->query('category_id'),
        ];

        return $request->ajax()
            ? view('cashbook::movements._dialog-fragment', $data)
            : view('cashbook::movements.create', $data);
    }

    public function store(StoreCashMovementRequest $request): RedirectResponse
    {
        $session = $this->cash->activeSession();

        if ($session === null) {
            return redirect()->route('cashbook.index')
                ->with('error', 'Açık kasa vardiyası yok. Önce kasayı açın.');
        }

        $this->authorize('create', [CashMovement::class, $session]);

        // FormRequest `exists|where(is_active,true)` ile doğrulamıştır; bu
        // noktada kategori kesin AKTİF ve VAR olmalıdır.
        $category = $request->category();

        if ($category === null) {
            return $this->backToCreate($request)->with('error', 'Geçersiz ya da pasif hareket türü.');
        }

        try {
            $this->cash->recordMovement(
                session: $session,
                type: $category->system_type,
                currency: $request->currency(),
                amount: $request->money(),
                actor: $request->user(),
                description: $request->validated()['description'] ?? null,
                counterparty: $request->validated()['counterparty'] ?? null,
                categoryId: $category->getKey(),
                categoryLabel: $category->name,
            );
        } catch (CashBookException $e) {
            return $this->backToCreate($request)->with('error', $e->getMessage());
        }

        // Kaydettikten sonra kullanıcı kasa ekranına döner (kullanıcı talimatı §12).
        return redirect()->route('cashbook.index')
            ->with('success', 'Kasa hareketi kaydedildi.');
    }

    /**
     * Başarısız gönderim HER ZAMAN standalone "Hareket ekle" sayfasına döner
     * — `back()`/Referer'a GÜVENİLMEZ (v2.1.1-B.1 §5). Form artık Hızlı
     * İşlem'in modal/sheet'inden de gönderilebilir; modal HANGİ sayfanın
     * üzerinde açılmışsa (Pano, Tahsilatlar, Firmalar…) Referer o sayfa olur
     * ve o sayfa bu formu/hataları hiç göstermez — kullanıcı hatasını görmeden
     * kaybolmuş sanır. Standart POST + yönlendirilmiş bağımsız hata ekranı
     * (kullanıcı talimatı §5) burada EN GÜVENLİ desendir: hedef her zaman
     * aynı, öngörülebilir ve formu gerçekten render eden sayfadır.
     */
    private function backToCreate(Request $request): RedirectResponse
    {
        return redirect()->route('cashbook.movements.create')->withInput($request->except('_token'));
    }

    /** Düzeltme: hareketin tam tersini yazar. Orijinal kayıt silinmez. */
    public function reverse(Request $request, CashMovement $movement): RedirectResponse
    {
        $this->authorize('reverse', $movement);

        try {
            $this->cash->reverseMovement($movement, $request->user());
        } catch (CashBookException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cashbook.index')
            ->with('success', 'Hareket ters kayıtla iptal edildi. Orijinal kayıt defterde kalır.');
    }
}
