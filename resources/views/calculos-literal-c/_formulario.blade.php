@php
	$esEdicion = isset($calculo);
	$valor = fn (string $campo, mixed $default = null) => old($campo, $esEdicion ? $calculo->{$campo} : $default);
	$fechaIngreso = $esEdicion && $calculo->fecha_ingreso ? $calculo->fecha_ingreso->format('Y-m-d') : null;
	$fechaEgreso = $esEdicion && $calculo->fecha_egreso ? $calculo->fecha_egreso->format('Y-m-d') : null;
@endphp

<form method="POST" action="{{ $esEdicion ? route('calculos-literal-c.update', $calculo) : route('calculos-literal-c.store') }}" class="space-y-4">
	@csrf
	@if ($esEdicion)
		@method('PUT')
	@endif

	<fieldset class="bg-white border border-gray-200 p-5">
		<legend class="text-sm font-semibold text-gray-700 px-1">Datos del patrono y del trabajador</legend>
		<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Patrono *</label>
				<input type="text" name="patrono_nombre" value="{{ $valor('patrono_nombre') }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				@error('patrono_nombre') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">RIF del patrono (opcional)</label>
				<input type="text" name="patrono_rif" value="{{ $valor('patrono_rif') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Trabajador *</label>
				<input type="text" name="trabajador_nombre" value="{{ $valor('trabajador_nombre') }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				@error('trabajador_nombre') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Cédula (opcional)</label>
				<input type="text" name="trabajador_cedula" value="{{ $valor('trabajador_cedula') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Cargo (opcional)</label>
				<input type="text" name="cargo" value="{{ $valor('cargo') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
			</div>
		</div>
	</fieldset>

	<fieldset class="bg-white border border-gray-200 p-5">
		<legend class="text-sm font-semibold text-gray-700 px-1">Fechas y salario</legend>
		<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-2">
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha de ingreso *</label>
				<input type="date" name="fecha_ingreso" value="{{ old('fecha_ingreso', $fechaIngreso) }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				@error('fecha_ingreso') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha de egreso *</label>
				<input type="date" name="fecha_egreso" value="{{ old('fecha_egreso', $fechaEgreso) }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				@error('fecha_egreso') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Tipo de salario *</label>
				<select name="tipo_salario" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
					@foreach (\App\Models\CalculoLiquidacionLiteralC::tiposSalario() as $value => $label)
						<option value="{{ $value }}" {{ $valor('tipo_salario', 'mensual') === $value ? 'selected' : '' }}>{{ $label }}</option>
					@endforeach
				</select>
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Salario *</label>
				<input type="text" inputmode="decimal" name="salario" value="{{ $valor('salario') }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				@error('salario') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Moneda *</label>
				<select name="moneda" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
					@foreach (\App\Models\CalculoLiquidacionLiteralC::monedas() as $value => $label)
						<option value="{{ $value }}" {{ $valor('moneda', 'USD') === $value ? 'selected' : '' }}>{{ $label }}</option>
					@endforeach
				</select>
			</div>
			<div class="md:col-span-3">
				<label class="flex items-center gap-2 text-sm text-gray-700">
					<input type="checkbox" name="mostrar_equivalencia" value="1" {{ old('mostrar_equivalencia', $esEdicion ? $calculo->mostrar_equivalencia : false) ? 'checked' : '' }} class="h-4 w-4 text-blue-600 border-gray-300">
					Mostrar en el recibo la equivalencia informativa en la otra moneda
				</label>
				<p class="text-[11px] text-gray-500 mt-0.5">Usa la tasa BCV cargada; no convierte el monto que se paga.</p>
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Días de utilidades *</label>
				<input type="number" name="dias_utilidades" min="30" max="120" value="{{ $valor('dias_utilidades', 30) }}" required class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				<p class="text-[11px] text-gray-500 mt-0.5">Entre 30 y 120 días.</p>
				@error('dias_utilidades') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
		</div>
	</fieldset>

	<fieldset class="bg-white border border-gray-200 p-5">
		<legend class="text-sm font-semibold text-gray-700 px-1">Vacaciones y utilidades vencidas</legend>
		<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Años de vacaciones vencidas</label>
				<input type="number" name="anios_vacaciones_vencidas" min="0" value="{{ $valor('anios_vacaciones_vencidas', 0) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				<p class="text-[11px] text-gray-500 mt-0.5">El bono vencido usa los mismos días.</p>
				@error('anios_vacaciones_vencidas') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Años de utilidades vencidas</label>
				<input type="number" name="anios_utilidades_vencidas" min="0" value="{{ $valor('anios_utilidades_vencidas', 0) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				<p class="text-[11px] text-gray-500 mt-0.5">No pueden superar los años efectivos de servicio.</p>
				@error('anios_utilidades_vencidas') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
		</div>
	</fieldset>

	<fieldset class="bg-white border border-gray-200 p-5">
		<legend class="text-sm font-semibold text-gray-700 px-1">Deducciones manuales</legend>
		<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-2">
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Anticipo de prestaciones</label>
				<input type="text" inputmode="decimal" name="anticipo" value="{{ $valor('anticipo', '0.00') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Intereses pagados</label>
				<input type="text" inputmode="decimal" name="intereses_pagados" value="{{ $valor('intereses_pagados', '0.00') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Saldo de préstamos personales</label>
				<input type="text" inputmode="decimal" name="prestamo_saldo" value="{{ $valor('prestamo_saldo', '0.00') }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
			</div>
		</div>
	</fieldset>

	<div class="flex justify-end gap-2">
		<a href="{{ $esEdicion ? route('calculos-literal-c.show', $calculo) : route('calculos-literal-c.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
		<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">{{ $esEdicion ? 'Guardar cambios' : 'Guardar cálculo' }}</button>
	</div>
</form>
