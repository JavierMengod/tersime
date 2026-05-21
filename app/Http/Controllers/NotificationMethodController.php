<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class NotificationMethodController extends Controller
{
    public function index()
    {
        return view('alertas.notificacion');
    }

    public function update(Request $request, $tipo)
    {
        $usuario = $request->user();

        /** ---------------- TELEGRAM ---------------- */
        if ($tipo === 'telegram') {
            $credencial = $usuario->credencialTelegram;

            if ($request->has('active') && !$request->has('chat_id')) {
                if (!$credencial) {
                    return back()->withErrors('Primero configura Telegram antes de activar/desactivar.');
                }
                $credencial->update(['activo' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Telegram actualizado.');
            }

            $validado = $request->validate([
                'chat_id'   => 'required|string',
                'bot_token' => $credencial ? 'nullable|string' : 'required|string',
            ]);

            $tokenRaw = !empty($validado['bot_token']) ? $validado['bot_token'] : null;
            if ($tokenRaw === null) {
                if (!$credencial) {
                    return back()->withErrors('Se requiere el Bot Token para la configuración inicial.')
                                 ->with('error_channel', 'telegram');
                }
                $tokenRaw = decrypt($credencial->bot_token);
            }

            try {
                $bot = new BotApi($tokenRaw);
                $bot->sendMessage($validado['chat_id'], '✅ Credenciales de Telegram configuradas correctamente.');
            } catch (\Exception $e) {
                Log::error("Error al enviar mensaje de prueba por Telegram: " . $e->getMessage());
                return back()->withErrors('Error al enviar mensaje de prueba. Revisa el token y el chat ID.')
                             ->with('error_channel', 'telegram');
            }

            $usuario->credencialTelegram()->updateOrCreate([], [
                'chat_id'   => $validado['chat_id'],
                'bot_token' => encrypt($tokenRaw),
                'activo'    => $credencial ? $credencial->activo : true,
            ]);

            return back()->with('status', 'Configuración de Telegram actualizada y verificada.');
        }

        /** ---------------- EMAIL ---------------- */
        if ($tipo === 'email') {
            $credencial = $usuario->credencialSmtp;

            if ($request->has('active') && !$request->has('smtp_host')) {
                if (!$credencial) {
                    return back()->withErrors('Primero configura Email antes de activar/desactivar.');
                }
                $credencial->update(['activo' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Email actualizado.');
            }

            $validado = $request->validate([
                'from_address' => 'required|email',
                'smtp_host'    => 'required|string|max:255',
                'smtp_port'    => 'required|integer|min:1|max:65535',
                'smtp_user'    => 'required|string',
                'smtp_pass'    => $credencial ? 'nullable|string' : 'required|string',
            ]);

            $this->validarHostPublico($validado['smtp_host'], 'smtp_host');

            $contrasena = !empty($validado['smtp_pass']) ? $validado['smtp_pass'] : null;
            if ($contrasena === null) {
                if (!$credencial) {
                    return back()->withErrors('Se requiere contraseña SMTP para la configuración inicial.')
                                 ->with('error_channel', 'email');
                }
                $contrasena = decrypt($credencial->contrasena);
            }

            try {
                config([
                    'mail.default'                 => 'smtp',
                    'mail.mailers.smtp.host'       => $validado['smtp_host'],
                    'mail.mailers.smtp.port'       => $validado['smtp_port'],
                    'mail.mailers.smtp.encryption' => 'tls',
                    'mail.mailers.smtp.username'   => $validado['smtp_user'],
                    'mail.mailers.smtp.password'   => $contrasena,
                    'mail.from.address'            => $validado['from_address'],
                    'mail.from.name'               => 'Tersime',
                ]);

                Mail::raw('✅ Configuración SMTP de Tersime verificada correctamente.', function ($message) use ($validado) {
                    $message->to($validado['from_address'])
                            ->from($validado['from_address'], 'Tersime')
                            ->subject('✅ Tersime — Configuración de correo verificada');
                });
            } catch (\Exception $e) {
                Log::error('Error enviando correo de prueba: ' . $e->getMessage());
                return back()->withErrors('No se pudo enviar el correo de prueba. Revisa el host, puerto y credenciales SMTP.')
                             ->with('error_channel', 'email');
            }

            $usuario->credencialSmtp()->updateOrCreate(
                ['user_id' => $usuario->id],
                [
                    'host'                => $validado['smtp_host'],
                    'puerto'              => $validado['smtp_port'],
                    'usuario'             => $validado['smtp_user'],
                    'direccion_remitente' => $validado['from_address'],
                    'contrasena'          => encrypt($contrasena),
                    'cifrado'             => 'tls',
                    'activo'              => $credencial ? $credencial->activo : true,
                ]
            );

            return back()->with('status', 'Configuración de correo actualizada y verificada.');
        }

        /** ---------------- DISCORD ---------------- */
        if ($tipo === 'discord') {
            $credencial = $usuario->credencialDiscord;

            if ($request->has('active') && !$request->has('webhook_url')) {
                if (!$credencial) {
                    return back()->withErrors('Primero configura Discord antes de activar/desactivar.');
                }
                $credencial->update(['activo' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Discord actualizado.');
            }

            $validado = $request->validate([
                'webhook_url' => 'required|url',
            ]);

            $this->validarUrlHttpsPublica($validado['webhook_url']);

            try {
                $respuesta = Http::post($validado['webhook_url'], [
                    'content' => '✅ Configuración de Discord verificada correctamente.',
                ]);
                if ($respuesta->failed()) {
                    throw new \Exception("Discord respondió HTTP " . $respuesta->status());
                }
            } catch (\Exception $e) {
                Log::error('Error enviando prueba a Discord: ' . $e->getMessage());
                return back()->withErrors('No se pudo enviar el mensaje de prueba a Discord. Revisa que el webhook sea correcto.')
                             ->with('error_channel', 'discord');
            }

            $usuario->credencialDiscord()->updateOrCreate(
                ['user_id' => $usuario->id],
                [
                    'webhook_url' => $validado['webhook_url'],
                    'activo'      => $credencial ? $credencial->activo : true,
                ]
            );

            return back()->with('status', 'Configuración de Discord actualizada y verificada.');
        }

        return back()->withErrors('Canal no reconocido.');
    }

    public function destroy(Request $request, $tipo)
    {
        $usuario = $request->user();
        $nombres = ['telegram' => 'Telegram', 'email' => 'Correo', 'discord' => 'Discord'];

        if ($tipo === 'telegram' && $usuario->credencialTelegram) {
            $usuario->credencialTelegram->delete();
        } elseif ($tipo === 'email' && $usuario->credencialSmtp) {
            $usuario->credencialSmtp->delete();
        } elseif ($tipo === 'discord' && $usuario->credencialDiscord) {
            $usuario->credencialDiscord->delete();
        }

        Log::info("Canal {$tipo} desconectado para usuario {$usuario->id}");
        return back()->with('status', ($nombres[$tipo] ?? $tipo) . ' desconectado correctamente.');
    }

    /**
     * Lanza ValidationException si la URL o el host resuelven a una dirección privada/loopback.
     * Solo se permiten URLs con HTTPS.
     */
    private function validarUrlHttpsPublica(string $url, string $campo = 'webhook_url'): void
    {
        $partes = parse_url($url);

        if (($partes['scheme'] ?? '') !== 'https') {
            throw ValidationException::withMessages([$campo => 'Solo se permiten URLs con HTTPS.']);
        }

        $host = $partes['host'] ?? '';
        if (empty($host)) {
            throw ValidationException::withMessages([$campo => 'URL inválida.']);
        }

        $ip = gethostbyname($host);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages([$campo => 'No se pudo resolver el host de la URL.']);
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ValidationException::withMessages([$campo => 'La URL apunta a una dirección de red privada o reservada.']);
        }
    }

    /**
     * Lanza ValidationException si el hostname resuelve a una dirección privada/loopback.
     */
    private function validarHostPublico(string $host, string $campo = 'smtp_host'): void
    {
        $ip = gethostbyname($host);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages([$campo => 'No se pudo resolver el host SMTP.']);
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ValidationException::withMessages([$campo => 'El host SMTP apunta a una dirección de red privada o reservada.']);
        }
    }
}
