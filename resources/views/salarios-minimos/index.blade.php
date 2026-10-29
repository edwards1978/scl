@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-5xl">
	<div class="flex items-center justify-between mb-4">
		<h1 class="text-xl font-semibold">Salarios Mínimos (Venezuela)</h1>
	</div>
	<p class="text-sm text-gray-600 mb-4">
		Se usa el registro más reciente cuya vigencia sea igual o anterior a la fecha del cálculo
		(hasta que entre en vigor una fila posterior). Los montos se guardan en Bs reexpresados
		para los cálculos; el valor histórico original se conserva en <em>nota</em>.
	</p>

	<fieldset class="bg-white border border-gray-200 p-4 mb-4">
		<legend class="text-sm font-semibold text-gray-700 px-1">Registrar / editar salario mínimo</legend>
		<form method="POST" action="{{ route('salarios-minimos.store') }}"
			class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
			@csrf
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Vigente desde</label>
				<input type="date" name="fecha_vigencia" required value="{{ old('fecha_vigencia') }}"
					class="w-full border border-gray-300 px-2 py-1.5">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Gaceta oficial</label>
				<input type="text" name="gaceta_oficial" required maxlength="50" value="{{ old('gaceta_oficial') }}"
					placeholder="N° 6.691 Ext." class="w-full border border-gray-300 px-2 py-1.5">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Mensual Bs (reexpresado)</label>
				<input type="number" name="mensual_bs" step="0.00000001" min="0.00000001" required
					value="{{ old('mensual_bs') }}" placeholder="130.00"
					class="w-full border border-gray-300 px-2 py-1.5">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Diario Bs</label>
				<input type="number" name="diario_bs" step="0.00000001" min="0" required
					value="{{ old('diario_bs') }}" placeholder="4.33"
					class="w-full border border-gray-300 px-2 py-1.5">
			</div>
			<div class="md:col-span-1">
				<button class="w-full px-3 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">+ Guardar</button>
			</div>
			<div class="md:col-span-5">
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Nota (valor original, opcional)</label>
				<input type="text" name="nota" maxlength="255" value="{{ old('nota') }}"
					placeholder="Ej. Bs. 400.000,00 (Bs. 0,40 Reexpresados)"
					class="w-full border border-gray-300 px-2 py-1.5">
			</div>
		</form>
		@error('fecha_vigencia')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
		@error('gaceta_oficial')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
		@error('mensual_bs')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
		@error('diario_bs')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
		@error('nota')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
	</fieldset>

	<fieldset class="bg-white border border-gray-200">
		<legend class="text-sm font-semibold text-gray-700 px-1">Histórico</legend>
		<div class="mb-1 flex justify-end">
			<x-orden-indicador campo="Vigencia" direccion="desc" />
		</div>
		@if ($salarios->isEmpty())
			<p class="text-xs text-gray-400 py-2 px-2">No hay salarios mínimos registrados.</p>
		@else
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-500 uppercase text-xs">
					<tr>
						<th class="text-left px-2 py-1.5">Vigencia</th>
						<th class="text-left px-2 py-1.5">Gaceta</th>
						<th class="text-right px-2 py-1.5">Mensual Bs</th>
						<th class="text-right px-2 py-1.5">Diario Bs</th>
						<th class="text-left px-2 py-1.5">Nota</th>
						<th class="text-right px-2 py-1.5">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					@foreach ($salarios as $s)
						<tr>
							<td class="px-2 py-1.5">{{ $s->fecha_vigencia->format('d/m/Y') }}</td>
							<td class="px-2 py-1.5">{{ $s->gaceta_oficial }}</td>
							<td class="px-2 py-1.5 text-right">{{ formato_monto((float) $s->mensual_bs, 8) }}</td>
							<td class="px-2 py-1.5 text-right">{{ formato_monto((float) $s->diario_bs, 8) }}</td>
							<td class="px-2 py-1.5 text-xs text-gray-500">{{ $s->nota }}</td>
							<td class="px-2 py-1.5 text-right">
								<form method="POST" action="{{ route('salarios-minimos.destroy', $s) }}"
									onsubmit="return confirm('¿Eliminar este salario mínimo?')" class="inline">
									@csrf @method('DELETE')
									<button type="submit" class="text-xs text-red-500 hover:underline">Eliminar</button>
								</form>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		@endif
	</fieldset>
</div>
@endsection