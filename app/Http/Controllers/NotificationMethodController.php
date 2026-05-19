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

    public function update(Request $request, $type)
    {
        $user = $request->user();

        /** ---------------- TELEGRAM ---------------- */
        if ($type === 'telegram') {
            $cred = $user->telegramCredential;

            // Toggle rápido (solo envía 'active', sin 'chat_id')
            if ($request->has('active') && !$request->has('chat_id')) {
                if (!$cred) {
                    return back()->withErrors('Primero configura Telegram antes de activar/desactivar.');
                }
                $cred->update(['active' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Telegram actualizado.');
            }

            $data = $request->validate([
                'chat_id'   => 'required|string',
                'bot_token' => $cred ? 'nullable|string' : 'required|string',
            ]);

            // Resolver token: usar el nuevo si se proporcionó, o el existente
            $rawToken = !empty($data['bot_token']) ? $data['bot_token'] : null;
            if ($rawToken === null) {
                if (!$cred) {
                    return back()->withErrors('Se requiere el Bot Token para la configuración inicial.')
                                 ->with('error_channel', 'telegram');
                }
                $rawToken = decrypt($cred->bot_token);
            }

            // Test primero, guardar solo si pasa
            try {
                $telegram = new BotApi($rawToken);
                $telegram->sendMessage($data['chat_id'], '✅ Credenciales de Telegram configuradas correctamente.');
            } catch (\Exception $e) {
                Log::error("Error al enviar mensaje de prueba por Telegram: " . $e->getMessage());
                return back()->withErrors('Error al enviar mensaje de prueba. Revisa el token y el chat ID.')
                             ->with('error_channel', 'telegram');
            }

            $user->telegramCredential()->updateOrCreate([], [
                'chat_id'   => $data['chat_id'],
                'bot_token' => encrypt($rawToken),
                'active'    => $cred ? $cred->active : true,
            ]);

            return back()->with('status', 'Configuración de Telegram actualizada y verificada.');
        }

        /** ---------------- EMAIL ---------------- */
        if ($type === 'email') {
            $cred = $user->smtpCredential;

            // Toggle rápido
            if ($request->has('active') && !$request->has('smtp_host')) {
                if (!$cred) {
                    return back()->withErrors('Primero configura Email antes de activar/desactivar.');
                }
                $cred->update(['active' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Email actualizado.');
            }

            $data = $request->validate([
                'from_address' => 'required|email',
                'smtp_host'    => 'required|string|max:255',
                'smtp_port'    => 'required|integer|min:1|max:65535',
                'smtp_user'    => 'required|string',
                'smtp_pass'    => $cred ? 'nullable|string' : 'required|string',
            ]);

            $this->assertPublicHost($data['smtp_host'], 'smtp_host');

            // Resolver contraseña: usar la nueva si se proporcionó, o la existente
            $rawPassword = !empty($data['smtp_pass']) ? $data['smtp_pass'] : null;
            if ($rawPassword === null) {
                if (!$cred) {
                    return back()->withErrors('Se requiere contraseña SMTP para la configuración inicial.')
                                 ->with('error_channel', 'email');
                }
                $rawPassword = decrypt($cred->password);
            }

            // Test primero con los datos del formulario
            try {
                config([
                    'mail.default'                 => 'smtp',
                    'mail.mailers.smtp.host'       => $data['smtp_host'],
                    'mail.mailers.smtp.port'       => $data['smtp_port'],
                    'mail.mailers.smtp.encryption' => 'tls',
                    'mail.mailers.smtp.username'   => $data['smtp_user'],
                    'mail.mailers.smtp.password'   => $rawPassword,
                    'mail.from.address'            => $data['from_address'],
                    'mail.from.name'               => 'Tersime',
                ]);

                Mail::raw('✅ Configuración SMTP de Tersime verificada correctamente.', function ($message) use ($data) {
                    $message->to($data['from_address'])
                            ->from($data['from_address'], 'Tersime')
                            ->subject('✅ Tersime — Configuración de correo verificada');
                });
            } catch (\Exception $e) {
                Log::error('Error enviando correo de prueba: ' . $e->getMessage());
                return back()->withErrors('No se pudo enviar el correo de prueba. Revisa el host, puerto y credenciales SMTP.')
                             ->with('error_channel', 'email');
            }

            // Guardar solo si el test pasó
            $user->smtpCredential()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'host'         => $data['smtp_host'],
                    'port'         => $data['smtp_port'],
                    'username'     => $data['smtp_user'],
                    'from_address' => $data['from_address'],
                    'password'     => encrypt($rawPassword),
                    'encryption'   => 'tls',
                    'active'       => $cred ? $cred->active : true,
                ]
            );

            return back()->with('status', 'Configuración de correo actualizada y verificada.');
        }

        /** ---------------- DISCORD ---------------- */
        if ($type === 'discord') {
            $cred = $user->discordCredential;

            // Toggle rápido
            if ($request->has('active') && !$request->has('webhook_url')) {
                if (!$cred) {
                    return back()->withErrors('Primero configura Discord antes de activar/desactivar.');
                }
                $cred->update(['active' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Discord actualizado.');
            }

            $data = $request->validate([
                'webhook_url' => 'required|url',
            ]);

            $this->assertPublicHttpsUrl($data['webhook_url']);

            // Test primero
            try {
                $response = Http::post($data['webhook_url'], [
                    'content' => '✅ Configuración de Discord verificada correctamente.',
                ]);
                if ($response->failed()) {
                    throw new \Exception("Discord respondió HTTP " . $response->status());
                }
            } catch (\Exception $e) {
                Log::error('Error enviando prueba a Discord: ' . $e->getMessage());
                return back()->withErrors('No se pudo enviar el mensaje de prueba a Discord. Revisa que el webhook sea correcto.')
                             ->with('error_channel', 'discord');
            }

            // Guardar solo si el test pasó
            $user->discordCredential()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'webhook_url' => $data['webhook_url'],
                    'active'      => $cred ? $cred->active : true,
                ]
            );

            return back()->with('status', 'Configuración de Discord actualizada y verificada.');
        }

        return back()->withErrors('Canal no reconocido.');
    }

    public function destroy(Request $request, $type)
    {
        $user  = $request->user();
        $names = ['telegram' => 'Telegram', 'email' => 'Correo', 'discord' => 'Discord'];

        if ($type === 'telegram' && $user->telegramCredential) {
            $user->telegramCredential->delete();
        } elseif ($type === 'email' && $user->smtpCredential) {
            $user->smtpCredential->delete();
        } elseif ($type === 'discord' && $user->discordCredential) {
            $user->discordCredential->delete();
        }

        Log::info("Canal {$type} desconectado para usuario {$user->id}");
        return back()->with('status', ($names[$type] ?? $type) . ' desconectado correctamente.');
    }

    /**
     * Throws ValidationException if the URL or host resolves to a private/loopback address
     * (SSRF protection). Only HTTPS scheme is permitted for webhook URLs.
     */
    private function assertPublicHttpsUrl(string $url, string $field = 'webhook_url'): void
    {
        $parsed = parse_url($url);

        if (($parsed['scheme'] ?? '') !== 'https') {
            throw ValidationException::withMessages([$field => 'Solo se permiten URLs con HTTPS.']);
        }

        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            throw ValidationException::withMessages([$field => 'URL inválida.']);
        }

        $ip = gethostbyname($host);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages([$field => 'No se pudo resolver el host de la URL.']);
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ValidationException::withMessages([$field => 'La URL apunta a una dirección de red privada o reservada.']);
        }
    }

    /**
     * Throws ValidationException if the hostname resolves to a private/loopback address.
     */
    private function assertPublicHost(string $host, string $field = 'smtp_host'): void
    {
        $ip = gethostbyname($host);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages([$field => 'No se pudo resolver el host SMTP.']);
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ValidationException::withMessages([$field => 'El host SMTP apunta a una dirección de red privada o reservada.']);
        }
    }

}
