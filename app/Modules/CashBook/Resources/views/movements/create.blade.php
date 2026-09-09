@extends('layouts.app')
@section('title', 'Kasa hareketi ekle')

@section('content')
    <x-ui.page-header title="Hareket ekle"
                      :subtitle="$session->openedBy?->name.' vardiyası'" />

    <x-ui.card class="mt-4">
        @if ($categories->isEmpty())
            <x-ui.alert tone="danger">
                Aktif bir kasa hareket türü yok. Önce
                @can('viewAny', \App\Modules\CashBook\Models\CashMovementCategory::class)
                    <a href="{{ route('cashbook.categories.index') }}" class="underline underline-offset-2">Kasa Hareket Türleri</a>
                @else
                    yönetici
                @endcan
                ekranından bir tür ekleyin/etkinleştirin.
            </x-ui.alert>
        @else
            <form method="POST" action="{{ route('cashbook.movements.store') }}" class="space-y-4">
                @csrf
                <x-ui.errors />

                {{--
                    v2.1.1-B §7: büyük radyo kart yerine sade bir açılır liste
                    — admin tanımlı kategoriler arasından seçilir, sabit sistem
                    davranışı (gelir/gider/yön) her zaman kategorinin kendisine
                    bağlıdır ve burada asla değiştirilmez.
                --}}
                <x-ui.select label="Hareket türü" name="category_id" required>
                    <option value="">Seçiniz…</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}"
                                data-requires-recipient="{{ $category->requires_recipient ? '1' : '0' }}"
                                @selected((string) old('category_id', $selectedCategoryId ?? '') === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </x-ui.select>

                <div class="grid gap-4 sm:grid-cols-2">
                    {{--
                        P0/§7: alan ASLA gerçekçi bir tutarla önceden doldurulmaz;
                        placeholder açıkça "Örn:" ile başlar ki gerçek bir değer
                        sanılmasın (v2.1.1 audit bulgusu).
                    --}}
                    <x-ui.input label="Tutar" name="amount" inputmode="decimal" required
                                placeholder="Örn: 2.500,00" :value="old('amount')" class="tabular-nums" />

                    <x-ui.select label="Para birimi" name="currency" required>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->value }}" @selected(old('currency', 'TRY') === $currency->value)>
                                {{ $currency->value }} — {{ $currency->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.input label="Açıklama" name="description" maxlength="255" :value="old('description')"
                            hint="Örn. 204 numaralı oda, mini bar, market alışverişi" />

                <div data-recipient-field>
                    <x-ui.input label="Teslim alan" name="counterparty" maxlength="150" :value="old('counterparty')"
                                hint="Bu hareket türü için zorunlu: parayı teslim alan kişi/yer." />
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit">Kaydet</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('cashbook.index')">Vazgeç</x-ui.button>
                </div>
            </form>

            <script>
                // Teslim alan alanı yalnızca "requires_recipient" kategorisinde
                // öne çıkar — JS yalnızca GÖRÜNÜMÜ yönetir, doğrulama sunucudadır
                // (StoreCashMovementRequest zaten zorunlu kılıyorsa reddeder).
                (function () {
                    var select = document.querySelector('select[name="category_id"]');
                    var field = document.querySelector('[data-recipient-field]');
                    if (!select || !field) return;

                    function sync() {
                        var option = select.options[select.selectedIndex];
                        var requires = option && option.dataset.requiresRecipient === '1';
                        field.hidden = !requires;
                    }

                    select.addEventListener('change', sync);
                    sync();
                })();
            </script>
        @endif
    </x-ui.card>
@endsection
