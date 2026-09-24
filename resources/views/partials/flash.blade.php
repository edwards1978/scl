@if (session('success'))
	<div class="mx-4 mt-3 p-3 bg-green-100 border border-green-300 text-green-700 text-sm">{{ session('success') }}</div>
@endif
@if (session('error'))
	<div class="mx-4 mt-3 p-3 bg-red-100 border border-red-300 text-red-700 text-sm">{{ session('error') }}</div>
@endif
@if (session('info'))
	<div class="mx-4 mt-3 p-3 bg-blue-100 border border-blue-300 text-blue-700 text-sm">{{ session('info') }}</div>
@endif
