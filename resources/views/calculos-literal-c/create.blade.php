@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-4xl">
	<div class="flex items-center justify-between mb-3">
		<div>
			<h1 class="text-xl font-semibold">Liquidación rápida Art. 142.c</h1>
			<p class="text-sm text-gray-500">Cálculo independiente; no modifica prestaciones, patronos ni trabajadores.</p>
		</div>
		<a href="{{ route('calculos-literal-c.index') }}" class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">← Volver</a>
	</div>

	@include('calculos-literal-c._formulario')
</div>
@endsection
