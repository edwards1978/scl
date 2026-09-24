@extends('layouts.app')

@section('content')
@php
	$moneda = $empleado->moneda;
@endphp
<div class="p-4 mx-auto max-w-6xl">
	<div class="flex items-center justify-between mb-1">
		<h1 class="text-xl font-semibold">Estado de Cuenta de Prestaciones Sociales</h1>
		<a href="{{ route('prestaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		{{ $empleado->nombre_completo }} · C.I. {{ $empleado->cedula }} · Ingreso {{ $empleado->fecha_ingreso?->format('d/m/Y') }}
	</p>

	<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Saldo actual</p>
			<p class="text-base font-semibold text-blue-700">{{ $moneda }} {{ formato_monto($saldoActual) }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Depósitos trimestrales</p>
			<p class="text-base font-semibold">{{ $moneda }} {{ formato_monto($totalDepositos) }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Días adicionales</p>
			<p class="text-base font-semibold">{{ $moneda }} {{ formato_monto($totalAdicionales) }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Intereses</p>
			<p class="text-base font-semibold">{{ $moneda }} {{ formato_monto($totalIntereses) }}</p>
		</div>
		<div class="bg-white border border-gray-200 p-3">
			<p class="text-xs text-gray-500 mb-1">Anticipos</p>
			<p class="text-base font-semibold text-red-600">{{ $moneda }} {{ formato_monto($totalAnticipos) }}</p>
		</div>
	</div>

	<div class="bg-white border border-gray-200 overflow-x-auto mb-4">
		<table class="w-full text-sm">
			<thead class="bg-gray-50 text-gray-600 text-xs uppercase">
				<tr>
					<th class="text-center px-3 py-2 w-12">Nº</th>
					<th class="text-left px-3 py-2">Periodo</th>
					<th class="text-right px-3 py-2">Sal. integral</th>
					<th class="text-right px-3 py-2">Saldo inicial</th>
					<th class="text-right px-3 py-2">Depósito trim.</th>
					<th class="text-right px-3 py-2">Días adic.</th>
					<th class="text-right px-3 py-2">Interés</th>
					<th class="text-right px-3 py-2">Anticipos</th>
					<th class="text-right px-3 py-2">Saldo final</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				@forelse ($cuenta as $c)
					<tr class="hover:bg-gray-50">
						<td class="px-3 py-2 text-center text-gray-500">{{ $loop->index }}</td>
						<td class="px-3 py-2">{{ \Carbon\Carbon::createFromFormat('Y-m', $c->periodo)->format('m/Y') }}</td>
						<td class="px-3 py-2 text-right text-gray-600">{{ formato_monto((float) $c->salario_integral_diario) }}</td>
						<td class="px-3 py-2 text-right">{{ formato_monto((float) $c->saldo_inicial) }}</td>
						<td class="px-3 py-2 text-right">{{ $c->deposito_trimestre > 0 ? $c->dias_deposito.' d · '.formato_monto((float) $c->deposito_trimestre) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ $c->dias_adicionales > 0 ? $c->dias_adicionales_cont.' d · '.formato_monto((float) $c->dias_adicionales) : '—' }}</td>
						<td class="px-3 py-2 text-right">{{ $c->interes_mes > 0 ? formato_monto((float) $c->interes_mes) : '—' }}</td>
						<td class="px-3 py-2 text-right text-red-600">{{ $c->anticipos > 0 ? '- '.formato_monto((float) $c->anticipos) : '—' }}</td>
						<td class="px-3 py-2 text-right font-medium">{{ formato_monto((float) $c->saldo_final) }}</td>
					</tr>
				@empty
					<tr><td colspan="9" class="text-center py-8 text-gray-400">Sin periodos calculados.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>

	<div class="flex justify-end gap-2">
		<form method="POST" action="{{ route('prestaciones.recalcular', $empleado->id) }}" onsubmit="return confirm('¿Recalcular el estado de cuenta completo?')">
			@csrf
			<button class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">↻ Recalcular</button>
		</form>
		<a href="{{ route('prestaciones.pdf', $empleado->id) }}" target="_blank" rel="noopener" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Descargar PDF</a>
	</div>
</div>
@endsection
