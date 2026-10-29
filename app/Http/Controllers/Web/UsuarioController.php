<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\Patrono;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $usuarios = User::with('patronos')
            ->orderBy('name')
            ->paginate($porPagina);

        return view('usuarios.index', compact('usuarios', 'porPagina'));
    }

    public function create(): View
    {
        $patronos = Patrono::orderBy('razon_social')->get();
        $roles = RolUsuario::opciones();

        return view('usuarios.create', compact('patronos', 'roles'));
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $usuario = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'rol' => $data['rol'],
                'password' => Hash::make($data['password']),
            ]);

            $usuario->patronos()->sync($data['patronos'] ?? []);
        });

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado exitosamente.');
    }

    public function edit(User $usuario): View
    {
        $patronos = Patrono::orderBy('razon_social')->get();
        $roles = RolUsuario::opciones();
        $asignados = $usuario->patronos()->pluck('patronos.id')->all();

        return view('usuarios.edit', compact('usuario', 'patronos', 'roles', 'asignados'));
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $data = $request->validated();
        $nuevoRol = RolUsuario::from($data['rol']);

        if ($usuario->esAdministrador()
            && ! $nuevoRol->esAdministrador()
            && User::where('rol', RolUsuario::Administrador->value)->count() <= 1) {
            return back()->withInput()->withErrors([
                'rol' => 'Debe existir al menos un usuario administrador.',
            ]);
        }

        DB::transaction(function () use ($usuario, $data, $nuevoRol): void {
            $usuario->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'rol' => $nuevoRol,
            ]);

            if (! empty($data['password'])) {
                $usuario->password = Hash::make($data['password']);
            }

            $usuario->save();
            $usuario->patronos()->sync($data['patronos'] ?? []);
        });

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->name} actualizado exitosamente.");
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($request->user()->is($usuario)) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puede eliminar su propio usuario.');
        }

        if ($usuario->esAdministrador()
            && User::where('rol', RolUsuario::Administrador->value)->count() <= 1) {
            return redirect()->route('usuarios.index')
                ->with('error', 'Debe existir al menos un usuario administrador.');
        }

        $nombre = $usuario->name;
        $usuario->delete();

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$nombre} eliminado exitosamente.");
    }
}
