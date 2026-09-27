<div class="grid gap-3 sm:grid-cols-2">
    <x-ui.input type="time" name="start_time" :id="$prefix.'-start'" label="Başlangıç" :value="$version ? substr($version->start_time,0,5) : '09:00'" required />
    <x-ui.input type="time" name="end_time" :id="$prefix.'-end'" label="Bitiş" :value="$version ? substr($version->end_time,0,5) : '18:00'" required />
    <label class="flex min-h-11 items-center gap-2 text-sm"><input type="hidden" name="ends_next_day" value="0"><input type="checkbox" name="ends_next_day" value="1" @checked(old('ends_next_day',$version?->ends_next_day ?? false))> Ertesi gün biter</label>
    <x-ui.input type="number" name="break_minutes" :id="$prefix.'-break'" label="Mola (dakika)" :value="$version?->break_minutes ?? 60" min="0" max="1439" required />
    <x-ui.input type="number" name="late_grace_minutes" :id="$prefix.'-late'" label="Geç kalma toleransı (dk)" :value="$version?->late_grace_minutes ?? 0" min="0" max="1440" />
    <x-ui.input type="number" name="early_grace_minutes" :id="$prefix.'-early'" label="Erken çıkma toleransı (dk)" :value="$version?->early_grace_minutes ?? 0" min="0" max="1440" />
</div>
<fieldset class="mt-3"><legend class="text-sm text-mut">Çalışma günleri</legend><div class="mt-1 flex flex-wrap gap-2">
    @foreach([1=>'Pazartesi',2=>'Salı',3=>'Çarşamba',4=>'Perşembe',5=>'Cuma',6=>'Cumartesi',7=>'Pazar'] as $number=>$day)
        <label class="flex min-h-11 items-center gap-2 rounded border border-line px-2 text-sm"><input type="checkbox" name="workdays[]" value="{{ $number }}" @checked(in_array($number, old('workdays',$version?->workdays ?? [1,2,3,4,5])))> {{ $day }}</label>
    @endforeach
</div>@error('workdays')<p class="text-sm text-danger">{{ $message }}</p>@enderror</fieldset>
