@csrf

<x-ui.errors />

@php($r = $room ?? null)

<div class="space-y-4">
    <x-ui.input label="Oda numarası" name="room_no" required maxlength="20" :value="$r->room_no ?? null" />
    <x-ui.input label="Oda adı" name="room_name" maxlength="150" :value="$r->room_name ?? null"
                hint="İsteğe bağlı, örn. 'Deniz Manzaralı'." />
    <x-ui.input label="Oda tipi" name="room_type" maxlength="50" :value="$r->room_type ?? null"
                hint="İsteğe bağlı, serbest metin — örn. 'Standart', 'Suit'." />
</div>
