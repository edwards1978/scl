@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex items-center justify-between mb-3">
		<div>
			<h1 class="text-xl font-semibold">Liquidación rápida Art. 142.c</h1>
			<p class="text-sm text-gray-500">Cálculo independiente; no modifica prestaciones, patronos ni trabajadores.</p>
		</div>
		<x-boton-volver :fallback="route('calculos-literal-c.index')" />
	</div>

	@include('calculos-literal-c._formulario')
</div>
@endsection
