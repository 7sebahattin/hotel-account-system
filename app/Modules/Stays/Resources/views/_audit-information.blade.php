<dl @class([
    'space-y-3 text-sm',
    'grid gap-x-6 gap-y-3 space-y-0 sm:grid-cols-2' => $compact ?? false,
])>
    <div>
        <dt class="text-xs uppercase tracking-wide text-mut">Oluşturan</dt>
        <dd class="mt-0.5 text-tx">
            {{ $stay->creator?->name ?? '—' }}
            <span class="text-mut">· {{ $stay->created_at->format('d.m.Y H:i') }}</span>
        </dd>
    </div>
    <div>
        <dt class="text-xs uppercase tracking-wide text-mut">Son güncelleyen</dt>
        <dd class="mt-0.5 text-tx">
            {{ $stay->updater?->name ?? '—' }}
            <span class="text-mut">· {{ $stay->updated_at->format('d.m.Y H:i') }}</span>
        </dd>
    </div>
</dl>
