<?php

namespace App\Http\Controllers;

use App\Models\AlertLog;
use App\Models\Informe;
use Illuminate\Http\Request;

class NotificacionesController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $tipo = $request->get('tipo', 'todas');

        $informes = Informe::where('user_id', $user->id)
            ->latest('generated_at')
            ->get();

        $alertas = AlertLog::where('user_id', $user->id)
            ->latest()
            ->get();

        // Notificaciones DB (campana) sin leer
        $dbNotificaciones = $user->notifications()->latest()->take(50)->get();

        return view('usuarios.notificaciones', compact('informes', 'alertas', 'dbNotificaciones', 'tipo'));
    }

    public function read(string $id)
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return response()->noContent();
    }

    public function readAll()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function marcarTodasLeidas()
    {
        return $this->readAll();
    }
}
