@php
	$rolActual = old('rol', $usuario?->rol?->value ?? \App\Enums\RolUsuario::Consulta->value);
	$patronosSeleccionados = collect(old('patronos', $asignados ?? []))->map(fn ($id) => (int) $id)->all();
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
	@csrf
	@if ($method !== 'POST')
		@method($method)
	@endif

	<fieldset class="bg-white border border-gray-200 p-5">
		<legend class="text-sm font-semibold text-gray-700 px-1">Datos del usuario</legend>
		<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Nombre</label>
				<input type="text" name="name" value="{{ old('name', $usuario?->name) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm" required>
				@error('name') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Correo</label>
				<input type="email" name="email" value="{{ old('email', $usuario?->email) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm" required>
				@error('email') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Rol</label>
				<select name="rol" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white" required>
					@foreach ($roles as $valor => $etiqueta)
						<option value="{{ $valor }}" {{ $rolActual === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
					@endforeach
				</select>
				@error('rol') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Contraseña {{ $usuario ? '(dejar vacío para conservar)' : '' }}</label>
				<input type="password" name="password" class="w-full border border-gray-300 px-2 py-1.5 text-sm" {{ $usuario ? '' : 'required' }}>
				@error('password') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 mb-0.5">Confirmar contraseña</label>
				<input type="password" name="password_confirmation" class="w-full border border-gray-300 px-2 py-1.5 text-sm" {{ $usuario ? '' : 'required' }}>
			</div>
		</div>
	</fieldset>

	<fieldset class="bg-white border border-gray-200 p-5">
		<legend class="text-sm font-semibold text-gray-700 px-1">Patronos asignados</legend>
		<p class="text-xs text-gray-500 mt-1 mb-3">
			El rol Administrador tiene acceso a todos los patronos. Analista y Consulta solo acceden a los patronos marcados aquí.
		</p>

		@if ($patronos->isEmpty())
			<p class="text-sm text-gray-400">No hay patronos registrados.</p>
		@else
			<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
				@foreach ($patronos as $patrono)
					<label class="flex items-center gap-2 text-sm text-gray-700">
						<input type="checkbox" name="patronos[]" value="{{ $patrono->id }}"
							{{ in_array($patrono->id, $patronosSeleccionados, true) ? 'checked' : '' }}
							class="border-gray-300">
						<span>{{ $patrono->razon_social }} <span class="text-xs text-gray-400">({{ $patrono->rif }})</span></span>
					</label>
				@endforeach
			</div>
		@endif

		@error('patronos') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
		@error('patronos.*') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
	</fieldset>

	<div class="flex justify-end gap-2">
		<a href="{{ route('usuarios.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
		<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">
			{{ $usuario ? 'Guardar cambios' : 'Crear usuario' }}
		</button>
	</div>
</form>
