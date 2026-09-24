<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ config('app.name', 'SCL') }} - Sistema de Cálculos Laborales</title>
	@vite(['resources/css/app.css', 'resources/js/app.js'])
	@livewireStyles
</head>
<body class="bg-gray-50">
	@php
		$currentRoute = request()->route()?->getName() ?? '';
		$currentUser = auth()->user();
	@endphp

	<div class="flex flex-col h-screen" x-data="{
		sidebarOpen: false,
		sidebarColapsado: false,
		estado: {},
		toggle(seccion) { this.estado[seccion] = !this.estado[seccion]; },
		toggleSidebar() { this.sidebarColapsado = !this.sidebarColapsado; }
	}">

		{{-- Main --}}
		<main class="flex-1 flex flex-col min-w-0">
			@include('partials.header')

			@include('partials.flash')

			{{-- Contenido --}}
			<div class="flex-1 overflow-y-auto">
				@yield('content')
				{{ $slot ?? '' }}
			</div>
		</main>
	</div>

	@stack('scripts')
	@livewireScripts
</body>
</html>
