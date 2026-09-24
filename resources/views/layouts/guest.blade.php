<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SCL') }} - Sistema de Cálculos Laborales</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="mb-4 text-center">
                <div class="w-14 h-14 mx-auto bg-blue-600 flex items-center justify-center mb-2">
                    <span class="text-white text-xl font-bold">SCL</span>
                </div>
                <h1 class="text-lg font-bold text-gray-800">Sistema de Cálculos Laborales</h1>
                <p class="text-xs text-gray-500">Venezuela</p>
            </div>

            <div class="w-full sm:max-w-md mt-2 px-6 py-6 bg-white border border-gray-200">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
