@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-2xl">
	<h1 class="text-xl font-semibold mb-1">⚙️ Configuración</h1>
	<p class="text-sm text-gray-600 mb-4">
		Aplicada al patrono: <span class="font-medium">{{ $patrono->razon_social }}</span>
	</p>

	<form method="POST" action="{{ route('configuracion.update') }}" class="space-y-4">
		@csrf @method('PUT')

		<fieldset class="bg-white border border-gray-200 p-5">
			<legend class="text-sm font-semibold text-gray-700 px-1">Utilidades y deducciones legales</legend>
			<div class="space-y-3 mt-2">
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Días de bonificación de fin de año</label>
					<input type="number" name="dias_bonificacion" min="1" max="120" value="{{ old('dias_bonificacion', $patrono->dias_bonificacion) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					<p class="text-xs text-gray-400 mt-0.5">Base del bono (mínimo 30, máximo 120). Por defecto 30.</p>
				</div>
				<div class="space-y-2">
					<label class="flex items-center gap-2 text-sm">
						<input type="checkbox" name="descuenta_ivss" value="1" {{ $patrono->descuenta_ivss ? 'checked' : '' }} class="h-4 w-4 text-blue-600 border-gray-300">
						IVSS (4%)
					</label>
					<label class="flex items-center gap-2 text-sm">
						<input type="checkbox" name="descuenta_paro_fijo" value="1" {{ $patrono->descuenta_paro_fijo ? 'checked' : '' }} class="h-4 w-4 text-blue-600 border-gray-300">
						Régimen de Paro (0.5%)
					</label>
					<label class="flex items-center gap-2 text-sm">
						<input type="checkbox" name="descuenta_faov" value="1" {{ $patrono->descuenta_faov ? 'checked' : '' }} class="h-4 w-4 text-blue-600 border-gray-300">
						FAOV (1%)
					</label>
					<label class="flex items-center gap-2 text-sm">
						<input type="checkbox" name="descuenta_inces" value="1" {{ $patrono->descuenta_inces ? 'checked' : '' }} class="h-4 w-4 text-blue-600 border-gray-300">
						INCES (0.5% en bonificación de fin de año, si ocupa 5+ trabajadores)
					</label>
				</div>
			</div>
		</fieldset>

		<fieldset class="bg-white border border-gray-200 p-5">
			<legend class="text-sm font-semibold text-gray-700 px-1">Recibos en dólares</legend>
			<div class="space-y-2 mt-2">
				<label class="flex items-center gap-2 text-sm">
					<input type="checkbox" name="mostrar_equivalencia_usd" value="1" {{ $patrono->mostrar_equivalencia_usd ? 'checked' : '' }} class="h-4 w-4 text-blue-600 border-gray-300">
					Mostrar equivalencia en Bs. en los recibos emitidos en USD
				</label>
			</div>
		</fieldset>

		<fieldset class="bg-white border border-gray-200 p-5">
			<legend class="text-sm font-semibold text-gray-700 px-1">Prestaciones sociales: depósito y anticipos</legend>
			<div class="space-y-3 mt-2">
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Sistema de depósito de prestaciones</label>
					<select name="deposito_tipo" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
						<option value="contabilidad" {{ $patrono->deposito_tipo === 'contabilidad' ? 'selected' : '' }}>Contabilidad</option>
						<option value="fideicomiso" {{ $patrono->deposito_tipo === 'fideicomiso' ? 'selected' : '' }}>Fideicomiso</option>
						<option value="fondo" {{ $patrono->deposito_tipo === 'fondo' ? 'selected' : '' }}>Fondo</option>
					</select>
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Anticipos máximos por año</label>
					<input type="number" name="anticipos_max_anio" min="1" max="24" value="{{ old('anticipos_max_anio', $patrono->anticipos_max_anio) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<div class="grid grid-cols-3 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">% Contabilidad</label>
						<input type="number" name="anticipos_pct_contabilidad" min="1" max="100" value="{{ old('anticipos_pct_contabilidad', $patrono->anticipos_pct_contabilidad) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">% Fideicomiso</label>
						<input type="number" name="anticipos_pct_fideicomiso" min="1" max="100" value="{{ old('anticipos_pct_fideicomiso', $patrono->anticipos_pct_fideicomiso) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">% Fondo</label>
						<input type="number" name="anticipos_pct_fondo" min="1" max="100" value="{{ old('anticipos_pct_fondo', $patrono->anticipos_pct_fondo) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
				</div>
				<p class="text-xs text-gray-400">Porcentaje del saldo de prestaciones que puede solicitarse como anticipo según el sistema de depósito.</p>
			</div>
		</fieldset>

		<div class="flex justify-end gap-2">
			<a href="{{ route('dashboard') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
			<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Guardar Configuración</button>
		</div>
	</form>
</div>
@endsection
