<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Configuracion;
use App\Models\EnvioProgramado;
use App\Models\ReporteEnviado;
use App\Models\User;
use App\Services\ReporteProgramadoService;
use App\Support\ReporteProgramado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/** Administración → Configuración: reportes programados y correo de salida. */
class ConfiguracionController extends Controller
{
    public function __construct(private ReporteProgramadoService $reportes) {}

    public function index(Request $request)
    {
        $this->authorize('configuracion.gestionar');

        return view('configuracion.index', [
            'tab' => $request->input('tab') === 'correo' ? 'correo' : 'reportes',
            'envios' => EnvioProgramado::orderBy('hora')->get(),
            'usuarios' => User::activos()->with('roles')->orderBy('name')->get(['id', 'name', 'email', 'puesto']),
            'historial' => ReporteEnviado::with('envio')->latest('id')->limit(15)->get(['id', 'envio_programado_id', 'titulo', 'correos_enviados', 'error', 'destinatarios', 'created_at']),
            'correo' => [
                'servidor' => Configuracion::valor('correo_servidor'),
                'puerto' => Configuracion::valor('correo_puerto', 587),
                'usuario' => Configuracion::valor('correo_usuario'),
                'remitente' => Configuracion::valor('correo_remitente'),
                'nombre' => Configuracion::valor('correo_nombre', 'Sistema Tuboformas'),
                'tiene_clave' => (bool) Configuracion::valor('correo_clave'),
            ],
        ]);
    }

    public function storeEnvio(Request $request)
    {
        $this->authorize('configuracion.gestionar');
        $e = EnvioProgramado::create($this->validarEnvio($request));
        Auditoria::registrar('crear', $e, $e->nombre);

        return redirect()->route('configuracion.index')->with('ok', "«{$e->nombre}» se enviará a las ".substr($e->hora, 0, 5).'.');
    }

    public function updateEnvio(Request $request, EnvioProgramado $envio)
    {
        $this->authorize('configuracion.gestionar');
        $envio->update($this->validarEnvio($request));
        Auditoria::registrar('editar', $envio, $envio->nombre);

        return redirect()->route('configuracion.index')->with('ok', 'Reporte programado guardado.');
    }

    public function destroyEnvio(EnvioProgramado $envio)
    {
        $this->authorize('configuracion.gestionar');
        Auditoria::registrar('eliminar', $envio, $envio->nombre);
        $envio->delete();

        return redirect()->route('configuracion.index')->with('ok', 'Reporte programado eliminado.');
    }

    /** Lo envía ya, a sus destinatarios, sin esperar la hora. */
    public function enviarAhora(EnvioProgramado $envio)
    {
        $this->authorize('configuracion.gestionar');
        $r = $this->reportes->enviar($envio, manual: true);

        return back()->with($r->error ? 'error' : 'ok', $r->error
            ? 'Se generó, pero hubo un problema con el correo: '.$r->error
            : 'Enviado'.($r->correos_enviados ? " a {$r->correos_enviados} ".($r->correos_enviados === 1 ? 'correo' : 'correos') : '').($envio->en_campana ? ' y a la campana' : '').'.');
    }

    /** Cómo se vería hoy, sin enviarlo. */
    public function vista(EnvioProgramado $envio)
    {
        $this->authorize('configuracion.gestionar');

        return response($this->reportes->html($envio));
    }

    public function verEnviado(Request $request, ReporteEnviado $reporte)
    {
        abort_unless($reporte->puedeVer($request->user()), 403);

        return response($reporte->html);
    }

    public function guardarCorreo(Request $request)
    {
        $this->authorize('configuracion.gestionar');
        $d = $request->validate([
            'servidor' => ['nullable', 'string', 'max:150'],
            'puerto' => ['nullable', 'integer', 'between:1,65535'],
            'usuario' => ['nullable', 'string', 'max:150'],
            'clave' => ['nullable', 'string', 'max:200'],
            'remitente' => ['nullable', 'email', 'max:150'],
            'nombre' => ['nullable', 'string', 'max:100'],
        ]);
        $valores = [
            'correo_servidor' => $d['servidor'] ?? null, 'correo_puerto' => $d['puerto'] ?? 587,
            'correo_usuario' => $d['usuario'] ?? null, 'correo_remitente' => $d['remitente'] ?? null,
            'correo_nombre' => $d['nombre'] ?? null,
        ];
        // La contraseña solo se cambia si se escribe una nueva.
        if (filled($d['clave'] ?? null)) {
            $valores['correo_clave'] = $d['clave'];
        }
        Configuracion::guardar($valores);
        Auditoria::registrar('editar', null, 'Configuración de correo');

        return redirect()->route('configuracion.index', ['tab' => 'correo'])->with('ok', 'Correo guardado. Envía una prueba para confirmar que funciona.');
    }

    public function probarCorreo(Request $request)
    {
        $this->authorize('configuracion.gestionar');
        $d = $request->validate(['para' => ['required', 'email']]);
        Configuracion::aplicarCorreo();
        try {
            Mail::raw("Este es un correo de prueba del sistema Tuboformas.\n\nSi lo recibes, los reportes programados y los avisos por correo van a llegar.",
                fn ($m) => $m->to($d['para'])->subject('Prueba de correo · Tuboformas'));
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo enviar: '.mb_strimwidth($e->getMessage(), 0, 220, '…'));
        }

        return back()->with('ok', "Correo de prueba enviado a {$d['para']}. Revisa también la carpeta de spam.");
    }

    private function validarEnvio(Request $request): array
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'hora' => ['required', 'date_format:H:i'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7'],
            'periodo' => ['required', Rule::in(array_keys(EnvioProgramado::PERIODOS))],
            'reportes' => ['required', 'array', 'min:1'],
            'reportes.*' => [Rule::in(array_keys(ReporteProgramado::REPORTES))],
            'usuarios' => ['nullable', 'array'],
            'usuarios.*' => ['integer', 'exists:users,id'],
            'correos' => ['nullable', 'string', 'max:1000'],
        ], [], ['reportes' => 'reportes', 'dias' => 'días']);
        $d['usuarios'] = array_map('intval', $d['usuarios'] ?? []);
        $d['por_correo'] = $request->boolean('por_correo');
        $d['en_campana'] = $request->boolean('en_campana');
        $d['activo'] = $request->boolean('activo');

        return $d;
    }
}
