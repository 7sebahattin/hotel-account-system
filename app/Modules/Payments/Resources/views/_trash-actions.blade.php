<div class="flex flex-wrap items-center gap-2">
    @can('restore', $payment)
        <form method="POST" action="{{ route('payments.untrash', $payment) }}">
            @csrf @method('PATCH')
            <x-ui.button type="submit" variant="secondary" class="min-h-9 px-3">Geri al</x-ui.button>
        </form>
    @endcan
    @can('forceDelete', $payment)
        <form method="POST" action="{{ route('payments.force-delete', $payment) }}"
              onsubmit="return confirm('Bu tahsilat kaydı veritabanından KALICI olarak silinecek. Bu işlem GERİ ALINAMAZ. Emin misiniz?')">
            @csrf @method('DELETE')
            <x-ui.button type="submit" variant="danger" class="min-h-9 px-3">Kalıcı sil</x-ui.button>
        </form>
    @endcan
</div>
