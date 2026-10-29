@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-2xl">
	<h1 class="text-xl font-semibold mb-4">Editar Empleado</h1>

	<form method="POST" action="{{ route('empleados.update', $empleado) }}" class="space-y-4">
		@csrf @method('PUT')

		<fieldset class="bg-white border border-gray-200 p-5">
			<legend class="text-sm font-semibold text-gray-700 px-1">Datos Personales</legend>
			<div class="space-y-3 mt-2">
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Nombres</label>
						<input type="text" name="nombres" value="{{ old('nombres', $empleado->nombres) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						@error('nombres') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Apellidos</label>
						<input type="text" name="apellidos" value="{{ old('apellidos', $empleado->apellidos) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						@error('apellidos') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Cédula</label>
					<input type="text" name="cedula" value="{{ old('cedula', $empleado->cedula) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					@error('cedula') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
				</div>
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha de Nacimiento <span class="text-gray-400">(opcional)</span></label>
						<input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', optional($empleado->fecha_nacimiento)->format('Y-m-d')) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Género</label>
						<select name="genero" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="">Seleccione...</option>
							<option value="M" {{ old('genero', $empleado->genero) === 'M' ? 'selected' : '' }}>Masculino</option>
							<option value="F" {{ old('genero', $empleado->genero) === 'F' ? 'selected' : '' }}>Femenino</option>
						</select>
					</div>
				</div>
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Teléfono <span class="text-gray-400">(opcional)</span></label>
						<input type="text" name="telefono" value="{{ old('telefono', $empleado->telefono) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Correo <span class="text-gray-400">(opcional)</span></label>
						<input type="email" name="correo" value="{{ old('correo', $empleado->correo) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Dirección <span class="text-gray-400">(opcional)</span></label>
					<textarea name="direccion" rows="2" class="w-full border border-gray-300 px-2 py-1.5 text-sm">{{ old('direccion', $empleado->direccion) }}</textarea>
				</div>
			</div>
		</fieldset>

		<fieldset class="bg-white border border-gray-200 p-5">
			<legend class="text-sm font-semibold text-gray-700 px-1">Datos Laborales</legend>
			<div class="space-y-3 mt-2">
			<div class="bg-blue-50 border border-blue-200 px-3 py-2">
				<label class="block text-xs font-medium text-blue-700 mb-0.5">Patrono</label>
				<p class="text-sm font-semibold text-blue-800">
					{{ $patrono->razon_social }} ({{ $patrono->rif }})
					<button type="button" onclick="document.getElementById('modal-cambiar-patrono').showModal()" class="ml-2 text-xs font-medium text-blue-600 underline">Cambiar</button>
				</p>
			</div>
				<div class="grid grid-cols-3 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha de Ingreso</label>
						<input type="date" name="fecha_ingreso" value="{{ old('fecha_ingreso', $empleado->fecha_ingreso->format('Y-m-d')) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						@error('fecha_ingreso') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Salario Base mensual ({{ old('moneda', $empleado->moneda) === 'USD' ? 'USD' : 'Bs.' }})</label>
						<input type="text" name="salario_base" value="{{ old('salario_base', number_format($empleado->salario_base, 2, '.', '')) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						@error('salario_base') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Moneda del Sueldo</label>
						<select name="moneda" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="BS" {{ old('moneda', $empleado->moneda) === 'BS' ? 'selected' : '' }}>Bs. (Bolívares)</option>
							<option value="USD" {{ old('moneda', $empleado->moneda) === 'USD' ? 'selected' : '' }}>USD (Dólar)</option>
						</select>
					</div>
				</div>
				<div class="grid grid-cols-3 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Vigencia del nuevo salario</label>
						<input type="date" name="fecha_vigencia_salario" value="{{ old('fecha_vigencia_salario', now()->format('Y-m-d')) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						<p class="text-xs text-gray-500 mt-0.5">Solo se aplica si cambia el salario base o la moneda.</p>
						@error('fecha_vigencia_salario') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
				</div>
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Cargo</label>
						<input type="text" name="cargo" value="{{ old('cargo', $empleado->cargo) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Departamento</label>
						<input type="text" name="departamento" value="{{ old('departamento', $empleado->departamento) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
				</div>
				<div class="grid grid-cols-3 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Tipo Contrato</label>
						<select name="tipo_contrato" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="Indeterminado" {{ old('tipo_contrato', $empleado->tipo_contrato) === 'Indeterminado' ? 'selected' : '' }}>Indeterminado</option>
							<option value="Determinado" {{ old('tipo_contrato', $empleado->tipo_contrato) === 'Determinado' ? 'selected' : '' }}>Determinado</option>
							<option value="Obra" {{ old('tipo_contrato', $empleado->tipo_contrato) === 'Obra' ? 'selected' : '' }}>Obra</option>
						</select>
						@error('tipo_contrato') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Tipo Salario</label>
						<select name="tipo_salario" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="Fijo" {{ old('tipo_salario', $empleado->tipo_salario) === 'Fijo' ? 'selected' : '' }}>Fijo</option>
							<option value="Variable" {{ old('tipo_salario', $empleado->tipo_salario) === 'Variable' ? 'selected' : '' }}>Variable</option>
							<option value="Mixto" {{ old('tipo_salario', $empleado->tipo_salario) === 'Mixto' ? 'selected' : '' }}>Mixto</option>
						</select>
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Estatus</label>
						<select name="estatus" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
							<option value="Activo" {{ old('estatus', $empleado->estatus) === 'Activo' ? 'selected' : '' }}>Activo</option>
							<option value="Suspendido" {{ old('estatus', $empleado->estatus) === 'Suspendido' ? 'selected' : '' }}>Suspendido</option>
							<option value="Inactivo" {{ old('estatus', $empleado->estatus) === 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
						</select>
					</div>
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Jornada Laboral</label>
					<input type="text" name="jornada_laboral" value="{{ old('jornada_laboral', $empleado->jornada_laboral) }}" placeholder="Ej: Lunes a Viernes 8:00-17:00" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
			</div>
		</fieldset>

		<fieldset class="bg-white border border-gray-200 p-5" x-data="{ cap: {{ old('capitaliza_intereses', $empleado->capitaliza_intereses) ? 'true' : 'false' }} }">
			<legend class="text-sm font-semibold text-gray-700 px-1">Capitalización de intereses sobre prestaciones</legend>
			<div class="space-y-3 mt-2">
				<div class="flex items-start gap-2">
					<input type="hidden" name="capitaliza_intereses" value="0">
					<input id="capitaliza_intereses" type="checkbox" name="capitaliza_intereses" value="1" x-model="cap" class="mt-1 border-gray-300">
					<div>
						<label for="capitaliza_intereses" class="text-sm text-gray-700">Capitalizar intereses anualmente</label>
						<p class="text-[11px] text-gray-500">Requiere manifestación escrita del trabajador. Sin esta autorización, los intereses quedan pendientes y no se suman al capital.</p>
					</div>
				</div>
				<div x-show="cap" class="grid grid-cols-1 md:grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Fecha de la autorización</label>
						<input type="date" name="capitalizacion_autorizada_desde" x-bind:disabled="!cap" value="{{ old('capitalizacion_autorizada_desde', optional($empleado->capitalizacion_autorizada_desde)->format('Y-m-d')) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						@error('capitalizacion_autorizada_desde') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Documento o referencia</label>
						<input type="text" name="capitalizacion_documento" x-bind:disabled="!cap" value="{{ old('capitalizacion_documento', $empleado->capitalizacion_documento) }}" placeholder="Ej: carta firmada 15/03/2025" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
						@error('capitalizacion_documento') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
					</div>
				</div>
			</div>
		</fieldset>

		<div class="flex justify-end gap-2">
			<a href="{{ route('empleados.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
			<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Guardar Cambios</button>
		</div>
	</form>

	<dialog id="modal-cambiar-patrono" class="backdrop:bg-black/50 p-0 rounded-lg shadow-xl">
		<form method="POST" action="{{ route('empleados.cambiar-patrono', $empleado) }}" class="p-5 min-w-[350px]">
			@csrf
			<h2 class="text-base font-semibold mb-1">Transferir empleado a otro patrono</h2>
			<p class="text-sm text-gray-500 mb-4">
				<strong>{{ $empleado->nombres }} {{ $empleado->apellidos }}</strong> ({{ $empleado->cedula }})
				pasará de <strong>{{ $patrono->razon_social }}</strong> al patrono que selecciones.
			</p>

			<div class="mb-4">
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Nuevo patrono</label>
				<select name="nuevo_patrono_id" required class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
					<option value="">Seleccione un patrono...</option>
					@foreach ($patronos as $p)
						<option value="{{ $p->id }}" {{ $p->id === $patrono->id ? 'selected' : '' }}>
							{{ $p->razon_social }} ({{ $p->rif }})
						</option>
					@endforeach
				</select>
				@error('nuevo_patrono_id') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>

			<div class="flex justify-end gap-2">
				<button type="button" onclick="this.closest('dialog').close()" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</button>
				<button type="submit" class="px-4 py-1.5 text-sm bg-amber-600 text-white hover:bg-amber-700">Transferir</button>
			</div>
		</form>
	</dialog>
</div>
@endsection
