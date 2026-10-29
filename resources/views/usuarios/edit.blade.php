@extends('layouts.app')

@section('content')
<div class="p-4 mx-auto max-w-2xl">
	<h1 class="text-xl font-semibold mb-4">Editar Usuario</h1>

	@include('usuarios._form', [
		'usuario' => $usuario,
		'action' => route('usuarios.update', $usuario),
		'method' => 'PUT',
	])
</div>
@endsection
