@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex items-center justify-between mb-3">
		<div>
			<h1 class="text-xl font-semibold">Editar cálculo Art. 142.c</h1>
			<p class="text-sm text-gray-500">Los cambios se guardan solo al pulsar “Guardar cambios”.</p>
		</div>
		<a href="{{ route('calculos-literal-c.show', $calculo) }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
	</div>

	@include('calculos-literal-c._formulario', ['calculo' => $calculo])
</div>
@endsection
