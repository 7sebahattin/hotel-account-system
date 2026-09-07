@props(['message' => 'Kayıt bulunamadı.'])

<p {{ $attributes->class('rounded-[10px] border border-dashed border-line bg-card px-6 py-10 text-center text-sm text-mut') }}>
    {{ $slot->isEmpty() ? $message : $slot }}
</p>
