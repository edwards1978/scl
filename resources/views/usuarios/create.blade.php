@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-2xl">
	<h1 class="text-xl font-semibold mb-4">Nuevo Usuario</h1>

	@include('usuarios._form', [
		'usuario' => null,
		'action' => route('usuarios.store'),
		'method' => 'POST',
	])
</div>
@endsection
