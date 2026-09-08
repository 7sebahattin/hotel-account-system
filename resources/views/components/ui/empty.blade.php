@props(['message' => 'Kayıt bulunamadı.'])

<p {{ $attributes->class('rounded-xl border border-dashed border-field bg-card px-6 py-10 text-center text-sm text-mut') }}>
    {{ $slot->isEmpty() ? $message : $slot }}
</p>
