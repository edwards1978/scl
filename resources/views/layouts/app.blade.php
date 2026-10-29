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
		$menu = menu_para_usuario($currentUser);

		$activeSection = null;
		foreach ($menu as $section => $seccion) {
			foreach ($seccion['opciones'] ?? [] as $op) {
				$matches = false;
				foreach ($op['activo'] ?? [] as $prefijo) {
					if (str_starts_with($currentRoute, $prefijo)) {
						$matches = true;
						break;
					}
				}
				foreach ($op['excluir'] ?? [] as $excl) {
					if (str_contains($currentRoute, $excl)) {
						$matches = false;
						break;
					}
				}
				if ($matches) {
					$activeSection = $section;
					break 2;
				}
			}
		}
	@endphp

	<div class="flex h-screen" x-data="{
		sidebarOpen: false,
		sidebarColapsado: (window.innerWidth < 1024) ? false : (localStorage.getItem('sidebar_colapsado') === '1'),
		estado: JSON.parse(localStorage.getItem('sidebar_estado_scl') || '{}'),
		init() {
			const secciones = Object.keys({{ Illuminate\Support\Js::from($menu) }});
			secciones.forEach(s => {
				if (this.estado[s] === undefined) {
					this.estado[s] = s === '{{ $activeSection }}';
				}
			});
		},
		toggle(seccion) {
			this.estado[seccion] = !this.estado[seccion];
			localStorage.setItem('sidebar_estado_scl', JSON.stringify(this.estado));
		},
		toggleSidebar() {
			this.sidebarColapsado = !this.sidebarColapsado;
			localStorage.setItem('sidebar_colapsado', this.sidebarColapsado ? '1' : '0');
		}
	}">

		{{-- Backdrop móvil --}}
		<template x-teleport="body">
			<div x-show="sidebarOpen" class="fixed inset-0 bg-black/40 z-40 lg:hidden" @@click="sidebarOpen = false"></div>
		</template>

		{{-- Sidebar --}}
		<aside
			class="bg-white border-r border-gray-200 flex flex-col shrink-0 fixed lg:static inset-y-0 left-0 z-50 transform transition-all duration-200 ease-in-out lg:translate-x-0 w-60"
			:class="[
				sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
				sidebarColapsado ? 'lg:w-16' : 'lg:w-60'
			]"
		>
			<a href="{{ route('dashboard') }}" class="flex items-center h-12 px-4 hover:bg-gray-50 transition" :class="sidebarColapsado ? 'lg:justify-center lg:px-0' : ''">
				<div class="min-w-0">
					<h1 class="text-sm font-bold text-gray-800 leading-tight" x-show="!sidebarColapsado">SCL</h1>
					<h1 class="text-sm font-bold text-gray-800 leading-tight" x-show="sidebarColapsado" x-cloak>SC</h1>
					<p class="text-[10px] text-gray-400 leading-tight" x-show="!sidebarColapsado">Sistema de Cálculos Laborales</p>
				</div>
			</a>

			<nav class="flex-1 overflow-y-auto p-2 text-sm">
				@include('partials.menu-sidebar')
			</nav>
		</aside>

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
