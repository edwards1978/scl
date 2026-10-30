@extends('layouts.app')

@section('content')
@php
	$asignaciones = collect($calculo->conceptos ?? [])->where('tipo', 'asignacion');
	$deducciones = collect($calculo->conceptos ?? [])->where('tipo', 'deduccion');
@endphp
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
		<div>
			<h1 class="text-xl font-semibold">Cálculo de liquidación Art. 142.c</h1>
			<p class="text-sm text-gray-500">Cálculo independiente. No genera renglones en prestaciones sociales.</p>
		</div>
		<div class="flex gap-2">
			<a href="{{ route('calculos-literal-c.pdf', $calculo) }}" target="_blank" rel="noopener" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Recibo PDF</a>
			@if (auth()->user()->puedeGestionar())
				<a href="{{ route('calculos-literal-c.edit', $calculo) }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Editar</a>
				<form method="POST" action="{{ route('calculos-literal-c.duplicar', $calculo) }}">
					@csrf
					<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Duplicar</button>
				</form>
				<form method="POST" action="{{ route('calculos-literal-c.destroy', $calculo) }}" onsubmit="return confirm('¿Eliminar definitivamente este cálculo?')">
					@csrf
					@method('DELETE')
					<button class="px-3 py-1.5 text-sm border border-red-300 text-red-600 hover:bg-red-50">Eliminar</button>
				</form>
			@endif
			<a href="{{ route('calculos-literal-c.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
		</div>
	</div>


	<div class="bg-white border border-gray-200 p-4 mb-4">
		<div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
			<div><span class="text-gray-500">Patrono:</span> <strong>{{ $calculo->patrono_nombre }}</strong></div>
			<div><span class="text-gray-500">RIF:</span> {{ $calculo->patrono_rif ?: '—' }}</div>
			<div><span class="text-gray-500">Trabajador:</span> <strong>{{ $calculo->trabajador_nombre }}</strong></div>
			<div><span class="text-gray-500">Cédula:</span> {{ $calculo->trabajador_cedula ?: '—' }}</div>
			<div><span class="text-gray-500">Cargo:</span> {{ $calculo->cargo ?: '—' }}</div>
			<div><span class="text-gray-500">Ingreso:</span> {{ $calculo->fecha_ingreso->format('d/m/Y') }}</div>
			<div><span class="text-gray-500">Egreso:</span> {{ $calculo->fecha_egreso->format('d/m/Y') }}</div>
			<div><span class="text-gray-500">Tipo de salario:</span> {{ $calculo->labelTipoSalario() }}</div>
			<div><span class="text-gray-500">Salario:</span> {{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->salario) }}</div>
			<div><span class="text-gray-500">Días de utilidades:</span> {{ $calculo->dias_utilidades }}</div>
			<div><span class="text-gray-500">Salario diario normal:</span> {{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->salario_diario_normal) }}</div>
			<div><span class="text-gray-500">Salario diario integral:</span> {{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->salario_diario_integral) }}</div>
		</div>
	</div>

	<div class="bg-white border border-gray-200 overflow-x-auto">
		<table class="min-w-full text-sm">
			<thead class="bg-gray-50 text-xs uppercase text-gray-500">
				<tr>
					<th class="px-3 py-2 text-left">Concepto</th>
					<th class="px-3 py-2 text-right">Días</th>
					<th class="px-3 py-2 text-right">Salario diario</th>
					<th class="px-3 py-2 text-right">Importe</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@foreach ($asignaciones as $concepto)
					<tr>
						<td class="px-3 py-2">
							{{ $concepto['concepto'] }}
							<span class="block text-xs text-gray-500">{{ $concepto['base_legal'] }}</span>
						</td>
						<td class="px-3 py-2 text-right">{{ $concepto['dias'] !== null ? $concepto['dias'] : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ $concepto['salario_diario'] !== null ? formato_monto((float) $concepto['salario_diario']) : '—' }}</td>
						<td class="px-3 py-2 text-right font-medium">{{ formato_monto((float) $concepto['importe']) }}</td>
					</tr>
				@endforeach
				<tr class="bg-gray-50 font-semibold">
					<td colspan="3" class="px-3 py-2 text-right">Total asignaciones</td>
					<td class="px-3 py-2 text-right">{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->total_asignaciones) }}</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="bg-white border border-gray-200 overflow-x-auto mt-4">
		<table class="min-w-full text-sm">
			<thead class="bg-gray-50 text-xs uppercase text-gray-500">
				<tr><th class="px-3 py-2 text-left">Deducción</th><th class="px-3 py-2 text-right">Importe</th></tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@foreach ($deducciones as $deduccion)
					<tr>
						<td class="px-3 py-2">{{ $deduccion['concepto'] }} <span class="text-xs text-gray-500">({{ $deduccion['base_legal'] }})</span></td>
						<td class="px-3 py-2 text-right">-{{ formato_monto((float) $deduccion['importe']) }}</td>
					</tr>
				@endforeach
				<tr class="bg-gray-50 font-semibold">
					<td class="px-3 py-2 text-right">Total deducciones</td>
					<td class="px-3 py-2 text-right">-{{ formato_monto((float) $calculo->total_deducciones) }}</td>
				</tr>
				<tr class="bg-blue-50 font-bold text-blue-900">
					<td class="px-3 py-2 text-right">Neto a pagar</td>
					<td class="px-3 py-2 text-right">{{ $calculo->labelMoneda() }} {{ formato_monto((float) $calculo->neto) }}</td>
				</tr>
			</tbody>
		</table>
	</div>

	@if ($equivalencia)
		<p class="mt-3 text-xs text-gray-500">
			Equivalencia informativa: {{ $equivalencia['moneda'] === 'USD' ? 'USD' : 'Bs.' }}
			{{ formato_monto($equivalencia['monto']) }} a la tasa BCV {{ formato_monto((float) $calculo->tasa_bcv) }}.
		</p>
	@endif

	<div class="mt-4 text-xs text-gray-500">
		Creado por {{ $calculo->creador?->name ?? '—' }} el {{ $calculo->created_at?->format('d/m/Y H:i') }}.
		@if ($calculo->editor)
			Actualizado por {{ $calculo->editor->name }} el {{ $calculo->updated_at?->format('d/m/Y H:i') }}.
		@endif
	</div>
</div>
@endsection
