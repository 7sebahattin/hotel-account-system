@extends('layouts.app')
@section('content')<x-ui.page-header title="Yeni personel" subtitle="İletişim ve görev bilgilerini kaydedin" icon="users"/><x-ui.card class="mt-5"><form method="POST" action="{{ route('personnel.employees.store') }}">@csrf @include('personnel::employees._employee-form')</form></x-ui.card>@endsection
