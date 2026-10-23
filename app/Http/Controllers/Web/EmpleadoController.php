<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmpleadoRequest;
use App\Http\Requests\UpdateEmpleadoRequest;
use App\Models\BonificacionFinAno;
use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\MovimientoPrestaciones;
use App\Models\Parametro;
use App\Models\PrestacionSocial;
use App\Models\Patrono;
use App\Models\Sueldo;
use App\Models\Vacacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmpleadoController extends Controller
{
    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();

        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $query = Empleado::with('patrono')->where('patrono_id', $patrono->id);

        if ($buscar = $request->get('buscar')) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombres', 'like', "%{$buscar}%")
                  ->orWhere('apellidos', 'like', "%{$buscar}%")
                  ->orWhere('cedula', 'like', "%{$buscar}%");
            });
        }

        $empleados = $query->orderBy('apellidos')->orderBy('nombres')->paginate($porPagina);

        $tasaDolar = (float) Parametro::get('tasa_dolar', 0.0);

        return view('empleados.index', compact('empleados', 'patrono', 'tasaDolar'));
    }

    public function create(): View
    {
        $patrono = $this->patronoActual();

        return view('empleados.create', compact('patrono'));
    }

    public function store(StoreEmpleadoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['patrono_id'] = (int) session('patrono_id');
        $data['salario_inicial'] = $data['salario_inicial'] ?? $data['salario_base'];

        $empleado = Empleado::create($data);

        return redirect()->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombre_completo} creado exitosamente.");
    }

    public function edit(Empleado $empleado): View
    {
        $this->verificarPertenece($empleado);

        $patrono = $this->patronoActual();
        $patronos = Patrono::where('estado', 'Activo')->orderBy('razon_social')->get();

        return view('empleados.edit', compact('empleado', 'patrono', 'patronos'));
    }

    public function update(UpdateEmpleadoRequest $request, Empleado $empleado): RedirectResponse
    {
        $this->verificarPertenece($empleado);

        $data = $request->validated();
        $data['patrono_id'] = $empleado->patrono_id;

        $empleado->update($data);

        return redirect()->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombre_completo} actualizado exitosamente.");
    }

    public function destroy(Empleado $empleado): RedirectResponse
    {
        $this->verificarPertenece($empleado);

        $tieneHistorial = Vacacion::where('empleado_id', $empleado->id)->exists()
            || Sueldo::where('empleado_id', $empleado->id)->exists()
            || CambioSalario::where('empleado_id', $empleado->id)->exists()
            || MovimientoPrestaciones::where('empleado_id', $empleado->id)->exists()
            || PrestacionSocial::where('empleado_id', $empleado->id)->exists()
            || Liquidacion::where('empleado_id', $empleado->id)->exists()
            || BonificacionFinAno::where('empleado_id', $empleado->id)->exists();

        if ($tieneHistorial) {
            return redirect()->route('empleados.index')
                ->with('error', 'No se puede eliminar un trabajador con historial laboral. Use el estatus Inactivo.');
        }

        $nombre = $empleado->nombre_completo;
        $empleado->delete();

        return redirect()->route('empleados.index')
            ->with('success', "Empleado {$nombre} eliminado exitosamente.");
    }

    public function cambiarPatrono(Request $request, Empleado $empleado): RedirectResponse
    {
        $this->verificarPertenece($empleado);

        $request->validate([
            'nuevo_patrono_id' => 'required|exists:patronos,id',
        ]);

        $nuevoPatrono = Patrono::findOrFail($request->integer('nuevo_patrono_id'));

        if ($nuevoPatrono->estado !== 'Activo') {
            return back()->withErrors(['nuevo_patrono_id' => 'El patrono seleccionado no está activo.']);
        }

        $existe = Empleado::where('patrono_id', $nuevoPatrono->id)
            ->where('cedula', $empleado->cedula)
            ->where('id', '!=', $empleado->id)
            ->exists();

        if ($existe) {
            return back()->withErrors(['nuevo_patrono_id' => "La cédula {$empleado->cedula} ya está registrada en el patrono seleccionado."]);
        }

        $patronoAnterior = (int) $empleado->patrono_id;

        DB::transaction(function () use ($empleado, $nuevoPatrono, $patronoAnterior) {
            $empleado->update(['patrono_id' => $nuevoPatrono->id]);

            Vacacion::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);

            MovimientoPrestaciones::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);

            PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);

            Liquidacion::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);

            BonificacionFinAno::where('empleado_id', $empleado->id)
                ->where('patrono_id', $patronoAnterior)
                ->update(['patrono_id' => $nuevoPatrono->id]);
        });

        return redirect()->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombre_completo} transferido a {$nuevoPatrono->razon_social} exitosamente.");
    }

    private function verificarPertenece(Empleado $empleado): void
    {
        abort_if((int) $empleado->patrono_id !== (int) session('patrono_id'), 404);
    }
}
