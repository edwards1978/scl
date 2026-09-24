@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-md">
	<h1 class="text-xl font-semibold mb-1">Seleccionar Patrono</h1>
	<p class="text-sm text-gray-600 mb-6">El funcionamiento del sistema se realiza sobre el patrono seleccionado. Los empleados, cálculos y reportes se filtrarán automáticamente por este patrono.</p>

	<form method="POST" action="{{ route('entorno.guardar') }}" class="space-y-4">
		@csrf

		<div>
			<label class="block text-xs font-medium text-gray-600 mb-0.5">Patrono</label>
			<select name="patrono_id" class="w-full border border-gray-300 px-2 py-1.5 text-sm bg-white" onchange="window.location='{{ route('entorno.seleccionar') }}?patrono='+this.value">
				@foreach ($patronos as $p)
					<option value="{{ $p->id }}" {{ $p->id == $patronoId ? 'selected' : '' }}>{{ $p->razon_social }} ({{ $p->rif }})</option>
				@endforeach
			</select>
			@error('patrono_id') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
		</div>

		<div class="flex justify-end gap-2">
			<a href="{{ route('patronos.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">Gestionar patronos</a>
			<button type="submit" class="px-4 py-1.5 text-sm bg-blue-600 text-white hover:bg-blue-700">Seleccionar</button>
		</div>
	</form>
</div>
@endsection
