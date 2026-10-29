@props(['campo', 'direccion' => 'asc'])

@php
	$flecha = $direccion === 'desc' ? '↓' : '↑';
	$sentido = $direccion === 'desc' ? 'descendente' : 'ascendente';
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-[11px] text-gray-500']) }}>
	<span class="uppercase tracking-wide text-gray-400">Ordenado por:</span>
	<span class="inline-flex items-center gap-1 bg-gray-100 px-1.5 py-0.5 text-gray-600" title="Orden {{ $sentido }}">
		{{ $campo }}
		<span aria-hidden="true">{{ $flecha }}</span>
		<span class="sr-only">({{ $sentido }})</span>
	</span>
</div>
