@props(['title' => null, 'padded' => true])

<div {{ $attributes->class(['rounded-[10px] border border-line bg-card', 'p-4' => $padded]) }}>
    @if ($title)
        <h2 @class(['text-sm font-medium text-tx', 'px-4 pt-4' => ! $padded, 'mb-3' => $padded])>
            {{ $title }}
        </h2>
    @endif

    {{ $slot }}
</div>
