@extends('layouts.app')

@section('content')
<div class="p-4 mx-4 max-w-5xl">
	<div class="flex items-center justify-between mb-3">
		<h1 class="text-xl font-semibold">Bonificación de fin de año — {{ $empleado->nombre_completo }}</h1>
		<a href="{{ route('utilidades.index', ['anio' => $bonificacion->anio]) }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Listado</a>
	</div>

	<div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-4">
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Trabajador</span><span class="block font-medium">{{ $empleado->nombre_completo }} ({{ $empleado->cedula }})</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Año</span><span class="block font-medium">{{ $bonificacion->anio }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Periodo</span><span class="block font-medium">{{ $bonificacion->periodo_inicio->format('d/m/Y') }} — {{ $bonificacion->periodo_fin->format('d/m/Y') }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Meses laborados</span><span class="block font-medium">{{ $bonificacion->meses_laborados }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Días a pagar</span><span class="block font-medium">{{ $bonificacion->dias_a_pagar }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Moneda</span><span class="block font-medium">{{ $bonificacion->moneda }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Salario diario</span><span class="block font-medium">{{ $bonificacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) ($bonificacion->moneda === 'USD' ? $bonificacion->salario_diario_usd : $bonificacion->salario_diario_bs)) }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Sueldo mensual ({{ $bonificacion->moneda }})</span><span class="block font-medium">{{ $bonificacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) ($bonificacion->moneda === 'USD' ? $bonificacion->salario_mensual_usd : $bonificacion->salario_mensual_bs)) }}</span></div>
	</div>

	<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
		<div>
			<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Asignaciones</h3>
			<table class="w-full text-xs">
				<tr class="bg-gray-50 text-gray-600"><th class="text-left px-2 py-1.5">Concepto</th><th class="text-right px-2 py-1.5">Días</th><th class="text-right px-2 py-1.5">Importe</th></tr>
				@foreach ($bonificacion->asignaciones as $c)
					<tr class="border-b"><td class="px-2 py-1">{{ $c['concepto'] }}</td>
						<td class="text-right px-2 py-1">{{ $c['dias'] }}</td>
						<td class="text-right px-2 py-1">{{ formato_monto((float) $c['importe']) }}</td></tr>
				@endforeach
				<tr class="font-semibold"><td colspan="2" class="pt-1">Total</td><td class="text-right">{{ formato_monto((float) $bonificacion->total_asignaciones) }}</td></tr>
			</table>
		</div>
		<div>
			<h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Deducciones</h3>
			<table class="w-full text-xs">
				<tr class="bg-gray-50 text-gray-600"><th class="text-left px-2 py-1.5">Concepto</th><th class="text-right px-2 py-1.5">%</th><th class="text-right px-2 py-1.5">Importe</th></tr>
				@forelse ($bonificacion->deducciones as $d)
					<tr class="border-b"><td class="px-2 py-1">{{ $d['concepto'] }}</td>
						<td class="text-right px-2 py-1">{{ $d['porcentaje'] * 100 }}%</td>
						<td class="text-right px-2 py-1">{{ formato_monto((float) $d['importe']) }}</td></tr>
				@empty
					<tr><td colspan="3" class="py-1 text-gray-400">Sin deducciones</td></tr>
				@endforelse
				<tr class="font-semibold"><td colspan="2" class="pt-1">Total</td><td class="text-right">{{ formato_monto((float) $bonificacion->total_deducciones) }}</td></tr>
			</table>
		</div>
	</div>

	<div class="border-t-2 border-gray-800 pt-2 mb-4 flex justify-between items-center">
		<span>Neto a pagar</span>
		<span class="text-xl font-bold">{{ $moneda['principal'] }}</span>
	</div>
	@if ($moneda['equivalente'])
		<div class="text-right text-xs text-gray-400 mb-3">{{ $moneda['equivalente'] }}</div>
	@endif

	<div class="flex justify-end gap-2">
		<a href="{{ route('utilidades.pdf', $bonificacion->id) }}" target="_blank" rel="noopener" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Descargar PDF</a>
	</div>

	@include('partials.auditoria', ['registro' => $bonificacion])
</div>
@endsection