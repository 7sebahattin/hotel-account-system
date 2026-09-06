{{--
    Tailwind sınıfları statik yazılmalıdır; "border-{{ $color }}-200" gibi
    dinamik kurgular derleme sırasında taranamaz ve purge edilir.
--}}
@if (session()->has('success'))
    <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('success') }}
    </div>
@endif

@if (session()->has('error'))
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ session('error') }}
    </div>
@endif

@if (session()->has('status'))
    <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
        {{ session('status') }}
    </div>
@endif
