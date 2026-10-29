@extends('layouts.app')

@section('content')
<div class="p-4 mx-4 max-w-5xl">
	<div class="flex items-center justify-between mb-3">
		<h1 class="text-xl font-semibold">Vacación #{{ $vacacion->id }} — {{ $empleado->nombre_completo }}</h1>
		<a href="{{ route('vacaciones.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Listado</a>
	</div>

	<div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-4">
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Trabajador</span><span class="block font-medium">{{ $empleado->nombre_completo }} ({{ $empleado->cedula }})</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Patrono</span><span class="block font-medium">{{ $vacacion->patrono->razon_social }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Periodo trabajado</span><span class="block font-medium">{{ $vacacion->periodo_desde->format('d/m/Y') }} — {{ $vacacion->periodo_hasta->format('d/m/Y') }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Año de servicio</span><span class="block font-medium">{{ $vacacion->nro_vacacion }}º</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Periodo disfrute</span><span class="block font-medium">{{ $vacacion->fecha_inicio_real?->format('d/m/Y') ?? '—' }} — {{ $vacacion->fecha_fin_real?->format('d/m/Y') ?? '—' }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Reintegro</span><span class="block font-medium">{{ $vacacion->fecha_reintegro?->format('d/m/Y') ?? '—' }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Sueldo diario</span><span class="block font-medium">{{ $vacacion->moneda === 'USD' ? 'USD' : 'Bs.' }} {{ formato_monto((float) $vacacion->salario_normal / 30) }}</span></div>
		<div class="bg-gray-50 border border-gray-200 px-3 py-2"><span class="text-xs text-gray-500">Moneda</span><span class="block font-medium">{{ $vacacion->moneda }}</span></div>
	</div>

	@if ($vacacion->estatus_calculo !== 'por_aprobar')
		<div class="mb-3 text-xs text-gray-500">Feriados/descanso en el periodo:
			@if ($vacacion->feriados_descanso_en_periodo)
				{{ implode(', ', $vacacion->feriados_descanso_en_periodo) }}
			@else
				Ninguno
			@endif
		</div>
	@endif

	@if ($recibo)
		<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
			<div>
				<h3 class="text-xs font-semibold text-gray-500 uppercase mb-1">Asignaciones</h3>
				<table class="w-full text-xs">
					<tr><th class="text-left py-0.5">Concepto</th><th class="text-right">Días</th><th class="text-right">Importe</th></tr>
					@foreach ($recibo->asignaciones as $c)
						<tr class="border-b"><td class="py-0.5">{{ $c['concepto'] }}</td><td class="text-right py-0.5">{{ $c['dias'] }}</td><td class="text-right py-0.5">{{ formato_monto((float) $c['importe']) }}</td></tr>
					@endforeach
					<tr class="font-semibold"><td colspan="2" class="pt-1">Total</td><td class="text-right pt-1">{{ formato_monto((float) $recibo->total_asignaciones) }}</td></tr>
				</table>
			</div>
			<div>
				<h3 class="text-xs font-semibold text-gray-500 uppercase mb-1">Deducciones</h3>
				<table class="w-full text-xs">
					<tr><th class="text-left py-0.5">Concepto</th><th class="text-right">%</th><th class="text-right">Importe</th></tr>
					@forelse ($recibo->deducciones as $d)
						<tr class="border-b"><td class="py-0.5">{{ $d['concepto'] }}</td><td class="text-right py-0.5">{{ $d['porcentaje'] * 100 }}%</td><td class="text-right py-0.5">{{ formato_monto((float) $d['importe']) }}</td></tr>
					@empty
						<tr><td colspan="3" class="py-1 text-gray-400">Sin deducciones (patrono sin descuentos configurados)</td></tr>
					@endforelse
					<tr class="font-semibold"><td colspan="2" class="pt-1">Total</td><td class="text-right pt-1">{{ formato_monto((float) $recibo->total_deducciones) }}</td></tr>
				</table>
			</div>
		</div>
		<div class="border-t-2 border-gray-800 pt-2 mb-4 flex justify-between">
			<span class="text-gray-500">Neto a pagar</span>
			<span class="text-xl font-bold">{{ $moneda['principal'] }}</span>
		</div>
		@if ($moneda['equivalente'])
			<div class="text-right text-xs text-gray-400 mb-3">{{ $moneda['equivalente'] }}</div>
		@endif
	@else
		<p class="text-xs text-gray-500 mb-4">Aún no hay recibo de pago. Apruebe y pague para generar el recibo.</p>
	@endif

	<div class="flex gap-2 pt-3 border-t border-gray-200">
		@can('aprobar')
		@if ($vacacion->puedeAprobar())
			<form action="{{ route('vacaciones.estatus', $vacacion) }}" method="POST" onsubmit="return confirm('¿Aprobar este cálculo?')">
				@csrf @method('PUT')
				<input type="hidden" name="accion" value="aprobar">
				<button class="px-3 py-1.5 text-sm bg-amber-600 text-white hover:bg-amber-700">✓ Aprobar</button>
			</form>
		@elseif ($vacacion->puedePagar())
			<form action="{{ route('vacaciones.estatus', $vacacion) }}" method="POST" onsubmit="return confirm('¿Registrar el pago y emitir el recibo?')">
				@csrf @method('PUT')
				<input type="hidden" name="accion" value="pagar">
				<button class="px-3 py-1.5 text-sm bg-green-600 text-white hover:bg-green-700">💰 Registrar pago</button>
			</form>
		@elseif ($vacacion->puedeDisfrutar())
			<form action="{{ route('vacaciones.estatus', $vacacion) }}" method="POST" onsubmit="return confirm('¿Marcar este periodo como disfrutado?')">
				@csrf @method('PUT')
				<input type="hidden" name="accion" value="disfrutar">
				<button class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">🏖 Marcar disfrutada</button>
			</form>
		@endif
		@if ($vacacion->puedeAnular())
			<form action="{{ route('vacaciones.estatus', $vacacion) }}" method="POST" onsubmit="return confirm('¿Anular este cálculo?')">
				@csrf @method('PUT')
				<input type="hidden" name="accion" value="anular">
				<button class="px-3 py-1.5 text-sm bg-gray-200 text-gray-600 hover:bg-gray-300">✕ Anular</button>
			</form>
		@endif
		@endcan
		@if (in_array($vacacion->estatus_vacacion, ['pagada', 'disfrutada'], true))
			<a href="{{ route('vacaciones.pdf', $vacacion) }}" target="_blank" rel="noopener" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">📄 Recibo PDF</a>
		@endif
		@if ($vacacion->puedeBorrar())
			@can('eliminar')
			<form action="{{ route('vacaciones.destroy', $vacacion) }}" method="POST" onsubmit="return confirm('¿Eliminar este periodo?')">
				@csrf @method('DELETE')
				<button class="px-3 py-1.5 text-sm bg-red-600 text-white hover:bg-red-700">🗑 Eliminar</button>
			</form>
			@endcan
		@endif
	</div>

	@include('partials.auditoria', ['registro' => $vacacion])
</div>
@endsection
