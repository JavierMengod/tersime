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

        return view('usuario.notificaciones', compact('informes', 'alertas', 'dbNotificaciones', 'tipo'));
    }

    public function marcarTodasLeidas()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Todas las notificaciones marcadas como leídas.');
    }
}
