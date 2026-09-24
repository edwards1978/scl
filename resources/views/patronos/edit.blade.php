@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-2xl">
	<h1 class="text-xl font-semibold mb-4">Editar Patrono</h1>

	<form method="POST" action="{{ route('patronos.update', $patrono) }}" class="space-y-4">
		@csrf @method('PUT')

		<fieldset class="bg-white border border-gray-200 p-5">
			<legend class="text-sm font-semibold text-gray-700 px-1">Datos del Patrono</legend>
			<div class="space-y-3 mt-2">
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">R.I.F.</label>
					<input type="text" name="rif" value="{{ old('rif', $patrono->rif) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					@error('rif') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Razón Social</label>
					<input type="text" name="razon_social" value="{{ old('razon_social', $patrono->razon_social) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					@error('razon_social') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Nombre Comercial <span class="text-gray-400">(opcional)</span></label>
					<input type="text" name="nombre_comercial" value="{{ old('nombre_comercial', $patrono->nombre_comercial) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Registro Mercantil <span class="text-gray-400">(opcional)</span></label>
					<input type="text" name="registro_mercantil" value="{{ old('registro_mercantil', $patrono->registro_mercantil) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Dirección Fiscal</label>
					<textarea name="direccion_fiscal" rows="2" class="w-full border border-gray-300 px-2 py-1.5 text-sm">{{ old('direccion_fiscal', $patrono->direccion_fiscal) }}</textarea>
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Representante Legal</label>
					<input type="text" name="representante_legal" value="{{ old('representante_legal', $patrono->representante_legal) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
				</div>
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Teléfono</label>
						<input type="text" name="telefono" value="{{ old('telefono', $patrono->telefono) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
					<div>
						<label class="block text-xs font-medium text-gray-600 mb-0.5">Correo</label>
						<input type="email" name="correo" value="{{ old('correo', $patrono->correo) }}" class="w-full border border-gray-300 px-2 py-1.5 text-sm">
					</div>
				</div>
				<div>
					<label class="block text-xs font-medium text-gray-600 mb-0.5">Estado</label>
					<select name="estado" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white">
						<option value="Activo" {{ $patrono->estado === 'Activo' ? 'selected' : '' }}>Activo</option>
						<option value="Inactivo" {{ $patrono->estado === 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
					</select>
				</div>
			</div>
		</fieldset>

		<div class="flex justify-end gap-2">
			<a href="{{ route('patronos.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
			<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Guardar Cambios</button>
		</div>
	</form>
</div>
@endsection
