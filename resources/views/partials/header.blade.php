<header class="sticky top-0 z-30 bg-white border-b border-gray-200">
	<div class="flex items-center justify-between px-4 h-12">
		<div class="flex items-center gap-2">
			@if (request()->routeIs('dashboard'))
				<a href="{{ route('dashboard') }}" class="p-1.5 hover:bg-gray-100 text-gray-600" aria-label="Inicio">
					<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
				</a>
			@else
				<button @@click="sidebarOpen = !sidebarOpen" class="lg:hidden p-1.5 hover:bg-gray-100 text-gray-600" aria-label="Menú">
					<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
					</svg>
				</button>
				<button @@click="toggleSidebar()" class="hidden lg:inline-flex p-1.5 hover:bg-gray-100 text-gray-600" aria-label="Contraer/Expandir menú" title="Contraer/Expandir menú">
					<svg x-show="!sidebarColapsado" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
					</svg>
					<svg x-show="sidebarColapsado" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
					</svg>
				</button>
			@endif
		</div>

		<div class="flex items-center gap-3">
			{{-- Patrono seleccionado --}}
			@if (session('patrono_id'))
				<div class="flex items-center gap-2 text-xs text-gray-600">
					<span class="font-medium text-gray-800">{{ session('nombre_patrono') }}</span>
					<a href="{{ route('entorno.seleccionar') }}" class="text-gray-400 hover:text-blue-500 text-sm px-1" title="Seleccionar patrono">
						<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
						</svg>
					</a>
				</div>
			@else
				<a href="{{ route('entorno.seleccionar') }}" class="px-2 py-1 bg-blue-600 text-white hover:bg-blue-700 text-[10px] font-medium leading-none">
					Seleccionar patrono
				</a>
			@endif

			{{-- Usuario y Cierre de Sesión --}}
			@if ($currentUser)
				<div class="flex items-center gap-2 border-l border-gray-200 pl-3">
					<span class="text-xs text-gray-500 hidden sm:inline">{{ $currentUser->name }}</span>
					<form method="POST" action="{{ route('logout') }}">
						@csrf
						<button type="submit" class="text-xs px-2 py-1 bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-red-600 transition">
							Cerrar Sesión
						</button>
					</form>
				</div>
			@endif
		</div>
	</div>
</header>
