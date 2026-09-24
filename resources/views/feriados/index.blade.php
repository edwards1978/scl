@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex items-center justify-between mb-4">
		<h1 class="text-xl font-semibold">Días Feriados</h1>
		<a href="{{ route('feriados.regenerar') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-100">↻ Regenerar año vigente</a>
	</div>

	{{-- Resumen de materializados --}}
	<fieldset class="bg-white border border-gray-200 p-4 mb-4">
		<legend class="text-sm font-semibold text-gray-700 px-1">Vigencia</legend>
		<p class="text-xs text-gray-500">
			Los feriados fijos se guardan por día y mes. Los variables (Carnaval y Semana Santa) se calculan
			automáticamente desde el Domingo de Pascua y se materializan por fecha cuando se requiere un cálculo.
		</p>
	</fieldset>

	{{-- Tabla de fijos --}}
	<fieldset class="bg-white border border-gray-200 p-4">
		<legend class="text-sm font-semibold text-gray-700 px-1">Feriados fijos (día / mes)</legend>

		@if ($fijos->isEmpty())
			<p class="text-xs text-gray-400 py-2">No hay feriados fijos registrados.</p>
		@else
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-500 uppercase text-xs">
					<tr>
						<th class="text-left px-2 py-1.5">Fecha</th>
						<th class="text-left px-2 py-1.5">Descripción</th>
						<th class="text-center px-2 py-1.5">Activo</th>
						<th class="text-right px-2 py-1.5">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($fijos as $f)
						<tr>
							<td class="px-2 py-1.5 text-xs">{{ str_pad((string) $f->dia, 2, '0', STR_PAD_LEFT) }}/{{ str_pad((string) $f->mes, 2, '0', STR_PAD_LEFT) }}</td>
							<td class="px-2 py-1.5">{{ $f->descripcion }}</td>
							<td class="px-2 py-1.5 text-center">
								<form method="POST" action="{{ route('feriados.update', $f) }}" class="inline">
									@csrf @method('PUT')
									<input type="hidden" name="dia" value="{{ $f->dia }}">
									<input type="hidden" name="mes" value="{{ $f->mes }}">
									<input type="hidden" name="descripcion" value="{{ $f->descripcion }}">
									<input type="hidden" name="activo" value="{{ $f->activo ? '0' : '1' }}">
									<button type="submit" class="text-sm {{ $f->activo ? 'text-green-600' : 'text-gray-400' }} hover:underline">{{ $f->activo ? 'Sí' : 'No' }}</button>
								</form>
							</td>
							<td class="px-2 py-1.5 text-right">
								<form method="POST" action="{{ route('feriados.destroy', $f) }}" onsubmit="return confirm('¿Eliminar este feriado fijo?')" class="inline">
									@csrf @method('DELETE')
									<button type="submit" class="text-xs text-red-500 hover:underline">Eliminar</button>
								</form>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		@endif

		{{-- Formulario nuevo fijo --}}
		<form method="POST" action="{{ route('feriados.store') }}" class="mt-4 pt-3 border-t border-gray-200 flex flex-wrap gap-2 items-end">
			@csrf
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Día</label>
				<input type="number" name="dia" min="1" max="31" required value="{{ old('dia') }}" class="w-20 border border-gray-300 px-2 py-1.5 text-sm">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Mes</label>
				<select name="mes" class="w-28 border border-gray-300 px-2 py-1.5 text-sm bg-white">
					@for ($m = 1; $m <= 12; $m++)
						<option value="{{ $m }}" {{ old('mes') == $m ? 'selected' : '' }}>{{ $m }}</option>
					@endfor
				</select>
			</div>
			<div class="flex-1 min-w-40">
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Descripción</label>
				<input type="text" name="descripcion" required value="{{ old('descripcion') }}" placeholder="Ej. Día de la Virgen del Valle (regional)" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
			</div>
			<button type="submit" class="px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Agregar</button>
		</form>
		@error('dia')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
		@error('mes')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
		@error('descripcion')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
	</fieldset>

	@if ($materializados->isNotEmpty())
		<fieldset class="bg-white border border-gray-200 p-4 mt-4">
			<legend class="text-sm font-semibold text-gray-700 px-1">Últimos materializados</legend>
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-500 uppercase text-xs">
					<tr>
						<th class="text-left px-2 py-1.5">Fecha</th>
						<th class="text-left px-2 py-1.5">Descripción</th>
						<th class="text-center px-2 py-1.5">Origen</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($materializados as $f)
						<tr>
							<td class="px-2 py-1.5 text-xs">{{ $f->fecha->format('d/m/Y') }}</td>
							<td class="px-2 py-1.5">{{ $f->descripcion }}</td>
							<td class="px-2 py-1.5 text-center text-xs">{{ $f->calculado ? 'Variable' : 'Fijo' }}</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</fieldset>
	@endif
</div>
@endsection