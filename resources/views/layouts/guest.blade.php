<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6">
        <div class="mx-auto w-full max-w-sm">
            <h1 class="text-center text-xl font-semibold tracking-tight text-slate-900">
                {{ config('app.name') }}
            </h1>

            <div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
