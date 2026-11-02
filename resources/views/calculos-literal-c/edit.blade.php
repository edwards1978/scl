@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex items-center justify-between mb-3">
		<div>
			<h1 class="text-xl font-semibold">Editar cálculo Art. 142.c</h1>
			<p class="text-sm text-gray-500">Los cambios se guardan solo al pulsar “Guardar cambios”.</p>
		</div>
		<x-boton-volver :fallback="route('calculos-literal-c.show', $calculo)" />
	</div>

	@include('calculos-literal-c._formulario', ['calculo' => $calculo])
</div>
@endsection
