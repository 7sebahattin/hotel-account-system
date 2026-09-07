<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-bg text-tx antialiased">
    <div class="flex min-h-screen flex-col justify-center px-4 py-12 sm:px-6">
        <main class="mx-auto w-full max-w-sm">
            <h1 class="text-center text-[19px] font-medium text-tx">{{ config('app.name') }}</h1>

            <x-ui.card class="mt-6 p-6">
                @yield('content')
            </x-ui.card>
        </main>
    </div>
</body>
</html>
