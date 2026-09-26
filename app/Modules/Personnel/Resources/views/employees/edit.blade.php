@extends('layouts.app')
@section('content')<x-ui.page-header title="Personeli düzenle" :subtitle="$employee->full_name" icon="pencil"/><x-ui.card class="mt-5"><form method="POST" action="{{ route('personnel.employees.update',$employee) }}">@csrf @method('PUT') @include('personnel::employees._employee-form')</form></x-ui.card>@endsection
