@php
	$currentRoute = request()->route()?->getName() ?? '';
@endphp

@foreach (config('menu') as $section => $seccion)
	<button @click="toggle('{{ $section }}')" x-show="!sidebarColapsado" class="w-full flex items-center justify-between px-3 pt-3 pb-1 text-[10px] font-semibold uppercase text-gray-400 tracking-wider hover:text-gray-500">
		<span>{{ $seccion['titulo'] }}</span>
		<span x-text="estado.{{ $section }} ? '▾' : '▸'" class="text-xs"></span>
	</button>
	<div x-show="sidebarColapsado || estado.{{ $section }}" class="space-y-0.5">
		@foreach ($seccion['opciones'] as $op)
			@php
				$activo = false;
				foreach ($op['activo'] ?? [] as $prefijo) {
					if (str_starts_with($currentRoute, $prefijo)) {
						$activo = true;
						break;
					}
				}
				foreach ($op['excluir'] ?? [] as $excl) {
					if (str_contains($currentRoute, $excl)) {
						$activo = false;
						break;
					}
				}
			@endphp
			@if (! empty($op['create']))
				<div class="flex items-center transition {{ $activo ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
					<a href="{{ route($op['ruta']) }}" title="{{ $op['label'] }}"
						class="flex-1 flex items-center gap-2 px-3 py-1.5"
						:class="sidebarColapsado ? 'lg:justify-center lg:px-0' : ''">
						<span x-show="sidebarColapsado" x-cloak>@include('partials.icono', ['icono' => $op['icono'], 'class' => 'w-5 h-5'])</span>
						<span x-show="!sidebarColapsado">@include('partials.icono', ['icono' => $op['icono'], 'class' => 'w-3.5 h-3.5'])</span>
						<span x-show="!sidebarColapsado" class="truncate">{{ $op['label'] }}</span>
					</a>
					<a href="{{ route($op['create']) }}" x-show="!sidebarColapsado" class="self-stretch flex items-center px-2 py-1.5 text-gray-400 hover:text-blue-500 hover:bg-blue-50 text-sm leading-tight" title="Agregar {{ $op['label'] }}">+</a>
				</div>
			@else
				<a href="{{ route($op['ruta']) }}" title="{{ $op['label'] }}"
					class="flex items-center gap-2 px-3 py-1.5 transition {{ $activo ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}"
					:class="sidebarColapsado ? 'lg:justify-center lg:px-0' : ''">
					<span x-show="sidebarColapsado" x-cloak>@include('partials.icono', ['icono' => $op['icono'], 'class' => 'w-5 h-5'])</span>
					<span x-show="!sidebarColapsado">@include('partials.icono', ['icono' => $op['icono'], 'class' => 'w-3.5 h-3.5'])</span>
					<span x-show="!sidebarColapsado" class="truncate">{{ $op['label'] }}</span>
				</a>
			@endif
		@endforeach
	</div>
	<div class="border-t border-gray-100 my-2"></div>
@endforeach
